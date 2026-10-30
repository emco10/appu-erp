<?php

require_once __DIR__ . '/document-numbering.php';
require_once __DIR__ . '/document-history.php';
require_once __DIR__ . '/currency.php';

function app_finance_payment_config(string $type): array
{
    if ($type === 'receivable') {
        return [
            'account_table' => 'tbl_accounts_receivable',
            'party_column' => 'customer_id',
            'header_table' => 'tbl_incoming_payment',
            'detail_table' => 'tbl_incoming_payment_detail',
            'detail_header_column' => 'incoming_payment_id',
            'detail_account_column' => 'account_receivable_id',
            'document_type' => 'incoming_payment',
            'numbering_type' => 'INCOMING_PAYMENT',
            'numbering_prefix' => 'COB',
        ];
    }
    if ($type === 'payable') {
        return [
            'account_table' => 'tbl_accounts_payable',
            'party_column' => 'provider_id',
            'header_table' => 'tbl_outgoing_payment',
            'detail_table' => 'tbl_outgoing_payment_detail',
            'detail_header_column' => 'outgoing_payment_id',
            'detail_account_column' => 'account_payable_id',
            'document_type' => 'outgoing_payment',
            'numbering_type' => 'OUTGOING_PAYMENT',
            'numbering_prefix' => 'PAG',
        ];
    }
    throw new InvalidArgumentException('Tipo de pago financiero inválido.');
}

