<?php
require '../../global/connection.php';
$searchTerm = '%' . trim($_GET['NOM_VEND'] ?? '') . '%';
$sqlStatement = $pdo->prepare("SELECT CONCAT(te.name, ' ', te.last_name_1, ' ', te.last_name_2) AS NOM_VEND, tu.id AS ID_USER
    FROM tbl_user tu INNER JOIN tbl_employee te ON tu.employee_id=te.id
    WHERE tu.active_status = 1 AND (te.name LIKE :name OR te.last_name_1 LIKE :last_name_1 OR te.last_name_2 LIKE :last_name_2)
    ORDER BY te.name ASC");
$sqlStatement->execute([
    'name' => $searchTerm,
    'last_name_1' => $searchTerm,
    'last_name_2' => $searchTerm,
]);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_VEND = $LST["ID_USER"];
        $NOM_VEND = $LST["NOM_VEND"];
        $ROW = [ "id" => $ID_VEND, "value" => $NOM_VEND ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "id" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
