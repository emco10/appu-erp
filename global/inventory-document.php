<?php
require_once __DIR__ . '/../modules/inventory/stock.php';
require_once __DIR__ . '/document-numbering.php';
require_once __DIR__ . '/document-history.php';

function app_inventory_document_create(PDO $pdo, array $input, int $userId): array
{
    $type = strtoupper(trim((string) ($input['document_type'] ?? '')));
    $warehouseId = filter_var($input['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
    $date = trim((string) ($input['document_date'] ?? ''));
    $reference = trim((string) ($input['external_reference'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $lines = $input['lines'] ?? [];
    if (!in_array($type, ['ENTRY', 'ISSUE'], true)) throw new DomainException('Selecciona Entrada o Salida de inventario.');
    if ($warehouseId === false || $warehouseId < 1) throw new DomainException('Selecciona un almacén activo.');
    $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) throw new DomainException('La fecha del documento no es válida.');
    if ($reason === '' || mb_strlen($reason) > 100) throw new DomainException('Indica un motivo válido para el movimiento.');
    if (!is_array($lines) || !$lines || count($lines) > 200) throw new DomainException('El documento debe contener entre 1 y 200 líneas.');
    if ($userId < 1) throw new DomainException('No se pudo identificar al usuario responsable.');

    $warehouse = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id=? AND active_status=1 FOR UPDATE');
    $warehouse->execute([$warehouseId]);
    if (!$warehouse->fetchColumn()) throw new DomainException('El almacén no existe o está inactivo.');

    $normalized = [];
    foreach ($lines as $line) {
        $productId = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $unitCost = filter_var($line['unit_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
        $lotNumber = trim((string) ($line['lot_number'] ?? ''));
        $expirationDate = trim((string) ($line['expiration_date'] ?? ''));
        $lineNotes = trim((string) ($line['line_notes'] ?? ''));
        if ($productId === false || $productId < 1 || $quantity === false || $quantity <= 0) throw new DomainException('Todas las líneas requieren artículo y cantidad mayor que cero.');
        if (!is_finite((float) $quantity) || $quantity < 0.001 || abs((float) $quantity - round((float) $quantity, 3)) > 0.0000001) throw new DomainException('La cantidad debe ser al menos 0.001 y tener como máximo tres decimales.');
        if (isset($normalized[$productId])) throw new DomainException('Un artículo no puede repetirse en el mismo documento.');
        if ($type === 'ENTRY' && ($unitCost === false || $unitCost < 0)) throw new DomainException('El costo unitario de entrada no puede ser negativo.');
        if ($type === 'ENTRY' && (!is_finite((float) $unitCost) || abs((float) $unitCost - round((float) $unitCost, 6)) > 0.000000001)) throw new DomainException('El costo unitario admite como máximo seis decimales.');
        if ($expirationDate !== '') {
            $expiration = DateTimeImmutable::createFromFormat('Y-m-d', $expirationDate);
            if (!$expiration || $expiration->format('Y-m-d') !== $expirationDate || $lotNumber === '') throw new DomainException('Todo vencimiento debe corresponder a un lote válido.');
            if ($expirationDate < $date) throw new DomainException('No se puede ingresar un lote vencido a la fecha del documento.');
        }
        if ($type === 'ISSUE' && ($lotNumber !== '' || $expirationDate !== '')) throw new DomainException('Las salidas asignan lotes automáticamente por FEFO; no edites el lote de salida.');
        $normalized[$productId] = ['product_id' => (int) $productId, 'quantity' => round((float) $quantity, 3), 'unit_cost' => round((float) $unitCost, 6), 'lot_number' => substr($lotNumber, 0, 80), 'expiration_date' => $expirationDate ?: null, 'line_notes' => substr($lineNotes, 0, 250)];
    }

    $number = app_next_document_number($pdo, $type === 'ENTRY' ? 'INVENTORY_ENTRY' : 'INVENTORY_ISSUE', $type === 'ENTRY' ? 'EI' : 'SI');
    $insertHeader = $pdo->prepare("INSERT INTO tbl_inventory_document(number,document_type,warehouse_id,document_date,status,external_reference,reason,notes,created_by) VALUES(?,?,?,?,'Registrado',?,?,?,?)");
    $insertHeader->execute([$number, $type, $warehouseId, $date, substr($reference, 0, 100), $reason, substr($notes, 0, 500), $userId]);
    $documentId = (int) $pdo->lastInsertId();
    $productStatement = $pdo->prepare('SELECT id,code,name,active_status FROM tbl_product WHERE id=? FOR UPDATE');
    $stockStatement = $pdo->prepare('SELECT quantity,reserved_quantity,average_unit_cost FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=? FOR UPDATE');
    $lotCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_product_lot WHERE product_id=?');
    $insertLine = $pdo->prepare('INSERT INTO tbl_inventory_document_detail(inventory_document_id,line_number,product_id,quantity,unit_cost,inventory_value,lot_number,expiration_date,line_notes) VALUES(?,?,?,?,?,?,?,?,?)');
    $lineNumber = 0;
    foreach ($normalized as $line) {
        $lineNumber++;
        $productStatement->execute([$line['product_id']]); $product = $productStatement->fetch();
        if (!$product || !(int) $product['active_status']) throw new DomainException('Uno de los artículos no existe o está inactivo.');
        $stockStatement->execute([$warehouseId, $line['product_id']]); $stock = $stockStatement->fetch() ?: ['quantity' => 0, 'reserved_quantity' => 0, 'average_unit_cost' => 0];
        if ($type === 'ENTRY') {
            $lotCount->execute([$line['product_id']]);
            if ((int) $lotCount->fetchColumn() > 0 && $line['lot_number'] === '') throw new DomainException('El artículo ' . $product['code'] . ' controla lotes; indica el lote de entrada.');
            $value = round($line['quantity'] * $line['unit_cost'], 4);
        } else {
            if ((float) $stock['quantity'] - (float) $stock['reserved_quantity'] + 0.000001 < $line['quantity']) throw new DomainException('Stock disponible insuficiente para ' . $product['code'] . '.');
            $line['unit_cost'] = round((float) $stock['average_unit_cost'], 6);
            $value = round($line['quantity'] * $line['unit_cost'], 4);
        }
        $insertLine->execute([$documentId, $lineNumber, $line['product_id'], $line['quantity'], $line['unit_cost'], $value, $line['lot_number'], $line['expiration_date'], $line['line_notes']]);
        $lineId = (int) $pdo->lastInsertId();
        $delta = $type === 'ENTRY' ? $line['quantity'] : -$line['quantity'];
        $valueDelta = $type === 'ENTRY' ? $value : -$value;
        warehouse_apply_stock_delta($pdo, $line['product_id'], $delta, $type === 'ENTRY' ? STOCK_MOVEMENT_ENTRY : STOCK_MOVEMENT_ISSUE, $userId, ($type === 'ENTRY' ? 'Entrada de inventario ' : 'Salida de inventario ') . $number, 'inventory_document', $documentId, $number, null, (int) $warehouseId, $valueDelta, $lineId);
        if ($type === 'ENTRY' && $line['lot_number'] !== '') warehouse_apply_lot_delta($pdo, $line['product_id'], (int) $warehouseId, $line['lot_number'], $line['expiration_date'], $line['quantity']);
    }
    $watermark = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM tbl_warehouse_movement')->fetchColumn();
    $pdo->prepare('UPDATE tbl_inventory_document SET movement_watermark_id=? WHERE id=?')->execute([$watermark, $documentId]);
    app_record_document_status($pdo, 'inventory_document', $documentId, $number, '', 'Registrado', $type === 'ENTRY' ? 'Entrada' : 'Salida', $userId, $reason);
    return ['id' => $documentId, 'number' => $number, 'document_type' => $type];
}

function app_inventory_document_cancel(PDO $pdo, int $documentId, string $reason, int $userId): void
{
    if ($documentId < 1 || mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 500) throw new DomainException('Indica un motivo de anulación de 5 a 500 caracteres.');
    $statement = $pdo->prepare('SELECT * FROM tbl_inventory_document WHERE id=? FOR UPDATE');$statement->execute([$documentId]);$header=$statement->fetch();
    if (!$header) throw new DomainException('El documento de inventario no existe.');
    if ($header['status'] === 'Anulado') throw new DomainException('El documento ya está anulado.');
    $details = $pdo->prepare('SELECT * FROM tbl_inventory_document_detail WHERE inventory_document_id=? ORDER BY line_number FOR UPDATE');$details->execute([$documentId]);$lines=$details->fetchAll();
    if ($header['document_type'] === 'ENTRY') {
        $later = $pdo->prepare("SELECT COUNT(*) FROM tbl_warehouse_movement movement JOIN tbl_inventory_document_detail detail ON detail.inventory_document_id=? AND detail.product_id=movement.product_id LEFT JOIN tbl_inventory_document source_document ON movement.source_type='inventory_document' AND source_document.id=movement.source_id WHERE movement.warehouse_id=? AND movement.id>? AND movement.quantity<0 AND NOT(movement.source_type='inventory_document' AND movement.source_id=?) AND (movement.source_type<>'inventory_document' OR source_document.status<>'Anulado')");
        $later->execute([$documentId,(int)$header['warehouse_id'],(int)$header['movement_watermark_id'],$documentId]);
        if ((int)$later->fetchColumn()>0) throw new DomainException('No se puede anular: existen salidas posteriores de uno o más artículos.');
    }
    foreach ($lines as $line) {
        if ($header['document_type'] === 'ENTRY') {
            warehouse_apply_stock_delta($pdo,(int)$line['product_id'],-(float)$line['quantity'],STOCK_MOVEMENT_DOCUMENT_REVERSAL,$userId,'Anulación de entrada '.$header['number'],'inventory_document',$documentId,(string)$header['number'],null,(int)$header['warehouse_id'],-(float)$line['inventory_value'],(int)$line['id']);
            if (trim((string)$line['lot_number'])!=='') warehouse_apply_lot_delta($pdo,(int)$line['product_id'],(int)$header['warehouse_id'],(string)$line['lot_number'],$line['expiration_date'] ?: null,-(float)$line['quantity']);
        } else {
            warehouse_apply_stock_delta($pdo,(int)$line['product_id'],(float)$line['quantity'],STOCK_MOVEMENT_DOCUMENT_REVERSAL,$userId,'Anulación de salida '.$header['number'],'inventory_document',$documentId,(string)$header['number'],null,(int)$header['warehouse_id'],(float)$line['inventory_value'],(int)$line['id']);
        }
    }
    $pdo->prepare("UPDATE tbl_inventory_document SET status='Anulado',cancelled_by=?,cancelled_at=NOW(),cancellation_reason=? WHERE id=?")->execute([$userId,trim($reason),$documentId]);
    app_record_document_status($pdo,'inventory_document',$documentId,(string)$header['number'],'Registrado','Anulado','Anulación',$userId,trim($reason));
}
