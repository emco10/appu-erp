<?php

/** Reabre una orden atendida cuando su ultimo documento de venta activo se anula. */
function app_reopen_sales_order_after_sale_cancel(PDO $pdo, int $salesOrderId, string $documentType, int $documentId, int $userId, string $reference, bool $restoreReservation = true): bool
{
    if ($salesOrderId <= 0) return false;
    $orderStatement = $pdo->prepare('SELECT number,status,warehouse_id FROM tbl_sales_order WHERE id=? FOR UPDATE');
    $orderStatement->execute([$salesOrderId]);
    $order = $orderStatement->fetch();
    if (!$order || $order['status'] !== 'Atendida') return false;
    $activeDocuments = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM tbl_invoice WHERE sales_order_id=? AND status NOT IN (2,4) AND document_subtype <> 'ADVANCE' AND NOT (?='INVOICE' AND id=?))
              + (SELECT COUNT(*) FROM tbl_receipt WHERE sales_order_id=? AND status NOT IN (2,4) AND NOT (?='RECEIPT' AND id=?))"
    );
    $activeDocuments->execute([$salesOrderId,$documentType,$documentId,$salesOrderId,$documentType,$documentId]);
    if ((int)$activeDocuments->fetchColumn() > 0) return false;
    if ($restoreReservation) {
        $details = $pdo->prepare('SELECT item_id,item_quantity FROM tbl_sales_order_detail WHERE sales_order_id=?');
        $details->execute([$salesOrderId]);
        foreach ($details->fetchAll() as $detail) {
            warehouse_apply_reservation_delta($pdo,(int)$detail['item_id'],(float)$detail['item_quantity'],(int)$order['warehouse_id']);
        }
    }
    $pdo->prepare("UPDATE tbl_sales_order SET status='Confirmada',last_update=NOW() WHERE id=?")->execute([$salesOrderId]);
    app_record_document_status($pdo,'sales_order',$salesOrderId,(string)$order['number'],'Atendida','Confirmada','Reapertura',$userId,'Documento de venta anulado: '.$reference);
    return true;
}

