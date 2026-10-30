<?php
require '../../global/connection.php';

$FILTER_COTIZ = $_POST["FILTER"];
$ESTADO_ORDEN = $_POST["ESTADO"];

if ($ESTADO_ORDEN !== 'ALL') {
    $ESTADO_ORDEN = filter_var($ESTADO_ORDEN, FILTER_VALIDATE_INT);
    if ($ESTADO_ORDEN === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
}
$TIPO_ORDEN = "";
if(isset($_POST["TIPO_ORDEN"])){
    $TIPO_ORDEN = $_POST["TIPO_ORDEN"];
    switch ($TIPO_ORDEN) {
        case 'COMPRA':
            $TIPO_ORDEN = 1;
            break;
        case 'SERVICIO':
            $TIPO_ORDEN = 2;
            break;
        default:
            $TIPO_ORDEN = '';
    }
}

$query_estado = "";
$query_tipo = "";
if ($FILTER_COTIZ == "ALL") {
    if ($ESTADO_ORDEN != "ALL") {
        $query_estado = " WHERE ord.status = $ESTADO_ORDEN ";
        if($TIPO_ORDEN != ""){
            $query_tipo = " AND ord.type = $TIPO_ORDEN ";
        }
    } else {
        if($TIPO_ORDEN != ""){
            $query_tipo = " WHERE ord.type = $TIPO_ORDEN ";
        }
    }
    $sqlStatement = $pdo->prepare("SELECT ord.type AS TIPOORDEN, ord.id AS IDORDEN, ord.number AS ORDNUMBER, ord.status AS ORDESTADO, ord.currency AS TIPOMON, ord.issue_date AS FECEMIS, ord.delivery_date AS FECDEL, ord.provider_id AS PROVID, ord.purchase_request_id AS REQUESTID, prov.business_name AS NOMPROV, ord.payment_days AS PAYDAYS, ord.account_number AS ACCNUM, ord.quotation AS COTIZ, ord.requester AS REQUES, ord.approver AS APROV, ord.observation AS OBSERV, ord.total_purchase AS TOTCOMP, ord.total_tax AS TOTIGV, ord.total_net AS TOTNET, ord.exchange_rate_sale AS EXCVENTA, ord.exchange_rate_purchase AS EXCCOMP FROM tbl_order ord INNER JOIN tbl_provider prov ON ord.provider_id=prov.id $query_estado $query_tipo ORDER BY ord.id DESC");
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['TIPOORDEN'] = $ROW["TIPOORDEN"];
            $ROWDATA['IDORDEN'] = $ROW["IDORDEN"];
            $ROWDATA['ORDNUMBER'] = $ROW["ORDNUMBER"];
            $ROWDATA['ORDESTADO'] = $ROW["ORDESTADO"];
            $ROWDATA['TIPOMON'] = $ROW["TIPOMON"];
            $ROWDATA['FECEMIS'] = date("d-m-Y", strtotime($ROW["FECEMIS"]));
            $ROWDATA['FECDEL'] = date("d-m-Y", strtotime($ROW["FECDEL"]));
            $ROWDATA['PROVID'] = $ROW["PROVID"];
            $ROWDATA['REQUESTID'] = $ROW["REQUESTID"];
            $ROWDATA['NOMPROV'] = $ROW["NOMPROV"];
            $ROWDATA['PAYDAYS'] = $ROW["PAYDAYS"];
            $ROWDATA['ACCNUM'] = $ROW["ACCNUM"];
            $ROWDATA['COTIZ'] = $ROW["COTIZ"];
            $ROWDATA['REQUES'] = $ROW["REQUES"];
            $ROWDATA['APROV'] = $ROW["APROV"];
            $ROWDATA['OBSERV'] = $ROW["OBSERV"];
            $ROWDATA['TOTCOMP'] = $ROW["TOTCOMP"];
            $ROWDATA['TOTIGV'] = $ROW["TOTIGV"];
            $ROWDATA['PORCIGV'] = (float) $ROW["TOTCOMP"] !== 0.0 ? ($ROW["TOTIGV"] / $ROW["TOTCOMP"]) : 0;
            $ROWDATA['TOTNET'] = $ROW["TOTNET"];
            $ROWDATA['EXCVENTA'] = $ROW["EXCVENTA"];
            $ROWDATA['EXCCOMP'] = $ROW["EXCCOMP"];
            array_push($json_data, $ROWDATA);
        }
    }
    echo json_encode($json_data);
    //echo json_encode(array("data" => $json_data));
} else {
    if ($ESTADO_ORDEN != "ALL") {
        $query_estado = " AND ord.status = $ESTADO_ORDEN ";
    }
    $query_tipo = $TIPO_ORDEN !== '' ? " AND ord.type = $TIPO_ORDEN " : '';
    $sqlStatement = $pdo->prepare("SELECT ord.id AS IDORDEN, ord.number AS ORDNUMBER, ord.status AS ORDESTADO, ord.currency AS TIPOMON, ord.issue_date AS FECEMIS, ord.delivery_date AS FECDEL, ord.provider_id AS PROVID, ord.provider_address_id AS PROVIDER_ADDRESS_ID, ord.provider_address AS PROVIDER_ADDRESS, ord.purchase_request_id AS REQUESTID, ord.payment_days AS PAYDAYS, ord.account_number AS ACCNUM, ord.quotation AS COTIZ, ord.requester AS REQUES, ord.approver AS APROV, ord.observation AS OBSERV, ord.total_purchase AS TOTCOMP, ord.total_tax AS TOTIGV, ord.total_net AS TOTNET, ord.exchange_rate_sale AS EXCVENTA, ord.exchange_rate_purchase AS EXCCOMP FROM tbl_order ord WHERE ord.id=:ORDID $query_estado $query_tipo");
    $sqlStatement->bindParam("ORDID", $FILTER_COTIZ, PDO::PARAM_STR);
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['IDORDEN'] = $ROW["IDORDEN"];
            $ROWDATA['ORDNUMBER'] = $ROW["ORDNUMBER"];
            $ROWDATA['ORDESTADO'] = $ROW["ORDESTADO"];
            $ROWDATA['TIPOMON'] = $ROW["TIPOMON"];
            $ROWDATA['FECEMIS'] = date("Y-m-d", strtotime($ROW["FECEMIS"]));
            $customStatement = $pdo->prepare("SELECT field_definition_id,field_value FROM tbl_custom_field_value WHERE entity='order' AND record_id=?");
            $customStatement->execute([(int) $ROW['IDORDEN']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value'];
            $ROWDATA['FECDEL'] = date("Y-m-d", strtotime($ROW["FECDEL"]));
            $ROWDATA['PROVID'] = $ROW["PROVID"];
            $ROWDATA['REQUESTID'] = $ROW["REQUESTID"];
            $ROWDATA['PAYDAYS'] = $ROW["PAYDAYS"];
            $ROWDATA['ACCNUM'] = $ROW["ACCNUM"];
            $ROWDATA['COTIZ'] = $ROW["COTIZ"];
            $ROWDATA['REQUES'] = $ROW["REQUES"];
            $ROWDATA['APROV'] = $ROW["APROV"];
            $ROWDATA['OBSERV'] = $ROW["OBSERV"];
            $ROWDATA['TOTCOMP'] = $ROW["TOTCOMP"];
            $ROWDATA['TOTIGV'] = $ROW["TOTIGV"];
            $ROWDATA['PORCIGV'] = (float) $ROW["TOTCOMP"] !== 0.0 ? ($ROW["TOTIGV"] / $ROW["TOTCOMP"]) * 100 : 0;
            $ROWDATA['TOTNET'] = $ROW["TOTNET"];
            $ROWDATA['EXCVENTA'] = $ROW["EXCVENTA"];
            $ROWDATA['EXCCOMP'] = $ROW["EXCCOMP"];
            array_push($json_data, $ROWDATA);
        }
    }
    echo json_encode($json_data);
}
