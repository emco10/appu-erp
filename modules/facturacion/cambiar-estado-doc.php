<?php

require '../../global/connection.php';
require_once '../inventory/stock.php';
require_once '../../global/document-history.php';
require_once '../../global/sales-order-lifecycle.php';
require_once '../finanzas/sync.php';
require_once '../../global/sales-advance.php';
require_once '../../global/sales-delivery-billing.php';

app_require_permission('sales.manage');

$documentType = strtoupper(trim((string) ($_POST['TIPO_DOC'] ?? '')));
$documentId = filter_var($_POST['ID_DOC'] ?? null, FILTER_VALIDATE_INT);
$newStatus = filter_var($_POST['ESTADO_DOC'] ?? null, FILTER_VALIDATE_INT);

$documentMap = [
    'INVOICE' => [
        'table' => 'tbl_invoice',
        'detail_table' => 'tbl_invoice_detail',
        'detail_key' => 'invoice_id',
        'source_type' => 'invoice',
        'label' => 'factura',
    ],
    'RECEIPT' => [
        'table' => 'tbl_receipt',
        'detail_table' => 'tbl_receipt_detail',
        'detail_key' => 'receipt_id',
        'source_type' => 'receipt',
        'label' => 'boleta',
    ],
    'CREDIT_NOTE' => [
        'table' => 'tbl_credit_note',
        'detail_table' => 'tbl_credit_note_detail',
        'detail_key' => 'credit_note_id',
        'source_type' => 'credit_note',
        'label' => 'nota de credito',
    ],
];

if (!isset($documentMap[$documentType]) || $documentId === false || !in_array($newStatus, [1, 2, 3, 4], true)) {
    http_response_code(422);
    echo '0';
    exit;
}

$config = $documentMap[$documentType];
$userId = (int) $_SESSION['loggedInUser']['USERID'];

