<?php
require '../../global/connection.php';
$searchTerm = trim((string) ($_GET['NOM_PROV'] ?? $_GET['q'] ?? ''), "* ");
$includeInactive = (int) ($_GET['include_inactive'] ?? 0) === 1;
$sqlStatement = $pdo->prepare('SELECT id, code, business_name, active_status FROM tbl_provider WHERE ' . ($includeInactive ? '1=1' : 'active_status = 1') . ' AND (business_name LIKE :business_term OR code LIKE :code_term) ORDER BY business_name ASC LIMIT 100');
$term = '%' . $searchTerm . '%';
$sqlStatement->execute(['business_term' => $term, 'code_term' => $term]);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_PROV = $LST["id"];
        $NOM_PROV = $LST["business_name"];
        $active = (int) $LST['active_status'] === 1;
        $label = $NOM_PROV . ' | Código: ' . $LST['code'] . ($active ? '' : ' | Inactivo');
        $ROW = [ "id" => $ID_PROV, "label" => $label, "value" => $label, "code" => $LST['code'], "active_status" => $active ? 1 : 0 ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "id" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
