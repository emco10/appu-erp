<?php
require '../../global/connection.php';
$searchTerm = trim($_GET['orden_cotizacion'] ?? '');
$sqlStatement = $pdo->prepare('SELECT id, number FROM tbl_quotation WHERE number LIKE :term ORDER BY number ASC');
$sqlStatement->execute(['term' => '%' . $searchTerm . '%']);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_COTIZ = $LST["id"];
        $NOM_COTIZ = $LST["number"];
        //$NOM_COTIZ = $LST["number"] . " | ". date("d-m-Y",strtotime($LST["registration_date"]));
        $ROW = [ "key" => $ID_COTIZ, "value" => $NOM_COTIZ ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "key" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
