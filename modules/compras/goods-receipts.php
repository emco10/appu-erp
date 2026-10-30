<?php
require __DIR__.'/../../global/connection.php';
require_once __DIR__.'/../inventory/stock.php';
require_once __DIR__.'/../../global/document-history.php';
require_once __DIR__.'/../../global/document-numbering.php';
require_once __DIR__.'/../../global/purchase-receipt.php';
require_once __DIR__.'/../../global/document-engine.php';
require_once __DIR__.'/../../global/inventory-valuation.php';
require_once __DIR__.'/../finanzas/sync.php';

header('Content-Type: application/json; charset=utf-8');
$method=$_SERVER['REQUEST_METHOD']??'GET';
$reply=static function(int $status,array $data):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;};
$validType=static function($value):string{$type=strtoupper(trim((string)$value));if(!in_array($type,['ORDER','PURCHASE'],true))throw new DomainException('Tipo de documento base inválido.');return $type;};

function goods_receipt_base(PDO $pdo,string $type,int $id,bool $lock=false):array
{
    $table=$type==='ORDER'?'tbl_order':'tbl_purchase';
    $sql="SELECT document.*,provider.code AS provider_code,provider.business_name AS provider_name,provider.address AS master_address FROM {$table} document JOIN tbl_provider provider ON provider.id=document.provider_id WHERE document.id=?".($type==='ORDER'?' AND document.type=1':'').($lock?' FOR UPDATE':'');
    $statement=$pdo->prepare($sql);$statement->execute([$id]);$base=$statement->fetch();
    if(!$base)throw new DomainException('El documento base no existe.');
    $allowed=$type==='ORDER'?['Aprobado','Convertida','Parcial','Atendido']:['Aprobado','Parcial','Atendido'];
    if(!in_array((string)$base['status'],$allowed,true))throw new DomainException('El documento base no está aprobado o vigente.');
    $base['base_type']=$type;$base['base_label']=$type==='ORDER'?'Orden de compra':(((string)($base['document_subtype']??'STANDARD'))==='RESERVE'?'Factura de reserva':'Factura de proveedor');
    $base['currency']=app_currency_code((string)$base['currency']);$base['valuation_currency']=app_currency_code((string)$pdo->query('SELECT currency FROM tbl_company_settings WHERE id=1')->fetchColumn());
    $base['suggested_valuation_exchange_rate']=$base['currency']===$base['valuation_currency']?1:($base['valuation_currency']==='PEN'&&$base['currency']==='USD'&&(float)($base['exchange_rate_purchase']??0)>0?(float)$base['exchange_rate_purchase']:null);
    return $base;
}

function goods_receipt_lines(PDO $pdo,string $type,int $id,bool $lock=false):array
{
    $lines=app_purchase_receipt_lines($pdo,$type,$id,$lock);
    foreach($lines as &$line)$line['item_unit_price']=(float)$line['ordered_quantity']>0?(float)$line['item_discounted_total']/(float)$line['ordered_quantity']:0;
    unset($line);
    return $lines;
}

