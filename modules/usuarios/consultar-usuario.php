<?php
require '../../global/connection.php';
require_once '../../global/document-history.php';
$FILTER_USER = trim((string) ($_POST["FILTER"] ?? 'ALL'));
if ($FILTER_USER == "ALL") {
    $sqlStatement = $pdo->prepare("SELECT u.id AS usuario_id, 
        u.username, u.registration_date, u.last_seen_at, u.role_id, u.active_status, role.name AS role_name, e.id AS empleado_id, e.job,
        CONCAT(e.last_name_1, ' ', e.last_name_2, ', ', e.name) AS empleado_nombre 
        FROM tbl_user u
        JOIN tbl_employee e ON u.employee_id = e.id
        JOIN tbl_role role ON role.id = u.role_id
        ORDER BY u.id DESC");
    
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();

        $json_data = array();
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO_USR'] = "USR-" . $ROW["usuario_id"];
            $ROWDATA['CODIGO_EMP'] = "EMP-" . $ROW["empleado_id"];
            $ROWDATA['USERNAME'] = $ROW["username"];
            $ROWDATA['NOMBRE_EMP'] = $ROW["empleado_nombre"];
            $ROWDATA['CARGO_EMP'] = $ROW["job"];
            $ROWDATA['ROL'] = $ROW["role_name"];
            $ROWDATA['FEC_REG'] = date("d/m/Y H:i",strtotime($ROW["registration_date"]));
            $ROWDATA['ULTIMA_ACTIVIDAD'] = !empty($ROW["last_seen_at"]) ? date("d/m/Y H:i", strtotime($ROW["last_seen_at"])) : "Sin actividad";
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];

            array_push($json_data, $ROWDATA);
        }
        echo json_encode(array("data" => $json_data));

} else {
    $ID_REAL = str_replace("USR-","",$FILTER_USER);
    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_user
        WHERE id=:USER_ID");
    $sqlStatement->bindParam("USER_ID", $ID_REAL, PDO::PARAM_STR);
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = $ROW["id"];
            $ROWDATA['USERNAME'] = $ROW["username"];
            $ROWDATA['CODIGO_EMP'] = $ROW["employee_id"];
            $ROWDATA['ROLE_ID'] = $ROW["role_id"];
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['FEC_REG'] = date("Y-m-d",strtotime($ROW["registration_date"]));
            $customStatement = $pdo->prepare('SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = ? AND record_id = ?');
            $customStatement->execute(['user', (int) $ROW['id']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) {
                $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value'];
            }

            array_push($json_data, $ROWDATA);
        }
    }
    echo json_encode($json_data);
}
