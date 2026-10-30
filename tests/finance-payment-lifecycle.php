<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($max) => 0);
app_start_session();
require $root . '/global/connection.php';
require_once $root . '/global/finance-payments.php';

$pdo->beginTransaction();
try {
    $userId = (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $customerId = (int) $pdo->query('SELECT client_id FROM tbl_customer ORDER BY client_id LIMIT 1')->fetchColumn();
    $providerId = (int) $pdo->query('SELECT id FROM tbl_provider ORDER BY id LIMIT 1')->fetchColumn();
    if (!$userId) throw new RuntimeException('Falta un usuario base para la prueba financiera.');
    if (!$customerId) {
        $pdo->prepare('INSERT INTO tbl_customer(ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)')->execute(['20999999981','CLIENTE FINANZAS QA','FINANZAS QA','','','','','','','','','','','']);
        $customerId = (int) $pdo->lastInsertId();
    }
    if (!$providerId) {
        $pdo->prepare('INSERT INTO tbl_provider(code,business_name,address,country,city,district,contact1_name,contact1_phone,contact2_name,contact2_phone,bank1_name,bank1_currency,bank1_account_number,bank1_account_holder,bank2_name,bank2_currency,bank2_account_number,bank2_account_holder) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(['QA-FIN-' . substr(uniqid(), -6),'PROVEEDOR FINANZAS QA','','Peru','','','','','','','','','','','','','','']);
        $providerId = (int) $pdo->lastInsertId();
    }

    $seed = (int) (microtime(true) * 1000) % 100000000;
    $insertReceivable = $pdo->prepare("INSERT INTO tbl_accounts_receivable(document_type,document_id,document_number,customer_id,issue_date,due_date,currency,total_amount,balance_amount) VALUES ('qa_finance',?,?,?,?,?,'PEN',?,?)");
    $insertReceivable->execute([$seed, 'QA-CXC-1-' . $seed, $customerId, date('Y-m-d'), date('Y-m-d'), 100, 100]);
    $ar1 = (int) $pdo->lastInsertId();
    $insertReceivable->execute([$seed + 1, 'QA-CXC-2-' . $seed, $customerId, date('Y-m-d'), date('Y-m-d'), 80, 80]);
    $ar2 = (int) $pdo->lastInsertId();

    $incoming = app_create_finance_payment($pdo, 'receivable', [
        ['account_id' => $ar1, 'amount' => 40, 'notes' => 'Parcial'],
        ['account_id' => $ar2, 'amount' => 80, 'notes' => 'Total'],
    ], ['payment_date' => date('Y-m-d'), 'payment_method' => 'Transferencia', 'reference' => 'QA-CXC'], $userId);
    if ((float) $incoming['total'] !== 120.0 || (int) $incoming['allocations'] !== 2) throw new RuntimeException('Cabecera de cobro múltiple incorrecta.');
    $rows = $pdo->query("SELECT id,paid_amount,balance_amount,status FROM tbl_accounts_receivable WHERE id IN ({$ar1},{$ar2}) ORDER BY id")->fetchAll();
    if ((float) $rows[0]['paid_amount'] !== 40.0 || (float) $rows[0]['balance_amount'] !== 60.0 || $rows[0]['status'] !== 'Parcial') throw new RuntimeException('Aplicación parcial de CxC incorrecta.');
    if ((float) $rows[1]['paid_amount'] !== 80.0 || (float) $rows[1]['balance_amount'] !== 0.0 || $rows[1]['status'] !== 'Pagada') throw new RuntimeException('Aplicación total de CxC incorrecta.');
    $flowCount = (int) $pdo->query("SELECT COUNT(*) FROM tbl_document_flow WHERE target_type='incoming_payment' AND target_id=" . (int) $incoming['id'] . " AND relationship_type='PAYMENT_APPLICATION'")->fetchColumn();
    if ($flowCount !== 2) throw new RuntimeException('El cobro múltiple no registró toda su trazabilidad documental.');
    app_reverse_finance_payment($pdo, 'receivable', (int) $incoming['id'], 'Reversión automática QA', $userId);
    $rows = $pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_receivable WHERE id IN ({$ar1},{$ar2}) ORDER BY id")->fetchAll();
    if ((float) $rows[0]['paid_amount'] !== 0.0 || (float) $rows[0]['balance_amount'] !== 100.0 || $rows[0]['status'] !== 'Pendiente') throw new RuntimeException('Reversión de CxC parcial incorrecta.');
    if ((float) $rows[1]['paid_amount'] !== 0.0 || (float) $rows[1]['balance_amount'] !== 80.0 || $rows[1]['status'] !== 'Pendiente') throw new RuntimeException('Reversión de CxC total incorrecta.');
    if (!app_reverse_finance_payment($pdo, 'receivable', (int) $incoming['id'], 'Segunda reversión QA', $userId)['already_reversed']) throw new RuntimeException('La reversión financiera no es idempotente.');

    $insertPayable = $pdo->prepare("INSERT INTO tbl_accounts_payable(document_type,document_id,document_number,provider_id,issue_date,due_date,currency,total_amount,balance_amount) VALUES ('qa_finance',?,?,?,?,?,'PEN',?,?)");
    $insertPayable->execute([$seed + 2, 'QA-CXP-1-' . $seed, $providerId, date('Y-m-d'), date('Y-m-d'), 150, 150]);
    $ap1 = (int) $pdo->lastInsertId();
    $outgoing = app_create_finance_payment($pdo, 'payable', [['account_id' => $ap1, 'amount' => 75]], ['payment_date' => date('Y-m-d'), 'payment_method' => 'Transferencia', 'reference' => 'QA-CXP'], $userId);
    $row = $pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_payable WHERE id={$ap1}")->fetch();
    if ((float) $row['paid_amount'] !== 75.0 || (float) $row['balance_amount'] !== 75.0 || $row['status'] !== 'Parcial') throw new RuntimeException('Aplicación parcial de CxP incorrecta.');
    $flowCount = (int) $pdo->query("SELECT COUNT(*) FROM tbl_document_flow WHERE target_type='outgoing_payment' AND target_id=" . (int) $outgoing['id'] . " AND relationship_type='PAYMENT_APPLICATION'")->fetchColumn();
    if ($flowCount !== 1) throw new RuntimeException('El pago efectuado no registró su trazabilidad documental.');
    app_reverse_finance_payment($pdo, 'payable', (int) $outgoing['id'], 'Reversión automática QA', $userId);
    $row = $pdo->query("SELECT paid_amount,balance_amount,status FROM tbl_accounts_payable WHERE id={$ap1}")->fetch();
    if ((float) $row['paid_amount'] !== 0.0 || (float) $row['balance_amount'] !== 150.0 || $row['status'] !== 'Pendiente') throw new RuntimeException('Reversión de CxP incorrecta.');

    try {
        app_create_finance_payment($pdo, 'payable', [['account_id' => $ap1, 'amount' => 151]], [], $userId);
        throw new RuntimeException('Se aceptó un pago superior al saldo.');
    } catch (DomainException $expected) {
        if ($expected->getMessage() !== 'El pago supera el saldo pendiente.') throw $expected;
    }

    echo 'PASS incoming/outgoing payment partial full multi reversal lifecycle' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL finance payment lifecycle: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
