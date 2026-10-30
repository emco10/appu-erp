<?php
require '../../global/connection.php';
$searchTerm = trim((string) ($_GET['factura_num'] ?? ''), "* ");
$sqlStatement = $pdo->prepare('SELECT id, number FROM tbl_invoice WHERE number LIKE :term ORDER BY number ASC');
$sqlStatement->execute(['term' => '%' . $searchTerm . '%']);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_FAC = $LST["id"];
        $NUM_FAC = $LST["number"];
        $ROW = [ "key" => $ID_FAC, "value" => $NUM_FAC ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "key" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
