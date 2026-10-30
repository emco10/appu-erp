<?php
require '../../global/connection.php';
require_once '../../global/warehouse-management.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$json = static function (int $status, array $payload): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; };

if ($method === 'GET') {
    if (!app_can('management.view') && !app_can('inventory.view') && !app_can('sales.view') && !app_can('purchases.view') && !app_can('logistics.view')) app_require_permission('management.view');
    $rows = $pdo->query('SELECT id,code,name,address,active_status FROM tbl_warehouse ORDER BY active_status DESC,name')->fetchAll();
    foreach ($rows as &$row) $row['usage'] = app_warehouse_usage($pdo, (int) $row['id']);
    unset($row);
    $json(200, ['warehouses' => $rows]);
}

app_require_permission('management.manage');
$action = strtolower(trim((string) ($_POST['action'] ?? 'save')));
try {
    $pdo->beginTransaction();
    if ($action === 'save') {
        $warehouseId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $warehouseId = $warehouseId === false || $warehouseId < 1 ? null : (int) $warehouseId;
        $id = app_save_warehouse($pdo, $warehouseId, (string) ($_POST['code'] ?? ''), (string) ($_POST['name'] ?? ''), (string) ($_POST['address'] ?? ''));
        $pdo->commit();
        $json(200, ['message' => $warehouseId ? 'Almacén actualizado correctamente.' : 'Almacén creado correctamente.', 'warehouse_id' => $id]);
    }
    if ($action === 'toggle') {
        $warehouseId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($warehouseId === false || $warehouseId < 1) throw new InvalidArgumentException('Almacén inválido.');
        $active = (int) ($_POST['active_status'] ?? 0) === 1;
        $usage = app_set_warehouse_active($pdo, (int) $warehouseId, $active);
        $pdo->commit();
        $json(200, ['message' => $active ? 'Almacén activado correctamente.' : 'Almacén desactivado correctamente.', 'usage' => $usage]);
    }
    throw new InvalidArgumentException('Acción de almacén inválida.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $json(422, ['error' => $exception->getMessage()]);
}
