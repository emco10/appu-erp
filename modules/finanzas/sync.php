<?php

function finance_sync_receivable(PDO $pdo, int $invoiceId): void
{
    $statement = $pdo->prepare("SELECT invoice.series,invoice.number,invoice.customer_id,invoice.date,invoice.payment_days,invoice.currency,invoice.total_net,invoice.status,COALESCE((SELECT SUM(applied_amount) FROM tbl_sales_advance_application WHERE final_invoice_id=invoice.id AND status='ACTIVE'),0) AS advance_applied FROM tbl_invoice invoice WHERE invoice.id=?");
    $statement->execute([$invoiceId]);
    $invoice = $statement->fetch();
    if (!$invoice || (int) $invoice['customer_id'] <= 0) {
        return;
    }
    $number = $invoice['series'] . '-' . $invoice['number'];
    $receivableTotal = max(0, (float)$invoice['total_net'] - (float)$invoice['advance_applied']);
    $pdo->prepare("INSERT INTO tbl_accounts_receivable (document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount,status) VALUES ('invoice',?,?,?,?,DATE_ADD(?, INTERVAL ? DAY),?,?,?,?) ON DUPLICATE KEY UPDATE document_number=VALUES(document_number),customer_id=VALUES(customer_id),issue_date=VALUES(issue_date),due_date=VALUES(due_date),currency=VALUES(currency),total_amount=VALUES(total_amount),balance_amount=IF(VALUES(status)='Anulada',0,GREATEST(0,VALUES(total_amount)-paid_amount)),status=IF(VALUES(status)='Anulada','Anulada',IF(paid_amount>=VALUES(total_amount),'Pagada',IF(paid_amount>0,'Parcial','Pendiente')))" )
        ->execute([$invoiceId, $number, (int) $invoice['customer_id'], $invoice['date'], $invoice['date'], (int) $invoice['payment_days'], $invoice['currency'], $receivableTotal, $receivableTotal, in_array((int) $invoice['status'], [2,4], true) ? 'Anulada' : 'Pendiente']);
}

function finance_sync_payable(PDO $pdo, int $purchaseId): void
{
    $statement = $pdo->prepare('SELECT number, supplier_reference, provider_id, issue_date, payment_days, currency, total_net, status FROM tbl_purchase WHERE id = ?');
    $statement->execute([$purchaseId]);
    $purchase = $statement->fetch();
    if (!$purchase || (int) $purchase['provider_id'] <= 0) {
        return;
    }
    $pdo->prepare("INSERT INTO tbl_accounts_payable (document_type,document_id,document_number,provider_id,issue_date,due_date,currency,total_amount,balance_amount,status) VALUES ('purchase',?,?,?,?,DATE_ADD(?, INTERVAL ? DAY),?,?,?,?) ON DUPLICATE KEY UPDATE document_number=VALUES(document_number),provider_id=VALUES(provider_id),issue_date=VALUES(issue_date),due_date=VALUES(due_date),currency=VALUES(currency),total_amount=VALUES(total_amount),balance_amount=IF(VALUES(status)='Anulada',0,GREATEST(0,VALUES(total_amount)-paid_amount)),status=IF(VALUES(status)='Anulada','Anulada',IF(paid_amount>=VALUES(total_amount),'Pagada',IF(paid_amount>0,'Parcial','Pendiente')))" )
        ->execute([$purchaseId, $purchase['number'], (int) $purchase['provider_id'], $purchase['issue_date'], $purchase['issue_date'], (int) $purchase['payment_days'], $purchase['currency'], (float) $purchase['total_net'], (float) $purchase['total_net'], in_array($purchase['status'], ['Anulado', 'Anulada', 'Cancelada'], true) ? 'Anulada' : 'Pendiente']);
    $pdo->prepare("UPDATE tbl_accounts_payable SET supplier_reference = ? WHERE document_type = 'purchase' AND document_id = ?")
        ->execute([$purchase['supplier_reference'], $purchaseId]);
}

