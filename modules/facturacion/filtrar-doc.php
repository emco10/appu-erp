<?php
require '../../global/connection.php';

$tipoDoc = $_POST['TIPO_DOC'] ?? '';
$defaultLoad = (int) ($_POST['defaultLoad'] ?? 0);
$FACT_NRO = trim($_POST['fact_nroo'] ?? '');
$FACT_CLIENT = trim($_POST['fact_client'] ?? '');
$docStatus = $_POST['fact_estado'] ?? '';
//$docVendedor = $_POST["fact_vendedor"];

$FACT_FINI = "";
$FACT_FFIN = "";

if (!empty($_POST['fact_fini'])) {
    $FACT_FINI = $_POST['fact_fini'];
}
if (!empty($_POST['fact_ffin'])) {
    $FACT_FFIN = $_POST['fact_ffin'];
}

$conditions = [];
$params = [];

if ($FACT_NRO !== '') {
    $conditions[] = '(ti.series LIKE :document_series OR ti.number LIKE :document_number)';
    $params['document_series'] = '%' . $FACT_NRO . '%';
    $params['document_number'] = '%' . $FACT_NRO . '%';
}

if ($FACT_CLIENT !== '') {
    $conditions[] = 'ti.name LIKE :customer';
    $params['customer'] = '%' . $FACT_CLIENT . '%';
}

if ($FACT_FINI !== '' && $FACT_FFIN !== '') {
    $conditions[] = 'ti.date BETWEEN :date_from AND :date_to';
    $params['date_from'] = $FACT_FINI;
    $params['date_to'] = $FACT_FFIN;
}

if ($docStatus !== '') {
    $validatedStatus = filter_var($docStatus, FILTER_VALIDATE_INT);
    if ($validatedStatus === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
    $conditions[] = 'ti.status = :status';
    $params['status'] = $validatedStatus;
}

$COND_COMP = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$table = "";

if ($tipoDoc == "INVOICE"){
    $table = "tbl_invoice";
}else if($tipoDoc == "RECEIPT"){
    $table = "tbl_receipt";
}else if($tipoDoc == "CREDIT_NOTE"){
    $table = "tbl_credit_note";
}else{
    echo false;
    return;
}

$limitString = "";

if ($defaultLoad == 1){
    $limitString = "LIMIT 50";
}

$sqlStatement = $pdo->prepare("SELECT ti.id AS DOC_ID, ti.series AS DOC_SERIES, ti.number AS DOC_NUMBER, ti.status AS DOC_STATUS_ID, ti.customer_id AS CUSTOMER_ID, ti.name AS CUSTOMER_NAME, ti.date AS DOC_DATE, ti.total_net AS DOC_TOTAL_NET, ti.seller_id AS SELLER_ID, (SELECT CONCAT(e.last_name_1, ' ', e.last_name_2, ', ', e.name) FROM tbl_user u JOIN tbl_employee e ON u.employee_id = e.id WHERE u.id = ti.seller_id) AS SELLER_NAME FROM " . $table . " ti " . $COND_COMP . " ORDER BY ti.id DESC " . $limitString);

$sqlStatement->execute($params);

$rowsNumber = $sqlStatement->rowCount();
$json_data = array();

if ($rowsNumber > 0) {
    foreach ($sqlStatement as $ROW) {

        $DOC['ID'] = $ROW["DOC_ID"];
        $DOC['SERIES_NUMBER'] = $ROW["DOC_SERIES"] . "-" . $ROW["DOC_NUMBER"];
        $DOC['DATE'] = date("d/m/Y", strtotime($ROW["DOC_DATE"]));
        $DOC['CUSTOMER'] = $ROW["CUSTOMER_NAME"];
        $DOC['TOTAL_NET'] = number_format($ROW["DOC_TOTAL_NET"], 2, '.','');

        $STATUS_ID = $ROW["DOC_STATUS_ID"];
        $DOC['STATUS_ID'] = (int) $STATUS_ID;
        
        if ($STATUS_ID == 1){
            $DOC['STATUS'] = "Vigente";
        }elseif($STATUS_ID == 2){
            $DOC['STATUS'] = "Anulado";
        }elseif($STATUS_ID == 3){
            $DOC['STATUS'] = "Pendiente de Pago";
        }elseif($STATUS_ID == 4){
            $DOC['STATUS'] = "Cancelado";
        }

        $DOC['SELLER_NAME'] = $ROW["SELLER_NAME"];
        
        /*
        $DOC['SELLER_ID'] = $ROW["SELLERID"];
        $DOC['CUSTOMER_ID'] = $ROW["CUSTOMER_ID"];
        */

        array_push($json_data, $DOC);
    }
}

echo json_encode($json_data);
