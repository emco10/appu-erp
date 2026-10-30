<?php
require '../../global/connection.php';

$conditions = [];
$params = [];
$purchaseNumber = trim($_POST['orden_nroo'] ?? '');
$provider = trim($_POST['orden_prov'] ?? '');
$dateFrom = trim($_POST['orden_fini'] ?? '');
$dateTo = trim($_POST['orden_ffin'] ?? '');

if ($purchaseNumber !== '') {
    $conditions[] = '(ord.number LIKE :purchase_number OR ord.supplier_reference LIKE :purchase_number)';
    $params['purchase_number'] = '%' . $purchaseNumber . '%';
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

$sqlStatement = $pdo->prepare("SELECT ord.id AS P_ID, ord.type AS P_TYPE, ord.document_subtype AS P_DOCUMENT_SUBTYPE, ord.number AS P_NUMBER, ord.supplier_reference AS P_SUPPLIER_REFERENCE, ord.status AS P_STATUS, ord.currency AS P_CURRENCY, ord.issue_date AS P_DATE, ord.provider_id AS PROV_ID, prov.business_name AS PROV_NAME, ord.requester AS P_REQUESTER, ord.approver AS P_APPROVER, ord.observation AS P_OBS, ord.total_net AS P_TOTAL FROM tbl_purchase ord INNER JOIN tbl_provider prov ON ord.provider_id=prov.id $COND_COMP ORDER BY ord.number ASC");

$sqlStatement->execute($params);
$rowsNumber = $sqlStatement->rowCount();
$json_data = array();

if ($rowsNumber > 0) { 
    foreach ($sqlStatement as $ROW) {
        
        $ROWDATA['ID'] = $ROW["P_ID"];
        $ROWDATA['NUMBER'] = $ROW["P_NUMBER"];
        $ROWDATA['DOCUMENT_SUBTYPE'] = $ROW["P_DOCUMENT_SUBTYPE"];
        $ROWDATA['SUPPLIER_REFERENCE'] = $ROW["P_SUPPLIER_REFERENCE"];
        $ROWDATA['DATE'] = date("d/m/Y", strtotime($ROW["P_DATE"]));
        $ROWDATA['PROV_NAME'] = $ROW["PROV_NAME"];
        $ROWDATA['CURRENCY'] = $ROW["P_CURRENCY"];
        $ROWDATA['TOTAL'] = number_format($ROW["P_TOTAL"], 2, '.','');
        $ROWDATA['STATUS'] = $ROW["P_STATUS"];
        
        array_push($json_data, $ROWDATA);
    }
}

echo json_encode($json_data);
