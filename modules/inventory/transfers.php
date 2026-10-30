<?php
require '../../global/connection.php';
require_once __DIR__ . '/stock.php';
require_once '../../global/document-numbering.php';
require_once '../../global/document-history.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    app_require_permission('inventory.view');
    $warehouses = $pdo->query("SELECT id, code, name FROM tbl_warehouse WHERE active_status = 1 ORDER BY name")->fetchAll();
    $products = $pdo->query("SELECT id, code, name FROM tbl_product WHERE active_status = 1 ORDER BY name")->fetchAll();
    $lots = $pdo->query("SELECT id, warehouse_id, product_id, lot_number, expiration_date, quantity, reserved_quantity FROM tbl_product_lot WHERE quantity > 0 ORDER BY expiration_date IS NULL, expiration_date, lot_number")->fetchAll();
    $transfers = $pdo->query("SELECT movement.id, movement.source_id AS transfer_id, movement.registration_date, movement.doc_reference, movement.observation, movement.quantity, product.code AS product_code, product.name AS product_name, warehouse.name AS warehouse_name, movement.type, COALESCE(header.status, 'Registrada') AS transfer_status FROM tbl_warehouse_movement movement JOIN tbl_product product ON product.id=movement.product_id JOIN tbl_warehouse warehouse ON warehouse.id=movement.warehouse_id LEFT JOIN tbl_inventory_transfer header ON header.id = movement.source_id AND movement.source_type = 'warehouse_transfer' WHERE movement.type IN (6,7) ORDER BY movement.id DESC LIMIT 100")->fetchAll();
    echo json_encode(['warehouses' => $warehouses, 'products' => $products, 'lots' => $lots, 'transfers' => $transfers], JSON_UNESCAPED_UNICODE); exit;
}
app_require_permission('inventory.manage');
$action = strtolower(trim((string) ($_POST['action'] ?? 'create')));
if(!in_array($action,['create','cancel'],true)){http_response_code(422);echo json_encode(['error'=>'Acción de transferencia inválida.'],JSON_UNESCAPED_UNICODE);exit;}
if ($action === 'cancel') {
    $transferId = filter_var($_POST['transfer_id'] ?? null, FILTER_VALIDATE_INT);
    if ($transferId === false || $transferId < 1) { http_response_code(422); echo json_encode(['error' => 'Transferencia invalida.'], JSON_UNESCAPED_UNICODE); exit; }
    $cancellationReason = trim((string) ($_POST['cancellation_reason'] ?? ''));
    if (mb_strlen($cancellationReason) < 5) { http_response_code(422); echo json_encode(['error' => 'Indica un motivo de anulacion de al menos 5 caracteres.'], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $pdo->beginTransaction();
        $headerStatement = $pdo->prepare('SELECT * FROM tbl_inventory_transfer WHERE id = ? FOR UPDATE');
        $headerStatement->execute([$transferId]);
        $header = $headerStatement->fetch();
        if (!$header) throw new DomainException('La transferencia no existe.');
        if ($header['status'] === 'Anulada') throw new DomainException('La transferencia ya esta anulada.');
        $quantity = (float) $header['quantity']; $fromId = (int) $header['from_warehouse_id']; $toId = (int) $header['to_warehouse_id']; $productId = (int) $header['product_id'];
        $valueStatement=$pdo->prepare("SELECT COALESCE(-SUM(inventory_value_delta),0) FROM tbl_warehouse_movement WHERE source_type='warehouse_transfer' AND source_id=? AND product_id=? AND type=?");$valueStatement->execute([$transferId,$productId,STOCK_MOVEMENT_TRANSFER_OUT]);$transferValue=round((float)$valueStatement->fetchColumn(),4);
        $out = $pdo->prepare('UPDATE tbl_warehouse_stock SET quantity = quantity + ?, inventory_value = inventory_value + ? WHERE warehouse_id = ? AND product_id = ?');
        $out->execute([$quantity, $transferValue, $fromId, $productId]);
        $back = $pdo->prepare('UPDATE tbl_warehouse_stock SET quantity = quantity - ?, inventory_value = inventory_value - ? WHERE warehouse_id = ? AND product_id = ? AND quantity - reserved_quantity >= ? AND inventory_value - ? >= -0.01');
        $back->execute([$quantity, $transferValue, $toId, $productId, $quantity, $transferValue]);
        if ($back->rowCount() !== 1) throw new DomainException('No hay stock disponible en el almacen destino para revertir la transferencia.');
        $pdo->prepare('UPDATE tbl_warehouse_stock SET inventory_value=GREATEST(0,inventory_value),average_unit_cost=IF(quantity>0,GREATEST(0,inventory_value)/quantity,0) WHERE product_id=? AND warehouse_id IN (?,?)')->execute([$productId,$fromId,$toId]);
        if ($header['lot_id'] !== null) {
            $lotStatement = $pdo->prepare('SELECT lot_number, expiration_date FROM tbl_product_lot WHERE id = ?');
            $lotStatement->execute([(int) $header['lot_id']]); $lot = $lotStatement->fetch();
            if (!$lot) throw new DomainException('El lote de la transferencia no existe.');
            warehouse_apply_lot_delta($pdo, $productId, $fromId, (string) $lot['lot_number'], $lot['expiration_date'], $quantity);
            warehouse_apply_lot_delta($pdo, $productId, $toId, (string) $lot['lot_number'], $lot['expiration_date'], -$quantity);
        }
        $pdo->prepare('UPDATE tbl_product SET stock_quantity = (SELECT COALESCE(SUM(quantity), 0) FROM tbl_warehouse_stock WHERE product_id = ?) WHERE id = ?')->execute([$productId, $productId]);
        $userId = (int) $_SESSION['loggedInUser']['USERID'];
        $movement = $pdo->prepare('INSERT INTO tbl_warehouse_movement (type, product_id, warehouse_id, quantity, inventory_value_delta, observation, doc_reference, source_type, source_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $observation = 'Anulacion de transferencia ' . $header['number'];
        $movement->execute([STOCK_MOVEMENT_DOCUMENT_REVERSAL, $productId, $fromId, $quantity, $transferValue, $observation, $header['number'], 'warehouse_transfer', $transferId, $userId]);
        $movement->execute([STOCK_MOVEMENT_DOCUMENT_REVERSAL, $productId, $toId, -$quantity, -$transferValue, $observation, $header['number'], 'warehouse_transfer', $transferId, $userId]);
        $pdo->prepare("UPDATE tbl_inventory_transfer SET status = 'Anulada' WHERE id = ?")->execute([$transferId]);
        app_record_document_status($pdo, 'stock_transfer', $transferId, (string) $header['number'], 'Registrada', 'Anulada', 'Anulacion', $userId, $cancellationReason);
        $pdo->commit(); echo json_encode(['message' => 'Transferencia anulada y stock revertido correctamente.'], JSON_UNESCAPED_UNICODE); exit;
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(422); echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
}
$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$fromId = filter_var($_POST['from_warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$toId = filter_var($_POST['to_warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
$lotId = filter_var($_POST['lot_id'] ?? null, FILTER_VALIDATE_INT);
$lotId = $lotId !== false && $lotId !== null && $lotId > 0 ? (int) $lotId : null;
if ($productId === false || $fromId === false || $toId === false || $quantity === false || !is_finite((float)$quantity) || $productId < 1 || $fromId < 1 || $toId < 1 || $fromId === $toId || $quantity < 0.001 || abs((float)$quantity-round((float)$quantity,3))>0.0000001) {
    http_response_code(422); echo json_encode(['error' => 'Completa un traslado válido.'], JSON_UNESCAPED_UNICODE); exit;
}
try {
    $pdo->beginTransaction();
    $warehouseCheck = $pdo->prepare('SELECT COUNT(*) FROM tbl_warehouse WHERE id IN (?, ?) AND active_status=1'); $warehouseCheck->execute([$fromId, $toId]);
    if ((int) $warehouseCheck->fetchColumn() !== 2) throw new DomainException('El almacén de origen o destino no existe.');
    $productStatement = $pdo->prepare('SELECT id, stock_quantity FROM tbl_product WHERE id = ? AND active_status=1 FOR UPDATE');
    $productStatement->execute([$productId]);
    $product = $productStatement->fetch();
    if (!$product) throw new DomainException('El articulo seleccionado no existe.');
    $warehouseCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_warehouse_stock WHERE product_id = ?');
    $warehouseCount->execute([$productId]);
    if ((int) $warehouseCount->fetchColumn() === 0 && $fromId === 1) {
        $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity) VALUES (1, ?, ?)')->execute([$productId, (float) $product['stock_quantity']]);
    }
    $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, 0)')->execute([$fromId, $productId]);
    $pdo->prepare('INSERT IGNORE INTO tbl_warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, 0)')->execute([$toId, $productId]);
    $lot = null;
    $lotCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_product_lot WHERE warehouse_id = ? AND product_id = ? AND quantity > 0');
    $lotCount->execute([$fromId, $productId]);
    if ((int) $lotCount->fetchColumn() > 0 && $lotId === null) {
        throw new DomainException('Selecciona el lote de origen para conservar la trazabilidad.');
    }
    if ($lotId !== null) {
        $lotStatement = $pdo->prepare('SELECT id, lot_number, expiration_date, quantity, reserved_quantity FROM tbl_product_lot WHERE id = ? AND warehouse_id = ? AND product_id = ? FOR UPDATE');
        $lotStatement->execute([$lotId, $fromId, $productId]);
        $lot = $lotStatement->fetch();
        if (!$lot || ((float) $lot['quantity'] - (float) $lot['reserved_quantity']) < $quantity) {
            throw new DomainException('El lote seleccionado no tiene cantidad disponible suficiente.');
        }
    }
    $sourceValue=$pdo->prepare('SELECT quantity,reserved_quantity,inventory_value,average_unit_cost FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=? FOR UPDATE');$sourceValue->execute([$fromId,$productId]);$sourceStock=$sourceValue->fetch();if(!$sourceStock)throw new DomainException('No existe stock del artículo en el almacén de origen.');$transferValue=round($quantity*(float)$sourceStock['average_unit_cost'],4);
    $out = $pdo->prepare('UPDATE tbl_warehouse_stock SET quantity=quantity-?,inventory_value=inventory_value-? WHERE warehouse_id=? AND product_id=? AND quantity-reserved_quantity>=? AND inventory_value-?>=-0.01'); $out->execute([$quantity,$transferValue,$fromId,$productId,$quantity,$transferValue]);
    if ($out->rowCount() !== 1) throw new DomainException('Stock disponible insuficiente en el almacén de origen.');
    $pdo->prepare('UPDATE tbl_warehouse_stock SET quantity=quantity+?,inventory_value=inventory_value+? WHERE warehouse_id=? AND product_id=?')->execute([$quantity,$transferValue,$toId,$productId]);
    $pdo->prepare('UPDATE tbl_warehouse_stock SET inventory_value=GREATEST(0,inventory_value),average_unit_cost=IF(quantity>0,GREATEST(0,inventory_value)/quantity,0) WHERE product_id=? AND warehouse_id IN (?,?)')->execute([$productId,$fromId,$toId]);
    $pdo->prepare('UPDATE tbl_product SET stock_quantity = (SELECT COALESCE(SUM(quantity), 0) FROM tbl_warehouse_stock WHERE product_id = ?) WHERE id = ?')->execute([$productId, $productId]);
    if ($lot !== null) {
        warehouse_apply_lot_delta($pdo, $productId, $fromId, (string) $lot['lot_number'], $lot['expiration_date'], -$quantity);
        warehouse_apply_lot_delta($pdo, $productId, $toId, (string) $lot['lot_number'], $lot['expiration_date'], $quantity);
    }
    $userId = (int) $_SESSION['loggedInUser']['USERID'];
    $reference = app_next_document_number($pdo, 'STOCK_TRANSFER', 'TR');
    $observation = 'Transferencia interna ' . $fromId . ' -> ' . $toId;
    $headerInsert = $pdo->prepare('INSERT INTO tbl_inventory_transfer (number, from_warehouse_id, to_warehouse_id, product_id, lot_id, quantity, transfer_date, observation, created_by) VALUES (?,?,?,?,?,?,CURDATE(),?,?)');
    $headerInsert->execute([$reference, $fromId, $toId, $productId, $lotId, $quantity, $observation, $userId]);
    $transferId = (int) $pdo->lastInsertId();
    $customFields = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];
    app_save_custom_fields($pdo, 'stock_transfer', $transferId, $customFields);
    $insert = $pdo->prepare('INSERT INTO tbl_warehouse_movement (type, product_id, warehouse_id, quantity, inventory_value_delta, observation, doc_reference, source_type, source_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([STOCK_MOVEMENT_TRANSFER_OUT, $productId, $fromId, -$quantity, -$transferValue, $observation, $reference, 'warehouse_transfer', $transferId, $userId]);
    $insert->execute([STOCK_MOVEMENT_TRANSFER_IN, $productId, $toId, $quantity, $transferValue, $observation, $reference, 'warehouse_transfer', $transferId, $userId]);
    $pdo->commit(); echo json_encode(['message' => 'Transferencia registrada correctamente.', 'reference' => $reference], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); http_response_code(422); echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE); }
