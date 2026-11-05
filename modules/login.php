<?php

define('APPU_PUBLIC_ENDPOINT', true);
require '../global/connection.php';

header('Content-Type: application/json; charset=utf-8');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    echo json_encode(['error' => true]);
    exit;
}

$username = trim($_POST['txtUsername'] ?? '');
$password = $_POST['txtPassword'] ?? '';
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['error' => true]);
    exit;
}

if (app_login_is_rate_limited($pdo, $username, $remoteAddress)) {
    http_response_code(429);
    header('Retry-After: 900');
    echo json_encode(['error' => true, 'message' => 'Demasiados intentos. Espera 15 minutos antes de volver a intentar.']);
    exit;
}

$sqlStatement = $pdo->prepare("SELECT u.id AS USERID, u.username AS USERNAME,
    u.password AS PASS, u.photo_url AS PHOTO_URL, u.role_id AS ROLE_ID,
    r.code AS ROLE_CODE, r.name AS ROLE_NAME, e.job AS JOB,
    CONCAT(e.name, ' ', e.last_name_1) AS EMPLOYEE_NAME
    FROM tbl_user u
    JOIN tbl_employee e ON u.employee_id=e.id AND e.active_status = 1
    JOIN tbl_role r ON r.id=u.role_id
    WHERE u.username=:username AND u.active_status = 1");
$sqlStatement->execute(['username' => $username]);
$sqlData = $sqlStatement->fetch();

if (!$sqlData || !password_verify($password, $sqlData['PASS'])) {
    app_record_failed_login($pdo, $username, $remoteAddress);
    echo json_encode(['error' => true]);
    exit;
}

app_clear_login_attempts($pdo, $username, $remoteAddress);

if (password_needs_rehash($sqlData['PASS'], PASSWORD_DEFAULT)) {
    $rehashStatement = $pdo->prepare('UPDATE tbl_user SET password = ? WHERE id = ?');
    $rehashStatement->execute([password_hash($password, PASSWORD_DEFAULT), (int) $sqlData['USERID']]);
}

unset($sqlData['PASS']);
$sqlData['PHOTO_URL'] = basename($sqlData['PHOTO_URL'] ?: 'default-avatar.png');

$permissionStatement = $pdo->prepare(
    'SELECT permission.code
     FROM tbl_role_permission role_permission
     JOIN tbl_permission permission ON permission.id = role_permission.permission_id
     WHERE role_permission.role_id = ?
     ORDER BY permission.code'
);
$permissionStatement->execute([(int) $sqlData['ROLE_ID']]);
$sqlData['PERMISSIONS'] = $permissionStatement->fetchAll(PDO::FETCH_COLUMN);

session_regenerate_id(true);
$_SESSION['loggedInUser'] = $sqlData;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['_created_at'] = time();
$_SESSION['_last_activity_at'] = time();
$_SESSION['_regenerated_at'] = time();

echo json_encode(['error' => false]);
