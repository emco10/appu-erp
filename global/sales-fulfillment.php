<?php
require_once __DIR__ . '/document-engine.php';
require_once __DIR__ . '/document-history.php';
require_once __DIR__ . '/sales-delivery.php';
require_once __DIR__ . '/../modules/inventory/stock.php';

/** Root order first, then source document, then guide/lines and stock. */
function app_lock_delivery_source(PDO $pdo, string $type, int $id): array
{
    if (!$pdo->inTransaction()) throw new LogicException('La entrega requiere una transacción.');
    if (!in_array($type, ['sales_order','invoice','receipt'], true)) throw new DomainException('Origen de entrega inválido.');
    if ($type !== 'sales_order') {
        $source = app_document_header($pdo,$type,$id);
        if ((int)($source['sales_order_id'] ?? 0)>0) app_document_header($pdo,'sales_order',(int)$source['sales_order_id'],true);
    }
    return app_document_header($pdo,$type,$id,true);
}

function app_order_has_final_invoice(PDO $pdo, int $orderId): bool
{
    $query = $pdo->prepare("SELECT id FROM tbl_invoice WHERE sales_order_id=? AND document_subtype<>'ADVANCE' AND inventory_origin<>'DELIVERY' AND status NOT IN (2,4) FOR UPDATE");
    $query->execute([$orderId]);
    if ($query->fetch()) return true;
    $query = $pdo->prepare('SELECT id FROM tbl_receipt WHERE sales_order_id=? AND status NOT IN (2,4) FOR UPDATE'); $query->execute([$orderId]);
    return (bool)$query->fetch();
}

/** All side effects are owned by the caller's transaction. */
function app_post_sales_delivery(PDO $pdo, int $guideId, int $userId): void
{
    app_require_document_engine($pdo);
    $guide = app_document_header($pdo,'remission_guide',$guideId,true);
    $source = app_lock_delivery_source($pdo,$guide['source_type'],(int)$guide['source_id']);
    if ((int)$guide['warehouse_id'] !== (int)$source['warehouse_id']) throw new AppValidationException([app_field_error('warehouse_id','WAREHOUSE_MISMATCH','El almacén debe coincidir con el documento de origen.')]);
    $fromOrder = $guide['source_type'] === 'sales_order';
    $reserve = $guide['source_type'] === 'invoice' && ($source['document_subtype'] ?? '') === 'RESERVE';
    if ($fromOrder && ($source['status'] !== 'Confirmada' || app_order_has_final_invoice($pdo,(int)$guide['source_id']))) throw new DomainException('La orden ya tiene una factura o no está confirmada. Registra el despacho desde la factura correspondiente.');
    if (!$fromOrder && in_array((int)$source['status'],[2,4],true)) throw new DomainException('El documento de origen está cancelado.');
    if ($guide['source_type'] === 'invoice' && ($source['document_subtype'] ?? '') === 'ADVANCE') throw new DomainException('Un anticipo no representa mercancía para entregar.');
    if ($reserve && (int)($source['sales_order_id'] ?? 0)<=0) throw new DomainException('La reserva no conserva una orden de venta válida.');
    $query = $pdo->prepare('SELECT * FROM tbl_remission_guide_detail WHERE remission_guide_id=? ORDER BY id'); $query->execute([$guideId]);
    $details = $query->fetchAll();
    if (!$details) throw new DomainException('La entrega necesita al menos una línea.');
    foreach ($details as $detail) {
        $quantity = (float)$detail['quantity'];
        if ($fromOrder && abs($quantity-round($quantity))>.000001) throw new AppValidationException([app_field_error('lines['.$detail['source_detail_id'].'].quantity','INTEGER','La cantidad de entrega debe ser un número entero para esta unidad.')]);
        app_apply_document_line($pdo,$guide['source_type'],(int)$guide['source_id'],(int)$detail['source_detail_id'],'remission_guide',$guideId,(int)$detail['id'],'FULFILLMENT',$quantity,$userId);
        if ($fromOrder || $reserve) {
            warehouse_apply_reservation_delta($pdo,(int)$detail['product_id'],-$quantity,(int)$guide['warehouse_id']);
            warehouse_apply_stock_delta($pdo,(int)$detail['product_id'],-$quantity,STOCK_MOVEMENT_SALE,$userId,'Salida física por entrega','remission_guide',$guideId,$guide['number'],null,(int)$guide['warehouse_id'],null,(int)$detail['id']);
        }
    }
    $pdo->prepare('UPDATE tbl_remission_guide SET inventory_posted=? WHERE id=?')->execute([$fromOrder || $reserve ? 1 : 0,$guideId]);
}

