<?php
require '../../global/connection.php';
$searchTerm = trim((string) ($_GET['orden_nomprod'] ?? ''), "* ");
$query = 'SELECT id, code, barcode, name, brand FROM tbl_product WHERE active_status = 1 AND (name LIKE :term OR code LIKE :term OR barcode LIKE :term OR brand LIKE :term)';
$params = ['term' => '%' . $searchTerm . '%'];

if (isset($_GET['prov_id']) && $_GET['prov_id'] !== '') {
    $providerId = filter_var($_GET['prov_id'], FILTER_VALIDATE_INT);
    if ($providerId === false) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([['id' => '', 'value' => 'Proveedor inválido']]);
        exit;
    }
    // El catálogo actual de artículos es global; no se filtra por proveedor.
}
$query .= ' ORDER BY name ASC';
$sqlStatement = $pdo->prepare($query);
$sqlStatement->execute($params);
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_PROD = $LST["id"];
        $NOM_PROD = $LST["name"];
        $ROW = [ "id" => $ID_PROD, "value" => $LST['code'] . ' | ' . $NOM_PROD . ($LST['barcode'] !== '' ? ' | ' . $LST['barcode'] : '') ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "id" => "", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
