<?php
require '../../global/connection.php';
$conditions = [];
$params = [];
$quotationNumber = trim($_POST['cotiz_nroo'] ?? '');
$customer = trim($_POST['cotiz_client'] ?? '');
$dateFrom = trim($_POST['cotiz_fini'] ?? '');
$dateTo = trim($_POST['cotiz_ffin'] ?? '');

if ($quotationNumber !== '') {
    $conditions[] = 'tc.number LIKE :quotation_number';
    $params['quotation_number'] = '%' . $quotationNumber . '%';
}
if ($customer !== '') {
    $conditions[] = 'tc.name LIKE :customer';
    $params['customer'] = '%' . $customer . '%';
}
if ($dateFrom !== '' && $dateTo !== '') {
    $conditions[] = 'tc.date BETWEEN :date_from AND :date_to';
    $params['date_from'] = $dateFrom;
    $params['date_to'] = $dateTo;
}

$COND_COMP = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sqlStatement = $pdo->prepare("SELECT tc.id AS IDCOTIZ, tc.number AS NUMCOTIZ, tc.status AS ESTCOTIZ, tc.customer_id AS CLIID, tc.ruc AS CLIRUC, tc.name AS CLINOM, tc.address AS CLIADD, tc.reference AS CLIREF, tc.payment_days AS PAYDAYS, tc.delivery_date AS DELDATE, tc.currency AS CURRENCY, tc.discount_rate AS DESCRATE, tc.discount_value AS DESCVAL, tc.total_sub AS TOTSUB, tc.total_tax AS TOTTAX, tc.total_net AS TOTNETO, tc.seller_id AS SELLERID, tc.date AS FECHA FROM tbl_quotation tc $COND_COMP");
$sqlStatement->execute($params);
$rowsNumber = $sqlStatement->rowCount();
$json_data = array();
if ($rowsNumber > 0) {
    foreach ($sqlStatement as $ROW) {
        $ROWDATA['CLIENTRUC'] = $ROW["CLIRUC"];
        $ROWDATA['CODIGOID'] = $ROW["IDCOTIZ"];
        $ROWDATA['CODIGO'] = $ROW["NUMCOTIZ"];
        $ROWDATA['ESTADO'] = $ROW["ESTCOTIZ"];
        $ROWDATA['ESTADO_VAL'] = $ROW["ESTCOTIZ"] == 1 ? "Vigente" : "Anulado";
        $ROWDATA['CLIENTNAME'] = $ROW["CLINOM"];
        $ROWDATA['CLIENTADDR'] = $ROW["CLIADD"];
        $ROWDATA['CLIENTREFER'] = $ROW["CLIREF"];
        $ROWDATA['PAY_DAYS'] = $ROW["PAYDAYS"];
        $ROWDATA['DELIV_DATE'] = date("d/m/Y", strtotime($ROW["DELDATE"]));
        $ROWDATA['CURRENCY'] = $ROW["CURRENCY"];
        $ROWDATA['DESC_RATE'] = $ROW["DESCRATE"];
        $ROWDATA['DESC_VAL'] = $ROW["DESCVAL"];
        $ROWDATA['TOTAL_SUB'] = $ROW["TOTSUB"];
        $ROWDATA['TOTAL_TAX'] = $ROW["TOTTAX"];
        $ROWDATA['TOTAL_NET'] = $ROW["TOTNETO"];
        $ROWDATA['USER_ID'] = $ROW["SELLERID"];
        $ROWDATA['FECREG'] = date("d/m/Y", strtotime($ROW["FECHA"]));
        array_push($json_data, $ROWDATA);
    }
}
echo json_encode($json_data);
