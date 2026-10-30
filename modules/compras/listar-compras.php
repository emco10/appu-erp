<?php
require '../../global/connection.php';

$query_estado = "";
$tipo_orden = "";
$query_tipo = "";

if(isset($_POST["ESTADO"])){
    $estado = filter_var($_POST['ESTADO'], FILTER_VALIDATE_INT);
    if ($estado === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
    $query_estado = " WHERE status=$estado ";
}

/*
if(isset($_POST["orden_tipo"])){
    $tipo_orden = $_POST["orden_tipo"];
    switch ($tipo_orden) {
        case 'COMPRA':
            $tipo_orden = 1;
        break;
        case 'SERVICIO':
            $tipo_orden = 2;
        break;
    }
    if($query_estado != "" && $query_estado != null){
        $query_tipo = " AND type=".$tipo_orden." ";
    } else {
        $query_tipo = " WHERE type=".$tipo_orden." ";
    }
}
*/

$sqlStatement = $pdo->prepare("SELECT * FROM tbl_purchase $query_estado $query_tipo ORDER BY id DESC");
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$DATA = array();
if ($rowsNumber > 0) {
    array_push($DATA, ["id"=>"","text"=>"Seleccione una compra"]);
    while ($LST = $sqlStatement->fetch()) {
        $ID_COTIZ = $LST["id"];
        $NOM_COTIZ = $LST["number"] . " | " . (($LST['document_subtype'] ?? 'RESERVE') === 'RESERVE' ? 'Reserva' : 'Estándar') . " | ". date("d-m-Y",strtotime($LST["issue_date"]));
        $ROW = [
            "id" => $ID_COTIZ,
            "text" => $NOM_COTIZ
        ];
        array_push($DATA, $ROW);
    }
} else {
    array_push($DATA, ["id"=>"","text"=>"No se han encontrado compras"]);
}
echo json_encode($DATA);
