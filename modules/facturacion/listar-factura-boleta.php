<?php
require '../../global/connection.php';

$query_adic = "";
$queryParams = [];

if(isset($_POST["ESTADO"])){
    $estado = filter_var($_POST['ESTADO'], FILTER_VALIDATE_INT);
    if ($estado === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
    $query_adic = ' WHERE status = ? ';
    $queryParams = [$estado, $estado];
}

$sqlStatement = $pdo->prepare("(SELECT id, 'invoice' AS document_type, series, number, date, registration_date FROM tbl_invoice $query_adic)
    UNION ALL
    (SELECT id, 'receipt' AS document_type, series, number, date, registration_date FROM tbl_receipt $query_adic)
    ORDER BY registration_date DESC");

$sqlStatement->execute($queryParams);
$rowsNumber = $sqlStatement->rowCount();
$DATA = array();

if ($rowsNumber > 0) {
    array_push($DATA, ["id"=>"","text"=>"Seleccione una factura o boleta"]);
    while ($LST = $sqlStatement->fetch()) {
        $ID_FAC = ($LST["document_type"] === 'receipt' ? 'B:' : 'F:') . $LST["id"];
        $NOM_FAC = $LST["series"] . "-" . $LST["number"] . " | ". date("d-m-Y",strtotime($LST["date"]));
        $ROW = [
            "id" => $ID_FAC,
            "text" => $NOM_FAC,
            "document_type" => $LST["document_type"],
            "document_id" => (int) $LST["id"]
        ];
        array_push($DATA, $ROW);
    }
} else {
    array_push($DATA, ["id"=>"","text"=>"No se han encontrado documentos"]);
}

echo json_encode($DATA);
