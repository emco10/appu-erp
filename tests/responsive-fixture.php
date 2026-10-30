<?php

declare(strict_types=1);

// Este punto de entrada solo funciona en el servidor efimero de pruebas. Apache
// no define la variable y, por tanto, no puede utilizarse para omitir el login.
if (PHP_SAPI !== 'cli-server' || getenv('APPU_RESPONSIVE_TEST') !== '1') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/global/config.php';

$sessionDirectory = __DIR__ . '/artifacts/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0770, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('No se pudo preparar el directorio de sesiones de prueba.');
}
session_save_path($sessionDirectory);
app_start_session();

$_SESSION['loggedInUser'] = [
    'USERID' => 0,
    'ROLE_CODE' => 'admin',
    'ROLE_NAME' => 'Administrador',
    'PERMISSIONS' => [],
    'PHOTO_URL' => '',
    'EMPLOYEE_NAME' => 'Prueba adaptable',
    'USERNAME' => 'responsive-test',
    'JOB' => 'QA',
];

$allowedViews = [
    'views/home',
    'views/clientes/registro-cliente',
    'views/cotizacion/registro-cotizacion',
    'views/compras/solicitudes',
    'views/finanzas/cuentas',
    'views/gestion/index',
];
$view = (string) ($_GET['view'] ?? 'views/home');
if (!in_array($view, $allowedViews, true)) {
    http_response_code(400);
    echo 'Vista de prueba no permitida.';
    exit;
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', $root);
$_GET['url'] = $view;

include $root . '/views/template/main.php';
