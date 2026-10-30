<?php
require '../../global/connection.php';

$query_adic = "";

if(isset($_POST["ESTADO"])){
    $estado = filter_var($_POST['ESTADO'], FILTER_VALIDATE_INT);
    if ($estado === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
    $query_adic = " WHERE status=$estado ";
}

$sqlStatement = $pdo->prepare("SELECT * FROM tbl_invoice $query_adic ORDER BY id DESC");
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$DATA = array();

if ($rowsNumber > 0) {
    array_push($DATA, ["id"=>"","text"=>"Seleccione una factura"]);
    while ($LST = $sqlStatement->fetch()) {
        $ID_FAC = $LST["id"];
        $typeLabel = ($LST['document_subtype'] ?? 'STANDARD') === 'RESERVE' ? 'Reserva' : (($LST['document_subtype'] ?? 'STANDARD') === 'ADVANCE' ? 'Anticipo' : 'Estándar');
        $NOM_FAC = $LST["series"] . "-" . $LST["number"] . " | " . $typeLabel . " | ". date("d-m-Y",strtotime($LST["date"]));
        $ROW = [
            "id" => $ID_FAC,
            "text" => $NOM_FAC
        ];
        array_push($DATA, $ROW);
    }
} else {
    array_push($DATA, ["id"=>"","text"=>"No se han encontrado facturas"]);
}

echo json_encode($DATA);
