<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/authorization.php';

app_start_session();

if (PHP_SAPI !== 'cli' && !defined('APPU_PUBLIC_ENDPOINT')) {
    if (empty($_SESSION['loggedInUser'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => 'Sesión expirada.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => true, 'message' => 'La solicitud no superó la validación de seguridad.']);
            exit;
        }
    }

}

$server = 'mysql:dbname=' . DB . ';host=' . SERVER . ';charset=utf8mb4';

try {
    $pdo = new PDO($server, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '-05:00'");
} catch (PDOException $exception) {
    error_log('APPU database connection error: ' . $exception->getMessage());
    http_response_code(500);
    echo 'No fue posible conectar con la base de datos.';
    exit;
}

if(PHP_SAPI!=='cli'&&!defined('APPU_PUBLIC_ENDPOINT')){
    if(!app_refresh_logged_in_user($pdo)){
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>true,'message'=>'Tu acceso ya no está activo. Inicia sesión nuevamente.'],JSON_UNESCAPED_UNICODE);
        exit;
    }
    app_enforce_endpoint_permission();
}

if (!empty($_SESSION['loggedInUser']['USERID'])) {
    try {
        $presenceStatement = $pdo->prepare('UPDATE tbl_user SET last_seen_at = NOW() WHERE id = ?');
        $presenceStatement->execute([(int) $_SESSION['loggedInUser']['USERID']]);
    } catch (Throwable $ignored) {
        // La presencia no debe impedir el funcionamiento del ERP si una base antigua aún no tiene la columna.
    }
}
