<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);
app_start_session();
require $root . '/global/connection.php';
require_once $root . '/global/sales-delivery.php';
require_once $root . '/global/sales-document-validation.php';
require_once $root . '/modules/finanzas/sync.php';
require_once $root . '/modules/inventory/stock.php';
require_once $root . '/global/document-history.php';
require_once $root . '/global/sales-order-lifecycle.php';
$pdo->beginTransaction();
try {
    $userId = (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $warehouseId = (int) $pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    if (!$userId || !$warehouseId) throw new RuntimeException('Faltan datos base para probar entregas.');
    $ruc = '20999999992';
    $pdo->prepare('INSERT INTO tbl_customer (ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$ruc,'CLIENTE QA ENTREGA','QA','','','','Direccion QA','15','1501','150101','','','','',0]);
    $customerId = (int) $pdo->lastInsertId();
    $productCode = 'QA-DEL-' . substr(uniqid(), -8);
    $pdo->prepare('INSERT INTO tbl_product (code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES (?,?,?,?,?,?,?,1)')->execute([$productCode,'APPU','ARTICULO QA','Temporal',10,10,'UND']);
    $productId = (int) $pdo->lastInsertId();
    $orderNumber = 'QA-OV-' . substr(uniqid(), -8);
    $pdo->prepare('INSERT INTO tbl_sales_order (number,status,customer_id,warehouse_id,ruc,customer_name,address,issue_date,delivery_date,currency,total_sub,total_tax,total_net,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$orderNumber,'Confirmada',$customerId,$warehouseId,$ruc,'CLIENTE QA ENTREGA','Direccion QA',date('Y-m-d'),date('Y-m-d'),'PEN',100,18,118,$userId]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_sales_order_detail (sales_order_id,item_id,item_code,item_name,item_quantity,item_unit_price,item_total) VALUES (?,?,?,?,?,?,?)')->execute([$orderId,$productId,$productCode,'ARTICULO QA',10,10,100]);
    $sourceDetailId = (int) $pdo->lastInsertId();
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Pendiente') throw new RuntimeException('Estado inicial incorrecto.');
    $postedRows = [[$productId,$productCode,'ARTICULO QA','Temporal',10,10,100]];
    $validated = app_validate_sales_order_document($pdo,$orderId,$customerId,$warehouseId,'PEN',$postedRows,100,18,118);
    if ((int)$validated['id'] !== $orderId) throw new RuntimeException('No valido la orden para facturacion.');
    try {
        app_validate_sales_order_document($pdo,$orderId,$customerId,$warehouseId,'PEN',[[$productId,$productCode,'ARTICULO QA','Temporal',10,9,90]],100,18,118);
        throw new RuntimeException('Acepto una cantidad distinta a la orden.');
    } catch (DomainException $expectedMismatch) {}
    $guideInsert = $pdo->prepare("INSERT INTO tbl_remission_guide (number,status,source_type,source_id,warehouse_id,issue_date,transfer_date,created_by) VALUES (?,'Registrada','sales_order',?,?,?,?,?)");
    $guideInsert->execute(['QA-GR-1-'.substr(uniqid(),-8),$orderId,$warehouseId,date('Y-m-d'),date('Y-m-d'),$userId]);
    $guideOne=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_remission_guide_detail (remission_guide_id,source_detail_id,product_id,quantity) VALUES (?,?,?,?)')->execute([$guideOne,$sourceDetailId,$productId,4]);
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Parcial') throw new RuntimeException('No marco entrega parcial.');
    $guideInsert->execute(['QA-GR-2-'.substr(uniqid(),-8),$orderId,$warehouseId,date('Y-m-d'),date('Y-m-d'),$userId]);
    $guideTwo=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_remission_guide_detail (remission_guide_id,source_detail_id,product_id,quantity) VALUES (?,?,?,?)')->execute([$guideTwo,$sourceDetailId,$productId,6]);
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Completa') throw new RuntimeException('No marco entrega completa.');
    $pdo->prepare("UPDATE tbl_remission_guide SET status='Anulada' WHERE id=?")->execute([$guideTwo]);
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Parcial') throw new RuntimeException('La anulacion no restituyo el saldo.');

    $invoiceNumber = 'QA-' . substr(uniqid(),-8);
    $pdo->prepare('INSERT INTO tbl_invoice (series,number,status,quotation_id,sales_order_id,customer_id,warehouse_id,ruc,name,address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(['Q001',$invoiceNumber,1,0,$orderId,$customerId,$warehouseId,$ruc,'CLIENTE QA ENTREGA','Direccion QA','',15,date('Y-m-d'),date('Y-m-d'),'PEN',0,0,100,18,118,0,$userId]);
    $invoiceId=(int)$pdo->lastInsertId();
    finance_sync_receivable($pdo,$invoiceId);
    $account=$pdo->prepare("SELECT total_amount,balance_amount,status,DATEDIFF(due_date,issue_date) AS payment_days FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id=?");
    $account->execute([$invoiceId]); $accountRow=$account->fetch();
    if (!$accountRow || (float)$accountRow['balance_amount']!==118.0 || (int)$accountRow['payment_days']!==15 || $accountRow['status']!=='Pendiente') throw new RuntimeException('La factura no genero correctamente su cuenta por cobrar.');
    $pdo->prepare('INSERT INTO tbl_warehouse_stock (warehouse_id,product_id,quantity,reserved_quantity) VALUES (?,?,10,0) ON DUPLICATE KEY UPDATE quantity=10,reserved_quantity=0')->execute([$warehouseId,$productId]);
    $pdo->prepare("UPDATE tbl_sales_order SET status='Atendida' WHERE id=?")->execute([$orderId]);
    if (!app_reopen_sales_order_after_sale_cancel($pdo,$orderId,'INVOICE',$invoiceId,$userId,'Q001-'.$invoiceNumber)) throw new RuntimeException('No reabrio la orden atendida.');
    $orderCheck=$pdo->prepare('SELECT status FROM tbl_sales_order WHERE id=?'); $orderCheck->execute([$orderId]);
    $reservationCheck=$pdo->prepare('SELECT reserved_quantity FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?'); $reservationCheck->execute([$warehouseId,$productId]);
    if ($orderCheck->fetchColumn()!=='Confirmada' || (float)$reservationCheck->fetchColumn()!==10.0) throw new RuntimeException('La reapertura no restauro estado y reserva.');
    echo 'PASS sales order delivery status lifecycle' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL sales delivery: '.$exception->getMessage().PHP_EOL); exit(1);
}
