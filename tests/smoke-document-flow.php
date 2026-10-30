<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
$_SESSION['loggedInUser'] = ['USERID' => 0, 'ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/modules/reportes/flujo-documental.php';

chdir($root . '/modules/reportes');
ob_start();
include 'flujo-documental.php';
$response = ob_get_clean();
$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
foreach (['flows', 'summary'] as $section) {
    if (!array_key_exists($section, $data) || !is_array($data[$section])) {
        throw new RuntimeException('Sección de trazabilidad inválida: ' . $section);
    }
}
foreach ($data['flows'] as $flow) {
    foreach (['source_type', 'source_id', 'target_type', 'target_id', 'relationship_type'] as $field) {
        if (!array_key_exists($field, $flow)) throw new RuntimeException('Relación documental incompleta: ' . $field);
    }
}
echo 'PASS document flow report JSON relations=' . count($data['flows']) . PHP_EOL;
