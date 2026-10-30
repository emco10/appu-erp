<?php

const STOCK_MOVEMENT_ENTRY = 1;
const STOCK_MOVEMENT_ADJUSTMENT = 2;
const STOCK_MOVEMENT_SALE = 3;
const STOCK_MOVEMENT_CUSTOMER_RETURN = 4;
const STOCK_MOVEMENT_DOCUMENT_REVERSAL = 5;
const STOCK_MOVEMENT_TRANSFER_OUT = 6;
const STOCK_MOVEMENT_TRANSFER_IN = 7;
const STOCK_MOVEMENT_COST_ADJUSTMENT = 8;
const STOCK_MOVEMENT_ISSUE = 9;

function warehouse_credit_note_returns_stock(string $reason): bool
{
    return in_array($reason, ['DEVOLUCION_TOTAL', 'DEVOLUCION_ITEM'], true);
}

/**
 * Consume lotes usando FEFO (primero vence, primero sale) y deja trazabilidad
 * para que una anulacion pueda devolver exactamente las cantidades al lote.
 */
function warehouse_allocate_lots(PDO $pdo, int $productId, int $warehouseId, float $quantity, ?string $sourceType, ?int $sourceId, ?int $userId): void
{
    if ($quantity <= 0 || $sourceType === null || $sourceId === null) {
        return;
    }

    $statement = $pdo->prepare(
        'SELECT id, quantity, reserved_quantity
         FROM tbl_product_lot
         WHERE product_id = ? AND warehouse_id = ? AND quantity - reserved_quantity > 0
         ORDER BY expiration_date IS NULL, expiration_date, id
         FOR UPDATE'
    );
    $statement->execute([$productId, $warehouseId]);
    $remaining = $quantity;
    $lots = $statement->fetchAll();
    if (!$lots) {
        $lotHistory = $pdo->prepare('SELECT COUNT(*) FROM tbl_product_lot WHERE product_id = ?');
        $lotHistory->execute([$productId]);
        if ((int) $lotHistory->fetchColumn() === 0) return;
    }
    $available = 0.0;
    foreach ($lots as $lot) {
        $available += max(0.0, (float) $lot['quantity'] - (float) $lot['reserved_quantity']);
    }
    if ($available + 0.000001 < $quantity) {
        throw new DomainException('La salida supera la existencia disponible de los lotes del almacén seleccionado.');
    }

    $update = $pdo->prepare('UPDATE tbl_product_lot SET quantity = quantity - ? WHERE id = ? AND quantity - reserved_quantity >= ?');
    $insert = $pdo->prepare(
        'INSERT INTO tbl_inventory_lot_application (source_type, source_id, product_id, warehouse_id, lot_id, quantity, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($lots as $lot) {
        if ($remaining <= 0.000001) {
            break;
        }
        $lotAvailable = max(0.0, (float) $lot['quantity'] - (float) $lot['reserved_quantity']);
        $allocated = min($remaining, $lotAvailable);
        if ($allocated <= 0) {
            continue;
        }
        $update->execute([$allocated, (int) $lot['id'], $allocated]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('No se pudo actualizar el lote seleccionado.');
        }
        $insert->execute([$sourceType, $sourceId, $productId, $warehouseId, (int) $lot['id'], $allocated, $userId]);
        $remaining -= $allocated;
    }
    if ($remaining > 0.000001) {
        throw new RuntimeException('No se pudo completar la asignacion de lotes.');
    }
}

function warehouse_restore_lots_for_source(PDO $pdo, int $productId, int $warehouseId, ?string $sourceType, ?int $sourceId): float
{
    if ($sourceType === null || $sourceId === null) {
        return 0.0;
    }
    $statement = $pdo->prepare(
        'SELECT id, lot_id, quantity
         FROM tbl_inventory_lot_application
         WHERE source_type = ? AND source_id = ? AND product_id = ? AND warehouse_id = ? AND status = \'ACTIVE\'
         FOR UPDATE'
    );
    $statement->execute([$sourceType, $sourceId, $productId, $warehouseId]);
    $applications = $statement->fetchAll();
    if (!$applications) {
        return 0.0;
    }
    $lotUpdate = $pdo->prepare('UPDATE tbl_product_lot SET quantity = quantity + ? WHERE id = ?');
    $applicationUpdate = $pdo->prepare("UPDATE tbl_inventory_lot_application SET status = 'REVERSED', reversed_at = NOW() WHERE id = ?");
    $restored = 0.0;
    foreach ($applications as $application) {
        $quantity = (float) $application['quantity'];
        $lotUpdate->execute([$quantity, (int) $application['lot_id']]);
        $applicationUpdate->execute([(int) $application['id']]);
        $restored += $quantity;
    }
    return $restored;
}