function app_create_finance_payment(PDO $pdo, string $type, array $allocations, array $data, int $userId): array
{
    $config = app_finance_payment_config($type);
    if ($userId < 1) throw new InvalidArgumentException('Usuario financiero inválido.');
    if ($allocations === []) throw new InvalidArgumentException('Selecciona al menos una cuenta para aplicar el pago.');
    $paymentDate = trim((string) ($data['payment_date'] ?? '')) ?: date('Y-m-d');
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $paymentDate) throw new InvalidArgumentException('La fecha del pago no es válida.');
    $paymentMethod = trim((string) ($data['payment_method'] ?? 'Efectivo')) ?: 'Efectivo';
    if (!in_array($paymentMethod, ['Efectivo', 'Transferencia', 'Tarjeta', 'Cheque'], true)) throw new InvalidArgumentException('Selecciona un medio de pago válido.');

    $locked = [];
    $seen = [];
    $partyId = null;
    $currency = null;
    $total = 0.0;
    foreach ($allocations as $allocation) {
        $accountId = filter_var($allocation['account_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($allocation['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($accountId === false || $accountId < 1 || $amount === false || !is_finite((float) $amount) || $amount < 0.01 || abs((float) $amount - round((float) $amount, 2)) > 0.0000001) {
            throw new InvalidArgumentException('Importe de pago inválido.');
        }
        if (isset($seen[$accountId])) throw new DomainException('No repitas la misma cuenta en una aplicación múltiple.');
        $seen[$accountId] = true;
        $statement = $pdo->prepare("SELECT document_type,document_id,total_amount,paid_amount,balance_amount,currency,status,{$config['party_column']} AS party_id FROM {$config['account_table']} WHERE id=? FOR UPDATE");
        $statement->execute([$accountId]);
        $account = $statement->fetch();
        if (!$account || in_array((string) $account['status'], ['Anulada', 'Cancelada', 'Pagada'], true)) {
            throw new DomainException('La cuenta no está disponible para recibir una aplicación.');
        }
        $account['currency']=app_currency_code((string)$account['currency']);
        $amount = round((float) $amount, 2);
        $balance = round((float) $account['balance_amount'], 2);
        if ($amount > $balance) throw new DomainException('El pago supera el saldo pendiente.');
        if ($partyId !== null && ((int) $account['party_id'] !== $partyId || (string) $account['currency'] !== $currency)) {
            throw new DomainException('Las cuentas deben pertenecer al mismo socio de negocio y moneda.');
        }
        $partyId = (int) $account['party_id'];
        $currency = (string) $account['currency'];
        $total = round($total + $amount, 2);
        $locked[] = [
            'id' => (int) $accountId,
            'amount' => $amount,
            'balance' => $balance,
            'notes' => trim((string) ($allocation['notes'] ?? '')),
            'document_type' => (string) $account['document_type'],
            'document_id' => (int) $account['document_id'],
        ];
    }

    $financialAccountId = filter_var($data['financial_account_id'] ?? null, FILTER_VALIDATE_INT);
    $financialAccountId = $financialAccountId === false || $financialAccountId < 1 ? null : $financialAccountId;
    if ($financialAccountId !== null) {
        $statement = $pdo->prepare('SELECT currency FROM tbl_financial_account WHERE id=? AND active_status=1');
        $statement->execute([$financialAccountId]);
        $financialAccount = $statement->fetch();
        if (!$financialAccount || app_currency_code((string) $financialAccount['currency']) !== $currency) {
            throw new DomainException('La cuenta financiera no coincide con la moneda del documento.');
        }
    }

    $paymentNumber = app_next_document_number($pdo, $config['numbering_type'], $config['numbering_prefix']);
    $reference = trim((string) ($data['reference'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $insert = $pdo->prepare("INSERT INTO {$config['header_table']} (payment_number,{$config['party_column']},payment_date,currency,total_amount,payment_method,financial_account_id,reference,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $insert->execute([$paymentNumber, $partyId, $paymentDate, $currency, $total, $paymentMethod, $financialAccountId, $reference, $notes, $userId]);
    $paymentId = (int) $pdo->lastInsertId();
    app_save_custom_fields($pdo, $config['document_type'], $paymentId, is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : []);

    $detailInsert = $pdo->prepare("INSERT INTO {$config['detail_table']} ({$config['detail_header_column']},{$config['detail_account_column']},applied_amount,notes) VALUES (?,?,?,?)");
    $accountUpdate = $pdo->prepare("UPDATE {$config['account_table']} SET paid_amount=paid_amount+?,balance_amount=?,status=? WHERE id=?");
    foreach ($locked as $item) {
        $detailInsert->execute([$paymentId, $item['id'], $item['amount'], $item['notes']]);
        $newBalance = round($item['balance'] - $item['amount'], 2);
        $accountUpdate->execute([$item['amount'], $newBalance, $newBalance <= 0 ? 'Pagada' : 'Parcial', $item['id']]);
        if ($item['document_id'] > 0) {
            app_record_document_flow($pdo, $item['document_type'], $item['document_id'], $config['document_type'], $paymentId, $userId, 'PAYMENT_APPLICATION', $item['amount']);
        }
    }
    app_record_document_status($pdo, $config['document_type'], $paymentId, $paymentNumber, '', 'Registrado', 'Creacion', $userId, count($locked) > 1 ? 'Aplicado a varios documentos.' : 'Pago registrado.');
    return ['id' => $paymentId, 'number' => $paymentNumber, 'total' => $total, 'allocations' => count($locked), 'type' => $type];
}

function app_reverse_finance_payment(PDO $pdo, string $type, int $paymentId, string $reason, int $userId): array
{
    $config = app_finance_payment_config($type);
    if ($paymentId < 1) throw new InvalidArgumentException('Pago inválido.');
    if ($userId < 1) throw new InvalidArgumentException('Usuario financiero inválido.');
    $reason = trim($reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) throw new InvalidArgumentException('Indica un motivo de anulación de al menos 5 caracteres.');

    $statement = $pdo->prepare("SELECT id,payment_number,status FROM {$config['header_table']} WHERE id=? FOR UPDATE");
    $statement->execute([$paymentId]);
    $header = $statement->fetch();
    if (!$header) throw new RuntimeException('Pago inexistente.');
    if ((string) $header['status'] === 'Anulado') return ['already_reversed' => true, 'type' => $type, 'id' => $paymentId];

    $details = $pdo->prepare("SELECT {$config['detail_account_column']} AS account_id,applied_amount FROM {$config['detail_table']} WHERE {$config['detail_header_column']}=? FOR UPDATE");
    $details->execute([$paymentId]);
    foreach ($details->fetchAll() as $detail) {
        if ($type === 'receivable') {
            $application = $pdo->prepare("SELECT COUNT(*) FROM tbl_accounts_receivable account JOIN tbl_invoice invoice ON account.document_type='invoice' AND account.document_id=invoice.id JOIN tbl_sales_advance_application advance_application ON advance_application.advance_invoice_id=invoice.id AND advance_application.status='ACTIVE' WHERE account.id=? AND invoice.document_subtype='ADVANCE'");
            $application->execute([(int)$detail['account_id']]);
            if ((int)$application->fetchColumn() > 0) throw new DomainException('El cobro pertenece a un anticipo aplicado; anula primero la factura final para liberar el anticipo.');
        }
        $accountStatement = $pdo->prepare("SELECT total_amount,paid_amount,status FROM {$config['account_table']} WHERE id=? FOR UPDATE");
        $accountStatement->execute([(int) $detail['account_id']]);
        $account = $accountStatement->fetch();
        if (!$account) throw new RuntimeException('La cuenta asociada al pago no existe.');
        $newPaid = max(0, round((float) $account['paid_amount'] - (float) $detail['applied_amount'], 2));
        $newBalance = (string) $account['status'] === 'Anulada' ? 0 : max(0, round((float) $account['total_amount'] - $newPaid, 2));
        $newStatus = (string) $account['status'] === 'Anulada' ? 'Anulada' : ($newPaid <= 0 ? 'Pendiente' : ($newBalance <= 0 ? 'Pagada' : 'Parcial'));
        $pdo->prepare("UPDATE {$config['account_table']} SET paid_amount=?,balance_amount=?,status=? WHERE id=?")->execute([$newPaid, $newBalance, $newStatus, (int) $detail['account_id']]);
    }
    $pdo->prepare("UPDATE {$config['header_table']} SET status='Anulado',notes=CONCAT(COALESCE(notes,''),' | Pago anulado: ',?) WHERE id=?")->execute([substr($reason, 0, 200), $paymentId]);
    app_record_document_status($pdo, $config['document_type'], $paymentId, (string) $header['payment_number'], 'Registrado', 'Anulado', 'Anulacion', $userId, $reason);
    return ['already_reversed' => false, 'type' => $type, 'id' => $paymentId];
}
