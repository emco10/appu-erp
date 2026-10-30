<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($max) => 0);
app_start_session();
require $root . '/global/connection.php';

$pdo->beginTransaction();
try {
    $suffix = strtoupper(substr(uniqid(), -8));
    $document = 'QA' . substr(preg_replace('/\D/', '', uniqid()), -10);
    $pdo->prepare('INSERT INTO tbl_employee(name,last_name_1,last_name_2,id_doc_type,id_doc_number,civil_status,email,phone,address,job,study_level,study_career,birth_date,admission_date,active_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)')
        ->execute(['Empleado', 'Prueba', $suffix, 'DNI', $document, 'Soltero', 'qa-' . strtolower($suffix) . '@example.test', '', 'QA', 'Pruebas', '', '', '1990-01-01', '2026-01-01']);
    $employeeId = (int) $pdo->lastInsertId();
    $roleId = (int) $pdo->query('SELECT id FROM tbl_role ORDER BY id LIMIT 1')->fetchColumn();
    if (!$roleId) throw new RuntimeException('No existen perfiles para probar usuarios.');

    $insertUser = $pdo->prepare('INSERT INTO tbl_user(username,password,employee_id,role_id,registration_date,active_status) VALUES (?,?,?,?,NOW(),1)');
    $insertUser->execute(['qa_' . strtolower($suffix), password_hash('Prueba#2026', PASSWORD_DEFAULT), $employeeId, $roleId]);
    try {
        $insertUser->execute(['qa2_' . strtolower($suffix), password_hash('Prueba#2026', PASSWORD_DEFAULT), $employeeId, $roleId]);
        throw new RuntimeException('La base permitiÃ³ dos usuarios para el mismo empleado.');
    } catch (PDOException $expected) {
        if ((string) $expected->getCode() !== '23000') throw $expected;
    }

    $employeeQuery = file_get_contents($root . '/modules/empleados/consultar-empleado.php');
    $userQuery = file_get_contents($root . '/modules/usuarios/consultar-usuario.php');
    $userSave = file_get_contents($root . '/modules/usuarios/insert-update-usuario.php');
    if (!str_contains($employeeQuery, "['ACTIVE_STATUS']") || !str_contains($userQuery, "['ACTIVE_STATUS']")) throw new RuntimeException('Los formularios no reciben el estado numÃ©rico.');
    if (!str_contains($userSave, '$u_active === 0') || !str_contains($userSave, 'EMPLOYEE_ASSIGNED')) throw new RuntimeException('Faltan protecciones administrativas.');

    echo 'PASS employee user identity status and administrator safeguards' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL administration integrity: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
