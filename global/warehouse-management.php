<?php

function app_warehouse_usage(PDO $pdo, int $warehouseId): array
{
    $stock = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) quantity,COALESCE(SUM(reserved_quantity),0) reserved FROM tbl_warehouse_stock WHERE warehouse_id=?');
    $stock->execute([$warehouseId]);
    $totals = $stock->fetch() ?: ['quantity' => 0, 'reserved' => 0];
    $lots = $pdo->prepare('SELECT COUNT(*) lots FROM tbl_product_lot WHERE warehouse_id=? AND (quantity<>0 OR reserved_quantity<>0)');
    $lots->execute([$warehouseId]);
    $orders = $pdo->prepare("SELECT COUNT(*) FROM tbl_sales_order WHERE warehouse_id=? AND status NOT IN ('Anulada','Atendida','Cerrada','Cancelada')");
    $orders->execute([$warehouseId]);
    $quotations = $pdo->prepare('SELECT COUNT(*) FROM tbl_quotation WHERE warehouse_id=? AND status IN (1,3)');
    $quotations->execute([$warehouseId]);
    return [
        'stock_quantity' => round((float) $totals['quantity'], 3),
        'reserved_quantity' => round((float) $totals['reserved'], 3),
        'active_lots' => (int) $lots->fetchColumn(),
        'open_sales_orders' => (int) $orders->fetchColumn(),
        'open_quotations' => (int) $quotations->fetchColumn(),
    ];
}

function app_save_warehouse(PDO $pdo, ?int $warehouseId, string $code, string $name, string $address): int
{
    $code = strtoupper(trim($code));
    $name = trim($name);
    $address = trim($address);
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/', $code)) throw new InvalidArgumentException('El código debe tener entre 2 y 30 caracteres: letras, números, guion o guion bajo.');
    if ($name === '' || mb_strlen($name) > 150) throw new InvalidArgumentException('Ingresa un nombre de almacén válido.');
    if (mb_strlen($address) > 300) throw new InvalidArgumentException('La dirección supera los 300 caracteres.');
    $duplicate = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE code=? AND id<>? LIMIT 1');
    $duplicate->execute([$code, $warehouseId ?: 0]);
    if ($duplicate->fetchColumn()) throw new DomainException('Ya existe un almacén con ese código.');
    if ($warehouseId) {
        $exists = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id=? FOR UPDATE');
        $exists->execute([$warehouseId]);
        if (!$exists->fetchColumn()) throw new RuntimeException('El almacén no existe.');
        $pdo->prepare('UPDATE tbl_warehouse SET code=?,name=?,address=? WHERE id=?')->execute([$code, $name, $address, $warehouseId]);
        return $warehouseId;
    }
    $pdo->prepare('INSERT INTO tbl_warehouse(code,name,address) VALUES (?,?,?)')->execute([$code, $name, $address]);
    return (int) $pdo->lastInsertId();
}

function app_set_warehouse_active(PDO $pdo, int $warehouseId, bool $active): array
{
    if ($warehouseId < 1) throw new InvalidArgumentException('Almacén inválido.');
    $warehouse = $pdo->prepare('SELECT id,active_status FROM tbl_warehouse WHERE id=? FOR UPDATE');
    $warehouse->execute([$warehouseId]);
    if (!$warehouse->fetch()) throw new RuntimeException('El almacén no existe.');
    $usage = app_warehouse_usage($pdo, $warehouseId);
    if (!$active) {
        $activeCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_warehouse WHERE active_status=1')->fetchColumn();
        if ($activeCount <= 1) throw new DomainException('Debe permanecer al menos un almacén activo.');
        $blocks = [];
        if (abs($usage['stock_quantity']) > 0.0001) $blocks[] = 'stock ' . number_format($usage['stock_quantity'], 3, '.', '');
        if (abs($usage['reserved_quantity']) > 0.0001) $blocks[] = 'reservado ' . number_format($usage['reserved_quantity'], 3, '.', '');
        if ($usage['active_lots'] > 0) $blocks[] = $usage['active_lots'] . ' lote(s) con saldo';
        if ($usage['open_sales_orders'] > 0) $blocks[] = $usage['open_sales_orders'] . ' orden(es) de venta abierta(s)';
        if ($usage['open_quotations'] > 0) $blocks[] = $usage['open_quotations'] . ' cotización(es) vigente(s)';
        if ($blocks) throw new DomainException('No se puede desactivar: ' . implode(', ', $blocks) . '. Traslada o cierra estas operaciones primero.');
    }
    $pdo->prepare('UPDATE tbl_warehouse SET active_status=? WHERE id=?')->execute([$active ? 1 : 0, $warehouseId]);
    return $usage;
}
