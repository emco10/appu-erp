<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($max) => 0);
app_start_session();
require $root . '/global/connection.php';

$company = $pdo->query('SELECT id,tax_id,legal_name,currency FROM tbl_company_settings WHERE id=1')->fetch();
if (!$company || !preg_match('/^(10|20)\d{9}$/', (string) $company['tax_id']) || trim((string) $company['legal_name']) === '') throw new RuntimeException('Configuración de empresa incompleta.');
if (!in_array((string) $company['currency'], ['PEN','USD','EUR'], true)) throw new RuntimeException('Moneda base inválida.');
$checks = [
    'listas' => 'SELECT COUNT(*) FROM tbl_app_value_list',
    'perfiles' => 'SELECT COUNT(*) FROM tbl_role',
    'permisos' => 'SELECT COUNT(*) FROM tbl_permission',
    'cuentas financieras' => 'SELECT COUNT(*) FROM tbl_financial_account',
    'series' => 'SELECT COUNT(*) FROM tbl_document_series',
    'listas de precios' => 'SELECT COUNT(*) FROM tbl_price_list',
];
foreach ($checks as $label => $sql) {
    if ((int) $pdo->query($sql)->fetchColumn() < 1) throw new RuntimeException('Gestión no tiene ' . $label . ' configurados.');
}
$invalidAssignments = (int) $pdo->query('SELECT COUNT(*) FROM tbl_role_permission rp LEFT JOIN tbl_role r ON r.id=rp.role_id LEFT JOIN tbl_permission p ON p.id=rp.permission_id WHERE r.id IS NULL OR p.id IS NULL')->fetchColumn();
if ($invalidAssignments !== 0) throw new RuntimeException('Existen permisos asignados a perfiles inexistentes.');
echo 'PASS management company roles permissions lists accounts series integrity' . PHP_EOL;
