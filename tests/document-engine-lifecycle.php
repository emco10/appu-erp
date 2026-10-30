<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/../global/sales-delivery-billing.php';
if (!str_ends_with(DB,'_qa')) throw new RuntimeException('Use una base _qa.');
function engineAssert(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $user=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $warehouse=(int)$pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    $customer=(int)$pdo->query('SELECT client_id FROM tbl_customer WHERE active_status=1 ORDER BY client_id LIMIT 1')->fetchColumn();
    $suffix=bin2hex(random_bytes(4)); $today=date('Y-m-d');
    $pdo->prepare("INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES(?,'QA','Articulo motor QA','Temporal',0,10,'UND',1)")->execute(['QA-ENGINE-'.$suffix]); $product=(int)$pdo->lastInsertId();
    warehouse_apply_stock_delta($pdo,$product,10,STOCK_MOVEMENT_ENTRY,$user,'Inicial QA','goods_receipt',999001,'QA-INITIAL',null,$warehouse);
    warehouse_apply_lot_delta($pdo,$product,$warehouse,'QA-LOT-'.$suffix,'2028-01-01',10);
    $pdo->prepare("INSERT INTO tbl_sales_order(number,status,customer_id,warehouse_id,customer_name,issue_date,delivery_date,currency,total_sub,total_tax,total_net,user_id) VALUES(?,'Confirmada',?,?,'Cliente QA',?,?,'PEN',100,18,118,?)")->execute(['QA-ENGINE-'.$suffix,$customer,$warehouse,$today,$today,$user]); $order=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_sales_order_detail(sales_order_id,item_id,item_code,item_name,item_description,item_quantity,item_unit_price,item_total) VALUES(?,?,'QA','Articulo motor QA','Temporal',10,10,100)")->execute([$order,$product]); $sourceLine=(int)$pdo->lastInsertId();
    warehouse_apply_reservation_delta($pdo,$product,10,$warehouse);
    $deliver=function(int $quantity)use($pdo,$order,$warehouse,$today,$user,$sourceLine,$product,$suffix):int {
        $pdo->prepare("INSERT INTO tbl_remission_guide(number,status,source_type,source_id,warehouse_id,issue_date,transfer_date,created_by) VALUES(?,'Registrada','sales_order',?,?,?,?,?)")->execute(['QA-GR-'.$quantity.'-'.$suffix,$order,$warehouse,$today,$today,$user]); $guide=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO tbl_remission_guide_detail(remission_guide_id,source_detail_id,product_id,quantity) VALUES(?,?,?,?)')->execute([$guide,$sourceLine,$product,$quantity]);
        app_post_sales_delivery($pdo,$guide,$user); app_sync_sales_order_delivery_status($pdo,$order); return $guide;
    };
    $stock=function()use($pdo,$warehouse,$product):array { $q=$pdo->prepare('SELECT quantity,reserved_quantity FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?');$q->execute([$warehouse,$product]);return array_map('floatval',$q->fetch()); };
    $first=$deliver(6);
    engineAssert($stock()===['quantity'=>4.0,'reserved_quantity'=>4.0],'La entrega 6 no dejó físico/comprometido en 4.');
    engineAssert(app_document_open_lines($pdo,'sales_order',$order,'FULFILLMENT')[0]['open_quantity']===4.0,'El saldo de línea no es 4.');
    $pdo->exec('SAVEPOINT over_delivery');
    try { $deliver(5); throw new RuntimeException('Se permitió entregar 5 de un saldo 4.'); }
    catch (AppValidationException $expected) { engineAssert(str_contains($expected->errors[0]['message'],'4 unidades pendientes'),'El error no indica el saldo 4.'); $pdo->exec('ROLLBACK TO SAVEPOINT over_delivery'); }
    engineAssert($stock()===['quantity'=>4.0,'reserved_quantity'=>4.0],'El rechazo alteró existencias.');
    $second=$deliver(4);
    engineAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0],'La segunda entrega no agotó físico/reserva.');
    engineAssert(app_order_domain_status(app_document_header($pdo,'sales_order',$order))===AppDocumentStatus::CLOSED,'La orden no quedó cerrada por cumplimiento.');
    $preview=app_delivery_billing_preview($pdo,[$first,$second]); $lines=[];
    foreach($preview['lines'] as $line) $lines[]=['guide_id'=>$line['guide_id'],'guide_line_id'=>$line['guide_line_id'],'quantity'=>$line['open_quantity']];
    $q=$pdo->prepare('SELECT COUNT(*) FROM tbl_warehouse_movement WHERE product_id=?');$q->execute([$product]);$movements=(int)$q->fetchColumn();
    $partialLine=$lines[0];$partialLine['quantity']=2;
    $partial=app_invoice_from_deliveries($pdo,['lines'=>[$partialLine],'document_date'=>$today],$user);
    engineAssert($partial['total']===23.6,'La facturación parcial de 2 unidades es incorrecta.');
    $lines[0]['quantity']=4;
    $invoice=app_invoice_from_deliveries($pdo,['lines'=>$lines,'document_date'=>$today],$user);
    engineAssert($invoice['total']===94.4 && $invoice['total']+$partial['total']===118.0 && $invoice['fiscal_status']==='NO_APLICA','Totales/estado fiscal incorrectos.');
    engineAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0],'La factura duplicó la salida.');
    $q->execute([$product]);engineAssert((int)$q->fetchColumn()===$movements,'La factura produjo otro movimiento.');
    $q=$pdo->prepare("SELECT SUM(balance_amount) FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id IN (?,?)");$q->execute([$invoice['invoice_id'],$partial['invoice_id']]);engineAssert(abs((float)$q->fetchColumn()-118)<.001,'No se creó CxC.');
    try { app_cancel_sales_delivery($pdo,$first,$user,'Prueba QA anular'); throw new RuntimeException('Se anuló una entrega facturada.'); }
    catch (DomainException $expected) { engineAssert(str_contains($expected->getMessage(),'factura activa'),'Falta protección por factura.'); }
    $pdo->prepare('UPDATE tbl_invoice SET status=2 WHERE id=?')->execute([$invoice['invoice_id']]);
    app_reverse_document_lines($pdo,'invoice',$invoice['invoice_id'],$user); app_sync_delivery_order_billing($pdo,$order,$user);
    engineAssert(app_document_header($pdo,'sales_order',$order)['status']==='Confirmada','No se reabrió la orden con otra factura parcial activa.');
    $pdo->prepare('UPDATE tbl_invoice SET status=2 WHERE id=?')->execute([$partial['invoice_id']]);
    app_reverse_document_lines($pdo,'invoice',$partial['invoice_id'],$user); app_sync_delivery_order_billing($pdo,$order,$user);
    engineAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0],'Anular factura alteró el stock entregado.');
    app_cancel_sales_delivery($pdo,$second,$user,'Reversión de prueba');
    engineAssert($stock()===['quantity'=>4.0,'reserved_quantity'=>4.0],'La reversión parcial no restauró físico/reserva.');
    app_cancel_sales_delivery($pdo,$first,$user,'Reversión final de prueba');
    engineAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0],'La reversión final no concilia.');
    $q=$pdo->prepare('SELECT SUM(quantity) FROM tbl_warehouse_movement WHERE product_id=?');$q->execute([$product]);engineAssert((float)$q->fetchColumn()===10.0,'El ledger no concilia con el stock.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_warehouse_movement WHERE source_type='remission_guide' AND source_id IN (?,?) AND document_line_id IS NULL");$q->execute([$first,$second]);engineAssert((int)$q->fetchColumn()===0,'El movimiento no conserva línea documental.');
    $pdo->rollBack();echo "PASS order 10 delivery 6 reject 5 deliver 4 bill deliveries without double stock line traceability receivable and reversals\n";
} catch(Throwable $error) { if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL document engine: '.$error->getMessage().PHP_EOL);exit(1); }
