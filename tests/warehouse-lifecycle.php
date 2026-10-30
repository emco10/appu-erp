<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($max) => 0);
app_start_session();
require $root . '/global/connection.php';
require_once $root . '/global/warehouse-management.php';

$pdo->beginTransaction();
try {
    $suffix = strtoupper(substr(uniqid(), -6));
    $warehouseId = app_save_warehouse($pdo, null, 'QA-' . $suffix, 'Almacén QA', 'Dirección inicial');
    app_save_warehouse($pdo, $warehouseId, 'QA-' . $suffix, 'Almacén QA editado', 'Dirección actualizada');
    $warehouse = $pdo->query('SELECT name,address FROM tbl_warehouse WHERE id=' . $warehouseId)->fetch();
    if (($warehouse['name'] ?? '') !== 'Almacén QA editado' || ($warehouse['address'] ?? '') !== 'Dirección actualizada') throw new RuntimeException('La edición del almacén no persistió.');
    app_set_warehouse_active($pdo, $warehouseId, false);
    if ((int) $pdo->query('SELECT active_status FROM tbl_warehouse WHERE id=' . $warehouseId)->fetchColumn() !== 0) throw new RuntimeException('No se desactivó el almacén vacío.');
    app_set_warehouse_active($pdo, $warehouseId, true);

    $productId = (int) $pdo->query('SELECT id FROM tbl_product ORDER BY id LIMIT 1')->fetchColumn();
    if (!$productId) {
        $pdo->prepare('INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES (?,?,?,?,0,?,?,1)')->execute(['QA-WH-' . $suffix,'APPU','ARTÍCULO ALMACÉN QA','Temporal',10,'UND']);
        $productId = (int) $pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO tbl_warehouse_stock(warehouse_id,product_id,quantity,reserved_quantity) VALUES (?,?,5,2)')->execute([$warehouseId, $productId]);
    try { app_set_warehouse_active($pdo, $warehouseId, false); throw new RuntimeException('Se desactivó un almacén con stock y reserva.'); }
    catch (DomainException $expected) { if (!str_contains($expected->getMessage(), 'stock')) throw $expected; }

    $pdo->prepare('DELETE FROM tbl_warehouse_stock WHERE warehouse_id=?')->execute([$warehouseId]);
    $pdo->prepare('UPDATE tbl_warehouse SET active_status=0 WHERE id<>?')->execute([$warehouseId]);
    try { app_set_warehouse_active($pdo, $warehouseId, false); throw new RuntimeException('Se desactivó el último almacén activo.'); }
    catch (DomainException $expected) { if (!str_contains($expected->getMessage(), 'al menos un almacén activo')) throw $expected; }

    echo 'PASS warehouse create edit safe deactivate lifecycle' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL warehouse lifecycle: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
