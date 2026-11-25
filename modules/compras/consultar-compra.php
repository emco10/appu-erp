<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');

$filter = $_POST['FILTER'] ?? '';
$status = $_POST['ESTADO'] ?? '';
if ($status !== 'ALL') $status = filter_var($status, FILTER_VALIDATE_INT);
if ($status === false || !is_string($filter)) {
    http_response_code(422);
    echo json_encode([]);
    return;
}

$type = match ($_POST['TIPO_ORDEN'] ?? '') {
    'COMPRA' => 1,
    'SERVICIO' => 2,
    default => null,
};
$listing = $filter === 'ALL';
if ($listing) {
    $sqlStatement = $pdo->prepare("SELECT ord.type AS TIPOORDEN, ord.document_subtype AS DOCUMENT_SUBTYPE, ord.id AS IDORDEN, ord.number AS ORDNUMBER, ord.supplier_reference AS SUPPLIER_REFERENCE, ord.status AS ORDESTADO, ord.currency AS TIPOMON, ord.issue_date AS FECEMIS, ord.delivery_date AS FECDEL, ord.provider_id AS PROVID, ord.purchase_order_id AS PURCHASE_ORDER_ID, prov.business_name AS NOMPROV, ord.payment_days AS PAYDAYS, ord.account_number AS ACCNUM, ord.quotation AS COTIZ, ord.requester AS REQUES, ord.approver AS APROV, ord.observation AS OBSERV, ord.total_purchase AS TOTCOMP, ord.total_tax AS TOTIGV, ord.total_net AS TOTNET, ord.exchange_rate_sale AS EXCVENTA, ord.exchange_rate_purchase AS EXCCOMP FROM tbl_purchase ord INNER JOIN tbl_provider prov ON ord.provider_id=prov.id WHERE (:all_status = 1 OR ord.status = :status) AND (:all_types = 1 OR ord.type = :type) ORDER BY ord.id DESC");
} else {
    $sqlStatement = $pdo->prepare("SELECT ord.type AS TIPOORDEN, ord.document_subtype AS DOCUMENT_SUBTYPE, ord.id AS IDORDEN, ord.number AS ORDNUMBER, ord.supplier_reference AS SUPPLIER_REFERENCE, ord.status AS ORDESTADO, ord.currency AS TIPOMON, ord.issue_date AS FECEMIS, ord.delivery_date AS FECDEL, ord.provider_id AS PROVID, ord.provider_address_id AS PROVIDER_ADDRESS_ID, ord.provider_address AS PROVIDER_ADDRESS, ord.purchase_order_id AS PURCHASE_ORDER_ID, ord.import_goods_receipt_id AS IMPORT_RECEIPT_ID, ord.payment_days AS PAYDAYS, ord.account_number AS ACCNUM, ord.quotation AS COTIZ, ord.requester AS REQUES, ord.approver AS APROV, ord.observation AS OBSERV, ord.total_purchase AS TOTCOMP, ord.total_tax AS TOTIGV, ord.total_net AS TOTNET, ord.exchange_rate_sale AS EXCVENTA, ord.exchange_rate_purchase AS EXCCOMP FROM tbl_purchase ord WHERE ord.id=:ORDID AND (:all_status = 1 OR ord.status = :status) AND (:all_types = 1 OR ord.type = :type)");
    $sqlStatement->bindParam('ORDID', $filter, PDO::PARAM_STR);
}
$allStatuses = (int) ($status === 'ALL');
$statusValue = $allStatuses ? 0 : $status;
$allTypes = (int) ($type === null);
$typeValue = $type ?? 0;
$sqlStatement->bindParam('all_status', $allStatuses, PDO::PARAM_INT);
$sqlStatement->bindParam('status', $statusValue, PDO::PARAM_INT);
$sqlStatement->bindParam('all_types', $allTypes, PDO::PARAM_INT);
$sqlStatement->bindParam('type', $typeValue, PDO::PARAM_INT);
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$json_data = [];

if ($listing) {
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['TIPOORDEN'] = $ROW["TIPOORDEN"];
            $ROWDATA['DOCUMENT_SUBTYPE'] = $ROW["DOCUMENT_SUBTYPE"];
            $ROWDATA['IDORDEN'] = $ROW["IDORDEN"];
            $ROWDATA['ORDNUMBER'] = $ROW["ORDNUMBER"];
            $ROWDATA['SUPPLIER_REFERENCE'] = $ROW["SUPPLIER_REFERENCE"];
            $ROWDATA['ORDESTADO'] = $ROW["ORDESTADO"];
            $ROWDATA['TIPOMON'] = $ROW["TIPOMON"];
            $ROWDATA['FECEMIS'] = date("d-m-Y", strtotime($ROW["FECEMIS"]));
            $ROWDATA['FECDEL'] = date("d-m-Y", strtotime($ROW["FECDEL"]));
            $ROWDATA['PROVID'] = $ROW["PROVID"];
            $ROWDATA['PURCHASE_ORDER_ID'] = $ROW["PURCHASE_ORDER_ID"];
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
} else {
    if ($rowsNumber > 0) {
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['IDORDEN'] = $ROW["IDORDEN"];
            $ROWDATA['TIPOORDEN'] = $ROW["TIPOORDEN"];
            $ROWDATA['DOCUMENT_SUBTYPE'] = $ROW["DOCUMENT_SUBTYPE"];
            $ROWDATA['ORDNUMBER'] = $ROW["ORDNUMBER"];
            $ROWDATA['SUPPLIER_REFERENCE'] = $ROW["SUPPLIER_REFERENCE"];
            $ROWDATA['ORDESTADO'] = $ROW["ORDESTADO"];
            $ROWDATA['TIPOMON'] = $ROW["TIPOMON"];
            $ROWDATA['FECEMIS'] = date("Y-m-d", strtotime($ROW["FECEMIS"]));
            $ROWDATA['FECDEL'] = date("Y-m-d", strtotime($ROW["FECDEL"]));
            $ROWDATA['PROVID'] = $ROW["PROVID"];
            $ROWDATA['PROVIDER_ADDRESS_ID'] = $ROW["PROVIDER_ADDRESS_ID"];
            $ROWDATA['PROVIDER_ADDRESS'] = $ROW["PROVIDER_ADDRESS"];
            $ROWDATA['PURCHASE_ORDER_ID'] = $ROW["PURCHASE_ORDER_ID"];
            $ROWDATA['IMPORT_RECEIPT_ID'] = $ROW["IMPORT_RECEIPT_ID"];
            $customStatement = $pdo->prepare("SELECT field_definition_id,field_value FROM tbl_custom_field_value WHERE entity='purchase' AND record_id=?");
            $customStatement->execute([(int) $ROW['IDORDEN']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) {
                $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value'];
            }
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
}
echo json_encode($json_data);
