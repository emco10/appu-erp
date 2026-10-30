<?php
require_once __DIR__.'/document-engine.php';

/** Recalcula el cumplimiento logistico sin alterar el estado comercial. */
function app_sync_sales_order_delivery_status(PDO $pdo, int $salesOrderId): string
{
    if ($salesOrderId <= 0) throw new InvalidArgumentException('Orden de venta invalida.');
    $direct=app_document_engine_available($pdo)?" + COALESCE((SELECT SUM(quantity) FROM tbl_document_line_application application WHERE application.source_type='sales_order' AND application.source_id=? AND application.stage='FULFILLMENT' AND application.target_type IN ('invoice','receipt') AND application.status='ACTIVE'),0)":'';
    $statement = $pdo->prepare(
        "SELECT COALESCE(SUM(detail.item_quantity), 0) AS ordered_quantity,
                COALESCE((SELECT SUM(guide_detail.quantity)
                    FROM tbl_remission_guide_detail guide_detail
                    JOIN tbl_remission_guide guide ON guide.id = guide_detail.remission_guide_id
                    LEFT JOIN tbl_invoice reserve_invoice
                      ON guide.source_type = 'invoice' AND reserve_invoice.id = guide.source_id
                    WHERE ((guide.source_type = 'sales_order' AND guide.source_id = ?)
                       OR (reserve_invoice.document_subtype = 'RESERVE' AND reserve_invoice.sales_order_id = ?))
                      AND guide.status <> 'Anulada'), 0) {$direct} AS delivered_quantity
         FROM tbl_sales_order_detail detail WHERE detail.sales_order_id = ?"
    );
    $statement->execute($direct!==''?[$salesOrderId,$salesOrderId,$salesOrderId,$salesOrderId]:[$salesOrderId,$salesOrderId,$salesOrderId]);
    $quantities = $statement->fetch() ?: ['ordered_quantity' => 0, 'delivered_quantity' => 0];
    $ordered = (float) $quantities['ordered_quantity'];
    $delivered = (float) $quantities['delivered_quantity'];
    $status = $delivered <= 0.000001 ? 'Pendiente' : ($delivered + 0.000001 >= $ordered ? 'Completa' : 'Parcial');
    $pdo->prepare('UPDATE tbl_sales_order SET delivery_status = ?, last_update = NOW() WHERE id = ?')->execute([$status, $salesOrderId]);
    return $status;
}
