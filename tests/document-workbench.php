<?php
// Router for an explicit loopback QA server, never an Apache endpoint.
if (PHP_SAPI !== 'cli-server' || getenv('APPU_DOCUMENT_QA') !== '1' || !str_ends_with((string) getenv('APPU_DB_NAME'), '_qa') || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
$root = dirname(__DIR__);
require_once $root . '/global/config.php';
header('X-APPU-QA-Database: ' . DB);
app_start_session();
$pdo = new PDO('mysql:host=' . SERVER . ';dbname=' . DB . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if(getenv('APPU_QA_SESSION_AUTH')!=='1')$_SESSION['loggedInUser'] = ['USERID' => (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn(), 'ROLE_CODE' => 'admin', 'ROLE_NAME' => 'QA', 'PERMISSIONS' => [], 'PHOTO_URL' => '', 'EMPLOYEE_NAME' => 'QA documentos', 'USERNAME' => 'qa-document', 'JOB' => 'QA'];
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(24));
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(global|db|tests)/#', $path)) { http_response_code(404); exit; }
$file = realpath($root . $path);
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') return false;
    chdir(dirname($file)); require $file; return;
}
$_GET['url'] = trim($path, '/') ?: 'views/ventas/resumen-ordenes-venta';
chdir($root); require $root . '/index.php';
