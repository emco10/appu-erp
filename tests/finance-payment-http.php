<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/qa-http-client.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base _qa.');
$http=new AppQaHttpClient(getenv('APPU_QA_URL')?:'http://127.0.0.1:8765',DB);
$key='QA-FIN-'.bin2hex(random_bytes(4));$receivable=$payable=$incoming=$outgoing=null;$failed=false;
function financeHttpAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 $customer=(int)$pdo->query('SELECT client_id FROM tbl_customer ORDER BY client_id LIMIT 1')->fetchColumn();$provider=(int)$pdo->query('SELECT id FROM tbl_provider ORDER BY id LIMIT 1')->fetchColumn();
 financeHttpAssert($customer>0&&$provider>0,'Faltan socios de negocio base para probar Finanzas.');
 $today=date('Y-m-d');
 $pdo->prepare("INSERT INTO tbl_accounts_receivable(document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount)VALUES('qa_http',0,?,?,?,?, 'PEN',100,100)")->execute([$key.'-CXC',$customer,$today,$today]);$receivable=(int)$pdo->lastInsertId();
 $pdo->prepare("UPDATE tbl_accounts_receivable SET currency='MN' WHERE id=?")->execute([$receivable]);
 $pdo->prepare("INSERT INTO tbl_accounts_payable(document_type,document_id,document_number,provider_id,issue_date,due_date,currency,total_amount,balance_amount)VALUES('qa_http',0,?,?,?,?, 'PEN',150,150)")->execute([$key.'-CXP',$provider,$today,$today]);$payable=(int)$pdo->lastInsertId();
 [$status,$listing]=$http->request('/modules/finanzas/cuentas.php?type=receivable');financeHttpAssert($status===200&&$listing['type']==='receivable','No cargó Cuentas por cobrar.');financeHttpAssert(count(array_filter($listing['accounts'],fn($row)=>(int)$row['id']===$receivable))===1,'La CxC creada no aparece en el listado.');
 foreach([['amount'=>'0.001'],['amount'=>'1.001'],['payment_date'=>'2026-02-30'],['account_label'=>'Otro'],['payment_method'=>'Desconocido']] as $invalid){
  [$status]=$http->request('/modules/finanzas/cuentas.php',array_merge(['action'=>'payment','account_label'=>'Cobrar','account_id'=>$receivable,'amount'=>1,'payment_date'=>$today,'reference'=>$key],$invalid));financeHttpAssert($status===422,'Se aceptó una cabecera o importe financiero inválido: '.json_encode($invalid));
 }
 $financialAccount=(int)$pdo->query("SELECT id FROM tbl_financial_account WHERE active_status=1 AND currency='PEN' ORDER BY id LIMIT 1")->fetchColumn();
 [$status,$result]=$http->request('/modules/finanzas/cuentas.php',['action'=>'payment','account_label'=>'Cobrar','account_id'=>$receivable,'amount'=>40,'payment_date'=>$today,'payment_method'=>'Transferencia','financial_account_id'=>$financialAccount,'reference'=>$key]);financeHttpAssert($status===200,'No registró el cobro: '.json_encode($result));$incoming=(int)($result['payment']['id']??0);financeHttpAssert($incoming>0,'El endpoint no devolvió el cobro creado.');financeHttpAssert($pdo->query('SELECT currency FROM tbl_incoming_payment WHERE id='.$incoming)->fetchColumn()==='PEN','El cobro no normaliza MN a PEN conservando importes.');
 $row=$pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_receivable WHERE id={$receivable}")->fetch();financeHttpAssert((float)$row['paid_amount']===40.0&&(float)$row['balance_amount']===60.0&&$row['status']==='Parcial','El cobro parcial no actualizó la CxC.');
 [$status,$report]=$http->request('/modules/reportes/finanzas.php?'.http_build_query(['date_from'=>$today,'date_to'=>$today,'account_type'=>'receivable','party'=>$key.'-CXC']));financeHttpAssert($status===200&&count($report['accounts'])===1&&count($report['payments'])===1&&(float)$report['payments'][0]['amount']===40.0,'El reporte filtrado por documento no conserva la misma selección en cartera y cobros.');
 [$status,$report]=$http->request('/modules/reportes/finanzas.php?'.http_build_query(['date_from'=>$today,'date_to'=>$today,'account_type'=>'receivable','party'=>$key.'-INEXISTENTE']));financeHttpAssert($status===200&&count($report['accounts'])===0&&count($report['payments'])===0,'El filtro de socio/documento deja cobros ajenos en el reporte.');
 [$status,$error]=$http->request('/modules/finanzas/cuentas.php',['action'=>'payment','account_label'=>'Cobrar','account_id'=>$receivable,'amount'=>61,'payment_date'=>$today]);financeHttpAssert($status===422&&str_contains((string)($error['error']??''),'supera'),'Se permitió cobrar más que el saldo.');
 [$status,$result]=$http->request('/modules/finanzas/cuentas.php',['action'=>'reverse_payment','payment_type'=>'receivable','payment_id'=>$incoming,'reason'=>'Reversión integral HTTP QA']);financeHttpAssert($status===200,'No revirtió el cobro: '.json_encode($result));$row=$pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_receivable WHERE id={$receivable}")->fetch();financeHttpAssert((float)$row['paid_amount']===0.0&&(float)$row['balance_amount']===100.0&&$row['status']==='Pendiente','La reversión no restauró la CxC.');
 [$status,$result]=$http->request('/modules/finanzas/cuentas.php',['action'=>'payment','account_label'=>'Pagar','account_id'=>$payable,'amount'=>150,'payment_date'=>$today,'payment_method'=>'Transferencia','reference'=>$key]);financeHttpAssert($status===200,'No registró el pago efectuado: '.json_encode($result));$outgoing=(int)($result['payment']['id']??0);$row=$pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_payable WHERE id={$payable}")->fetch();financeHttpAssert((float)$row['paid_amount']===150.0&&(float)$row['balance_amount']===0.0&&$row['status']==='Pagada','El pago total no cerró la CxP.');
 [$status,$listing]=$http->request('/modules/finanzas/cuentas.php?type=payable');financeHttpAssert($status===200&&count(array_filter($listing['payments'],fn($row)=>(int)$row['payment_id']===$outgoing&&$row['account_type']==='payable'))===1,'El historial no muestra el pago efectuado.');
 [$status]=$http->request('/modules/finanzas/cuentas.php',['action'=>'reverse_payment','payment_type'=>'payable','payment_id'=>$outgoing,'reason'=>'Reversión integral HTTP QA']);financeHttpAssert($status===200,'No revirtió el pago efectuado.');
 echo "PASS HTTP receivable/payable listing partial full overpayment rejection and reversal\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL finance payment HTTP: '.$e->getMessage().PHP_EOL);$failed=true;}
finally{
 if($pdo->inTransaction())$pdo->rollBack();$pdo->beginTransaction();
 foreach([['incoming_payment',$incoming,'tbl_incoming_payment_detail','incoming_payment_id','tbl_incoming_payment'],['outgoing_payment',$outgoing,'tbl_outgoing_payment_detail','outgoing_payment_id','tbl_outgoing_payment']] as [$type,$id,$detail,$keyColumn,$header])if($id){$pdo->prepare("DELETE FROM tbl_document_status_history WHERE document_type=? AND document_id=?")->execute([$type,$id]);$pdo->prepare("DELETE FROM tbl_document_flow WHERE target_type=? AND target_id=?")->execute([$type,$id]);$pdo->prepare("DELETE FROM {$detail} WHERE {$keyColumn}=?")->execute([$id]);$pdo->prepare("DELETE FROM {$header} WHERE id=?")->execute([$id]);}
 if($receivable)$pdo->prepare('DELETE FROM tbl_accounts_receivable WHERE id=?')->execute([$receivable]);if($payable)$pdo->prepare('DELETE FROM tbl_accounts_payable WHERE id=?')->execute([$payable]);$pdo->commit();
}
exit($failed?1:0);