try {
    $pdo->beginTransaction();

    if ($documentType === 'INVOICE' && app_document_engine_available($pdo)) {
        $root = $pdo->prepare('SELECT sales_order_id FROM tbl_invoice WHERE id=?'); $root->execute([$documentId]);
        $rootId=(int)$root->fetchColumn();
        if ($rootId>0) app_document_header($pdo,'sales_order',$rootId,true);
    }

    $reasonColumn = $documentType === 'CREDIT_NOTE'
        ? ', reason, NULL AS document_subtype'
        : ($documentType === 'INVOICE' ? ', sales_order_id, document_subtype' : ", sales_order_id, 'STANDARD' AS document_subtype");
    $reasonColumn .= $documentType === 'INVOICE' && app_document_engine_available($pdo) ? ', inventory_origin' : ", 'DOCUMENT' AS inventory_origin";
    $headerStatement = $pdo->prepare(
        "SELECT status, series, number, warehouse_id{$reasonColumn}
         FROM {$config['table']}
         WHERE id = ?
         FOR UPDATE"
    );
    $headerStatement->execute([$documentId]);
    $document = $headerStatement->fetch();

    if (!$document) {
        throw new RuntimeException('Documento inexistente.');
    }

    $oldStatus = (int) $document['status'];
    $warehouseId = (int) ($document['warehouse_id'] ?? 1);
    if ($warehouseId <= 0) {
        $warehouseId = 1;
    }
    if ($oldStatus === $newStatus) {
        $pdo->commit();
        echo '1';
        exit;
    }

    // Un documento anulado o cancelado es terminal: no se reactiva ni se
    // cambia de estado accidentalmente desde el navegador.
    if (in_array($oldStatus, [2, 4], true)) {
        throw new DomainException('El documento ya está cerrado y no admite más cambios de estado.');
    }
    if (in_array($newStatus, [2, 4], true) && in_array($documentType, ['INVOICE', 'RECEIPT'], true)) {
        $credits=$pdo->prepare('SELECT id FROM tbl_credit_note WHERE referenced_doc_id=? AND referenced_doc_type_id=? AND status NOT IN (2,4) FOR UPDATE'); $credits->execute([$documentId,$documentType==='INVOICE'?1:2]);
        if ($credits->fetch()) throw new DomainException('El documento tiene notas de crédito activas; anúlalas primero.');
        if ($documentType === 'INVOICE' && ($document['document_subtype'] ?? 'STANDARD') === 'ADVANCE') finance_assert_advance_can_close($pdo,(int)$documentId);
        $paidStatement = $pdo->prepare(
            "SELECT COALESCE(paid_amount, 0) FROM tbl_accounts_receivable
             WHERE document_type = ? AND document_id = ? FOR UPDATE"
        );
        $paidStatement->execute([strtolower($documentType === 'INVOICE' ? 'invoice' : 'receipt'), (int) $documentId]);
        $paidAmount = $paidStatement->fetchColumn();
        if ($paidAmount !== false && (float) $paidAmount > 0) {
            throw new DomainException('El documento tiene cobros aplicados; primero reversa los cobros en Cuentas por cobrar.');
        }

        // Una guia activa depende del documento de venta. Primero se anula la
        // guia y luego el documento origen, evitando dejar trazabilidad rota.
        $guideStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tbl_document_flow flow
             INNER JOIN tbl_remission_guide guide
               ON guide.id = flow.target_id
             WHERE flow.source_type = ?
               AND flow.source_id = ?
               AND flow.target_type = 'remission_guide'
               AND guide.status <> 'Anulada'"
        );
        $guideStatement->execute([$config['source_type'], (int) $documentId]);
        if ((int) $guideStatement->fetchColumn() > 0) {
            throw new DomainException('El documento tiene guias de remision activas; anula primero las guias relacionadas.');
        }
    }
    $statusComment = trim((string) ($_POST['COMENTARIO'] ?? ''));
    if (in_array($newStatus, [2, 4], true) && mb_strlen($statusComment) < 5) {
        throw new DomainException('Indica un motivo de al menos 5 caracteres para anular o cancelar el documento.');
    }
    if ($statusComment === '') {
        $statusComment = $newStatus === 2 ? 'Documento anulado desde el formulario.' : ($newStatus === 4 ? 'Documento cancelado desde el formulario.' : 'Cambio de estado desde el formulario.');
    }

    $isCreditNoteReturn = $documentType === 'CREDIT_NOTE'
        && warehouse_credit_note_returns_stock((string) $document['reason']);
    $inventoryRelevant = ($documentType !== 'CREDIT_NOTE' || $isCreditNoteReturn)
        && ($document['inventory_origin'] ?? 'DOCUMENT') !== 'DELIVERY'
        && !($documentType === 'INVOICE' && in_array(($document['document_subtype'] ?? 'STANDARD'), ['RESERVE','ADVANCE'], true));
    // Tanto Anulado como Cancelado son estados terminales. Ambos deben
    // revertir el movimiento de inventario; de lo contrario el documento
    // desaparece de CxC pero sus unidades siguen descontadas del stock.
    $wasActiveInInventory = $inventoryRelevant && !in_array($oldStatus, [2, 4], true);
    $willBeActiveInInventory = $inventoryRelevant && !in_array($newStatus, [2, 4], true);

    if ($wasActiveInInventory !== $willBeActiveInInventory) {
        $detailStatement = $pdo->prepare(
            "SELECT item_id AS product_id, item_quantity AS quantity
             FROM {$config['detail_table']}
             WHERE {$config['detail_key']} = ?"
        );
        $detailStatement->execute([$documentId]);
        $items = $detailStatement->fetchAll();

        if (!$items) {
            throw new RuntimeException('El documento no contiene productos.');
        }

        $documentReference = $document['series'] . '-' . $document['number'];

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];

            if ($documentType === 'CREDIT_NOTE') {
                $quantityDelta = $willBeActiveInInventory ? $quantity : -$quantity;
                $movementType = $willBeActiveInInventory
                    ? STOCK_MOVEMENT_CUSTOMER_RETURN
                    : STOCK_MOVEMENT_DOCUMENT_REVERSAL;
                $observation = $willBeActiveInInventory
                    ? 'Reactivacion de devolucion'
                    : 'Anulacion de devolucion';
            } else {
                $quantityDelta = $willBeActiveInInventory ? -$quantity : $quantity;
                $movementType = $willBeActiveInInventory
                    ? STOCK_MOVEMENT_SALE
                    : STOCK_MOVEMENT_DOCUMENT_REVERSAL;
                $observation = $willBeActiveInInventory
                    ? 'Reactivacion de ' . $config['label']
                    : 'Anulacion de ' . $config['label'];
            }

            warehouse_apply_stock_delta(
                $pdo,
                (int) $item['product_id'],
                $quantityDelta,
                $movementType,
                $userId,
                $observation,
                $config['source_type'],
                (int) $documentId,
                $documentReference,
                null,
                $warehouseId
            );
        }
    }

    $updateStatus = $pdo->prepare("UPDATE {$config['table']} SET status = ?, last_update = NOW() WHERE id = ?");
    $updateStatus->execute([$newStatus, $documentId]);

    if ($documentType === 'CREDIT_NOTE') {
        $referenceStatement=$pdo->prepare('SELECT referenced_doc_id,referenced_doc_type_id FROM tbl_credit_note WHERE id=?');
        $referenceStatement->execute([$documentId]);$reference=$referenceStatement->fetch();
        if($reference) finance_recalculate_credit_notes($pdo,(int)$reference['referenced_doc_id'],(int)$reference['referenced_doc_type_id']);
    }

    if (in_array($documentType, ['INVOICE', 'RECEIPT'], true)) {
        $accountType = strtolower($documentType === 'INVOICE' ? 'invoice' : 'receipt');
        if (in_array($newStatus, [2, 4], true)) {
            if(app_document_engine_available($pdo))app_reverse_document_lines($pdo,$accountType,(int)$documentId,$userId);
            if ($documentType === 'INVOICE' && in_array(($document['document_subtype'] ?? 'STANDARD'), ['STANDARD','RESERVE'], true)) finance_reverse_sales_advance_applications($pdo,(int)$documentId);
            $pdo->prepare("UPDATE tbl_accounts_receivable SET status = 'Anulada', balance_amount = 0 WHERE document_type = ? AND document_id = ?")
                ->execute([$accountType, (int) $documentId]);
        } else {
            $pdo->prepare(
                "UPDATE tbl_accounts_receivable
                 SET balance_amount = GREATEST(0, total_amount - paid_amount),
                     status = IF(paid_amount >= total_amount, 'Pagada', IF(paid_amount > 0, 'Parcial', 'Pendiente'))
                 WHERE document_type = ? AND document_id = ?"
            )->execute([$accountType, (int) $documentId]);
        }

        if (in_array($newStatus, [2, 4], true) && (int) ($document['sales_order_id'] ?? 0) > 0 && ($document['document_subtype'] ?? 'STANDARD') !== 'ADVANCE') {
            if (($document['inventory_origin'] ?? 'DOCUMENT') === 'DELIVERY') {
                app_sync_delivery_order_billing($pdo,(int)$document['sales_order_id'],$userId);
            } else {
                $restoreReservation = !($documentType === 'INVOICE' && ($document['document_subtype'] ?? 'STANDARD') === 'RESERVE');
                app_reopen_sales_order_after_sale_cancel($pdo,(int)$document['sales_order_id'],$documentType,$documentId,$userId,$document['series'].'-'.$document['number'],$restoreReservation);
            }
            if(app_document_engine_available($pdo))app_sync_sales_order_delivery_status($pdo,(int)$document['sales_order_id']);
        }
    }

    app_record_document_status(
        $pdo,
        $config['source_type'],
        (int) $documentId,
        (string) $document['series'] . '-' . (string) $document['number'],
        app_sales_status_label($oldStatus),
        app_sales_status_label($newStatus),
        $newStatus === 2 ? 'Anulacion' : ($newStatus === 4 ? 'Cancelacion' : 'Cambio de estado'),
        $userId,
        $statusComment
    );

    $pdo->commit();
    echo '1';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('APPU document status error: ' . $exception->getMessage());
    app_api_error($exception,'No se pudo cambiar el estado del documento.');
}

