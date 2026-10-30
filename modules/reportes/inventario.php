<?php
require '../../global/connection.php';

header('Content-Type: application/json; charset=utf-8');

$warehouseId = filter_var($_GET['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$warehouseId = $warehouseId !== false && $warehouseId !== null && $warehouseId > 0 ? (int) $warehouseId : null;

$summaryStatement = $warehouseId
    ? $pdo->prepare("SELECT COUNT(*) AS product_count,
            COALESCE(SUM(CASE WHEN COALESCE(stock.quantity, 0) > 0 THEN 1 ELSE 0 END), 0) AS in_stock_count,
            COALESCE(SUM(CASE WHEN COALESCE(stock.quantity, 0) <= 0 THEN 1 ELSE 0 END), 0) AS zero_stock_count,
            COALESCE(SUM(COALESCE(stock.quantity, 0)), 0) AS total_units
     FROM tbl_product product
     LEFT JOIN tbl_warehouse_stock stock ON stock.product_id = product.id AND stock.warehouse_id = ?
     WHERE product.active_status = 1")
    : $pdo->prepare("SELECT COUNT(*) AS product_count,
            COALESCE(SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END), 0) AS in_stock_count,
            COALESCE(SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END), 0) AS zero_stock_count,
            COALESCE(SUM(stock_quantity), 0) AS total_units
     FROM tbl_product
     WHERE active_status = 1");
$summaryStatement->execute($warehouseId ? [$warehouseId] : []);
$stockSummary = $summaryStatement->fetch();
$valueStatement=$warehouseId?$pdo->prepare('SELECT COALESCE(SUM(inventory_value),0) FROM tbl_warehouse_stock WHERE warehouse_id=?'):$pdo->prepare('SELECT COALESCE(SUM(inventory_value),0) FROM tbl_warehouse_stock');$valueStatement->execute($warehouseId?[$warehouseId]:[]);$stockSummary['total_inventory_value']=(float)$valueStatement->fetchColumn();

$stockStatement = $warehouseId
    ? $pdo->prepare("SELECT product.id, product.code, product.brand, product.name,
            COALESCE(stock.quantity, 0) AS stock_quantity,COALESCE(stock.average_unit_cost,0) AS average_unit_cost,COALESCE(stock.inventory_value,0) AS inventory_value, product.unit_value
     FROM tbl_product product
     LEFT JOIN tbl_warehouse_stock stock ON stock.product_id = product.id AND stock.warehouse_id = ?
     WHERE product.active_status = 1
     ORDER BY stock_quantity ASC, product.name ASC LIMIT 500")
    : $pdo->prepare("SELECT product.id,product.code,product.brand,product.name,product.stock_quantity,product.unit_value,COALESCE(SUM(stock.inventory_value),0) AS inventory_value,IF(product.stock_quantity>0,COALESCE(SUM(stock.inventory_value),0)/product.stock_quantity,0) AS average_unit_cost FROM tbl_product product LEFT JOIN tbl_warehouse_stock stock ON stock.product_id=product.id WHERE product.active_status=1 GROUP BY product.id,product.code,product.brand,product.name,product.stock_quantity,product.unit_value ORDER BY product.stock_quantity ASC,product.name ASC LIMIT 500");
$stockStatement->execute($warehouseId ? [$warehouseId] : []);

$movementSql = "SELECT movement.registration_date, movement.type, movement.quantity,movement.inventory_value_delta,
            movement.observation, movement.doc_reference,
            product.code AS product_code, product.name AS product_name
     FROM tbl_warehouse_movement movement
     LEFT JOIN tbl_product product ON product.id = movement.product_id
     WHERE movement.registration_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)" . ($warehouseId ? " AND movement.warehouse_id = ?" : "") . "
     ORDER BY movement.registration_date DESC, movement.id DESC
     LIMIT 100";
$movementStatement = $pdo->prepare($movementSql);
$movementStatement->execute($warehouseId ? [$warehouseId] : []);

$expirySql = "SELECT lot.expiration_date, lot.quantity, lot.lot_number,
            product.code AS product_code, product.name AS product_name,
            warehouse.code AS warehouse_code, warehouse.name AS warehouse_name
     FROM tbl_product_lot lot
     JOIN tbl_product product ON product.id = lot.product_id
     JOIN tbl_warehouse warehouse ON warehouse.id = lot.warehouse_id
     WHERE lot.quantity > 0 AND lot.expiration_date IS NOT NULL
       AND lot.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)" . ($warehouseId ? " AND lot.warehouse_id = ?" : "") . "
     ORDER BY lot.expiration_date ASC, lot.id DESC
     LIMIT 100";
$expiryStatement = $pdo->prepare($expirySql);
$expiryStatement->execute($warehouseId ? [$warehouseId] : []);

$warehouses = $pdo->query("SELECT id, code, name FROM tbl_warehouse WHERE active_status = 1 ORDER BY name")->fetchAll();
$integrityIssues = $pdo->query("SELECT product.id,product.code,product.name,product.stock_quantity AS master_quantity,COALESCE(SUM(stock.quantity),0) AS warehouse_quantity,COALESCE(SUM(stock.reserved_quantity),0) AS reserved_quantity FROM tbl_product product LEFT JOIN tbl_warehouse_stock stock ON stock.product_id=product.id GROUP BY product.id,product.code,product.name,product.stock_quantity HAVING ABS(product.stock_quantity-COALESCE(SUM(stock.quantity),0))>0.000001 OR COALESCE(SUM(stock.quantity),0)<0 OR COALESCE(SUM(stock.reserved_quantity),0)>COALESCE(SUM(stock.quantity),0) ORDER BY product.name LIMIT 200")->fetchAll();

echo json_encode([
    'summary' => $stockSummary,
    'stock' => $stockStatement->fetchAll(),
    'movements' => $movementStatement->fetchAll(),
    'expirations' => $expiryStatement->fetchAll(),
    'warehouses' => $warehouses,
    'warehouse_id' => $warehouseId,
    'movement_days' => 90,
    'expiration_days' => 180,
    'integrity_issues' => $integrityIssues,
], JSON_UNESCAPED_UNICODE);
