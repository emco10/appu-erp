<?php
require '../../global/connection.php';
require_once '../../global/reporting.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('reports.view');

$dateFrom=$_GET['date_from']??date('Y-m-d',strtotime('-29 days'));$dateTo=$_GET['date_to']??date('Y-m-d');
$accountType=strtolower(trim((string)($_GET['account_type']??'all')));$currency=app_currency_code((string)($_GET['currency']??''));$partySearch=trim((string)($_GET['party']??''));
$validDate=static function($date):bool{if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return false;[$year,$month,$day]=array_map('intval',explode('-',$date));return checkdate($month,$day,$year);};
$fail=static function(string $message):void{http_response_code(422);echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;};
if(!$validDate($dateFrom)||!$validDate($dateTo)||$dateFrom>$dateTo)$fail('Indica un rango de fechas válido.');
if(!in_array($accountType,['all','receivable','payable'],true))$fail('El tipo de cuenta no es válido.');
if($currency!==''&&!preg_match('/^[A-Z0-9]{3,10}$/',$currency))$fail('La moneda no es válida.');
if(mb_strlen($partySearch)>100)$fail('La búsqueda del socio es demasiado extensa.');

$parts=[];$params=[];
if($accountType!=='payable'){
 $where=["account.status <> 'Anulada'",'account.balance_amount > 0'];
 if($currency!==''){$where[]=app_currency_sql('account.currency').' = ?';$params[]=$currency;}
 if($partySearch!==''){$where[]='(customer.business_name LIKE ? OR customer.ruc LIKE ? OR account.document_number LIKE ?)';$like='%'.$partySearch.'%';array_push($params,$like,$like,$like);}
 $parts[]="SELECT account.id,'receivable' account_type,'Cobrar' account_label,account.document_type,account.document_id,account.document_number,customer.client_id party_id,customer.ruc party_tax_id,customer.business_name party_name,account.issue_date,account.due_date,account.currency,account.total_amount,account.paid_amount,account.balance_amount,account.status,'' supplier_reference,GREATEST(DATEDIFF(CURDATE(),account.due_date),0) days_overdue,CASE WHEN account.due_date>=CURDATE() THEN 'Corriente' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 1 AND 30 THEN '1-30 días' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 31 AND 60 THEN '31-60 días' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 61 AND 90 THEN '61-90 días' ELSE 'Más de 90 días' END aging_bucket FROM tbl_accounts_receivable account LEFT JOIN tbl_customer customer ON customer.client_id=account.customer_id WHERE ".implode(' AND ',$where);
}
if($accountType!=='receivable'){
 $where=["account.status <> 'Anulada'",'account.balance_amount > 0'];
 if($currency!==''){$where[]=app_currency_sql('account.currency').' = ?';$params[]=$currency;}
 if($partySearch!==''){$where[]='(provider.business_name LIKE ? OR provider.code LIKE ? OR account.document_number LIKE ? OR purchase.supplier_reference LIKE ?)';$like='%'.$partySearch.'%';array_push($params,$like,$like,$like,$like);}
 $parts[]="SELECT account.id,'payable' account_type,'Pagar' account_label,account.document_type,account.document_id,account.document_number,provider.id party_id,provider.code party_tax_id,provider.business_name party_name,account.issue_date,account.due_date,account.currency,account.total_amount,account.paid_amount,account.balance_amount,account.status,COALESCE(purchase.supplier_reference,'') supplier_reference,GREATEST(DATEDIFF(CURDATE(),account.due_date),0) days_overdue,CASE WHEN account.due_date>=CURDATE() THEN 'Corriente' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 1 AND 30 THEN '1-30 días' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 31 AND 60 THEN '31-60 días' WHEN DATEDIFF(CURDATE(),account.due_date) BETWEEN 61 AND 90 THEN '61-90 días' ELSE 'Más de 90 días' END aging_bucket FROM tbl_accounts_payable account LEFT JOIN tbl_provider provider ON provider.id=account.provider_id LEFT JOIN tbl_purchase purchase ON account.document_type='purchase' AND purchase.id=account.document_id WHERE ".implode(' AND ',$where);
}
$statement=$pdo->prepare(implode(' UNION ALL ',$parts).' ORDER BY due_date,account_label,document_number');$statement->execute($params);$accounts=$statement->fetchAll();
$summary=[];$aging=[];$partners=[];$overdue=[];
foreach($accounts as &$account){
 $account['currency']=app_currency_code((string)$account['currency']);
 foreach(['total_amount','paid_amount','balance_amount'] as $field)$account[$field]=(float)$account[$field];$account['days_overdue']=(int)$account['days_overdue'];
 $base=$account['account_type'].'|'.$account['currency'];
 if(!isset($summary[$base]))$summary[$base]=['account_type'=>$account['account_type'],'account_label'=>$account['account_label'],'currency'=>$account['currency'],'account_count'=>0,'total_amount'=>0.0,'paid_amount'=>0.0,'balance_amount'=>0.0,'overdue_amount'=>0.0];
 $summary[$base]['account_count']++;$summary[$base]['total_amount']+=$account['total_amount'];$summary[$base]['paid_amount']+=$account['paid_amount'];$summary[$base]['balance_amount']+=$account['balance_amount'];
 if($account['days_overdue']>0){$summary[$base]['overdue_amount']+=$account['balance_amount'];$overdue[]=$account;}
 $key=$base.'|'.$account['aging_bucket'];if(!isset($aging[$key]))$aging[$key]=['account_type'=>$account['account_type'],'account_label'=>$account['account_label'],'currency'=>$account['currency'],'aging_bucket'=>$account['aging_bucket'],'document_count'=>0,'balance_amount'=>0.0];$aging[$key]['document_count']++;$aging[$key]['balance_amount']+=$account['balance_amount'];
 $key=$base.'|'.($account['party_id']??0);if(!isset($partners[$key]))$partners[$key]=['account_type'=>$account['account_type'],'account_label'=>$account['account_label'],'party_id'=>(int)($account['party_id']??0),'party_tax_id'=>$account['party_tax_id']??'','party_name'=>$account['party_name']?:'Sin socio','currency'=>$account['currency'],'document_count'=>0,'balance_amount'=>0.0,'overdue_amount'=>0.0];$partners[$key]['document_count']++;$partners[$key]['balance_amount']+=$account['balance_amount'];if($account['days_overdue']>0)$partners[$key]['overdue_amount']+=$account['balance_amount'];
}unset($account);$partners=array_values($partners);usort($partners,static fn(array $a,array $b):int=>$b['balance_amount']<=>$a['balance_amount']);

