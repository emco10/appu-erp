<?php
/** Cantidad física recibida por línea, conservando las entradas de la OC al facturarla. */
function app_purchase_receipt_lines(PDO $pdo, string $type, int $id, bool $lock = false): array
{
    $isOrder = $type === 'ORDER';
    $table = $isOrder ? 'tbl_order_detail' : 'tbl_purchase_detail';
    $parent = $isOrder ? 'order_id' : 'purchase_id';
    $physicalRelation = $isOrder
        ? "detail.order_detail_id=line.id OR detail.purchase_detail_id IN (SELECT purchase_line.id FROM tbl_purchase_detail purchase_line JOIN tbl_purchase purchase ON purchase.id=purchase_line.purchase_id WHERE purchase.purchase_order_id=line.order_id AND purchase_line.item_id=line.item_id)"
        : "detail.purchase_detail_id=line.id OR detail.order_detail_id IN (SELECT order_line.id FROM tbl_order_detail order_line JOIN tbl_purchase purchase ON purchase.purchase_order_id=order_line.order_id WHERE purchase.id=line.purchase_id AND order_line.item_id=line.item_id)";
    $statement = $pdo->prepare("SELECT line.id,line.item_id AS product_id,line.item_code,line.item_description,line.item_unit_value AS unit_of_measure,line.item_unit_price,line.item_discounted_total,line.item_quantity AS ordered_quantity,COALESCE((SELECT SUM(detail.quantity) FROM tbl_goods_receipt_detail detail JOIN tbl_goods_receipt receipt ON receipt.id=detail.goods_receipt_id WHERE receipt.status='Registrado' AND ({$physicalRelation})),0) AS received_quantity FROM {$table} line WHERE line.{$parent}=? ORDER BY line.id" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute([$id]);
    $lines = $statement->fetchAll();
    foreach ($lines as &$line) $line['pending_quantity'] = max(0, (int)$line['ordered_quantity'] - (int)$line['received_quantity']);
    unset($line);
    return $lines;
}

function app_sync_purchase_receipt_status(PDO $pdo, int $purchaseId): string
{
    $statement=$pdo->prepare('SELECT status FROM tbl_purchase WHERE id=? FOR UPDATE');
    $statement->execute([$purchaseId]); $row=$statement->fetch();
    if(!$row) throw new RuntimeException('Compra inexistente.');
    if(in_array($row['status'],['Anulado','Anulada','Cancelada'],true)) return (string)$row['status'];
    $ordered=0;$received=0;
    foreach(app_purchase_receipt_lines($pdo,'PURCHASE',$purchaseId,true) as $line){$ordered+=(float)$line['ordered_quantity'];$received+=(float)$line['received_quantity'];}
    $status=$received<=0.000001?'Aprobado':($received+0.000001>=$ordered?'Atendido':'Parcial');
    $pdo->prepare('UPDATE tbl_purchase SET status=?,last_update=NOW() WHERE id=?')->execute([$status,$purchaseId]);
    return $status;
}

function app_sync_purchase_order_receipt_status(PDO $pdo, int $orderId): string
{
    $statement=$pdo->prepare('SELECT status FROM tbl_order WHERE id=? AND type=1 FOR UPDATE');$statement->execute([$orderId]);$status=$statement->fetchColumn();
    if($status===false)throw new RuntimeException('Orden de compra inexistente.');
    if(in_array($status,['Anulado','Anulada','Cancelada','Pendiente','Rechazado'],true))return (string)$status;
    $ordered=0;$received=0;foreach(app_purchase_receipt_lines($pdo,'ORDER',$orderId,true) as $line){$ordered+=(float)$line['ordered_quantity'];$received+=(float)$line['received_quantity'];}
    $linked=$pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id=? AND status NOT IN ('Anulado','Anulada','Cancelada')");$linked->execute([$orderId]);
    $next=$received<=0?((int)$linked->fetchColumn()>0?'Convertida':'Aprobado'):($received+0.000001>=$ordered?'Atendido':'Parcial');
    $pdo->prepare('UPDATE tbl_order SET status=?,last_update=NOW() WHERE id=?')->execute([$next,$orderId]);
    return $next;
}
