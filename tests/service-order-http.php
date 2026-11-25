<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';require_once __DIR__.'/qa-http-client.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base QA.');
$http=new AppQaHttpClient(getenv('APPU_QA_URL')?:'http://127.0.0.1:8767',DB);$key='QA-SERVICE-'.bin2hex(random_bytes(5));$order=$customField=null;$failed=false;
$createKey=bin2hex(random_bytes(16));$editKey=bin2hex(random_bytes(16));
function serviceAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 $provider=(int)$pdo->query('SELECT id FROM tbl_provider WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();serviceAssert($provider>0,'Falta proveedor QA.');$today=date('Y-m-d');
 $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,required_status) VALUES('order',?,'Canal servicio QA','text',0)")->execute([strtolower(str_replace('-','_',$key))]);$customField=(int)$pdo->lastInsertId();
 $data=['orden_tipo'=>'SERVICIO','orden_id'=>'','orden_tccompra'=>0,'orden_tcventa'=>0,'orden_nrocuenta'=>'','orden_autorizador'=>'','orden_tipomoneda'=>'PEN','orden_fecemision'=>$today,'orden_fecentrega'=>$today,'orden_observ'=>$key,'orden_tipopago'=>15,'orden_proveedor'=>$provider,'orden_cotizacion'=>$key,'orden_solicitante'=>'Solicitante QA','orden_totcompra'=>100,'orden_igv'=>18,'orden_totneto'=>118,'orden_lstservicios'=>json_encode([[1,'Consultoría QA','Servicio profesional',50,2,0,100]])];
 $data['custom_field']=[$customField=>'Proyectos'];
 unset($data['orden_solicitante']);
 $data+=['response_format'=>'json','request_key'=>$createKey];
 [$status,,$html]=$http->request('/views/ordenes/orden-de-servicio');serviceAssert($status===200&&str_contains($html,'value="PEN"')&&str_contains($html,'orden-de-servicio.js'),'La vista no carga moneda canónica y controlador.');
 [$status,$created]=$http->request('/modules/ordenes/insert-update-orden.php',$data);
 $order=(int)($created['document_id']??0);
 serviceAssert($status===200&&$order>0&&$created['result']==='OK_INSERT'&&str_starts_with($created['number'],'OS-'),'No confirmó el alta de servicio.');
 $creation=$data;
 [$status,$listing]=$http->request('/modules/ordenes/listar-ordenes.php',['orden_tipo'=>'SERVICIO']);serviceAssert($status===200&&count(array_filter($listing,fn($row)=>(int)$row['id']===$order))===1,'La OS no aparece para búsqueda y navegación.');
 [$status,$header]=$http->request('/modules/ordenes/consultar-orden.php',['TIPO_ORDEN'=>'SERVICIO','FILTER'=>$order,'ESTADO'=>'ALL']);serviceAssert($status===200&&$header[0]['TIPOMON']==='PEN'&&$header[0]['ORDESTADO']==='Pendiente'&&$header[0]['REQUES']==='','No recuperó cabecera de OS con solicitante opcional.');
 serviceAssert(($header[0]['CUSTOM_FIELDS'][$customField]??'')==='Proyectos','La consulta no recupera los campos de usuario de la OS.');
 [$status,$flow]=$http->request('/modules/trazabilidad/flujo.php?type=order&id='.$order);
 serviceAssert($status===200&&$flow['nodes'][0]['label']==='Orden de servicio'&&$flow['nodes'][0]['url']==='../ordenes/orden-de-servicio?record_id='.$order,'La trazabilidad no abre la ficha de servicio.');
 [$status,$lines]=$http->request('/modules/ordenes/consultar-detalle-orden.php',['IDORDEN'=>$order]);serviceAssert($status===200&&count($lines)===1&&(int)$lines[0]['QUANTITY']===2&&$lines[0]['DESCRIPTION']==='Consultoría QA','No recuperó líneas de OS.');serviceAssert(empty($lines[0]['ITEM_ID']),'La OS vinculó un movimiento de artículo indebidamente.');
 if(getenv('APPU_SERVICE_BROWSER_QA')==='1'){
  putenv('APPU_QA_SERVICE_ORDER_ID='.$order);
  passthru('node '.escapeshellarg(__DIR__.'/service-order.browser.cjs'),$browserExit);
  serviceAssert($browserExit===0,'Falló la prueba de navegador de orden de servicio.');
  $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_document_request WHERE operation='purchase_order_save' AND result_type='order' AND result_id=?");
  $q->execute([$order]);
  serviceAssert((int)$q->fetchColumn()===2,'El navegador registró más de una edición.');
 }
 $product=$pdo->query('SELECT id,code FROM tbl_product WHERE active_status=1 ORDER BY id LIMIT 1')->fetch();
 serviceAssert(is_array($product),'Falta artículo QA para probar el tipo de orden.');
 $wrongType=array_replace($data,['orden_id'=>$order,'orden_tipo'=>'COMPRA','request_key'=>'',
     'orden_lstprods'=>json_encode([[1,$product['code'],'Artículo QA','','UND',50,2,0,100,(int)$product['id']]])]);
 [$status,$wrongResult]=$http->request('/modules/ordenes/insert-update-orden.php',$wrongType);
 serviceAssert($status===422&&str_contains($wrongResult['message']??'','formulario de compra o servicio'),'El servidor permitió cambiar el tipo de la orden.');
 [$status,$rejected]=$http->request('/modules/aprobaciones/procesar.php',['document_type'=>'order','document_id'=>$order,'approval_action'=>'reject','comment'=>'Corregir precio en QA']);
 serviceAssert($status===200&&empty($rejected['error']),'No se pudo rechazar la OS.');
 $data['orden_id']=$order;$data['orden_totcompra']=120;$data['orden_igv']=21.6;$data['orden_totneto']=141.6;
 $data['orden_lstservicios']=json_encode([[1,'Consultoría editada','Servicio profesional',60,2,0,120]]);
 $data['request_key']=$editKey;
 [$status,$updated]=$http->request('/modules/ordenes/insert-update-orden.php',$data);
 serviceAssert($status===200&&(int)$updated['document_id']===$order&&$updated['result']==='OK_UPDATE','No editó y reenvió la orden rechazada.');
 $invalid=$data;$invalid['request_key']='';$invalid['orden_lstservicios']=json_encode([[1,'Consultoría fracción','Servicio profesional',60,1.5,0,90]]);$invalid['orden_totcompra']=90;$invalid['orden_igv']=16.2;$invalid['orden_totneto']=106.2;[$status]=$http->request('/modules/ordenes/insert-update-orden.php',$invalid);serviceAssert($status===422,'El servidor aceptó una cantidad fraccionaria.');serviceAssert((float)$pdo->query('SELECT total_purchase FROM tbl_order WHERE id='.$order)->fetchColumn()===120.0,'Un fallo alteró la OS guardada.');
 [$status,$approved]=$http->request('/modules/aprobaciones/procesar.php',['document_type'=>'order','document_id'=>$order,'approval_action'=>'approve','comment'=>'Aprobación QA']);serviceAssert($status===200&&empty($approved['error']),'No aprobó OS: '.json_encode($approved));
 [$status,$retryCreate]=$http->request('/modules/ordenes/insert-update-orden.php',$creation);
 serviceAssert($status===200&&$retryCreate===$created,'El reintento de alta no recuperó la confirmación.');
 [$status,$retryEdit]=$http->request('/modules/ordenes/insert-update-orden.php',$data);
 serviceAssert($status===200&&$retryEdit===$updated,'El reintento de edición no recuperó la confirmación.');
 [$status,$locked]=$http->request('/modules/ordenes/insert-update-orden.php',array_replace($data,['request_key'=>'']));
 serviceAssert($status===422&&str_contains($locked['message']??'','órdenes pendientes o rechazadas'),'La edición de una OS aprobada debe devolver el motivo.');
 [$status,,$result]=$http->request('/modules/ordenes/eliminar-orden.php',['ID_ORDEN'=>$order,'reason'=>'Anulación integral de OS QA']);serviceAssert($status===200&&trim($result)==='1','No anuló OS conservando auditoría: '.$result);serviceAssert($pdo->query('SELECT status FROM tbl_order WHERE id='.$order)->fetchColumn()==='Anulado','No persistió anulación de OS.');
 foreach(['INVOICE','RECEIPT','CREDIT_NOTE'] as $type){[$status,$documents]=$http->request('/modules/facturacion/filtrar-doc.php',['TIPO_DOC'=>$type,'defaultLoad'=>1]);serviceAssert($status===200&&is_array($documents),'El resumen no carga: '.$type);foreach($documents as $document)serviceAssert(isset($document['STATUS_ID']),'Falta estado numérico en '.$type);}
 $q=$pdo->prepare("SELECT action,COUNT(*) AS total FROM tbl_document_status_history WHERE document_type='order' AND document_id=? GROUP BY action");
 $q->execute([$order]);$history=$q->fetchAll(PDO::FETCH_KEY_PAIR);
 serviceAssert((int)($history['Creacion']??0)===1&&(int)($history['Reenvio']??0)===1&&(int)($history['Anulacion']??0)===1,'El historial debe conservar un único evento por acción.');
 echo "PASS HTTP service order retry-safe create and edit, deep link, type and quantity guards, approval and cancellation\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL service order HTTP: '.$e->getMessage().PHP_EOL);$failed=true;}
finally{if($pdo->inTransaction())$pdo->rollBack();$pdo->beginTransaction();$pdo->prepare('DELETE FROM tbl_document_request WHERE request_key IN (?,?)')->execute([$createKey,$editKey]);if($order){$pdo->prepare("DELETE FROM tbl_document_request WHERE operation='purchase_order_save' AND result_type='order' AND result_id=?")->execute([$order]);$pdo->prepare("DELETE FROM tbl_document_status_history WHERE document_type='order' AND document_id=?")->execute([$order]);$pdo->prepare("DELETE FROM tbl_approval_history WHERE document_type='order' AND document_id=?")->execute([$order]);$pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='order' AND record_id=?")->execute([$order]);$pdo->prepare('DELETE FROM tbl_order_detail WHERE order_id=?')->execute([$order]);$pdo->prepare('DELETE FROM tbl_order WHERE id=?')->execute([$order]);}if($customField)$pdo->prepare('DELETE FROM tbl_custom_field_definition WHERE id=?')->execute([$customField]);$pdo->commit();}
exit($failed?1:0);
