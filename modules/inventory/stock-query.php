<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('inventory.view');
$warehouseId = filter_var($_GET['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);
$expiringDays = filter_var($_GET['expiring_days'] ?? null, FILTER_VALIDATE_INT);
foreach(['warehouse_id'=>$warehouseId,'product_id'=>$productId,'expiring_days'=>$expiringDays] as $name=>$value){$raw=$_GET[$name]??'';if($raw!==''&&($value===false||$value===null||$value<0)){http_response_code(422);echo json_encode(['error'=>'Los filtros de inventario deben ser números enteros válidos.'],JSON_UNESCAPED_UNICODE);exit;}}
$lotWhere = ['lot.quantity > 0']; $stockWhere = ['stock.quantity > 0']; $params = []; $stockParams = [];
if ($warehouseId !== false && $warehouseId !== null && $warehouseId > 0) { $lotWhere[] = 'lot.warehouse_id = ?'; $params[] = $warehouseId; $stockWhere[] = 'stock.warehouse_id = ?'; $stockParams[] = $warehouseId; }
if ($productId !== false && $productId !== null && $productId > 0) { $lotWhere[] = 'lot.product_id = ?'; $params[] = $productId; $stockWhere[] = 'stock.product_id = ?'; $stockParams[] = $productId; }
if ($expiringDays !== false && $expiringDays !== null && $expiringDays >= 0) { $lotWhere[] = 'lot.expiration_date IS NOT NULL AND lot.expiration_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) $expiringDays . ' DAY)'; $stockWhere[] = '1 = 0'; }
$query = '(SELECT lot.id, warehouse.code AS warehouse_code, warehouse.name AS warehouse_name, product.code AS product_code, product.name AS product_name, lot.lot_number, lot.expiration_date, lot.quantity, lot.reserved_quantity, (lot.quantity - lot.reserved_quantity) AS available_quantity, stock.average_unit_cost, ROUND(lot.quantity*stock.average_unit_cost,4) AS inventory_value FROM tbl_product_lot lot JOIN tbl_warehouse warehouse ON warehouse.id=lot.warehouse_id JOIN tbl_product product ON product.id=lot.product_id JOIN tbl_warehouse_stock stock ON stock.warehouse_id=lot.warehouse_id AND stock.product_id=lot.product_id WHERE ' . implode(' AND ', $lotWhere) . ')
UNION ALL
(SELECT 0 AS id, warehouse.code AS warehouse_code, warehouse.name AS warehouse_name, product.code AS product_code, product.name AS product_name, \'\' AS lot_number, NULL AS expiration_date, stock.quantity, stock.reserved_quantity, (stock.quantity - stock.reserved_quantity) AS available_quantity, stock.average_unit_cost, stock.inventory_value FROM tbl_warehouse_stock stock JOIN tbl_warehouse warehouse ON warehouse.id=stock.warehouse_id JOIN tbl_product product ON product.id=stock.product_id WHERE ' . implode(' AND ', $stockWhere) . ' AND NOT EXISTS (SELECT 1 FROM tbl_product_lot positive_lot WHERE positive_lot.warehouse_id=stock.warehouse_id AND positive_lot.product_id=stock.product_id AND positive_lot.quantity > 0))
ORDER BY expiration_date IS NULL, expiration_date, product_name';
$statement = $pdo->prepare($query); $statement->execute(array_merge($params, $stockParams));
$applicationWhere = ['application.status = \'ACTIVE\'']; $applicationParams = [];
if ($warehouseId !== false && $warehouseId !== null && $warehouseId > 0) { $applicationWhere[] = 'application.warehouse_id = ?'; $applicationParams[] = $warehouseId; }
if ($productId !== false && $productId !== null && $productId > 0) { $applicationWhere[] = 'application.product_id = ?'; $applicationParams[] = $productId; }
$applicationStatement = $pdo->prepare(
    'SELECT application.registration_date, application.source_type, application.source_id,
            warehouse.code AS warehouse_code, warehouse.name AS warehouse_name,
            product.code AS product_code, product.name AS product_name,
            lot.lot_number, lot.expiration_date, application.quantity
     FROM tbl_inventory_lot_application application
     JOIN tbl_warehouse warehouse ON warehouse.id = application.warehouse_id
     JOIN tbl_product product ON product.id = application.product_id
     JOIN tbl_product_lot lot ON lot.id = application.lot_id
     WHERE ' . implode(' AND ', $applicationWhere) . '
     ORDER BY application.registration_date DESC, application.id DESC
     LIMIT 200'
);
$applicationStatement->execute($applicationParams);
$warehouses = $pdo->query("SELECT id, code, name FROM tbl_warehouse WHERE active_status=1 ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, code, name FROM tbl_product WHERE active_status=1 ORDER BY name")->fetchAll();
echo json_encode(['rows' => $statement->fetchAll(), 'lot_consumptions' => $applicationStatement->fetchAll(), 'warehouses' => $warehouses, 'products' => $products], JSON_UNESCAPED_UNICODE);
