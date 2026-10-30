<?php
require '../../global/connection.php';
$FILTER_CLI = trim((string) ($_POST["FILTER"] ?? 'ALL'));
if ($FILTER_CLI == "ALL") {
    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_customer
        ORDER BY client_id DESC");
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    //if ($rowsNumber > 0) {
        $json_data = array();
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = "CLI-" . $ROW["client_id"];
            $ROWDATA['RAZ_SOC'] = $ROW["business_name"];
            $ROWDATA['NOM_COM'] = $ROW["trade_name"];
            $ROWDATA['RUC'] = $ROW["ruc"];
            $ROWDATA['TELEF'] = $ROW["phone"];
            $ROWDATA['CELULAR'] = $ROW["cellphone"];
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];

            array_push($json_data, $ROWDATA);
        }
        echo json_encode(array("data" => $json_data));
    //}
} else {
    $ID_REAL = str_replace("CLI-","",$FILTER_CLI);
    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_customer
        WHERE client_id=:CLIENT_ID");
    $sqlStatement->bindParam("CLIENT_ID", $ID_REAL, PDO::PARAM_STR);
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = $ROW["client_id"];
            $ROWDATA['RAZ_SOC'] = $ROW["business_name"];
            $ROWDATA['NOM_COM'] = $ROW["trade_name"];
            $ROWDATA['RUC'] = $ROW["ruc"];
            $ROWDATA['TELEF'] = $ROW["phone"];
            $ROWDATA['CELULAR'] = $ROW["cellphone"];
            $ROWDATA['NOM_COM'] = $ROW["trade_name"];
            $ROWDATA['FEC_REG'] = date("Y-m-d",strtotime($ROW["registration_date"]));
            $ROWDATA['EMAIL'] = $ROW["email"];
            $ROWDATA['DIRECC'] = $ROW["address"];
            $ROWDATA['DEPARTAMENTO'] = $ROW["department_id"];
            $ROWDATA['PROVINCIA'] = $ROW["province_id"];
            $ROWDATA['DISTRITO'] = $ROW["district_id"];
            $ROWDATA['NOMCON_1'] = $ROW["contact1_name"];
            $ROWDATA['CELCON_1'] = $ROW["contact1_phone"];
            $ROWDATA['NOMCON_2'] = $ROW["contact2_name"];
            $ROWDATA['CELCON_2'] = $ROW["contact2_phone"];
            $ROWDATA['PAGCOM'] = $ROW["commission"];
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];
            $ROWDATA['DEFAULT_CURRENCY'] = $ROW['default_currency'];
            $ROWDATA['PAYMENT_DAYS'] = (int) $ROW['payment_days'];
            $ROWDATA['CREDIT_LIMIT'] = (float) $ROW['credit_limit'];
            $ROWDATA['PRICE_LIST_ID'] = $ROW['price_list_id'] !== null ? (int) $ROW['price_list_id'] : null;
            $ROWDATA['SALES_EMPLOYEE_ID'] = $ROW['sales_employee_id'] !== null ? (int) $ROW['sales_employee_id'] : null;
            $customStatement = $pdo->prepare('SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = ? AND record_id = ?');
            $customStatement->execute(['customer', (int) $ROW['client_id']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) { $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value']; }

            array_push($json_data, $ROWDATA);
        }    
    }
    
    echo json_encode($json_data);
}
