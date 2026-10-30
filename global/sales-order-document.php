<?php

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/document-history.php';
require_once __DIR__ . '/document-engine.php';

/** A pending order is editable and has no reservation; confirmation posts it. */
function app_sales_order_snapshot(PDO $pdo, int $id, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT * FROM tbl_sales_order WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $order = $query->fetch();
    if (!$order) throw new DomainException('La orden de venta no existe.');
    $query = $pdo->prepare('SELECT * FROM tbl_sales_order_detail WHERE sales_order_id = ? ORDER BY id');
    $query->execute([$id]);
    $details = $query->fetchAll();
    $query = $pdo->prepare("SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = 'sales_order' AND record_id = ? ORDER BY field_definition_id");
    $query->execute([$id]);
    $custom = $query->fetchAll(PDO::FETCH_KEY_PAIR);
    $version = hash('sha256', json_encode([$order, $details, $custom]));
    $order['domain_status']=app_order_domain_status($order);
    if (app_document_engine_available($pdo)) {
        $open=array_column(app_document_open_lines($pdo,'sales_order',$id,'FULFILLMENT'),null,'id');
        foreach ($details as &$line) {
            $line['open_quantity']=$open[$line['id']]['open_quantity']??(float)$line['item_quantity'];
            $line['processed_quantity']=$open[$line['id']]['processed_quantity']??0;
        }
        unset($line);
    }
    return ['order' => $order, 'details' => $details, 'custom_fields' => (object) $custom, 'version' => $version];
}

