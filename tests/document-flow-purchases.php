<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/../global/document-flow-query.php';
require_once __DIR__.'/../global/finance-payments.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base _qa.');
function purchaseMapAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo->beginTransaction();
try{
 $user=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();$provider=(int)$pdo->query('SELECT id FROM tbl_provider ORDER BY id LIMIT 1')->fetchColumn();$warehouse=(int)$pdo->query('SELECT id FROM tbl_warehouse ORDER BY id LIMIT 1')->fetchColumn();$product=(int)$pdo->query('SELECT id FROM tbl_product ORDER BY id LIMIT 1')->fetchColumn();$key='QA-MAP-'.bin2hex(random_bytes(4));
 $pdo->prepare("INSERT INTO tbl_order(type,number,status,currency,issue_date,delivery_date,provider_id,payment_days,account_number,quotation,requester,approver,created_by,observation,total_purchase,total_tax,total_net,exchange_rate_sale,exchange_rate_purchase)VALUES(1,?,'Aprobado','PEN',CURDATE(),CURDATE(),?,0,'','','','',?,'QA',100,18,118,0,0)")->execute([$key,$provider,$user]);$order=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tbl_order_detail(order_id,item_id,item_code,item_description,item_gloss,item_unit_value,item_unit_price,item_quantity,item_discount_rate,item_discounted_total)VALUES(?,?,'QA','Mapa','', 'UND',10,10,0,100)")->execute([$order,$product]);$orderLine=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tbl_purchase(type,number,supplier_reference,status,document_subtype,currency,issue_date,delivery_date,provider_id,purchase_order_id,payment_days,created_by,observation,total_purchase,total_tax,total_net,exchange_rate_sale,exchange_rate_purchase)VALUES(1,?,?,'Aprobado','RESERVE','PEN',CURDATE(),CURDATE(),?,?,0,?,'QA',100,18,118,0,0)")->execute([$key.'-P',$key,$provider,$order,$user]);$purchase=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tbl_goods_receipt(number,purchase_order_id,base_document_type,base_document_id,provider_id,warehouse_id,status,receipt_date,received_by)VALUES(?,?,'ORDER',?,?,?,'Registrado',CURDATE(),?)")->execute([$key.'-EM',$order,$order,$provider,$warehouse,$user]);$receipt=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO tbl_goods_receipt_detail(goods_receipt_id,order_detail_id,base_document_type,base_line_id,product_id,item_code,item_description,unit_of_measure,quantity,base_unit_cost,base_inventory_value)VALUES(?,?,'ORDER',?,?,'QA','Mapa','UND',4,10,40)")->execute([$receipt,$orderLine,$orderLine,$product]);$receiptLine=(int)$pdo->lastInsertId();
 app_apply_document_line($pdo,'order',$order,$orderLine,'goods_receipt',$receipt,$receiptLine,'RECEPTION',4,$user);
 app_record_document_flow($pdo,'purchase_order',$order,'purchase',$purchase,$user,'CONVERSION');
 app_record_document_flow($pdo,'order',$order,'goods_receipt',$receipt,$user,'RECEPTION');
 $pdo->prepare("INSERT INTO tbl_accounts_payable(document_type,document_id,document_number,provider_id,issue_date,due_date,currency,total_amount,balance_amount)VALUES('purchase',?,?,?,CURDATE(),CURDATE(),'PEN',118,118)")->execute([$purchase,$key,$provider]);$account=(int)$pdo->lastInsertId();
 $payment=app_create_finance_payment($pdo,'payable',[['account_id'=>$account,'amount'=>50]],['payment_date'=>date('Y-m-d')],$user);
 $graph=app_query_document_flow($pdo,'purchase_order',$order);$byKey=array_column($graph['nodes'],null,'key');
 purchaseMapAssert(count($graph['nodes'])===4,'El mapa no recorre orden, recepción, factura y pago.');purchaseMapAssert($graph['selected']==='order:'.$order,'No normalizó el tipo legado purchase_order.');
 purchaseMapAssert(str_contains($byKey['goods_receipt:'.$receipt]['url'],'receipt_id='.$receipt),'La ruta de la entrada no abre su registro.');purchaseMapAssert(str_contains($byKey['outgoing_payment:'.$payment['id']]['url'],'payment_id='.$payment['id']),'La ruta del pago no conserva su registro.');
 purchaseMapAssert((float)$graph['line_applications'][0]['quantity']===4.0&&(int)$graph['line_applications'][0]['source_line_number']===1,'No conserva la aplicación por línea de recepción.');
 $edges=array_column($graph['edges'],null,'target');purchaseMapAssert((float)$edges['goods_receipt:'.$receipt]['quantity']===4.0,'La arista de recepción no muestra su cantidad.');purchaseMapAssert((float)$edges['outgoing_payment:'.$payment['id']]['amount']===50.0,'La arista de pago no muestra su importe.');
 $_SESSION['loggedInUser']=['ROLE_CODE'=>'purchasing','PERMISSIONS'=>['purchases.view']];$restricted=app_query_document_flow($pdo,'order',$order,true);purchaseMapAssert(count($restricted['nodes'])===3&&!in_array('outgoing_payment',array_column($restricted['nodes'],'type'),true),'El mapa revela pagos sin permiso financiero.');
 $_SESSION['loggedInUser']=['ROLE_CODE'=>'finance','PERMISSIONS'=>['finance.view']];$restricted=app_query_document_flow($pdo,'outgoing_payment',$payment['id'],true);purchaseMapAssert(count($restricted['nodes'])===1,'El mapa revela compras sin permiso de compras.');
 echo "PASS purchase map legacy aliases receipt line quantities payments routes and permission filtering\n";$pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL purchase map: '.$error->getMessage().PHP_EOL);exit(1);}
