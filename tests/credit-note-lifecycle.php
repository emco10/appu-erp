<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); require $root.'/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0); app_start_session();
require $root.'/global/connection.php'; require_once $root.'/modules/finanzas/sync.php';
$pdo->beginTransaction();
try {
    $userId=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $warehouseId=(int)$pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    if(!$userId||!$warehouseId)throw new RuntimeException('Faltan datos base.');
    $ruc='20'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);
    $pdo->prepare('INSERT INTO tbl_customer(ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$ruc,'CLIENTE QA NC','QA','','','','Direccion QA','15','1501','150101','','','','',0]);$customerId=(int)$pdo->lastInsertId();
    $number='QA'.substr(uniqid(),-8);$today=date('Y-m-d');
    $pdo->prepare("INSERT INTO tbl_invoice(series,number,status,quotation_id,customer_id,warehouse_id,ruc,name,address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id) VALUES('FQA',?,1,0,?,?,?,?,?,'',0,?,?,'PEN',0,0,100,18,118,?,?)")->execute([$number,$customerId,$warehouseId,$ruc,'CLIENTE QA NC','Direccion QA',$today,$today,$userId,$userId]);$invoiceId=(int)$pdo->lastInsertId();
    finance_sync_receivable($pdo,$invoiceId);
    $insert=$pdo->prepare("INSERT INTO tbl_credit_note(series,number,status,referenced_doc_id,referenced_doc_type_id,customer_id,warehouse_id,ruc,name,address,reference,payment_days,date,delivery_date,currency,reason,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id) VALUES('NCQ',?,1,?,1,?,?,?,?,?,'',0,?,?,'PEN','ANULACION',0,0,?,?,?, ?,?)");
    $insert->execute(['1'.$number,$invoiceId,$customerId,$warehouseId,$ruc,'CLIENTE QA NC','Direccion QA',$today,$today,50,9,59,$userId,$userId]);$noteOne=(int)$pdo->lastInsertId();
    finance_recalculate_credit_notes($pdo,$invoiceId,1);
    $account=$pdo->query("SELECT total_amount,balance_amount FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id={$invoiceId}")->fetch();
    if(abs((float)$account['total_amount']-59)>0.01||abs((float)$account['balance_amount']-59)>0.01)throw new RuntimeException('La nota no redujo correctamente la cuenta por cobrar.');
    $insert->execute(['2'.$number,$invoiceId,$customerId,$warehouseId,$ruc,'CLIENTE QA NC','Direccion QA',$today,$today,25,4.5,29.5,$userId,$userId]);$noteTwo=(int)$pdo->lastInsertId();
    finance_recalculate_credit_notes($pdo,$invoiceId,1);
    $balance=(float)$pdo->query("SELECT balance_amount FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id={$invoiceId}")->fetchColumn();
    if(abs($balance-29.5)>0.01)throw new RuntimeException('Las notas parciales no se acumularon correctamente.');
    $pdo->prepare('UPDATE tbl_credit_note SET status=2 WHERE id=?')->execute([$noteTwo]);finance_recalculate_credit_notes($pdo,$invoiceId,1);
    $balance=(float)$pdo->query("SELECT balance_amount FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id={$invoiceId}")->fetchColumn();
    if(abs($balance-59)>0.01)throw new RuntimeException('La anulacion de la nota no restauro el saldo.');
    $pdo->rollBack(); echo "PASS credit note partial accumulation and cancellation lifecycle\n";
} catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL credit note lifecycle: '.$exception->getMessage().PHP_EOL);exit(1);}
