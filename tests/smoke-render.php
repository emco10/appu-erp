<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
$_SESSION['loggedInUser'] = [
    'USERID' => 0, 'ROLE_CODE' => 'admin', 'ROLE_NAME' => 'Administrador',
    'PERMISSIONS' => [], 'PHOTO_URL' => '', 'EMPLOYEE_NAME' => 'Prueba APPU',
    'USERNAME' => 'smoke-test', 'JOB' => 'QA',
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
$_GET['url'] = $argv[1] ?? 'views/home';
set_error_handler(static function ($severity, $message, $file, $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

ob_start();
include $root . '/views/template/main.php';
$html = ob_get_clean();
$page = basename($_GET['url']);
$scriptAliases = ['entrada-inventario' => 'actualizar-stock', 'salida-inventario' => 'actualizar-stock', 'flujo'=>'flujo-comercial', 'flujo-documental'=>'flujo-comercial'];
$pageScript = $scriptAliases[$page] ?? $page;
foreach (['</html>', '>Navegar</button>', 'js/footer-functions.js?v=' . SCRIPT_VER, '/ajax/' . $pageScript . '.js?v=' . SCRIPT_VER] as $marker) {
    if (!str_contains($html, $marker)) throw new RuntimeException('Render incompleto; falta: ' . $marker);
}
if ($page === 'home') {
    $themeStyles = file_get_contents($root . '/css/style-appu.css');
    foreach (['appu-welcome-card', 'appu-welcome-title', 'card-primary.card-outline.appu-welcome-card > .card-header h5.appu-welcome-title', 'var(--appu-primary-dark)'] as $marker) {
        if (!str_contains($html, $marker) && !str_contains((string) $themeStyles, $marker)) {
            throw new RuntimeException('El saludo no tiene estilo legible para tema claro; falta: ' . $marker);
        }
    }
}
echo 'PASS ' . $_GET['url'] . ' bytes=' . strlen($html) . PHP_EOL;
