<?php

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/document-history.php';

    $u_usuario = trim($_POST['usuario_nombre']);
    $u_password = (string) ($_POST['usuario_pass'] ?? '');
    $u_empid = filter_var($_POST['usuario_empleado_id'] ?? null, FILTER_VALIDATE_INT);
    $u_roleid = filter_var($_POST['usuario_rol_id'] ?? null, FILTER_VALIDATE_INT);

    $u_fecreg = date("Y-m-d H:i:s");

    $u_iduser = $_POST['usuario_id'];
    $u_active = (int) ($_POST['usuario_active_status'] ?? 1) === 0 ? 0 : 1;
    $customFields = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];

    try {
        app_validate_custom_fields($pdo, 'user', $customFields);
    } catch (Throwable $exception) {
        http_response_code(422);
        echo 'ERROR_CUSTOM_FIELDS';
        exit;
    }

    if ($u_usuario === '' || $u_empid === false || $u_roleid === false) {
        http_response_code(422);
        echo 'ERROR';
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $u_usuario)) {
        http_response_code(422);
        echo 'ERROR_USERNAME';
        exit;
    }
    if ($u_password !== '' && (strlen($u_password) < 8 || strlen($u_password) > 72)) {
        http_response_code(422);
        echo 'ERROR_PASSWORD';
        exit;
    }
    if (array_key_exists('usuario_pass_conf', $_POST) && $u_password !== (string) $_POST['usuario_pass_conf']) {
        http_response_code(422);
        echo 'ERROR_PASSWORD_CONFIRMATION';
        exit;
    }
    if ((int) $u_iduser === (int) $_SESSION['loggedInUser']['USERID'] && $u_active === 0) {
        http_response_code(422);
        echo 'ERROR_SELF_DEACTIVATION';
        exit;
    }

    $employeeStatement = $pdo->prepare('SELECT id FROM tbl_employee WHERE id = ? AND active_status = 1');
    $employeeStatement->execute([$u_empid]);
    if (!$employeeStatement->fetchColumn()) {
        http_response_code(422);
        echo 'ERROR';
        exit;
    }

    $roleStatement = $pdo->prepare('SELECT id FROM tbl_role WHERE id = ?');
    $roleStatement->execute([$u_roleid]);
    if (!$roleStatement->fetchColumn()) {
        http_response_code(422);
        echo 'ERROR';
        exit;
    }

    $employeeUserStatement = $pdo->prepare('SELECT id FROM tbl_user WHERE employee_id = ? AND id <> ? LIMIT 1');
    $employeeUserStatement->execute([$u_empid, (int) $u_iduser]);
    if ($employeeUserStatement->fetchColumn()) {
        http_response_code(409);
        echo 'EMPLOYEE_ASSIGNED';
        exit;
    }

    if ($u_iduser == "" || $u_iduser == null) {
        $sqlStatement = $pdo->prepare("SELECT * FROM tbl_user WHERE username=:usuario");
        $sqlStatement->bindParam("usuario", $u_usuario, PDO::PARAM_STR);
    } else {
        $sqlStatement = $pdo->prepare("SELECT * FROM tbl_user WHERE (username=:usuario) AND (id <> :iduser)");
         $sqlStatement->bindParam("usuario", $u_usuario, PDO::PARAM_STR);
        $sqlStatement->bindParam("iduser", $u_iduser, PDO::PARAM_INT);
    }

    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    try {
    $pdo->beginTransaction();
    if ($rowsNumber == 0) {

        if ($u_password !== '') {
            $hashedPassword = password_hash($u_password, PASSWORD_DEFAULT);
        }

        if ($u_iduser == "" || $u_iduser == null) {
            if ($u_password === '') {
                throw new InvalidArgumentException('La contraseña es obligatoria para crear un usuario.');
            }

            $sqlStatement = $pdo->prepare("INSERT INTO tbl_user(username,password,employee_id,role_id,registration_date) VALUES(?,?,?,?,?)");
            if ($sqlStatement) {
                $sqlStatement->execute([$u_usuario,$hashedPassword,$u_empid,$u_roleid,$u_fecreg]);
                app_save_custom_fields($pdo, 'user', (int) $pdo->lastInsertId(), $customFields);
                $result = "OK_INSERT";
            } else {
                $result = "ERROR";
            }
        } else {

            $currentRoleStatement = $pdo->prepare('SELECT role_id FROM tbl_user WHERE id = ? FOR UPDATE');
            $currentRoleStatement->execute([$u_iduser]);
            $currentRoleValue = $currentRoleStatement->fetchColumn();
            if ($currentRoleValue === false) throw new InvalidArgumentException('El usuario no existe.');
            $currentRoleId = (int) $currentRoleValue;
            if ($currentRoleId === 1 && ($u_roleid !== 1 || $u_active === 0)) {
                $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_user WHERE role_id = 1 AND active_status = 1')->fetchColumn();
                if ($adminCount <= 1) {
                    throw new InvalidArgumentException('Debe conservarse al menos un administrador activo.');
                }
            }

            if ($u_password == "" || $u_password == null) {
                $sqlStatement = $pdo->prepare("UPDATE tbl_user SET username=?,employee_id=?,role_id=?,active_status=? WHERE id=?");
                $sqlParameters=[$u_usuario,$u_empid,$u_roleid,$u_active,$u_iduser];
            }else{
                $sqlStatement = $pdo->prepare("UPDATE tbl_user SET username=?,password=?,employee_id=?,role_id=?,active_status=? WHERE id=?");
                $sqlParameters=[$u_usuario,$hashedPassword,$u_empid,$u_roleid,$u_active,$u_iduser];
            }
            if ($sqlStatement) {
                $sqlStatement->execute($sqlParameters);
                app_save_custom_fields($pdo, 'user', (int) $u_iduser, $customFields);
                $result = "OK_UPDATE";
            } else {
                $result = "ERROR";
            }
        }
    } else {
        $result = "EXISTE";
    }
    $pdo->commit();
    echo $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('APPU user save: ' . $exception->getMessage());
        http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
        echo 'ERROR';
    }
} else {
    echo "ERROR";
}
