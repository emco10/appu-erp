<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn()=>true, fn()=>true, fn($id)=>'', fn($id,$data)=>true, fn($id)=>true, fn($max)=>0);
app_start_session();
require $root . '/global/connection.php';
require_once $root . '/modules/inventory/stock.php';
require_once $root . '/global/sales-delivery.php';
require_once $root . '/global/document-history.php';
require_once $root . '/global/sales-order-lifecycle.php';
require_once $root . '/global/sales-fulfillment.php';

$pdo->beginTransaction();
try {
    $userId = (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $warehouseId = (int) $pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    if (!$userId || !$warehouseId) throw new RuntimeException('Faltan usuario o almacen activos.');

    $suffix = substr(uniqid(), -8);
    $ruc = '20' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
    $pdo->prepare('INSERT INTO tbl_customer(ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$ruc,'CLIENTE QA RESERVA','QA','','','','Direccion QA','15','1501','150101','','','','',0]);
    $customerId = (int) $pdo->lastInsertId();

    $code = 'QA-RES-' . $suffix;
    $pdo->prepare('INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES(?,?,?,?,0,?,?,1)')
        ->execute([$code,'APPU','ARTICULO QA RESERVA','Temporal',20,'UND']);
    $productId = (int) $pdo->lastInsertId();
    warehouse_apply_stock_delta($pdo,$productId,10,STOCK_MOVEMENT_ENTRY,$userId,'Ingreso QA reserva','goods_receipt',990001,'QA-RES-IN',null,$warehouseId);
    warehouse_apply_lot_delta($pdo,$productId,$warehouseId,'LOTE-RES-'.$suffix,date('Y-m-d',strtotime('+180 days')),10);
    warehouse_apply_reservation_delta($pdo,$productId,10,$warehouseId);

    $today = date('Y-m-d');
    $orderNumber = 'QA-OV-RES-' . $suffix;
    $pdo->prepare("INSERT INTO tbl_sales_order(number,status,delivery_status,customer_id,warehouse_id,ruc,customer_name,address,issue_date,delivery_date,currency,total_sub,total_tax,total_net,user_id) VALUES(?,'Atendida','Pendiente',?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$orderNumber,$customerId,$warehouseId,$ruc,'CLIENTE QA RESERVA','Direccion QA',$today,$today,'PEN',200,36,236,$userId]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_sales_order_detail(sales_order_id,item_id,item_code,item_name,item_quantity,item_unit_price,item_total) VALUES(?,?,?,?,?,?,?)')
        ->execute([$orderId,$productId,$code,'ARTICULO QA RESERVA',10,20,200]);

    $invoiceNumber = 'QA-' . $suffix;
    $pdo->prepare("INSERT INTO tbl_invoice(series,number,status,document_subtype,quotation_id,sales_order_id,customer_id,warehouse_id,ruc,name,address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id) VALUES('R001',?,1,'RESERVE',0,?,?,?,?,?,'Direccion QA','',0,?,?,'PEN',0,0,200,36,236,?,?)")
        ->execute([$invoiceNumber,$orderId,$customerId,$warehouseId,$ruc,'CLIENTE QA RESERVA',$today,$today,$userId,$userId]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_invoice_detail(item_description,item_id,item_code,item_name,item_quantity,item_unit_price,invoice_id) VALUES(?,?,?,?,?,?,?)')
        ->execute(['Temporal',$productId,$code,'ARTICULO QA RESERVA',10,20,$invoiceId]);
    $invoiceDetailId = (int) $pdo->lastInsertId();

    $stock = $pdo->prepare('SELECT quantity,reserved_quantity FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?');
    $stock->execute([$warehouseId,$productId]);
    $row = $stock->fetch();
    if ((float)$row['quantity'] !== 10.0 || (float)$row['reserved_quantity'] !== 10.0) throw new RuntimeException('La factura de reserva altero el inventario al contabilizarse.');

    $insertGuide = $pdo->prepare("INSERT INTO tbl_remission_guide(number,status,source_type,source_id,warehouse_id,issue_date,transfer_date,created_by) VALUES(?,'Registrada','invoice',?,?,?,?,?)");
    $deliver = function(float $quantity, string $number) use ($pdo,$insertGuide,$invoiceId,$warehouseId,$today,$userId,$invoiceDetailId,$productId): int {
        $insertGuide->execute([$number,$invoiceId,$warehouseId,$today,$today,$userId]);
        $guideId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO tbl_remission_guide_detail(remission_guide_id,source_detail_id,product_id,quantity) VALUES(?,?,?,?)')->execute([$guideId,$invoiceDetailId,$productId,$quantity]);
        app_post_sales_delivery($pdo,$guideId,$userId);
        return $guideId;
    };

    $guideOne = $deliver(4,'QA-GR-RES-1-'.$suffix);
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Parcial') throw new RuntimeException('La primera guia no marco entrega parcial.');
    $stock->execute([$warehouseId,$productId]); $row = $stock->fetch();
    if ((float)$row['quantity'] !== 6.0 || (float)$row['reserved_quantity'] !== 6.0) throw new RuntimeException('La primera entrega no concilio stock y reserva.');

    $guideTwo = $deliver(6,'QA-GR-RES-2-'.$suffix);
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Completa') throw new RuntimeException('La segunda guia no completo la entrega.');
    $stock->execute([$warehouseId,$productId]); $row = $stock->fetch();
    if ((float)$row['quantity'] !== 0.0 || (float)$row['reserved_quantity'] !== 0.0) throw new RuntimeException('La entrega total no dejo inventario conciliado.');

    foreach ([$guideTwo => 6, $guideOne => 4] as $guideId => $quantity) {
        app_cancel_sales_delivery($pdo,(int)$guideId,$userId,'Reversión de prueba QA');
    }
    if (app_sync_sales_order_delivery_status($pdo,$orderId) !== 'Pendiente') throw new RuntimeException('Anular las guias no restituyo el pendiente logistico.');
    if (!app_reopen_sales_order_after_sale_cancel($pdo,$orderId,'INVOICE',$invoiceId,$userId,'R001-'.$invoiceNumber,false)) throw new RuntimeException('No reabrio la orden al anular la factura de reserva.');
    $stock->execute([$warehouseId,$productId]); $row = $stock->fetch();
    $status = $pdo->prepare('SELECT status FROM tbl_sales_order WHERE id=?'); $status->execute([$orderId]);
    if ((float)$row['quantity'] !== 10.0 || (float)$row['reserved_quantity'] !== 10.0 || $status->fetchColumn() !== 'Confirmada') throw new RuntimeException('La anulacion duplico inventario/reserva o no reabrio la orden.');

    echo 'PASS reserve invoice partial delivery and cancellation lifecycle' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL reserve invoice lifecycle: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
