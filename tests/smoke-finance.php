<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
$_SESSION['loggedInUser'] = ['USERID' => 0, 'ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/modules/reportes/finanzas.php';
$_GET = ['date_from' => date('Y-m-d', strtotime('-29 days')), 'date_to' => date('Y-m-d')];

chdir($root . '/modules/reportes');
ob_start();
include 'finanzas.php';
$response = ob_get_clean();
$report = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
foreach (['summary', 'payments', 'overdue', 'aging', 'partners', 'accounts', 'currencies', 'filters'] as $section) {
    if (!array_key_exists($section, $report) || !is_array($report[$section])) {
        throw new RuntimeException('Sección financiera inválida: ' . $section);
    }
}
foreach ($report['accounts'] as $account) {
    foreach (['account_type', 'document_type', 'document_id', 'document_number', 'party_name', 'due_date', 'currency', 'balance_amount', 'days_overdue', 'aging_bucket'] as $field) {
        if (!array_key_exists($field, $account)) throw new RuntimeException('Falta campo de cartera: ' . $field);
    }
    if ((float) $account['balance_amount'] <= 0) throw new RuntimeException('El detalle financiero incluyó una cuenta sin saldo abierto.');
}
$script = file_get_contents($root . '/ajax/finanzas.js');
foreach (['finance-report-type', 'finance-report-currency', 'finance-report-party', 'finance-report-export', 'record_id'] as $token) {
    if (strpos($script, $token) === false) throw new RuntimeException('Contrato de reporte financiero incompleto: ' . $token);
}
echo 'PASS financial report JSON' . PHP_EOL;