/** Validate the complete request before creating any business records. */
function app_validate_sales_order_input(PDO $pdo, array $input): array
{
    $errors = app_validate_fields($input, [
        'delivery_date' => ['label' => 'Fecha de entrega', 'required' => true, 'customValidator' => fn ($value) => app_valid_date($value), 'messages' => ['customValidator' => 'Fecha de entrega: ingrese una fecha válida.']],
        'currency' => ['label' => 'Moneda', 'required' => true, 'validValues' => ['PEN', 'USD', 'EUR'], 'messages' => ['validValues' => 'Moneda: seleccione PEN, USD o EUR.']],
        'reference' => ['label' => 'Referencia', 'maxLength' => 300],
    ]);
    $customerId = filter_var($input['customer_id'] ?? null, FILTER_VALIDATE_INT);
    $warehouseId = filter_var($input['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
    foreach ([['customer_id', $customerId, 'Cliente', 'tbl_customer', 'client_id'], ['warehouse_id', $warehouseId, 'Almacén', 'tbl_warehouse', 'id']] as [$path, $id, $label, $table, $column]) {
        $exists = false;
        if ($id > 0) {
            $query = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} = ? AND active_status = 1");
            $query->execute([$id]);
            $exists = (bool) $query->fetchColumn();
        }
        if (!$exists) $errors[] = app_field_error($path, $id > 0 ? 'INVALID_REFERENCE' : 'REQUIRED', $label . ': debe seleccionar un registro activo.');
    }
    $date = $input['delivery_date'] ?? '';
    $currency = strtoupper(trim(is_scalar($input['currency'] ?? '') ? (string) $input['currency'] : ''));
    $reference = is_scalar($input['reference'] ?? '') ? trim((string) ($input['reference'] ?? '')) : '';
    $rows = $input['details'] ?? null;
    if (is_string($rows)) $rows = json_decode($rows, true);
    if (!is_array($rows) || !array_is_list($rows) || !$rows) {
        $errors[] = app_field_error('details', 'REQUIRED', 'Artículos: agregue al menos una línea.');
        $rows = [];
    }
    $details = []; $seen = []; $subtotal = 0.0;
    $query = $pdo->prepare('SELECT id, code, name, description FROM tbl_product WHERE id = ? AND active_status = 1');
    foreach ($rows as $index => $row) {
        $row = is_array($row) ? $row : [];
        $prefix = 'Línea ' . ($index + 1) . ': ';
        $path = 'details[' . $index . ']';
        $productId = filter_var($row['product_id'] ?? null, FILTER_VALIDATE_INT);
        $query->execute([$productId ?: 0]); $product = $query->fetch();
        if (!$product) $errors[] = app_field_error($path . '.product_id', 'INVALID_REFERENCE', $prefix . 'debe seleccionar un artículo activo.');
        elseif (isset($seen[$productId])) $errors[] = app_field_error($path . '.product_id', 'DUPLICATE', $prefix . 'el artículo ya existe; consolide la cantidad.');
        $seen[$productId ?: 0] = true;
        $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$quantity || $quantity < 1 || $quantity > 2147483647) $errors[] = app_field_error($path . '.quantity', 'MIN', $prefix . 'la cantidad debe ser un entero mayor que cero.');
        $price = filter_var($row['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price === null || !is_finite((float) $price) || $price < 0 || $price > 999999999) $errors[] = app_field_error($path . '.unit_price', 'MIN', $prefix . 'ingrese un precio entre 0 y 999999999.');
        $lineTotal = round((float) $quantity * (float) $price, 2);
        $subtotal += $lineTotal;
        $details[] = [$product, (int) $quantity, (float) $price, $lineTotal];
    }
    $values = is_array($input['custom_field'] ?? null) ? $input['custom_field'] : [];
    $errors = array_merge($errors, app_custom_field_errors($pdo, 'sales_order', $values));
    if ($errors) throw new AppValidationException($errors);
    return ['customer_id' => (int) $customerId, 'warehouse_id' => (int) $warehouseId, 'delivery_date' => $date, 'currency' => $currency, 'reference' => $reference, 'details' => $details, 'custom_fields' => $values, 'subtotal' => round($subtotal, 2), 'tax' => round($subtotal * .18, 2), 'total' => round($subtotal + round($subtotal * .18, 2), 2)];
}

function app_write_sales_order_lines(PDO $pdo, int $id, array $details): void
{
    $insert = $pdo->prepare('INSERT INTO tbl_sales_order_detail (sales_order_id,item_id,item_code,item_name,item_description,item_quantity,item_unit_price,item_total) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($details as [$product, $quantity, $price, $total]) $insert->execute([$id, $product['id'], $product['code'], $product['name'], $product['description'], $quantity, $price, $total]);
}

/** Caller owns the transaction, including numbering, lines, UDFs and history. */
function app_save_sales_order_document(PDO $pdo, array $input, int $userId, ?int $id = null): array
{
    if (!$pdo->inTransaction()) throw new LogicException('La orden requiere una transacción.');
    $previous = null;
    if ($id !== null) {
        $previous = app_sales_order_snapshot($pdo, $id, true);
        if (!hash_equals($previous['version'], (string) ($input['version'] ?? ''))) throw new DomainException('La orden cambió desde que la abriste. Vuelve a consultarla antes de editar.', 409);
        if ($previous['order']['status'] !== 'Pendiente') throw new DomainException('Solo se pueden editar órdenes pendientes.');
        if ($previous['order']['quotation_id']) throw new DomainException('La orden procede de una cotización. Conserva sus importes y modifica el documento origen antes de convertirlo.');
        $derived = $pdo->prepare("SELECT COUNT(*) FROM tbl_document_flow WHERE source_type = 'sales_order' AND source_id = ?");
        $derived->execute([$id]);
        if ($derived->fetchColumn()) throw new DomainException('La orden tiene documentos relacionados y no permite editar sus líneas.');
    }
    $data = app_validate_sales_order_input($pdo, $input);
    $query = $pdo->prepare('SELECT * FROM tbl_customer WHERE client_id = ? AND active_status = 1 FOR UPDATE');
    $query->execute([$data['customer_id']]); $customer = $query->fetch();
    if (!$customer) throw new DomainException('El cliente ya no está activo.');
    $billing = app_customer_address_snapshot($pdo, $data['customer_id'], null, 'billing');
    $shipping = app_customer_address_snapshot($pdo, $data['customer_id'], filter_var($input['shipping_address_id'] ?? null, FILTER_VALIDATE_INT) ?: null, 'shipping');
    $fields = [$data['customer_id'], $data['warehouse_id'], $billing['id'] ?: null, $shipping['id'] ?: null, $customer['ruc'], $customer['business_name'], $billing['address_line'], $shipping['address_line'], $data['reference'], (int) $customer['payment_days'], $data['delivery_date'], $data['currency'], $data['subtotal'], $data['tax'], $data['total']];
    if ($id === null) {
        $number = app_next_document_number($pdo, 'SALES_ORDER', 'OV');
        $query = $pdo->prepare("INSERT INTO tbl_sales_order (customer_id,warehouse_id,billing_address_id,shipping_address_id,ruc,customer_name,address,shipping_address,reference,payment_days,delivery_date,currency,total_sub,total_tax,total_net,number,status,issue_date,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pendiente',?,?)");
        $query->execute(array_merge($fields, [$number, date('Y-m-d'), $userId]));
        $id = (int) $pdo->lastInsertId();
    } else {
        $number = $previous['order']['number'];
        $query = $pdo->prepare('UPDATE tbl_sales_order SET customer_id=?,warehouse_id=?,billing_address_id=?,shipping_address_id=?,ruc=?,customer_name=?,address=?,shipping_address=?,reference=?,payment_days=?,delivery_date=?,currency=?,total_sub=?,total_tax=?,total_net=?,last_update=NOW() WHERE id=?');
        $query->execute(array_merge($fields, [$id]));
        $pdo->prepare('DELETE FROM tbl_sales_order_detail WHERE sales_order_id=?')->execute([$id]);
    }
    app_write_sales_order_lines($pdo, $id, $data['details']);
    // This form sends a complete UDF set, including intentional empty values.
    $pdo->prepare("DELETE value FROM tbl_custom_field_value value JOIN tbl_custom_field_definition definition ON definition.id=value.field_definition_id WHERE value.entity='sales_order' AND value.record_id=? AND definition.active_status=1")->execute([$id]);
    app_save_custom_fields($pdo, 'sales_order', $id, $data['custom_fields'], true);
    app_record_document_status($pdo, 'sales_order', $id, $number, $previous ? 'Pendiente' : '', 'Pendiente', $previous ? 'Edicion' : 'Creacion', $userId, $previous ? 'Edición de orden pendiente.' : 'Orden de venta creada directamente.');
    return ['error' => false, 'order_id' => $id, 'number' => $number, 'message' => $previous ? 'Orden actualizada correctamente.' : 'Orden de venta creada correctamente.'];
}
