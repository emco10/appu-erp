<?php
require '../../global/connection.php';
$searchTerm = trim($_GET['ORDEN_NRO'] ?? '');
$sqlStatement = $pdo->prepare('SELECT id, number FROM tbl_order WHERE number LIKE :term ORDER BY number ASC');
$sqlStatement->execute(['term' => '%' . $searchTerm . '%']);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_ORDEN = $LST["id"];
        //$NUM_ORDEN = $LST["number"] . " | ". date("d-m-Y",strtotime($LST["issue_date"]));
        $NUM_ORDEN = $LST["number"];
        $ROW = [ "key" => $ID_ORDEN, "value" => $NUM_ORDEN ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "key" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
