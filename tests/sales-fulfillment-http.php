<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/../modules/inventory/stock.php';
require_once __DIR__.'/qa-http-client.php';
if (!str_ends_with(DB,'_qa')) throw new RuntimeException('Use una base _qa.');
$http=new AppQaHttpClient(getenv('APPU_QA_URL')?:'http://127.0.0.1:8765',DB);
function flowAssert(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$key='QAFLOW-'.bin2hex(random_bytes(4));$order=$product=$field=null;$guides=[];$invoices=[];$receipts=[];$testFailed=false;
try {
    $user=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    [$status,$catalog]=$http->request('/modules/ventas/ordenes.php?catalog=1');$warehouse=(int)$catalog['warehouses'][0]['id'];$customer=(int)$catalog['customers'][0]['id'];
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES(?,'QA','Articulo flujo HTTP','Temporal',0,10,'UND',1)")->execute([$key]);$product=(int)$pdo->lastInsertId();
    warehouse_apply_stock_delta($pdo,$product,10,STOCK_MOVEMENT_ENTRY,$user,'Inicial HTTP QA','goods_receipt',999005,$key,null,$warehouse,50);
    warehouse_apply_lot_delta($pdo,$product,$warehouse,$key.'-LOT','2028-01-01',10);$pdo->commit();
    $stock=function()use($pdo,$warehouse,$product):array {$q=$pdo->prepare('SELECT quantity,reserved_quantity,inventory_value FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?');$q->execute([$warehouse,$product]);return array_map('floatval',$q->fetch());};
    $input=['action'=>'create_direct','customer_id'=>$customer,'warehouse_id'=>$warehouse,'delivery_date'=>date('Y-m-d'),'currency'=>'PEN','reference'=>$key,'details'=>json_encode([['product_id'=>$product,'quantity'=>10,'unit_price'=>10]])];
    [$status,$created]=$http->request('/modules/ventas/ordenes.php',$input);flowAssert($status===200,'No se creó la orden: '.json_encode($created));$order=(int)$created['order_id'];
    [$status,$confirmed]=$http->request('/modules/ventas/ordenes.php',['action'=>'confirm','order_id'=>$order]);flowAssert($status===200,'No se confirmó la orden: '.json_encode($confirmed));
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0,'inventory_value'=>50.0],'La orden no comprometió stock.');
    [$status,$source]=$http->request('/modules/logistica/guias.php?action=source&source_type=sales_order&source_id='.$order);flowAssert($status===200,'No se consultó el origen.');$sourceLine=(int)$source['details'][0]['source_detail_id'];
    [$status,$error]=$http->request('/modules/logistica/guias.php',['action'=>'create']);flowAssert($status===422 && count($error['errors']??[])>=8,'La entrega vacía no devuelve todos los errores.');
    $delivery=['action'=>'create','source_type'=>'sales_order','source_id'=>$order,'warehouse_id'=>$warehouse,'transfer_date'=>date('Y-m-d'),'sender_name'=>'Empresa QA','sender_address'=>'Origen QA','recipient_name'=>'Cliente QA','recipient_address'=>'Destino QA','origin_address'=>'Origen QA','destination_address'=>'Destino QA'];
    $deliver=function(int $quantity)use($http,$delivery,$sourceLine):array {return $http->request('/modules/logistica/guias.php',$delivery+['items'=>json_encode([['source_detail_id'=>$sourceLine,'quantity'=>$quantity]])]);};
    $retryDelivery=$delivery+['request_key'=>$key.'-delivery','items'=>json_encode([['source_detail_id'=>$sourceLine,'quantity'=>6]])];
    [$status,$first]=$http->request('/modules/logistica/guias.php',$retryDelivery);flowAssert($status===201,'No se entregaron 6: '.json_encode($first));$guides[]=$first['guide_id'];
    [$status,$replayed]=$http->request('/modules/logistica/guias.php',$retryDelivery);flowAssert($status===201 && $replayed['guide_id']===$first['guide_id'],'El reintento duplicó la entrega.');
    $changed=$retryDelivery;$changed['items']=json_encode([['source_detail_id'=>$sourceLine,'quantity'=>2]]);[$status]=$http->request('/modules/logistica/guias.php',$changed);flowAssert($status===409,'Se reutilizó una operación con datos diferentes.');
    flowAssert($stock()===['quantity'=>4.0,'reserved_quantity'=>4.0,'inventory_value'=>20.0],'La primera entrega no descontó físico/liberó reserva.');
    $legacy=['facturacion_series'=>'QAI1','facturacion_cliente'=>$customer,'facturacion_cliruc'=>'20999999980','facturacion_valcliente'=>'QA','facturacion_clidirecc'=>'QA','facturacion_clirefer'=>$key,'facturacion_tipmon'=>'PEN','facturacion_fecentrega'=>date('Y-m-d'),'facturacion_porcdesc'=>0,'facturacion_cantdesc'=>0,'facturacion_formpago'=>0,'facturacion_fecha'=>date('Y-m-d'),'facturacion_opergrab'=>100,'facturacion_total'=>118,'facturacion_igv'=>18,'facturacion_prods'=>json_encode([[$product,$key,'QA','Temporal',10,10,100]]),'facturacion_estado'=>1,'facturacion_listadocotiz'=>'','facturacion_orden_venta'=>$order,'facturacion_almacen_id'=>$warehouse,'facturacion_usuarioid'=>$user,'facturacion_nro'=>'NO-CREAR'];
    [$status,$guard]=$http->request('/modules/facturacion/insert-factura.php',$legacy);flowAssert($status===422 && str_contains($guard['message']??'','entregas físicas'),'La factura directa ignoró las entregas físicas.');
    [$status,$rejected]=$deliver(5);flowAssert($status===422 && str_contains($rejected['errors'][0]['message']??'','4 unidades pendientes'),'No se rechazó 5 con saldo 4.');
    $lastBilling=null;
    $bill=function(int $guide)use($http,$key,&$lastBilling):array {[$status,$preview]=$http->request('/modules/ventas/documentos.php?action=delivery_billing_preview&guide_ids='.$guide);flowAssert($status===200,'No se cargó la entrega para facturar.');$line=$preview['lines'][0];$lastBilling=['action'=>'invoice_from_deliveries','request_key'=>$key.'-bill-'.$guide,'document_date'=>date('Y-m-d'),'lines'=>json_encode([['guide_id'=>$guide,'guide_line_id'=>$line['guide_line_id'],'quantity'=>$line['open_quantity']]])];return $http->request('/modules/ventas/documentos.php',$lastBilling);};
    [$status,$invoiceOne]=$bill($first['guide_id']);flowAssert($status===201 && abs($invoiceOne['total']-70.8)<.001,'No se facturó la primera entrega: '.json_encode($invoiceOne));$invoices[]=$invoiceOne['invoice_id'];
    [$status,$replayed]=$http->request('/modules/ventas/documentos.php',$lastBilling);flowAssert($status===201 && $replayed['invoice_id']===$invoiceOne['invoice_id'],'El reintento duplicó la factura.');
    flowAssert($stock()===['quantity'=>4.0,'reserved_quantity'=>4.0,'inventory_value'=>20.0],'Facturar volvió a descontar stock.');
    [$status,$blocked]=$http->request('/modules/logistica/guias.php',['action'=>'cancel','guide_id'=>$first['guide_id'],'cancellation_reason'=>'Prueba de dependencia']);flowAssert($status===422 && str_contains($blocked['message']??'','factura activa'),'Se anuló una entrega facturada.');
    [$status,$second]=$deliver(4);flowAssert($status===201,'No se completó la segunda entrega: '.json_encode($second));$guides[]=$second['guide_id'];
    [$status,$invoiceTwo]=$bill($second['guide_id']);flowAssert($status===201 && abs($invoiceTwo['total']-47.2)<.001,'No se facturó la entrega final.');$invoices[]=$invoiceTwo['invoice_id'];
    flowAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0,'inventory_value'=>0.0],'No concilia el flujo completo.');
    [$status,$snapshot]=$http->request('/modules/ventas/ordenes.php?view_id='.$order);flowAssert($snapshot['order']['domain_status']==='CLOSED' && $snapshot['order']['status']==='Atendida','La orden no cerró cumplimiento/facturación.');
    [$status,$flow]=$http->request('/modules/ventas/documentos.php?action=flow&type=sales_order&id='.$order);flowAssert($status===200 && count($flow['nodes']??[])===5 && count($flow['line_applications']??[])===2,'El mapa no conecta orden, entregas y facturas.');
    [$status,$flow]=$http->request('/modules/ventas/documentos.php?action=flow&type=invoice&id='.$invoiceOne['invoice_id']);flowAssert($status===200 && (int)$flow['line_applications'][0]['source_line_number']===1 && (float)$flow['line_applications'][0]['quantity']===6.0,'La factura perdió su relación de línea con la entrega.');
    [$status,$detail]=$http->request('/modules/logistica/guias.php?action=detail&guide_id='.$first['guide_id']);flowAssert($status===200 && (int)$detail['guide']['inventory_posted']===1 && $detail['guide']['source_number']===$created['number'],'No se abre la entrega con su origen.');
    $duplicateRequest=$lastBilling;$duplicateRequest['request_key']=$key.'-duplicate';[$status,$duplicate]=$http->request('/modules/ventas/documentos.php',$duplicateRequest);flowAssert($status===422,'Se facturó dos veces el mismo saldo.');
    foreach(array_reverse($invoices) as $index=>$invoice) {
        [$status,,$raw]=$http->request('/modules/facturacion/cambiar-estado-doc.php',['TIPO_DOC'=>'INVOICE','ID_DOC'=>$invoice,'ESTADO_DOC'=>2,'COMENTARIO'=>'Reversión HTTP de prueba']);flowAssert($status===200 && trim($raw)==='1','No se anuló la factura: '.$raw);
        $expected=$index===0?0.0:4.0;flowAssert($stock()['quantity']===$expected && $stock()['reserved_quantity']===$expected,'Anular factura alteró inventario/compromiso.');
        $guide=$index===0?$second['guide_id']:$first['guide_id'];
        [$status,$cancelled]=$http->request('/modules/logistica/guias.php',['action'=>'cancel','guide_id'=>$guide,'cancellation_reason'=>'Reversión HTTP de entrega']);flowAssert($status===200,'No se anuló la entrega: '.json_encode($cancelled));
    }
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0,'inventory_value'=>50.0],'La reversión no restauró físico/reserva/valor.');
    $legacy['facturacion_nro']=$key.'-DIRECT';
    [$status,,$raw]=$http->request('/modules/facturacion/insert-factura.php',$legacy);flowAssert($status===200 && trim($raw)==='OK_INSERT','La factura directa no puede consumir su propio compromiso: '.$raw);
    $q=$pdo->prepare('SELECT id FROM tbl_invoice WHERE sales_order_id=? AND series=? ORDER BY id DESC LIMIT 1');$q->execute([$order,$legacy['facturacion_series']]);$direct=(int)$q->fetchColumn();$invoices[]=$direct;
    flowAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0,'inventory_value'=>0.0],'La factura directa no liberó solo su reserva antes de consumir stock.');
    [$status,$directOrder]=$http->request('/modules/ventas/ordenes.php?view_id='.$order);flowAssert($directOrder['order']['domain_status']==='CLOSED' && (float)$directOrder['details'][0]['open_quantity']===0.0,'La factura directa dejó cantidad pendiente de cumplir.');
    [$status,$directFlow]=$http->request('/modules/ventas/documentos.php?action=flow&type=invoice&id='.$direct);$directEdges=array_values(array_filter($directFlow['edges']??[],fn($edge)=>$edge['source']==='sales_order:'.$order && $edge['target']==='invoice:'.$direct));flowAssert($status===200 && (float)($directEdges[0]['quantity']??0)===10.0,'El mapa suma dos veces las etapas de una misma cantidad.');
    [$status,,$raw]=$http->request('/modules/facturacion/cambiar-estado-doc.php',['TIPO_DOC'=>'INVOICE','ID_DOC'=>$direct,'ESTADO_DOC'=>2,'COMENTARIO'=>'Reversión de factura directa QA']);flowAssert($status===200 && trim($raw)==='1','No se anuló la factura directa.');
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0,'inventory_value'=>50.0],'La factura directa no restauró existencias y compromiso.');
    $boleta=$legacy;$boleta['facturacion_series']='QAB1';$boleta['facturacion_nro']=$key.'-RECEIPT';
    [$status,,$raw]=$http->request('/modules/facturacion/insert-boleta.php',$boleta);flowAssert($status===200 && trim($raw)==='OK_INSERT','La boleta no puede consumir su propio compromiso: '.$raw);
    $q=$pdo->prepare('SELECT id FROM tbl_receipt WHERE sales_order_id=? AND series=? ORDER BY id DESC LIMIT 1');$q->execute([$order,$boleta['facturacion_series']]);$receipt=(int)$q->fetchColumn();$receipts[]=$receipt;
    flowAssert($stock()===['quantity'=>0.0,'reserved_quantity'=>0.0,'inventory_value'=>0.0],'La boleta no concilia la reserva consumida.');
    $q=$pdo->prepare("SELECT balance_amount FROM tbl_accounts_receivable WHERE document_type='receipt' AND document_id=?");$q->execute([$receipt]);flowAssert((float)$q->fetchColumn()===118.0,'La boleta no generó su obligación comercial.');
    [$status,,$raw]=$http->request('/modules/facturacion/cambiar-estado-doc.php',['TIPO_DOC'=>'RECEIPT','ID_DOC'=>$receipt,'ESTADO_DOC'=>2,'COMENTARIO'=>'Reversión de boleta QA']);flowAssert($status===200 && trim($raw)==='1','No se anuló la boleta.');
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0,'inventory_value'=>50.0],'La boleta no restauró existencias y compromiso.');
    // Failure after movement posting must roll back header, numbering, lots and balances.
    $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,required_status) VALUES('remission_guide',?,'Motivo obligatorio QA','text',1)")->execute([strtolower(str_replace('-','_',$key))]);$field=(int)$pdo->lastInsertId();
    $q=$pdo->query("SELECT next_number FROM tbl_document_series WHERE document_type='REMISSION_GUIDE' AND active_status=1 ORDER BY id LIMIT 1");$next=(int)$q->fetchColumn();
    [$status,$lateFailure]=$deliver(2);flowAssert($status===422 && isset($lateFailure['errors']),'La falta de UDF no falló después del posteo.');
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>10.0,'inventory_value'=>50.0],'El fallo dejó stock parcial.');
    $q=$pdo->prepare('SELECT SUM(quantity) FROM tbl_product_lot WHERE product_id=? AND warehouse_id=?');$q->execute([$product,$warehouse]);flowAssert((float)$q->fetchColumn()===10.0,'El fallo dejó lotes parcialmente consumidos.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_inventory_lot_application WHERE product_id=? AND status='ACTIVE'");$q->execute([$product]);flowAssert((int)$q->fetchColumn()===0,'El fallo dejó aplicaciones de lote activas.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_document_line_application WHERE source_type='sales_order' AND source_id=? AND status='ACTIVE'");$q->execute([$order]);flowAssert((int)$q->fetchColumn()===0,'El fallo dejó aplicaciones documentales activas.');
    $q=$pdo->query("SELECT next_number FROM tbl_document_series WHERE document_type='REMISSION_GUIDE' AND active_status=1 ORDER BY id LIMIT 1");flowAssert((int)$q->fetchColumn()===$next,'El fallo consumió numeración.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_remission_guide WHERE source_type='sales_order' AND source_id=?");$q->execute([$order]);flowAssert((int)$q->fetchColumn()===2,'El fallo dejó una cabecera huérfana.');
    $pdo->prepare('DELETE FROM tbl_custom_field_definition WHERE id=?')->execute([$field]);$field=null;
    [$status,$cancelled]=$http->request('/modules/ventas/ordenes.php',['action'=>'cancel','order_id'=>$order,'cancel_reason'=>'Fin del proceso QA']);flowAssert($status===200,'No se liberó la orden al finalizar.');
    flowAssert($stock()===['quantity'=>10.0,'reserved_quantity'=>0.0,'inventory_value'=>50.0],'Cancelar la orden no liberó el compromiso.');
    $q=$pdo->prepare('SELECT SUM(quantity) FROM tbl_warehouse_movement WHERE product_id=?');$q->execute([$product]);flowAssert((float)$q->fetchColumn()===10.0,'El ledger no concilia.');
    echo "PASS HTTP order reserve partial delivery open balance overdelivery rejection delivery billing stock independence cancellation and late-failure rollback\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL fulfillment HTTP: '.$error->getMessage().PHP_EOL);$testFailed=true;}
finally {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM tbl_document_request WHERE request_key LIKE ?')->execute([$key.'%']);
    foreach($invoices as $id){$pdo->prepare('DELETE FROM tbl_sales_advance_application WHERE final_invoice_id=?')->execute([$id]);$pdo->prepare("DELETE FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id=?")->execute([$id]);$pdo->prepare('DELETE FROM tbl_invoice_detail WHERE invoice_id=?')->execute([$id]);$pdo->prepare('DELETE FROM tbl_invoice WHERE id=?')->execute([$id]);}
    foreach($receipts as $id){$pdo->prepare("DELETE FROM tbl_accounts_receivable WHERE document_type='receipt' AND document_id=?")->execute([$id]);$pdo->prepare('DELETE FROM tbl_receipt_detail WHERE receipt_id=?')->execute([$id]);$pdo->prepare('DELETE FROM tbl_receipt WHERE id=?')->execute([$id]);}
    if($order){$q=$pdo->prepare("SELECT id FROM tbl_remission_guide WHERE source_type='sales_order' AND source_id=?");$q->execute([$order]);$guides=$q->fetchAll(PDO::FETCH_COLUMN);}
    foreach($guides as $id){$pdo->prepare('DELETE FROM tbl_remission_guide_detail WHERE remission_guide_id=?')->execute([$id]);$pdo->prepare('DELETE FROM tbl_remission_guide WHERE id=?')->execute([$id]);}
    foreach(['sales_order'=>$order,'remission_guide'=>null,'invoice'=>null,'receipt'=>null] as $type=>$id){$ids=$type==='sales_order'?($id?[$id]:[]):($type==='invoice'?$invoices:($type==='receipt'?$receipts:$guides));foreach($ids as $record){$pdo->prepare('DELETE FROM tbl_document_status_history WHERE document_type=? AND document_id=?')->execute([$type,$record]);$pdo->prepare('DELETE FROM tbl_document_flow WHERE (source_type=? AND source_id=?) OR (target_type=? AND target_id=?)')->execute([$type,$record,$type,$record]);$pdo->prepare('DELETE FROM tbl_document_line_application WHERE (source_type=? AND source_id=?) OR (target_type=? AND target_id=?)')->execute([$type,$record,$type,$record]);$pdo->prepare('DELETE FROM tbl_custom_field_value WHERE entity=? AND record_id=?')->execute([$type,$record]);}}
    if($order){$pdo->prepare('DELETE FROM tbl_sales_order_detail WHERE sales_order_id=?')->execute([$order]);$pdo->prepare('DELETE FROM tbl_sales_order WHERE id=?')->execute([$order]);}
    if($product){foreach(['tbl_inventory_lot_application','tbl_warehouse_movement','tbl_product_lot','tbl_warehouse_stock'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?')->execute([$product]);$pdo->prepare('DELETE FROM tbl_product WHERE id=?')->execute([$product]);}
    if($field)$pdo->prepare('DELETE FROM tbl_custom_field_definition WHERE id=?')->execute([$field]);
    $pdo->commit();
}
exit($testFailed?1:0);
