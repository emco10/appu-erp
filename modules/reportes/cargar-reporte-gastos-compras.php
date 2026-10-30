<?php
require '../../global/connection.php';
require_once '../../global/reporting.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('reports.view');
$input=array_merge($_GET,$_POST);$year=filter_var($input['YEAR']??date('Y'),FILTER_VALIDATE_INT);
if($year===false||$year<1900||$year>2100)app_report_error('Selecciona un año válido.');
$from=$input['DATE_FROM']??sprintf('%04d-01-01',$year);$to=$input['DATE_TO']??sprintf('%04d-12-31',$year);
try{app_report_date_range($from,$to);}catch(InvalidArgumentException $e){app_report_error($e->getMessage());}
if((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>3660)app_report_error('El rango máximo permitido es de 10 años.');
$currency=app_currency_code((string)($input['currency']??''));$party=trim((string)($input['party']??''));
if($currency!==''&&!in_array($currency,['PEN','USD','EUR'],true))app_report_error('Selecciona una moneda válida.');
if(mb_strlen($party)>100)app_report_error('La búsqueda del proveedor es demasiado extensa.');
$where=["purchase.status IN ('Aprobado','Parcial','Atendido')",'purchase.issue_date BETWEEN ? AND ?'];$params=[$from,$to];$normalized=app_currency_sql('purchase.currency');
if($currency!==''){$where[]=$normalized.'=?';$params[]=$currency;}
if($party!==''){$where[]='(provider.business_name LIKE ? OR provider.code LIKE ? OR purchase.number LIKE ? OR purchase.supplier_reference LIKE ?)';$like='%'.$party.'%';array_push($params,$like,$like,$like,$like);}
$q=$pdo->prepare("SELECT purchase.id,purchase.number,purchase.supplier_reference,purchase.issue_date,purchase.status,purchase.document_subtype,{$normalized} currency,purchase.provider_id,provider.code provider_code,provider.business_name provider_name,purchase.total_purchase,purchase.total_tax,purchase.total_net FROM tbl_purchase purchase JOIN tbl_provider provider ON provider.id=purchase.provider_id WHERE ".implode(' AND ',$where).' ORDER BY purchase.issue_date,purchase.id');$q->execute($params);$documents=$q->fetchAll();
$summary=[];$monthly=[];$providers=[];
foreach($documents as &$document){
 foreach(['total_purchase','total_tax','total_net'] as $field)$document[$field]=(float)$document[$field];
 $code=$document['currency'];$summary[$code]??=['currency'=>$code,'document_count'=>0,'subtotal'=>0.0,'tax'=>0.0,'total'=>0.0];$summary[$code]['document_count']++;$summary[$code]['subtotal']+=$document['total_purchase'];$summary[$code]['tax']+=$document['total_tax'];$summary[$code]['total']+=$document['total_net'];
 $month=substr($document['issue_date'],0,7);$key=$month.'|'.$code;$monthly[$key]??=['month'=>$month,'currency'=>$code,'document_count'=>0,'total'=>0.0];$monthly[$key]['document_count']++;$monthly[$key]['total']+=$document['total_net'];
 $key=$document['provider_id'].'|'.$code;$providers[$key]??=['provider_id'=>(int)$document['provider_id'],'provider_code'=>$document['provider_code'],'provider_name'=>$document['provider_name'],'currency'=>$code,'document_count'=>0,'total'=>0.0];$providers[$key]['document_count']++;$providers[$key]['total']+=$document['total_net'];
}unset($document);ksort($summary);ksort($monthly);$providers=array_values($providers);usort($providers,static fn($a,$b)=>[$a['currency'],-$a['total']]<=>[$b['currency'],-$b['total']]);
echo json_encode(['date_from'=>$from,'date_to'=>$to,'filters'=>['currency'=>$currency,'party'=>$party],'summary'=>array_values($summary),'monthly'=>array_values($monthly),'providers'=>$providers,'documents'=>$documents],JSON_UNESCAPED_UNICODE);