function warehouse_apply_lot_delta(PDO $pdo, int $productId, int $warehouseId, string $lotNumber, ?string $expirationDate, float $quantityDelta): void
{
    $lotNumber = trim($lotNumber);
    if ($lotNumber === '') {
        return;
    }
    $pdo->prepare('INSERT IGNORE INTO tbl_product_lot (warehouse_id, product_id, lot_number, expiration_date, quantity) VALUES (?, ?, ?, ?, 0)')->execute([$warehouseId, $productId, $lotNumber, $expirationDate ?: null]);
    $update = $pdo->prepare('UPDATE tbl_product_lot SET quantity = quantity + ?, expiration_date = COALESCE(?, expiration_date) WHERE warehouse_id = ? AND product_id = ? AND lot_number = ? AND quantity + ? >= 0');
    $update->execute([$quantityDelta, $expirationDate ?: null, $warehouseId, $productId, $lotNumber, $quantityDelta]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Stock insuficiente en el lote seleccionado.');
    }
}

function warehouse_apply_stock_delta(
    PDO $pdo,
    int $productId,
    $quantityDelta,
    int $movementType,
    int $userId,
    string $observation,
    ?string $sourceType = null,
    ?int $sourceId = null,
    ?string $documentReference = null,
    ?int $providerId = null,
    int $warehouseId = 1,
    ?float $inventoryValueDelta = null,
    ?int $documentLineId = null
): void {
    $quantityDelta = (float) $quantityDelta;

    if ($productId <= 0 || $warehouseId <= 0 || $quantityDelta == 0.0) {
        throw new InvalidArgumentException('Movimiento de inventario invalido.');
    }

    $productStatement = $pdo->prepare('SELECT id, stock_quantity FROM tbl_product WHERE id = ? FOR UPDATE');
    $productStatement->execute([$productId]);
    $product = $productStatement->fetch();
    if (!$product) {
        throw new RuntimeException('Producto inexistente.');
    }

    // Compatibilidad con artículos antiguos: si aún no tienen desglose por
    // almacén, inicializamos el almacén principal con su stock histórico.
    $warehouseCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_warehouse_stock WHERE product_id = ?');
    $warehouseCount->execute([$productId]);
    if ((int) $warehouseCount->fetchColumn() === 0 && $warehouseId === 1) {
        $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity) VALUES (1, ?, ?)')->execute([$productId, (float) $product['stock_quantity']]);
    }
    $restoredLots = 0.0;
    if ($quantityDelta < 0 && $movementType !== STOCK_MOVEMENT_DOCUMENT_REVERSAL && $sourceType !== null && $sourceId !== null) {
        warehouse_allocate_lots($pdo, $productId, $warehouseId, abs($quantityDelta), $sourceType, $sourceId, $userId);
    } elseif ($quantityDelta > 0 && $movementType === STOCK_MOVEMENT_DOCUMENT_REVERSAL) {
        $restoredLots = warehouse_restore_lots_for_source($pdo, $productId, $warehouseId, $sourceType, $sourceId);
    }

    $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, 0)')->execute([$warehouseId, $productId]);
    $valuationStatement=$pdo->prepare('SELECT quantity,inventory_value,average_unit_cost FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=? FOR UPDATE');$valuationStatement->execute([$warehouseId,$productId]);$valuation=$valuationStatement->fetch();
    if ($inventoryValueDelta === null) {
        if ($quantityDelta < 0) {
            $inventoryValueDelta = round($quantityDelta * (float)$valuation['average_unit_cost'], 4);
        } elseif ($movementType===STOCK_MOVEMENT_CUSTOMER_RETURN) {
            $inventoryValueDelta=round($quantityDelta*(float)$valuation['average_unit_cost'],4);
        } elseif (in_array($movementType,[STOCK_MOVEMENT_DOCUMENT_REVERSAL,STOCK_MOVEMENT_TRANSFER_IN],true) && $sourceType !== null && $sourceId !== null) {
            $sourceValue=$pdo->prepare('SELECT COALESCE(-SUM(inventory_value_delta),0) FROM tbl_warehouse_movement WHERE source_type=? AND source_id=? AND product_id=? AND inventory_value_delta<0');$sourceValue->execute([$sourceType,$sourceId,$productId]);$inventoryValueDelta=(float)$sourceValue->fetchColumn();
        } else $inventoryValueDelta=0.0;
    }
    $inventoryValueDelta=round($inventoryValueDelta,4);
    $warehouseUpdate = $pdo->prepare('UPDATE tbl_warehouse_stock SET quantity = quantity + ?, inventory_value = inventory_value + ? WHERE warehouse_id = ? AND product_id = ? AND quantity + ? >= reserved_quantity AND inventory_value + ? >= -0.01');
    $warehouseUpdate->execute([$quantityDelta,$inventoryValueDelta,$warehouseId,$productId,$quantityDelta,$inventoryValueDelta]);
    if ($warehouseUpdate->rowCount() !== 1) {
        throw new DomainException('Stock insuficiente en el almacén seleccionado.');
    }
    $pdo->prepare('UPDATE tbl_warehouse_stock SET inventory_value=GREATEST(0,inventory_value),average_unit_cost=IF(quantity>0,GREATEST(0,inventory_value)/quantity,0) WHERE warehouse_id=? AND product_id=?')->execute([$warehouseId,$productId]);

    $pdo->prepare('UPDATE tbl_product SET stock_quantity = (SELECT COALESCE(SUM(quantity), 0) FROM tbl_warehouse_stock WHERE product_id = ?) WHERE id = ?')->execute([$productId, $productId]);

    $insertMovement = $pdo->prepare(
        'INSERT INTO tbl_warehouse_movement
            (type, product_id, warehouse_id, quantity, inventory_value_delta, observation, provider_id, doc_reference, source_type, source_id, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertMovement->execute([
        $movementType,
        $productId,
        $warehouseId,
        $quantityDelta,
        $inventoryValueDelta,
        substr($observation, 0, 100),
        $providerId,
        $documentReference === null ? null : substr($documentReference, 0, 50),
        $sourceType,
        $sourceId,
        $userId,
    ]);
    if ($documentLineId !== null) $pdo->prepare('UPDATE tbl_warehouse_movement SET document_line_id=? WHERE id=?')->execute([$documentLineId,(int)$pdo->lastInsertId()]);
}

function warehouse_apply_value_adjustment(PDO $pdo,int $productId,int $warehouseId,float $valueDelta,int $userId,string $observation,string $sourceType,int $sourceId,string $documentReference):void
{
    if($productId<1||$warehouseId<1||abs($valueDelta)<0.0001)throw new InvalidArgumentException('Ajuste de valor de inventario inválido.');
    $statement=$pdo->prepare('UPDATE tbl_warehouse_stock SET inventory_value=inventory_value+? WHERE warehouse_id=? AND product_id=? AND quantity>0 AND inventory_value+?>=-0.01');
    $statement->execute([$valueDelta,$warehouseId,$productId,$valueDelta]);
    if($statement->rowCount()!==1)throw new DomainException('No existe inventario valorizado suficiente para aplicar o revertir el costo del artículo.');
    $pdo->prepare('UPDATE tbl_warehouse_stock SET inventory_value=GREATEST(0,inventory_value),average_unit_cost=IF(quantity>0,GREATEST(0,inventory_value)/quantity,0) WHERE warehouse_id=? AND product_id=?')->execute([$warehouseId,$productId]);
    $pdo->prepare('INSERT INTO tbl_warehouse_movement(type,product_id,warehouse_id,quantity,inventory_value_delta,observation,doc_reference,source_type,source_id,user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([STOCK_MOVEMENT_COST_ADJUSTMENT,$productId,$warehouseId,0,$valueDelta,substr($observation,0,100),substr($documentReference,0,50),$sourceType,$sourceId,$userId]);
}

function warehouse_apply_reservation_delta(PDO $pdo, int $productId, float $quantityDelta, int $warehouseId = 1): void
{
    if ($productId <= 0 || $quantityDelta == 0.0) {
        throw new InvalidArgumentException('Reserva de inventario invalida.');
    }
    $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity, reserved_quantity) VALUES (?, ?, 0, 0)')->execute([$warehouseId, $productId]);
    if ($quantityDelta > 0) {
        $statement = $pdo->prepare('UPDATE tbl_warehouse_stock SET reserved_quantity = reserved_quantity + ? WHERE warehouse_id = ? AND product_id = ? AND quantity - reserved_quantity >= ?');
        $statement->execute([$quantityDelta, $warehouseId, $productId, $quantityDelta]);
    } else {
        $statement = $pdo->prepare('UPDATE tbl_warehouse_stock SET reserved_quantity = GREATEST(0, reserved_quantity + ?) WHERE warehouse_id = ? AND product_id = ? AND reserved_quantity + ? >= 0');
        $statement->execute([$quantityDelta, $warehouseId, $productId, $quantityDelta]);
    }
    if ($statement->rowCount() !== 1) {
        throw new DomainException($quantityDelta > 0 ? 'Stock disponible insuficiente para reservar.' : 'No existe reserva suficiente para liberar.');
    }
}

