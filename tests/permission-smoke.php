<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/global/config.php';
require_once dirname(__DIR__) . '/global/authorization.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();

$cases = [
    ['POST', '/modules/clientes/insert-update-cliente.php', 'customers.manage'],
    ['POST', '/modules/clientes/consultar-cliente.php', 'customers.view'],
    ['POST', '/modules/productos/insert-update-producto.php', 'inventory.manage'],
    ['GET', '/modules/productos/consultar-maestro.php', 'inventory.view'],
    ['GET', '/modules/reportes/finanzas.php', 'reports.view'],
    ['POST', '/modules/finanzas/cuentas.php', 'finance.manage'],
    ['POST', '/modules/compras/solicitudes.php', 'purchases.manage'],
    ['GET', '/modules/compras/solicitudes.php', 'purchases.view'],
    ['POST', '/modules/aprobaciones/procesar.php', 'purchases.view'],
];
foreach ($cases as [$method, $script, $expected]) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['SCRIPT_NAME'] = $script;
    $actual = app_endpoint_permission();
    if ($actual !== $expected) throw new RuntimeException("Permiso incorrecto para $script: $actual");
}

$_SESSION['loggedInUser'] = ['ROLE_CODE' => 'sales', 'PERMISSIONS' => ['customers.view']];
if (!app_can('customers.view') || app_can('customers.manage')) throw new RuntimeException('La separación view/manage no funciona.');
$_SESSION['loggedInUser'] = ['ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
if (!app_can('customers.manage')) throw new RuntimeException('El administrador perdió acceso total.');
foreach (['global/connection.php'=>'HTTP_X_CSRF_TOKEN','views/template/footer.php'=>'X-CSRF-Token'] as $file=>$token) if (strpos(file_get_contents(dirname(__DIR__).'/'.$file),$token)===false) throw new RuntimeException('Falta protección CSRF en '.$file);

echo 'PASS endpoint and role permissions' . PHP_EOL;
