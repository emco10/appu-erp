<?php

function finance_apply_available_sales_advances(PDO $pdo, int $finalInvoiceId, int $userId): float
{
    $finalStatement = $pdo->prepare("SELECT id,sales_order_id,customer_id,currency,total_net,document_subtype,status FROM tbl_invoice WHERE id=? FOR UPDATE");
    $finalStatement->execute([$finalInvoiceId]);
    $final = $finalStatement->fetch();
    if (!$final || (int)$final['sales_order_id'] <= 0 || !in_array($final['document_subtype'], ['STANDARD','RESERVE'], true) || in_array((int)$final['status'], [2,4], true)) return 0.0;

    $accountStatement = $pdo->prepare("SELECT id,paid_amount FROM tbl_accounts_receivable WHERE document_type='invoice' AND document_id=? FOR UPDATE");
    $accountStatement->execute([$finalInvoiceId]);
    $account = $accountStatement->fetch();
    if (!$account) throw new RuntimeException('La factura final no tiene cuenta por cobrar.');

    $advanceStatement = $pdo->prepare(
        "SELECT advance.id,
                GREATEST(0, account.paid_amount - COALESCE(SUM(CASE WHEN application.status='ACTIVE' THEN application.applied_amount ELSE 0 END),0)) AS available_amount
         FROM tbl_invoice advance
         JOIN tbl_accounts_receivable account ON account.document_type='invoice' AND account.document_id=advance.id
         LEFT JOIN tbl_sales_advance_application application ON application.advance_invoice_id=advance.id
         WHERE advance.sales_order_id=? AND advance.customer_id=? AND advance.currency=?
           AND advance.document_subtype='ADVANCE' AND advance.status NOT IN (2,4)
         GROUP BY advance.id,account.paid_amount
         HAVING available_amount > 0.000001
         ORDER BY advance.date,advance.id FOR UPDATE"
    );
    $advanceStatement->execute([(int)$final['sales_order_id'],(int)$final['customer_id'],(string)$final['currency']]);
    $remaining = (float)$final['total_net'];
    $applied = 0.0;
    $insert = $pdo->prepare("INSERT INTO tbl_sales_advance_application(advance_invoice_id,final_invoice_id,applied_amount,created_by) VALUES(?,?,?,?)");
    foreach ($advanceStatement->fetchAll() as $advance) {
        $amount = min($remaining, (float)$advance['available_amount']);
        if ($amount <= 0.000001) break;
        $insert->execute([(int)$advance['id'],$finalInvoiceId,$amount,$userId]);
        app_record_document_flow($pdo,'invoice',(int)$advance['id'],'invoice',$finalInvoiceId,$userId,'ADVANCE_APPLICATION',$amount);
        $remaining -= $amount;
        $applied += $amount;
    }
    $netReceivable = max(0.0, (float)$final['total_net'] - $applied);
    $paid = (float)$account['paid_amount'];
    $balance = max(0.0, $netReceivable - $paid);
    $status = $balance <= 0.000001 ? 'Pagada' : ($paid > 0 ? 'Parcial' : 'Pendiente');
    $pdo->prepare('UPDATE tbl_accounts_receivable SET total_amount=?,balance_amount=?,status=? WHERE id=?')->execute([$netReceivable,$balance,$status,(int)$account['id']]);
    return $applied;
}

function finance_reverse_sales_advance_applications(PDO $pdo, int $finalInvoiceId): void
{
    $pdo->prepare("UPDATE tbl_sales_advance_application SET status='REVERSED',reversed_at=NOW() WHERE final_invoice_id=? AND status='ACTIVE'")->execute([$finalInvoiceId]);
}

function finance_assert_advance_can_close(PDO $pdo, int $advanceInvoiceId): void
{
    $statement = $pdo->prepare("SELECT COUNT(*) FROM tbl_sales_advance_application WHERE advance_invoice_id=? AND status='ACTIVE'");
    $statement->execute([$advanceInvoiceId]);
    if ((int)$statement->fetchColumn() > 0) throw new DomainException('El anticipo está aplicado a una factura final activa; anula primero la factura final.');
}
