<?php
require '../../global/connection.php';

$conditions = [];
$params = [];
$orderNumber = trim($_POST['orden_nroo'] ?? '');
$provider = trim($_POST['orden_prov'] ?? '');
$dateFrom = trim($_POST['orden_fini'] ?? '');
$dateTo = trim($_POST['orden_ffin'] ?? '');

if ($orderNumber !== '') {
    $conditions[] = 'ord.number LIKE :order_number';
    $params['order_number'] = '%' . $orderNumber . '%';
}
if ($provider !== '') {
    $conditions[] = 'prov.business_name LIKE :provider';
    $params['provider'] = '%' . $provider . '%';
}
if ($dateFrom !== '' && $dateTo !== '') {
    $conditions[] = 'ord.issue_date BETWEEN :date_from AND :date_to';
    $params['date_from'] = $dateFrom;
    $params['date_to'] = $dateTo;
}

$COND_COMP = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sqlStatement = $pdo->prepare("SELECT ord.type AS TIPOORDEN, ord.id AS IDORDEN, ord.number AS ORDNUMBER, ord.status AS ORDESTADO, ord.currency AS TIPOMON, ord.issue_date AS FECEMIS, ord.delivery_date AS FECDEL, ord.provider_id AS PROVID, prov.business_name AS NOMPROV, ord.payment_days AS PAYDAYS, ord.account_number AS ACCNUM, ord.quotation AS COTIZ, ord.requester AS REQUES, ord.approver AS APROV, ord.observation AS OBSERV, ord.total_purchase AS TOTCOMP, ord.total_tax AS TOTIGV, ord.total_net AS TOTNET, ord.exchange_rate_sale AS EXCVENTA, ord.exchange_rate_purchase AS EXCCOMP FROM tbl_order ord INNER JOIN tbl_provider prov ON ord.provider_id=prov.id $COND_COMP ORDER BY ord.number ASC");
$sqlStatement->execute($params);
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
        $ROWDATA['TOTNET'] = number_format($ROW["TOTNET"], 2, '.','');
        $ROWDATA['EXCVENTA'] = $ROW["EXCVENTA"];
        $ROWDATA['EXCCOMP'] = $ROW["EXCCOMP"];
        array_push($json_data, $ROWDATA);
    }
}
echo json_encode($json_data);