function goods_receipt_recalculate_base(PDO $pdo,string $type,int $id,int $userId,string $reason):void
{
    $base=goods_receipt_base($pdo,$type,$id,true);$lines=goods_receipt_lines($pdo,$type,$id,true);$ordered=0;$received=0;
    foreach($lines as $line){$ordered+=(int)$line['ordered_quantity'];$received+=(int)$line['received_quantity'];}
    if($received<=0){$status='Aprobado';if($type==='ORDER'){$linked=$pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id=? AND status NOT IN ('Anulado','Anulada','Cancelada')");$linked->execute([$id]);if((int)$linked->fetchColumn()>0)$status='Convertida';}}
    elseif($received<$ordered)$status='Parcial';else $status='Atendido';
    if($status!==$base['status']){$table=$type==='ORDER'?'tbl_order':'tbl_purchase';$pdo->prepare("UPDATE {$table} SET status=?,last_update=NOW() WHERE id=?")->execute([$status,$id]);app_record_document_status($pdo,strtolower($type),$id,(string)$base['number'],(string)$base['status'],$status,'Recepción',$userId,$reason);}
    if($type==='PURCHASE'){
        app_sync_purchase_receipt_status($pdo,$id);
        if($status==='Atendido'&&($base['document_subtype']??'')==='STANDARD')finance_sync_payable($pdo,$id);
        if($status!=='Atendido'&&($base['document_subtype']??'')==='STANDARD')$pdo->prepare("UPDATE tbl_accounts_payable SET status='Anulada',balance_amount=0 WHERE document_type='purchase' AND document_id=?")->execute([$id]);
        if((int)($base['purchase_order_id']??0)>0)goods_receipt_recalculate_base($pdo,'ORDER',(int)$base['purchase_order_id'],$userId,$reason);
    }
}

if(defined('APPU_GOODS_RECEIPT_LIBRARY_ONLY'))return;

if($method==='GET'){
    app_require_permission('purchases.view');$action=strtolower(trim((string)($_GET['action']??'detail')));
    if($action==='list'){
        $rows=$pdo->query("SELECT receipt.id,receipt.number,receipt.receipt_date,receipt.status,receipt.provider_code,COALESCE(NULLIF(receipt.provider_name,''),provider.business_name) provider_name,receipt.base_document_type,receipt.base_document_id,CASE WHEN receipt.base_document_type='ORDER' THEN purchase_order.number ELSE purchase.number END base_number FROM tbl_goods_receipt receipt JOIN tbl_provider provider ON provider.id=receipt.provider_id LEFT JOIN tbl_order purchase_order ON purchase_order.id=receipt.purchase_order_id LEFT JOIN tbl_purchase purchase ON purchase.id=receipt.purchase_id ORDER BY receipt.id DESC LIMIT 500")->fetchAll();
        $reply(200,['receipts'=>$rows]);
    }
    if($action==='receipt'){
        $receiptId=(int)($_GET['receipt_id']??0);if($receiptId<1)$reply(422,['message'=>'Entrada de mercancías inválida.']);
        $statement=$pdo->prepare("SELECT receipt.*,warehouse.code warehouse_code,warehouse.name warehouse_name,CASE WHEN receipt.base_document_type='ORDER' THEN purchase_order.number ELSE purchase.number END base_number,CASE WHEN receipt.base_document_type='ORDER' THEN 'Orden de compra' WHEN purchase.document_subtype='RESERVE' THEN 'Factura de reserva' ELSE 'Factura de proveedor' END base_label FROM tbl_goods_receipt receipt JOIN tbl_warehouse warehouse ON warehouse.id=receipt.warehouse_id LEFT JOIN tbl_order purchase_order ON purchase_order.id=receipt.purchase_order_id LEFT JOIN tbl_purchase purchase ON purchase.id=receipt.purchase_id WHERE receipt.id=?");$statement->execute([$receiptId]);$receipt=$statement->fetch();if(!$receipt)$reply(404,['message'=>'La entrada de mercancías no existe.']);
        $details=$pdo->prepare("SELECT detail.*,product.code current_item_code,product.name current_item_name,COALESCE(order_line.item_quantity,purchase_line.item_quantity,detail.quantity) ordered_quantity,COALESCE(received.quantity,detail.quantity) received_quantity FROM tbl_goods_receipt_detail detail JOIN tbl_product product ON product.id=detail.product_id LEFT JOIN tbl_order_detail order_line ON order_line.id=detail.order_detail_id LEFT JOIN tbl_purchase_detail purchase_line ON purchase_line.id=detail.purchase_detail_id LEFT JOIN (SELECT other_detail.base_document_type,other_detail.base_line_id,SUM(other_detail.quantity) quantity FROM tbl_goods_receipt_detail other_detail JOIN tbl_goods_receipt other_receipt ON other_receipt.id=other_detail.goods_receipt_id WHERE other_receipt.status='Registrado' GROUP BY other_detail.base_document_type,other_detail.base_line_id) received ON received.base_document_type=detail.base_document_type AND received.base_line_id=detail.base_line_id WHERE detail.goods_receipt_id=? ORDER BY detail.id");$details->execute([$receiptId]);$lines=$details->fetchAll();foreach($lines as &$line)$line['pending_quantity']=max(0,(int)$line['ordered_quantity']-(int)$line['received_quantity']);unset($line);
        $balances=[];foreach(goods_receipt_lines($pdo,(string)$receipt['base_document_type'],(int)$receipt['base_document_id']) as $balance)$balances[(int)$balance['id']]=$balance;
        foreach($lines as &$line){$balance=$balances[(int)$line['base_line_id']]??null;if($balance){$line['received_quantity']=$balance['received_quantity'];$line['pending_quantity']=$balance['pending_quantity'];}}unset($line);
        $reply(200,['receipt'=>$receipt,'details'=>$lines]);
    }
    if($action==='eligible'){
        $orders=$pdo->query("SELECT 'ORDER' base_type,orders.id,orders.number,orders.issue_date,orders.delivery_date,orders.currency,orders.status,'Orden de compra' base_label,provider.code provider_code,provider.business_name provider_name,SUM(GREATEST(line.item_quantity-COALESCE(received.quantity,0),0)) pending_quantity FROM tbl_order orders JOIN tbl_provider provider ON provider.id=orders.provider_id JOIN tbl_order_detail line ON line.order_id=orders.id LEFT JOIN (SELECT detail.base_line_id,SUM(detail.quantity) quantity FROM tbl_goods_receipt_detail detail JOIN tbl_goods_receipt receipt ON receipt.id=detail.goods_receipt_id WHERE detail.base_document_type='ORDER' AND receipt.status='Registrado' GROUP BY detail.base_line_id) received ON received.base_line_id=line.id WHERE orders.type=1 AND orders.status IN ('Aprobado','Convertida','Parcial','Atendido') AND NOT EXISTS(SELECT 1 FROM tbl_purchase purchase WHERE purchase.purchase_order_id=orders.id AND purchase.status NOT IN ('Anulado','Anulada','Cancelada')) GROUP BY orders.id HAVING pending_quantity>0")->fetchAll();
        $purchases=$pdo->query("SELECT 'PURCHASE' base_type,purchase.id,purchase.number,purchase.issue_date,purchase.delivery_date,purchase.currency,purchase.status,IF(purchase.document_subtype='RESERVE','Factura de reserva','Factura de proveedor') base_label,provider.code provider_code,provider.business_name provider_name,SUM(GREATEST(line.item_quantity-COALESCE(received.quantity,0),0)) pending_quantity FROM tbl_purchase purchase JOIN tbl_provider provider ON provider.id=purchase.provider_id JOIN tbl_purchase_detail line ON line.purchase_id=purchase.id LEFT JOIN (SELECT detail.base_line_id,SUM(detail.quantity) quantity FROM tbl_goods_receipt_detail detail JOIN tbl_goods_receipt receipt ON receipt.id=detail.goods_receipt_id WHERE detail.base_document_type='PURCHASE' AND receipt.status='Registrado' GROUP BY detail.base_line_id) received ON received.base_line_id=line.id WHERE purchase.status IN ('Aprobado','Parcial','Atendido') GROUP BY purchase.id HAVING pending_quantity>0")->fetchAll();
        $rows=[];foreach(array_merge($orders,$purchases) as $candidate){$pending=0;foreach(goods_receipt_lines($pdo,$candidate['base_type'],(int)$candidate['id']) as $candidateLine)$pending+=(int)$candidateLine['pending_quantity'];if($pending>0){$candidate['pending_quantity']=$pending;$rows[]=$candidate;}}usort($rows,static fn($a,$b)=>strcmp($b['issue_date'].$b['id'],$a['issue_date'].$a['id']));$reply(200,['bases'=>$rows,'purchases'=>array_values(array_filter($rows,static fn($row)=>$row['base_type']==='PURCHASE'))]);
    }
    try{$type=$validType($_GET['base_type']??'');}catch(DomainException $error){$reply(422,['message'=>$error->getMessage()]);}$id=filter_var($_GET['base_id']??null,FILTER_VALIDATE_INT);if($id===false||$id<1)$reply(422,['message'=>'Documento base inválido.']);
    try{$base=goods_receipt_base($pdo,$type,(int)$id);$details=goods_receipt_lines($pdo,$type,(int)$id);$warehouses=$pdo->query("SELECT id,code,name FROM tbl_warehouse WHERE active_status=1 ORDER BY name")->fetchAll();$history=$pdo->prepare("SELECT receipt.id,receipt.number,receipt.status,receipt.receipt_date,receipt.supplier_delivery_reference,warehouse.code warehouse_code,warehouse.name warehouse_name FROM tbl_goods_receipt receipt JOIN tbl_warehouse warehouse ON warehouse.id=receipt.warehouse_id WHERE receipt.base_document_type=? AND receipt.base_document_id=? ORDER BY receipt.id DESC");$history->execute([$type,$id]);$reply(200,['base'=>$base,'purchase'=>$type==='PURCHASE'?$base:null,'details'=>$details,'warehouses'=>$warehouses,'receipts'=>$history->fetchAll()]);}catch(Throwable $e){$reply(422,['message'=>$e->getMessage()]);}
}

if($method!=='POST')$reply(405,['message'=>'Método no permitido.']);
app_require_permission('inventory.manage');$action=strtolower(trim((string)($_POST['action']??'create')));$userId=(int)$_SESSION['loggedInUser']['USERID'];
try{
    if(!in_array($action,['create','cancel'],true))throw new DomainException('Acción de entrada de mercancías inválida.');
    $pdo->beginTransaction();
    if($action==='cancel'){
        $receiptId=(int)($_POST['receipt_id']??0);$reason=trim((string)($_POST['reason']??''));if($receiptId<1||mb_strlen($reason)<5)throw new DomainException('Indica la recepción y un motivo de anulación de al menos 5 caracteres.');
        $s=$pdo->prepare('SELECT * FROM tbl_goods_receipt WHERE id=? FOR UPDATE');$s->execute([$receiptId]);$receipt=$s->fetch();if(!$receipt)throw new DomainException('La recepción no existe.');if($receipt['status']==='Anulada'){$pdo->commit();$reply(200,['message'=>'La recepción ya estaba anulada.']);}if($receipt['status']!=='Registrado')throw new DomainException('La recepción no está en un estado anulable.');
        $cost=$pdo->prepare("SELECT COUNT(*) FROM tbl_landed_cost WHERE goods_receipt_id=? AND status='Registrado'");$cost->execute([$receiptId]);if((int)$cost->fetchColumn()>0)throw new DomainException('Anula primero los costos de entrega vinculados.');
        if($receipt['base_document_type']==='ORDER'){
            $invoiced=$pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id=? AND status NOT IN ('Anulado','Anulada','Cancelada')");$invoiced->execute([(int)$receipt['base_document_id']]);if((int)$invoiced->fetchColumn()>0)throw new DomainException('Anula primero la factura de proveedor vinculada a la orden recibida.');
        }else{
            $purchase=$pdo->prepare('SELECT document_subtype FROM tbl_purchase WHERE id=? FOR UPDATE');$purchase->execute([(int)$receipt['purchase_id']]);$subtype=$purchase->fetchColumn();
            if($subtype==='STANDARD'){$paid=$pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM tbl_accounts_payable WHERE document_type='purchase' AND document_id=? FOR UPDATE");$paid->execute([(int)$receipt['purchase_id']]);if((float)$paid->fetchColumn()>0)throw new DomainException('La factura estándar tiene pagos aplicados; reversa primero los pagos antes de anular la recepción.');}
        }
        $details=$pdo->prepare('SELECT * FROM tbl_goods_receipt_detail WHERE goods_receipt_id=? FOR UPDATE');$details->execute([$receiptId]);foreach($details->fetchAll() as $line){$qty=(float)$line['quantity'];warehouse_apply_stock_delta($pdo,(int)$line['product_id'],-$qty,STOCK_MOVEMENT_DOCUMENT_REVERSAL,$userId,'Anulación de recepción '.$receipt['number'],'goods_receipt',$receiptId,(string)$receipt['number'],null,(int)$receipt['warehouse_id'],-(float)$line['base_inventory_value']);if(trim((string)$line['lot_number'])!=='')warehouse_apply_lot_delta($pdo,(int)$line['product_id'],(int)$receipt['warehouse_id'],(string)$line['lot_number'],$line['expiration_date']?:null,-$qty);}
        $pdo->prepare("UPDATE tbl_goods_receipt SET status='Anulada' WHERE id=?")->execute([$receiptId]);app_reverse_document_lines($pdo,'goods_receipt',$receiptId,$userId);app_record_document_status($pdo,'goods_receipt',$receiptId,(string)$receipt['number'],'Registrado','Anulada','Anulación',$userId,$reason);goods_receipt_recalculate_base($pdo,(string)$receipt['base_document_type'],(int)$receipt['base_document_id'],$userId,'Estado recalculado por anulación '.$receipt['number'].'.');$pdo->commit();$reply(200,['message'=>'Recepción anulada y existencias revertidas correctamente.']);
    }
    $type=$validType($_POST['base_type']??'');$baseId=(int)($_POST['base_id']??0);$warehouseId=(int)($_POST['warehouse_id']??0);$date=trim((string)($_POST['receipt_date']??''));$reference=trim((string)($_POST['supplier_delivery_reference']??''));$notes=trim((string)($_POST['notes']??''));$items=json_decode((string)($_POST['items']??''),true);
    if($baseId<1||$warehouseId<1||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!is_array($items)||!$items)throw new DomainException('Completa el documento base, almacén, fecha y cantidades a recibir.');
    $base=goods_receipt_base($pdo,$type,$baseId,true);$allLines=goods_receipt_lines($pdo,$type,$baseId,true);$byId=[];foreach($allLines as $line)$byId[(int)$line['id']]=$line;
    $valuation=app_inventory_valuation_context($pdo,(string)$base['currency'],$_POST['valuation_exchange_rate']??null,$base);
    if($type==='ORDER'){$linked=$pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id=? AND status NOT IN ('Anulado','Anulada','Cancelada')");$linked->execute([$baseId]);if((int)$linked->fetchColumn()>0)throw new DomainException('La orden ya tiene factura de proveedor; recibe las cantidades pendientes desde esa factura.');}
    $warehouse=$pdo->prepare('SELECT id FROM tbl_warehouse WHERE id=? AND active_status=1');$warehouse->execute([$warehouseId]);if(!$warehouse->fetch())throw new DomainException('El almacén no existe o está inactivo.');
    $dateObject=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dateObject||$dateObject->format('Y-m-d')!==$date)throw new DomainException('La fecha de contabilización no es válida.');
    $requested=[];foreach($items as $item){$lineId=filter_var($item['base_line_id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);if($lineId===false||$lineId<1||$qty===false||$qty<1)throw new DomainException('Las cantidades a recibir deben ser enteros positivos y pertenecer a una línea base.');if(isset($requested[$lineId]))throw new DomainException('No repitas una línea del documento base.');$requested[$lineId]=['quantity'=>$qty,'lot_number'=>trim((string)($item['lot_number']??'')),'expiration_date'=>trim((string)($item['expiration_date']??''))];}if(!$requested)throw new DomainException('Indica al menos una cantidad recibida.');
    $number=app_next_document_number($pdo,'GOODS_RECEIPT','EM');$insert=$pdo->prepare("INSERT INTO tbl_goods_receipt(number,purchase_id,purchase_order_id,base_document_type,base_document_id,provider_id,provider_code,provider_name,provider_address,currency,valuation_currency,valuation_exchange_rate,supplier_delivery_reference,warehouse_id,status,receipt_date,notes,received_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Registrado',?,?,?)");$insert->execute([$number,$type==='PURCHASE'?$baseId:null,$type==='ORDER'?$baseId:null,$type,$baseId,(int)$base['provider_id'],(string)$base['provider_code'],(string)$base['provider_name'],(string)($base['provider_address']?:$base['master_address']),$valuation['document_currency'],$valuation['valuation_currency'],$valuation['valuation_exchange_rate'],substr($reference,0,100),$warehouseId,$date,substr($notes,0,500),$userId]);$receiptId=(int)$pdo->lastInsertId();
    $insertLine=$pdo->prepare('INSERT INTO tbl_goods_receipt_detail(goods_receipt_id,purchase_detail_id,order_detail_id,base_document_type,base_line_id,product_id,item_code,item_description,unit_of_measure,quantity,document_unit_cost,document_inventory_value,base_unit_cost,base_inventory_value,lot_number,expiration_date) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach($requested as $lineId=>$data){
        if(!isset($byId[$lineId]))throw new DomainException('Una línea no pertenece al documento base.');$line=$byId[$lineId];$qty=(int)$data['quantity'];
        if(empty($line['product_id']))throw new DomainException('El artículo '.$line['item_code'].' no está vinculado al maestro de inventario.');
        if($qty>(int)$line['pending_quantity'])throw new DomainException('La cantidad supera el saldo pendiente de '.$line['item_code'].'.');
        $expiry=$data['expiration_date'];if($expiry!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry))throw new DomainException('Fecha de vencimiento inválida para '.$line['item_code'].'.');if($expiry!==''&&$expiry<date('Y-m-d'))throw new DomainException('No se puede recibir un lote vencido.');
        $documentUnitCost=(float)$line['item_unit_price'];$documentValue=round($qty*$documentUnitCost,4);$unitCost=round($documentUnitCost*$valuation['valuation_exchange_rate'],6);$value=round($documentValue*$valuation['valuation_exchange_rate'],4);
        $insertLine->execute([$receiptId,$type==='PURCHASE'?$lineId:null,$type==='ORDER'?$lineId:null,$type,$lineId,(int)$line['product_id'],(string)$line['item_code'],(string)$line['item_description'],(string)$line['unit_of_measure'],$qty,$documentUnitCost,$documentValue,$unitCost,$value,(string)$data['lot_number'],$expiry?:null]);$targetLineId=(int)$pdo->lastInsertId();app_apply_document_line($pdo,strtolower($type),$baseId,$lineId,'goods_receipt',$receiptId,$targetLineId,'RECEPTION',$qty,$userId);
        warehouse_apply_stock_delta($pdo,(int)$line['product_id'],$qty,STOCK_MOVEMENT_ENTRY,$userId,'Entrada de mercancías basada en '.$base['number'],'goods_receipt',$receiptId,$number,(int)$base['provider_id'],$warehouseId,$value);warehouse_apply_lot_delta($pdo,(int)$line['product_id'],$warehouseId,(string)$data['lot_number'],$expiry?:null,$qty);
    }
    app_copy_custom_fields($pdo,strtolower($type),$baseId,'goods_receipt',$receiptId);app_save_custom_fields($pdo,'goods_receipt',$receiptId,is_array($_POST['custom_field']??null)?$_POST['custom_field']:[]);app_record_document_status($pdo,'goods_receipt',$receiptId,$number,'','Registrado','Recepción',$userId,'Creada desde '.$base['base_label'].' '.$base['number'].'.');app_record_document_flow($pdo,strtolower($type),$baseId,'goods_receipt',$receiptId,$userId,'RECEPTION');goods_receipt_recalculate_base($pdo,$type,$baseId,$userId,'Estado actualizado por recepción '.$number.'.');$pdo->commit();$reply(201,['message'=>'Entrada de mercancías '.$number.' registrada correctamente.','receipt_id'=>$receiptId,'receipt_number'=>$number]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('APPU canonical goods receipt: '.$e->getMessage());$reply(422,['message'=>$e instanceof DomainException?$e->getMessage():'No se pudo procesar la entrada de mercancías.']);}
