<?php
require '../../global/connection.php';

app_require_permission('inventory.view');

$conditions = [];
$params = [];
$warehouseId = filter_var($_REQUEST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$productId = filter_var($_REQUEST['product_id'] ?? null, FILTER_VALIDATE_INT);
$movementType = filter_var($_REQUEST['movement_type'] ?? null, FILTER_VALIDATE_INT);
$dateFrom = trim((string) ($_REQUEST['date_from'] ?? ''));
$dateTo = trim((string) ($_REQUEST['date_to'] ?? ''));
if ($warehouseId !== false && $warehouseId !== null && $warehouseId > 0) { $conditions[] = 'movement.warehouse_id = ?'; $params[] = (int) $warehouseId; }
if ($productId !== false && $productId !== null && $productId > 0) { $conditions[] = 'movement.product_id = ?'; $params[] = (int) $productId; }
if ($movementType !== false && $movementType !== null && in_array((int) $movementType, range(1, 8), true)) { $conditions[] = 'movement.type = ?'; $params[] = (int) $movementType; }
$validDate = static function (string $date): bool { $parsed = DateTime::createFromFormat('!Y-m-d', $date); return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date; };
if ($validDate($dateFrom)) { $conditions[] = 'movement.registration_date >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($validDate($dateTo)) { $conditions[] = 'movement.registration_date <= ?'; $params[] = $dateTo . ' 23:59:59'; }
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$statement = $pdo->prepare(
    "SELECT movement.id AS ID,
            movement.type AS TIPO,
            movement.quantity AS CANTIDAD,
            movement.observation AS OBSERVACION,
            movement.doc_reference AS DOC_REFERENCIA,
            movement.expiration_date AS FECVENC,
            movement.registration_date AS FECREG,
            product.code AS CODIGO_PRODUCTO,
            product.name AS NOMBRE_PRODUCTO,
            warehouse.name AS NOMBRE_ALMACEN,
            provider.business_name AS NOMBRE_PROVEEDOR,
            app_user.username AS NOMBRE_USUARIO
     FROM tbl_warehouse_movement movement
     JOIN tbl_product product ON product.id = movement.product_id
     JOIN tbl_warehouse warehouse ON warehouse.id = movement.warehouse_id
     LEFT JOIN tbl_provider provider ON provider.id = movement.provider_id
     JOIN tbl_user app_user ON app_user.id = movement.user_id
     {$where}
     ORDER BY movement.id DESC"
);
$statement->execute($params);

$movementLabels = [
    1 => 'Ingreso Almacen',
    2 => 'Ajuste Stock',
    9 => 'Salida de inventario',
    3 => 'Salida por venta',
    4 => 'Devolucion de cliente',
    5 => 'Reversion de documento',
    6 => 'Transferencia - salida',
    7 => 'Transferencia - ingreso',
    8 => 'Reversion de transferencia',
];
$data = [];
foreach ($statement->fetchAll() as $row) {
    $data[] = [
        'ID' => $row['ID'],
        'TIPO' => $movementLabels[(int) $row['TIPO']] ?? 'Movimiento',
        'CANTIDAD' => $row['CANTIDAD'],
        'FECREG' => date('d/m/Y H:i', strtotime($row['FECREG'])),
        'NOMBRE_USUARIO' => $row['NOMBRE_USUARIO'],
        'CODIGO_PRODUCTO' => $row['CODIGO_PRODUCTO'],
        'NOMBRE_PRODUCTO' => $row['NOMBRE_PRODUCTO'],
        'NOMBRE_ALMACEN' => $row['NOMBRE_ALMACEN'],
        'DOC_REFERENCIA' => $row['DOC_REFERENCIA'] ?: '-',
        'FECVENC' => $row['FECVENC'] ? date('d/m/Y', strtotime($row['FECVENC'])) : '-',
        'NOMBRE_PROVEEDOR' => $row['NOMBRE_PROVEEDOR'] ?: '-',
        'OBSERVACION' => $row['OBSERVACION'] ?: '-',
    ];
}

echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
