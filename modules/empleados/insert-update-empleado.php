<?php

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/validation.php';
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
    $fieldErrors=[];
    if ($e_nombres === '') $fieldErrors[]=app_field_error('empleado_nombres','REQUIRED','Los nombres son obligatorios.');
    if ($e_apepat === '') $fieldErrors[]=app_field_error('empleado_apepat','REQUIRED','El apellido paterno es obligatorio.');
    if ($e_numdoc === '') $fieldErrors[]=app_field_error('empleado_numdoc','REQUIRED','El número de documento es obligatorio.');
    if (!$validDate($e_fecnac)) $fieldErrors[]=app_field_error('empleado_fecnac','INVALID_DATE','La fecha de nacimiento no es válida.');
    if (!$validDate($e_fecing)) $fieldErrors[]=app_field_error('empleado_fecing','INVALID_DATE','La fecha de ingreso no es válida.');
    if ($e_correo !== '' && filter_var($e_correo, FILTER_VALIDATE_EMAIL) === false)
        $fieldErrors[]=app_field_error('empleado_correo','INVALID_EMAIL','El correo electrónico no es válido.');
    if ($fieldErrors) { app_api_error(new AppValidationException($fieldErrors),'No se pudo validar el empleado.'); exit; }
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
        app_api_error($exception, 'No se pudo guardar el empleado. Reintenta o revisa el registro del servidor.');
    }
} else {
    echo "ERROR";
}
