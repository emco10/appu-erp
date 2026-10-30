<?php
require '../../global/connection.php';
require_once '../../global/finance-payments.php';
header('Content-Type: application/json; charset=utf-8');
$json=static function(int $status,array $data):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;};

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
 app_require_permission('finance.view');
 $requestedType=strtolower(trim((string)($_GET['type']??'')));
 if(!in_array($requestedType,['','receivable','payable'],true))$json(422,['error'=>'Tipo de cuenta financiera inválido.']);
 try{
  $pdo->exec("INSERT IGNORE INTO tbl_accounts_receivable(document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount) SELECT 'invoice',id,CONCAT(series,'-',number),customer_id,date,DATE_ADD(date,INTERVAL payment_days DAY),currency,total_net,total_net FROM tbl_invoice WHERE customer_id>0 AND status NOT IN(2,4)");
  $pdo->exec("INSERT IGNORE INTO tbl_accounts_receivable(document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount) SELECT 'receipt',id,CONCAT(series,'-',number),customer_id,date,DATE_ADD(date,INTERVAL payment_days DAY),currency,total_net,total_net FROM tbl_receipt WHERE customer_id>0 AND status NOT IN(2,4)");
  $pdo->exec("INSERT IGNORE INTO tbl_accounts_payable(document_type,document_id,document_number,provider_id,issue_date,due_date,currency,total_amount,balance_amount) SELECT 'purchase',id,number,provider_id,issue_date,DATE_ADD(issue_date,INTERVAL payment_days DAY),currency,total_net,total_net FROM tbl_purchase WHERE provider_id>0 AND status NOT IN('Anulado','Anulada','Cancelada') AND(document_subtype='RESERVE' OR status='Atendido')");
  $pdo->exec("UPDATE tbl_accounts_receivable account JOIN tbl_invoice document ON account.document_type='invoice' AND account.document_id=document.id SET account.status='Anulada',account.balance_amount=0 WHERE document.status IN(2,4)");
  $pdo->exec("UPDATE tbl_accounts_receivable account JOIN tbl_receipt document ON account.document_type='receipt' AND account.document_id=document.id SET account.status='Anulada',account.balance_amount=0 WHERE document.status IN(2,4)");
  $pdo->exec("UPDATE tbl_accounts_payable account JOIN tbl_purchase document ON account.document_type='purchase' AND account.document_id=document.id SET account.status='Anulada',account.balance_amount=0 WHERE document.status IN('Anulado','Anulada','Cancelada')");
  $pdo->exec("UPDATE tbl_accounts_payable account JOIN tbl_purchase document ON account.document_type='purchase' AND account.document_id=document.id SET account.supplier_reference=document.supplier_reference");
 }catch(Throwable $ignored){}
 $aging="CASE WHEN account.balance_amount<=0 THEN 'Pagada' WHEN account.due_date>=CURDATE() THEN 'Vigente' WHEN DATEDIFF(CURDATE(),account.due_date)<=30 THEN '1-30 días' WHEN DATEDIFF(CURDATE(),account.due_date)<=60 THEN '31-60 días' WHEN DATEDIFF(CURDATE(),account.due_date)<=90 THEN '61-90 días' ELSE '+90 días' END";
 $receivables=$pdo->query("SELECT account.id,'Cobrar' account_label,account.document_type,account.document_number,customer.business_name party_name,account.issue_date,account.due_date,account.currency,account.total_amount,account.paid_amount,account.balance_amount,account.status,{$aging} aging_bucket,GREATEST(DATEDIFF(CURDATE(),account.due_date),0) days_overdue FROM tbl_accounts_receivable account LEFT JOIN tbl_customer customer ON customer.client_id=account.customer_id ORDER BY account.due_date,account.id DESC")->fetchAll();
 $payables=$pdo->query("SELECT account.id,'Pagar' account_label,account.document_type,account.document_number,provider.business_name party_name,account.issue_date,account.due_date,account.currency,account.total_amount,account.paid_amount,account.balance_amount,account.status,COALESCE(purchase.supplier_reference,'') supplier_reference,{$aging} aging_bucket,GREATEST(DATEDIFF(CURDATE(),account.due_date),0) days_overdue FROM tbl_accounts_payable account LEFT JOIN tbl_provider provider ON provider.id=account.provider_id LEFT JOIN tbl_purchase purchase ON account.document_type='purchase' AND purchase.id=account.document_id ORDER BY account.due_date,account.id DESC")->fetchAll();
 $payments=$pdo->query("SELECT detail.id,header.id payment_id,header.payment_number,header.status,'receivable' account_type,detail.account_receivable_id account_id,header.payment_date,detail.applied_amount amount,header.payment_method,header.reference,detail.notes,account.document_number,financial.name financial_account_name FROM tbl_incoming_payment_detail detail JOIN tbl_incoming_payment header ON header.id=detail.incoming_payment_id JOIN tbl_accounts_receivable account ON account.id=detail.account_receivable_id LEFT JOIN tbl_financial_account financial ON financial.id=header.financial_account_id UNION ALL SELECT detail.id,header.id,header.payment_number,header.status,'payable',detail.account_payable_id,header.payment_date,detail.applied_amount,header.payment_method,header.reference,detail.notes,account.document_number,financial.name FROM tbl_outgoing_payment_detail detail JOIN tbl_outgoing_payment header ON header.id=detail.outgoing_payment_id JOIN tbl_accounts_payable account ON account.id=detail.account_payable_id LEFT JOIN tbl_financial_account financial ON financial.id=header.financial_account_id ORDER BY payment_date DESC,id DESC")->fetchAll();
 $financialAccounts=$pdo->query('SELECT id,code,name,account_type,currency FROM tbl_financial_account WHERE active_status=1 ORDER BY account_type,name')->fetchAll();
 foreach($receivables as &$row)$row['currency']=app_currency_code((string)$row['currency']);unset($row);
 foreach($payables as &$row)$row['currency']=app_currency_code((string)$row['currency']);unset($row);
 foreach($financialAccounts as &$row)$row['currency']=app_currency_code((string)$row['currency']);unset($row);
 $accounts=$requestedType==='receivable'?$receivables:($requestedType==='payable'?$payables:array_merge($receivables,$payables));
 if($requestedType!=='')$payments=array_values(array_filter($payments,static fn(array $payment):bool=>$payment['account_type']===$requestedType));
 $json(200,['accounts'=>$accounts,'payments'=>$payments,'financial_accounts'=>$financialAccounts,'type'=>$requestedType]);
}

