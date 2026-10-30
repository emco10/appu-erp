<?php
require '../../global/connection.php';
$clientId = filter_var($_GET['client_id'] ?? '', FILTER_VALIDATE_INT);
if ($clientId !== false && $clientId !== null && $clientId > 0) {
    $statement = $pdo->prepare('SELECT client_id, business_name, trade_name, ruc, address, email, phone, cellphone, department_id, province_id, district_id, contact1_name, contact1_phone, contact2_name, contact2_phone, commission, default_currency, payment_days, credit_limit, price_list_id, sales_employee_id, (SELECT id FROM tbl_user WHERE employee_id=tbl_customer.sales_employee_id AND active_status=1 LIMIT 1) AS sales_user_id, COALESCE((SELECT factor FROM tbl_price_list WHERE id=tbl_customer.price_list_id AND active_status=1),1) AS price_list_factor FROM tbl_customer WHERE client_id = ? AND active_status = 1 LIMIT 1');
    $statement->execute([$clientId]);
    $customer = $statement->fetch();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['found' => (bool) $customer, 'customer' => $customer ?: null], JSON_UNESCAPED_UNICODE);
    exit;
}
$ruc = preg_replace('/\D+/', '', (string) ($_GET['ruc'] ?? ''));
if ($ruc !== '') {
    $statement = $pdo->prepare('SELECT client_id, business_name, trade_name, ruc, address, email, phone, cellphone, department_id, province_id, district_id, contact1_name, contact1_phone, contact2_name, contact2_phone, commission, default_currency, payment_days, credit_limit, price_list_id, sales_employee_id, (SELECT id FROM tbl_user WHERE employee_id=tbl_customer.sales_employee_id AND active_status=1 LIMIT 1) AS sales_user_id, COALESCE((SELECT factor FROM tbl_price_list WHERE id=tbl_customer.price_list_id AND active_status=1),1) AS price_list_factor FROM tbl_customer WHERE ruc = ? AND active_status = 1 LIMIT 1');
    $statement->execute([$ruc]);
    $customer = $statement->fetch();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['found' => (bool) $customer, 'customer' => $customer ?: null], JSON_UNESCAPED_UNICODE);
    exit;
}
$searchTerm = trim($_GET['cotiz_nomcliente'] ?? $_GET['q'] ?? '');
$searchTerm = trim($searchTerm, "* ");
$includeInactive = (int) ($_GET['include_inactive'] ?? 0) === 1;
$sqlStatement = $pdo->prepare('SELECT client_id, business_name, trade_name, ruc, active_status FROM tbl_customer WHERE ' . ($includeInactive ? '1=1' : 'active_status = 1') . ' AND (business_name LIKE ? OR trade_name LIKE ? OR ruc LIKE ?) ORDER BY business_name ASC LIMIT 100');
$sqlStatement->execute(array_fill(0, 3, '%' . $searchTerm . '%'));
$rows = $sqlStatement->fetchAll();
$DATA = array();
if ($rows) {
    foreach ($rows as $LST) {
        $ID_CLI = $LST["client_id"];
        $NOM_CLI = $LST["business_name"];
        $tradeName = trim((string) $LST["trade_name"]);
        $ruc = trim((string) $LST["ruc"]);
        $active = (int) $LST['active_status'] === 1;
        $label = $NOM_CLI . ($tradeName !== '' ? ' - ' . $tradeName : '') . ($ruc !== '' ? ' | RUC: ' . $ruc : '') . ($active ? '' : ' | Inactivo');
        $ROW = [ "id" => $ID_CLI, "label" => $label, "value" => $label, "ruc" => $ruc, "active_status" => $active ? 1 : 0 ];
        array_push($DATA, $ROW);
    }
} else {
    $ROW = [  "id" => "", "label" => "Sin resultados", "value" => "Sin resultados" ];
    array_push($DATA, $ROW);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($DATA);