$paymentParts=[];$paymentParams=[];
if($accountType!=='payable'){
 $where=["header.status <> 'Anulado'",'header.payment_date BETWEEN ? AND ?'];array_push($paymentParams,$dateFrom,$dateTo);
 if($currency!==''){$where[]=app_currency_sql('header.currency').'=?';$paymentParams[]=$currency;}
 if($partySearch!==''){$where[]='(customer.business_name LIKE ? OR customer.ruc LIKE ? OR account.document_number LIKE ?)';$like='%'.$partySearch.'%';array_push($paymentParams,$like,$like,$like);}
 $paymentParts[]="SELECT 'receivable' account_type,header.payment_method,header.currency,COUNT(DISTINCT header.id) payment_count,COALESCE(SUM(detail.applied_amount),0) amount FROM tbl_incoming_payment_detail detail JOIN tbl_incoming_payment header ON header.id=detail.incoming_payment_id JOIN tbl_accounts_receivable account ON account.id=detail.account_receivable_id LEFT JOIN tbl_customer customer ON customer.client_id=header.customer_id WHERE ".implode(' AND ',$where).' GROUP BY header.payment_method,header.currency';
}
if($accountType!=='receivable'){
 $where=["header.status <> 'Anulado'",'header.payment_date BETWEEN ? AND ?'];array_push($paymentParams,$dateFrom,$dateTo);
 if($currency!==''){$where[]=app_currency_sql('header.currency').'=?';$paymentParams[]=$currency;}
 if($partySearch!==''){$where[]='(provider.business_name LIKE ? OR provider.code LIKE ? OR account.document_number LIKE ? OR purchase.supplier_reference LIKE ?)';$like='%'.$partySearch.'%';array_push($paymentParams,$like,$like,$like,$like);}
 $paymentParts[]="SELECT 'payable' account_type,header.payment_method,header.currency,COUNT(DISTINCT header.id) payment_count,COALESCE(SUM(detail.applied_amount),0) amount FROM tbl_outgoing_payment_detail detail JOIN tbl_outgoing_payment header ON header.id=detail.outgoing_payment_id JOIN tbl_accounts_payable account ON account.id=detail.account_payable_id LEFT JOIN tbl_provider provider ON provider.id=header.provider_id LEFT JOIN tbl_purchase purchase ON account.document_type='purchase' AND purchase.id=account.document_id WHERE ".implode(' AND ',$where).' GROUP BY header.payment_method,header.currency';
}
$paymentStatement=$pdo->prepare(implode(' UNION ALL ',$paymentParts).' ORDER BY amount DESC');$paymentStatement->execute($paymentParams);
$payments=[];foreach($paymentStatement->fetchAll() as $row){$row['currency']=app_currency_code((string)$row['currency']);$key=$row['account_type'].'|'.$row['payment_method'].'|'.$row['currency'];if(!isset($payments[$key])){$row['amount']=(float)$row['amount'];$row['payment_count']=(int)$row['payment_count'];$payments[$key]=$row;}else{$payments[$key]['amount']+=(float)$row['amount'];$payments[$key]['payment_count']+=(int)$row['payment_count'];}}
$currencies=$pdo->query("SELECT currency FROM (SELECT currency FROM tbl_accounts_receivable UNION SELECT currency FROM tbl_accounts_payable) currencies WHERE currency<>'' ORDER BY currency")->fetchAll(PDO::FETCH_COLUMN);
$currencies=array_values(array_unique(array_map('app_currency_code',$currencies)));sort($currencies);
echo json_encode(['generated_at'=>date('c'),'date_from'=>$dateFrom,'date_to'=>$dateTo,'filters'=>['account_type'=>$accountType,'currency'=>$currency,'party'=>$partySearch],'currencies'=>$currencies,'summary'=>array_values($summary),'aging'=>array_values($aging),'partners'=>$partners,'accounts'=>$accounts,'overdue'=>array_slice($overdue,0,100),'payments'=>array_values($payments)],JSON_UNESCAPED_UNICODE);