app_require_permission('finance.manage');
$action=strtolower(trim((string)($_POST['action']??'')));
try{
 if($action==='payment'){
  if(!in_array($_POST['account_label']??'',['Cobrar','Pagar'],true))throw new InvalidArgumentException('Indica si la operación es un cobro o un pago.');
  $type=($_POST['account_label']??'')==='Pagar'?'payable':'receivable';
  $allocations=json_decode((string)($_POST['allocations']??''),true);
  if(!is_array($allocations)||$allocations===[])$allocations=[['account_id'=>$_POST['account_id']??null,'amount'=>$_POST['amount']??null,'notes'=>$_POST['notes']??'']];
  $pdo->beginTransaction();
  $result=app_create_finance_payment($pdo,$type,$allocations,['payment_date'=>$_POST['payment_date']??'','payment_method'=>$_POST['payment_method']??'Efectivo','financial_account_id'=>$_POST['financial_account_id']??null,'reference'=>$_POST['reference']??'','notes'=>$_POST['notes']??'','custom_fields'=>is_array($_POST['custom_field']??null)?$_POST['custom_field']:[]],(int)$_SESSION['loggedInUser']['USERID']);
  $pdo->commit();$json(200,['message'=>$result['allocations']>1?'Pago aplicado a varios documentos correctamente.':'Pago registrado correctamente.','payment'=>$result]);
 }
 if($action==='reverse_payment'){
  $type=strtolower(trim((string)($_POST['payment_type']??'')));if(!in_array($type,['receivable','payable'],true))throw new InvalidArgumentException('Indica si el pago es recibido o efectuado.');
  $paymentId=filter_var($_POST['payment_id']??null,FILTER_VALIDATE_INT);if($paymentId===false||$paymentId<1)throw new InvalidArgumentException('Pago inválido.');
  $pdo->beginTransaction();$result=app_reverse_finance_payment($pdo,$type,(int)$paymentId,(string)($_POST['reason']??''),(int)$_SESSION['loggedInUser']['USERID']);$pdo->commit();
  $json(200,['message'=>$result['already_reversed']?'El pago ya estaba anulado.':'Pago anulado y saldos revertidos correctamente.']);
 }
 if($action==='create_receivable'||$action==='create_payable'){
  $payable=$action==='create_payable';$table=$payable?'tbl_accounts_payable':'tbl_accounts_receivable';$partyColumn=$payable?'provider_id':'customer_id';$partyId=filter_var($_POST['party_id']??null,FILTER_VALIDATE_INT);$total=filter_var($_POST['total_amount']??null,FILTER_VALIDATE_FLOAT);
  if($partyId===false||$partyId<1||$total===false||$total<=0)throw new InvalidArgumentException('Datos de cuenta inválidos.');
  $pdo->prepare("INSERT INTO {$table}(document_type,document_id,document_number,{$partyColumn},issue_date,due_date,currency,total_amount,balance_amount) VALUES(?,?,?,?,?,?,?,?,?)")->execute([trim((string)($_POST['document_type']??'manual')),(int)($_POST['document_id']??0),trim((string)($_POST['document_number']??'')),$partyId,$_POST['issue_date']??date('Y-m-d'),$_POST['due_date']??date('Y-m-d'),trim((string)($_POST['currency']??'PEN')),$total,$total]);
  $json(200,['message'=>'Cuenta registrada correctamente.']);
 }
 $json(422,['error'=>'Acción financiera inválida.']);
}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$json(422,['error'=>$exception->getMessage()]);}