function app_cancel_sales_delivery(PDO $pdo, int $guideId, int $userId, string $reason): array
{
    app_require_document_engine($pdo);
    if (mb_strlen(trim($reason))<5) throw new AppValidationException([app_field_error('cancellation_reason','MIN_LENGTH','Indica un motivo de anulación de al menos 5 caracteres.')]);
    $before = app_document_header($pdo,'remission_guide',$guideId);
    $source = app_lock_delivery_source($pdo,$before['source_type'],(int)$before['source_id']);
    $guide = app_document_header($pdo,'remission_guide',$guideId,true);
    if (!in_array($guide['status'],['Registrada','En transito'],true)) throw new DomainException('Solo se pueden anular entregas registradas o en tránsito.');
    $billed = $pdo->prepare("SELECT id FROM tbl_document_line_application WHERE source_type='remission_guide' AND source_id=? AND stage='BILLING' AND status='ACTIVE' FOR UPDATE"); $billed->execute([$guideId]);
    if ($billed->fetch()) throw new DomainException('La entrega tiene una factura activa; anula primero la factura relacionada.');
    if($guide['source_type']==='invoice'){
        $returned=$pdo->prepare("SELECT id FROM tbl_credit_note WHERE referenced_doc_type_id=1 AND referenced_doc_id=? AND status NOT IN(2,4) AND reason IN('DEVOLUCION_TOTAL','DEVOLUCION_ITEM') FOR UPDATE");$returned->execute([(int)$guide['source_id']]);
        if($returned->fetchColumn())throw new DomainException('La factura tiene devoluciones físicas activas; anula primero sus notas de crédito para revertir la entrega.');
    }
    if ((int)$guide['inventory_posted']) {
        $query = $pdo->prepare('SELECT * FROM tbl_remission_guide_detail WHERE remission_guide_id=? ORDER BY id'); $query->execute([$guideId]);
        foreach ($query->fetchAll() as $detail) {
            warehouse_apply_stock_delta($pdo,(int)$detail['product_id'],(float)$detail['quantity'],STOCK_MOVEMENT_DOCUMENT_REVERSAL,$userId,'Reversión de entrega','remission_guide',$guideId,$guide['number'],null,(int)$guide['warehouse_id'],null,(int)$detail['id']);
            warehouse_apply_reservation_delta($pdo,(int)$detail['product_id'],(float)$detail['quantity'],(int)$guide['warehouse_id']);
        }
    }
    app_reverse_document_lines($pdo,'remission_guide',$guideId,$userId);
    $pdo->prepare("UPDATE tbl_remission_guide SET status='Anulada',last_update=NOW() WHERE id=?")->execute([$guideId]);
    $orderId = $guide['source_type']==='sales_order' ? (int)$guide['source_id'] : (int)($source['sales_order_id'] ?? 0);
    if ($orderId>0) app_sync_sales_order_delivery_status($pdo,$orderId);
    app_record_document_status($pdo,'remission_guide',$guideId,$guide['number'],$guide['status'],'Anulada','Anulacion',$userId,trim($reason));
    return ['message'=>'Entrega anulada; sus cantidades vuelven a estar pendientes.'];
}
