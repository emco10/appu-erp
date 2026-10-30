<?php

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/document-history.php';

    $e_nombres = trim($_POST['empleado_nombres']);
    $e_apepat = trim($_POST['empleado_apepat']);
    $e_apemat = trim($_POST['empleado_apemat']);
    $e_direccion = trim($_POST['empleado_direccion']);
    $e_tipodoc = $_POST['empleado_tipodoc'];
    $e_numdoc = trim($_POST['empleado_numdoc']);
    $e_estadociv = $_POST['empleado_estado_civ'];
    $e_fecnac = trim((string) ($_POST['empleado_fecnac'] ?? ''));
    $e_cargo = $_POST['empleado_cargo'];
    $e_fecing = trim((string) ($_POST['empleado_fecing'] ?? ''));
    $e_telefono = trim($_POST['empleado_telefono']);
    $e_correo = trim($_POST['empleado_correo']);
    $e_gradoest = $_POST['empleado_grado_est'];
    $e_carrera = trim($_POST['empleado_carrera']);

    //$c_celcont_2 = $_POST['cliente_celcont_2'] == "" ? "ninguno" : $_POST['cliente_celcont_2'];
    //$c_pagcomi = !$_POST['cliente_pagocomision'] ? 0 : $_POST['cliente_pagocomision'];

    $e_idemp = $_POST['empleado_id'];
    $e_active = (int) ($_POST['empleado_active_status'] ?? 1) === 0 ? 0 : 1;
    $customFields = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];

    $validDate = static function (string $value): bool {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };
    if ($e_nombres === '' || $e_apepat === '' || $e_direccion === '' || $e_numdoc === '' || !$validDate($e_fecnac) || !$validDate($e_fecing)) {
        http_response_code(422);
        echo 'ERROR_VALIDATION';
        exit;
    }
    if ($e_correo !== '' && filter_var($e_correo, FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(422);
        echo 'ERROR_VALIDATION';
        exit;
    }
    if ((int) $e_idemp > 0 && $e_active === 0) {
        $activeUser = $pdo->prepare('SELECT COUNT(*) FROM tbl_user WHERE employee_id=? AND active_status=1');
        $activeUser->execute([(int) $e_idemp]);
        if ((int) $activeUser->fetchColumn() > 0) {
            http_response_code(409);
            echo 'EMPLOYEE_HAS_ACTIVE_USER';
            exit;
        }
    }

    try {
        app_validate_custom_fields($pdo, 'employee', $customFields);
    } catch (Throwable $exception) {
        http_response_code(422);
        echo 'ERROR_CUSTOM_FIELDS';
        exit;
    }

    try {
    $pdo->beginTransaction();
    if ((int) $e_idemp > 0) {
        $existing = $pdo->prepare('SELECT id FROM tbl_employee WHERE id=? FOR UPDATE');
        $existing->execute([(int) $e_idemp]);
        if (!$existing->fetchColumn()) throw new InvalidArgumentException('El empleado no existe.');
    }
    if ($e_idemp == "" || $e_idemp == null) {
        $sqlStatement = $pdo->prepare("SELECT * FROM tbl_employee WHERE id_doc_number=:id_doc_number");
        $sqlStatement->bindParam("id_doc_number", $e_numdoc, PDO::PARAM_STR);
    } else {
        $sqlStatement = $pdo->prepare("SELECT * FROM tbl_employee WHERE (id_doc_number=:id_doc_number) AND (id <> :idemp)");
        $sqlStatement->bindParam("id_doc_number", $e_numdoc, PDO::PARAM_STR);
        $sqlStatement->bindParam("idemp", $e_idemp, PDO::PARAM_INT);
    }

    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    if ($rowsNumber == 0) {
        if ($e_idemp == "" || $e_idemp == null) {
            $sqlStatement = $pdo->prepare("INSERT INTO tbl_employee (name,last_name_1,last_name_2,id_doc_type,id_doc_number,civil_status,email,phone,address,job,study_level,study_career,birth_date,admission_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($sqlStatement) {
                $sqlStatement->execute([$e_nombres,$e_apepat,$e_apemat,$e_tipodoc,$e_numdoc,$e_estadociv,$e_correo,$e_telefono,$e_direccion,$e_cargo,$e_gradoest,$e_carrera,$e_fecnac,$e_fecing]);
                app_save_custom_fields($pdo, 'employee', (int) $pdo->lastInsertId(), $customFields);
                $result = "OK_INSERT";
            } else {
                $result = "ERROR";
            }
        } else {
            $sqlStatement = $pdo->prepare("UPDATE tbl_employee SET name=?,last_name_1=?,last_name_2=?,id_doc_type=?,id_doc_number=?,civil_status=?,email=?,phone=?,address=?,job=?,study_level=?,study_career=?,birth_date=?,admission_date=?,active_status=? WHERE id=?");
            if ($sqlStatement) {
                $sqlStatement->execute([$e_nombres,$e_apepat,$e_apemat,$e_tipodoc,$e_numdoc,$e_estadociv,$e_correo,$e_telefono,$e_direccion,$e_cargo,$e_gradoest,$e_carrera,$e_fecnac,$e_fecing,$e_active,$e_idemp]);
                app_save_custom_fields($pdo, 'employee', (int) $e_idemp, $customFields);
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
        error_log('APPU employee save: ' . $exception->getMessage());
        http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
        echo 'ERROR';
    }
} else {
    echo "ERROR";
}