function finance_sync_receipt(PDO $pdo, int $receiptId): void
{
    $statement = $pdo->prepare('SELECT series, number, customer_id, date, payment_days, currency, total_net, status FROM tbl_receipt WHERE id = ?');
    $statement->execute([$receiptId]);
    $receipt = $statement->fetch();
    if (!$receipt || (int) $receipt['customer_id'] <= 0) {
        return;
    }
    $number = $receipt['series'] . '-' . $receipt['number'];
    $pdo->prepare("INSERT INTO tbl_accounts_receivable (document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount,status) VALUES ('receipt',?,?,?,?,DATE_ADD(?, INTERVAL ? DAY),?,?,?,?) ON DUPLICATE KEY UPDATE document_number=VALUES(document_number),customer_id=VALUES(customer_id),issue_date=VALUES(issue_date),due_date=VALUES(due_date),currency=VALUES(currency),total_amount=VALUES(total_amount),balance_amount=IF(VALUES(status)='Anulada',0,GREATEST(0,VALUES(total_amount)-paid_amount)),status=IF(VALUES(status)='Anulada','Anulada',IF(paid_amount>=VALUES(total_amount),'Pagada',IF(paid_amount>0,'Parcial','Pendiente')))" )
        ->execute([$receiptId, $number, (int) $receipt['customer_id'], $receipt['date'], $receipt['date'], (int) $receipt['payment_days'], $receipt['currency'], (float) $receipt['total_net'], (float) $receipt['total_net'], in_array((int) $receipt['status'], [2,4], true) ? 'Anulada' : 'Pendiente']);
}

function finance_apply_credit_note(PDO $pdo, int $referenceId, int $referenceType, float $creditAmount): void
{
    if ($referenceId <= 0 || $creditAmount <= 0) { return; }
    $type = $referenceType === 2 ? 'receipt' : 'invoice';
    $statement = $pdo->prepare('SELECT id, total_amount, paid_amount, balance_amount FROM tbl_accounts_receivable WHERE document_type = ? AND document_id = ? FOR UPDATE');
    $statement->execute([$type, $referenceId]);
    $account = $statement->fetch();
    if (!$account) {
        if ($type === 'receipt') { finance_sync_receipt($pdo, $referenceId); } else { finance_sync_receivable($pdo, $referenceId); }
        $statement->execute([$type, $referenceId]);
        $account = $statement->fetch();
    }
    if (!$account) { return; }
    $total = max(0, (float) $account['total_amount'] - $creditAmount);
    $balance = max(0, min($total - (float) $account['paid_amount'], (float) $account['balance_amount'] - $creditAmount));
    $status = $balance <= 0 ? 'Pagada' : ((float) $account['paid_amount'] > 0 ? 'Parcial' : 'Pendiente');
    $pdo->prepare('UPDATE tbl_accounts_receivable SET total_amount=?, balance_amount=?, status=? WHERE id=?')->execute([$total, $balance, $status, $account['id']]);
}

function finance_recalculate_credit_notes(PDO $pdo, int $referenceId, int $referenceType): void
{
    if ($referenceId <= 0) return;
    $type=$referenceType===2?'receipt':'invoice'; $table=$type==='receipt'?'tbl_receipt':'tbl_invoice';
    $source=$pdo->prepare("SELECT total_net FROM {$table} WHERE id=?");$source->execute([$referenceId]);$original=$source->fetchColumn();if($original===false)return;
    $credits=$pdo->prepare('SELECT COALESCE(SUM(total_net),0) FROM tbl_credit_note WHERE referenced_doc_id=? AND referenced_doc_type_id=? AND status NOT IN (2,4)');$credits->execute([$referenceId,$referenceType]);
    $account=$pdo->prepare('SELECT id,paid_amount FROM tbl_accounts_receivable WHERE document_type=? AND document_id=? FOR UPDATE');$account->execute([$type,$referenceId]);$row=$account->fetch();if(!$row)return;
    $advanceApplied=0.0;if($type==='invoice'){$advances=$pdo->prepare("SELECT COALESCE(SUM(applied_amount),0) FROM tbl_sales_advance_application WHERE final_invoice_id=? AND status='ACTIVE'");$advances->execute([$referenceId]);$advanceApplied=(float)$advances->fetchColumn();}
    $net=max(0,(float)$original-(float)$credits->fetchColumn()-$advanceApplied);$balance=max(0,$net-(float)$row['paid_amount']);$status=$balance<=0?'Pagada':((float)$row['paid_amount']>0?'Parcial':'Pendiente');
    $pdo->prepare('UPDATE tbl_accounts_receivable SET total_amount=?,balance_amount=?,status=? WHERE id=?')->execute([$net,$balance,$status,$row['id']]);
}
