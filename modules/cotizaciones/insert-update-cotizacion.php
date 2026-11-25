<?php

if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
    echo 'ERROR';
    return;
}

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/document-numbering.php';
require_once '../../global/business-partner-details.php';
require_once '../../global/sales-document-validation.php';
require_once '../../global/quotation-document.php';

$userId = (int) $_SESSION['loggedInUser']['USERID'];
$jsonResponse = ($_POST['response_format'] ?? '') === 'json';
$sendResult = static function (array $result) use ($jsonResponse): void {
    if ($jsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return;
    }
    echo $result['result'];
};

try {
    $pdo->beginTransaction();
    $replay = app_document_request_start($pdo, 'quotation_save', $_POST, $userId);
    if ($replay !== null) {
        $pdo->commit();
        $sendResult($replay);
        return;
    }

    $quotationId = null;
    $rawId = $_POST['id_cotizacion'] ?? '';
    if ($rawId !== '') {
        $quotationId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($quotationId === false) {
            throw new DomainException('Selecciona una cotización válida.');
        }
    }

    $previousStatus = null;
    $previousCustomValues = [];
    if ($quotationId !== null) {
        $snapshot = app_quotation_snapshot($pdo, $quotationId, true);
        if ($snapshot === null) {
            throw new DomainException('La cotización no existe.');
        }
        $quotation = $snapshot['quotation'];
        $version = $_POST['version'] ?? '';
        if (($jsonResponse || $version !== '') && (!is_string($version) || !hash_equals($snapshot['version'], $version))) {
            throw new DomainException('La cotización cambió desde que la abriste. Tus cambios siguen en pantalla. Vuelve a consultar la cotización antes de guardar.', 409);
        }
        $previousCustomValues = (array) $snapshot['custom_values'];
        $number = $quotation['number'];
        $previousStatus = (int) $quotation['status'];
        if ($previousStatus === 2) {
            throw new DomainException('La cotización anulada no se puede modificar.');
        }

        if (app_quotation_has_active_documents($pdo, $quotationId, true)) {
            throw new DomainException('La cotización tiene un documento de venta activo y ya no se puede modificar.');
        }
    }

    $lines = is_string($_POST['cotiz_prods'] ?? null) ? json_decode($_POST['cotiz_prods'], true) : null;
    $totals = app_validate_legacy_sales_input($pdo, $_POST, $lines, 'cotizacion', true);

    $customerId = filter_var($_POST['cotizacion_cliente'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($customerId === false) {
        throw new AppValidationException([app_field_error('cotizacion_valcliente', 'INVALID_CUSTOMER', 'Selecciona un cliente registrado.')]);
    }
    $statement = $pdo->prepare('SELECT ruc, business_name FROM tbl_customer WHERE client_id=? AND active_status=1');
    $statement->execute([$customerId]);
    $customer = $statement->fetch();
    if (!$customer) {
        throw new AppValidationException([app_field_error('cotizacion_valcliente', 'INVALID_CUSTOMER', 'El cliente seleccionado no existe o está inactivo.')]);
    }

    $warehouseId = filter_var($_POST['cotizacion_almacen_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    $warehouse = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id=? AND active_status=1');
    $warehouse->execute([$warehouseId]);
    if (!$warehouse->fetchColumn()) {
        throw new AppValidationException([app_field_error('cotizacion_almacen_id', 'INVALID_WAREHOUSE', 'El almacén seleccionado no existe o está inactivo.')]);
    }

    $billingId = filter_var($_POST['cotizacion_direccion_fiscal_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $shippingId = filter_var($_POST['cotizacion_direccion_entrega_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $billing = app_customer_address_snapshot($pdo, $customerId, $billingId, 'billing');
    $shipping = app_customer_address_snapshot($pdo, $customerId, $shippingId, 'shipping');

    $reference = $_POST['cotizacion_clirefer'] ?? '';
    if (!is_string($reference)) {
        throw new AppValidationException([app_field_error('cotizacion_clirefer', 'INVALID_TYPE', 'La referencia debe ser texto.')]);
    }
    $status = $previousStatus ?? 1;
    $values = [
        $status, $customerId, $warehouseId, $billing['id'] ?: null, $shipping['id'] ?: null,
        $customer['ruc'], $customer['business_name'], $billing['address_line'], $shipping['address_line'], trim($reference),
        $_POST['cotizacion_formpago'], $_POST['cotizacion_fecha'], $_POST['cotizacion_fecentrega'], $_POST['cotizacion_tipmon'],
        $totals['discount_rate'], $totals['discount'], $totals['subtotal'], $totals['tax'], $totals['total'],
        $_POST['cotizacion_usuarioid'], $userId, date('Y-m-d H:i:s'),
    ];

    if ($quotationId === null) {
        $number = app_next_document_number($pdo, 'QUOTATION', 'COTZ');
        $insert = $pdo->prepare('INSERT INTO tbl_quotation (number,status,customer_id,warehouse_id,billing_address_id,shipping_address_id,ruc,name,address,shipping_address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id,registration_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$number, ...$values]);
        $quotationId = (int) $pdo->lastInsertId();
        $result = 'OK_INSERT';
    } else {
        $update = $pdo->prepare('UPDATE tbl_quotation SET status=?,customer_id=?,warehouse_id=?,billing_address_id=?,shipping_address_id=?,ruc=?,name=?,address=?,shipping_address=?,reference=?,payment_days=?,date=?,delivery_date=?,currency=?,discount_rate=?,discount_value=?,total_sub=?,total_tax=?,total_net=?,seller_id=?,user_id=?,last_update=? WHERE id=?');
        $update->execute([...$values, $quotationId]);
        $pdo->prepare('DELETE FROM tbl_quotation_detail WHERE quotation_id=?')->execute([$quotationId]);
        $result = 'OK_UPDATE';
    }

    $insertLine = $pdo->prepare('INSERT INTO tbl_quotation_detail (item_description,item_id,item_name,item_quantity,item_unit_price,item_discount_rate,item_discounted_total,project_code,cost_center_code,lot_number,serial_number,expiration_date,quotation_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($lines as $line) {
        [$itemId, $name, $description, $price, $quantity, $discount, $amount, $project, $costCenter, $lot, $serial, $expiry] = $line;
        $insertLine->execute([$description, $itemId, $name, $quantity, $price, $discount, $amount, $project, $costCenter, $lot, $serial, $expiry ?: null, $quotationId]);
    }

    $customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];
    if ($jsonResponse) {
        $customValues = array_replace($previousCustomValues, $customValues);
    }
    app_save_custom_fields($pdo, 'quotation', $quotationId, $customValues, $jsonResponse);
    app_record_document_status(
        $pdo,
        'quotation',
        $quotationId,
        $number,
        $previousStatus === null ? '' : app_sales_status_label($previousStatus),
        app_sales_status_label($status),
        $previousStatus === null ? 'Creacion' : 'Actualizacion',
        $userId
    );
    $payload = ['error' => false, 'quotation_id' => $quotationId, 'number' => $number, 'result' => $result];
    app_document_request_finish($pdo, 'quotation_save', $_POST, $userId, 'quotation', $quotationId, $payload);
    $pdo->commit();
    $sendResult($payload);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!$exception instanceof DomainException && !$exception instanceof InvalidArgumentException) {
        error_log('APPU quotation save error: ' . $exception->getMessage());
    }
    app_api_error($exception, 'No se pudo guardar la cotización.');
}
