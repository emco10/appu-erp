<?php
require '../../global/connection.php';
$FILTER_PROV = trim((string) ($_POST["FILTER"] ?? 'ALL'));
if ($FILTER_PROV == "ALL") {
    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_provider ORDER BY id DESC");
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {        
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = "PROV-" . $ROW["id"];
            $ROWDATA['NUMERO'] = $ROW["code"];
            $ROWDATA['RAZ_SOC'] = $ROW["business_name"];
            $ROWDATA['DIRECC'] = $ROW["address"];
            $ROWDATA['PAIS'] = $ROW["country"];
            $ROWDATA['CIUDAD'] = $ROW["city"];
            $ROWDATA['FECREG'] = date("d/m/Y H:i",strtotime($ROW["registration_date"]));
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];
            array_push($json_data, $ROWDATA);
        }
    }
    echo json_encode(array("data" => $json_data));
} else {
    $ID_REAL = str_replace("PROV-","",$FILTER_PROV);
    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_provider
        WHERE id=:PROV_ID");
    $sqlStatement->bindParam("PROV_ID", $ID_REAL, PDO::PARAM_INT);
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {        
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = $ROW["id"];
            $ROWDATA['NUMERO'] = $ROW["code"];
            $ROWDATA['RAZ_SOC'] = $ROW["business_name"];
            $ROWDATA['DIRECC'] = $ROW["address"];
            $ROWDATA['PAIS'] = $ROW["country"];
            $ROWDATA['CIUDAD'] = $ROW["city"];
            $ROWDATA['DISTRITO'] = $ROW["district"];
            $ROWDATA['CONT1_NAME'] = $ROW["contact1_name"];
            $ROWDATA['CONT1_PHONE'] = $ROW["contact1_phone"];            
            $ROWDATA['CONT2_NAME'] = $ROW["contact2_name"];
            $ROWDATA['CONT2_PHONE'] = $ROW["contact2_phone"];            
            $ROWDATA['BANK1_NAME'] = $ROW["bank1_name"];
            $ROWDATA['BANK1_CURR'] = $ROW["bank1_currency"];
            $ROWDATA['BANK1_ACCNUM'] = $ROW["bank1_account_number"];
            $ROWDATA['BANK1_ACCHOL'] = $ROW["bank1_account_holder"];
            $ROWDATA['BANK2_NAME'] = $ROW["bank2_name"];
            $ROWDATA['BANK2_CURR'] = $ROW["bank2_currency"];
            $ROWDATA['BANK2_ACCNUM'] = $ROW["bank2_account_number"];
            $ROWDATA['BANK2_ACCHOL'] = $ROW["bank2_account_holder"];
            $ROWDATA['FEC_REG'] = date("Y-m-d",strtotime($ROW["registration_date"]));
            $ROWDATA['ESTADO'] = (int) $ROW['active_status'] === 1 ? 'Activo' : 'Inactivo';
            $ROWDATA['ACTIVE_STATUS'] = (int) $ROW['active_status'];
            $ROWDATA['DEFAULT_CURRENCY'] = $ROW['default_currency'];
            $ROWDATA['PAYMENT_DAYS'] = (int) $ROW['payment_days'];
            $ROWDATA['CREDIT_LIMIT'] = (float) $ROW['credit_limit'];
            $ROWDATA['BUYER_EMPLOYEE_ID'] = $ROW['buyer_employee_id'] !== null ? (int) $ROW['buyer_employee_id'] : null;
            $customStatement = $pdo->prepare('SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = ? AND record_id = ?');
            $customStatement->execute(['provider', (int) $ROW['id']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) { $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value']; }
            array_push($json_data, $ROWDATA);
        }        
    }
    echo json_encode($json_data);
}
