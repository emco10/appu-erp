<?php

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/document-numbering.php';
require_once '../../global/master-data-validation.php';
require_once '../../global/business-partner-details.php';
require_once '../inventory/stock.php';
require_once '../../global/sales-order-document.php';
require_once '../../global/sales-order-query.php';

header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.view');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (($_GET['catalog'] ?? '') === '1') {
        $customers = $pdo->query("SELECT customer.client_id AS id, customer.business_name, customer.ruc, customer.address, customer.payment_days, customer.default_currency, COALESCE(price_list.factor,1) AS price_factor FROM tbl_customer customer LEFT JOIN tbl_price_list price_list ON price_list.id=customer.price_list_id AND price_list.active_status=1 WHERE customer.active_status = 1 ORDER BY customer.business_name")->fetchAll();
        $products = $pdo->query("SELECT id, code, name, description, unit_price FROM tbl_product WHERE active_status = 1 ORDER BY name")->fetchAll();
        $warehouses = $pdo->query("SELECT id, code, name FROM tbl_warehouse WHERE active_status = 1 ORDER BY name")->fetchAll();
        $definitions = app_custom_field_definitions($pdo, 'sales_order');
        echo json_encode(['error' => false, 'customers' => $customers, 'products' => $products, 'warehouses' => $warehouses, 'custom_fields' => $definitions], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($_GET['view_id'])) {
        try {
            echo json_encode(['error' => false] + app_sales_order_snapshot($pdo, (int) $_GET['view_id']), JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            app_api_error($exception, 'No se pudo consultar la orden.');
        }
        exit;
    }
    $requestedOrderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);
    if ($requestedOrderId !== false && $requestedOrderId !== null && $requestedOrderId > 0) {
        $orderStatement = $pdo->prepare(
            'SELECT id, number, status, quotation_id, customer_id, warehouse_id, ruc, customer_name,
                    address, reference, payment_days, issue_date, delivery_date,
                    currency, discount_rate, discount_value, total_sub, total_tax,
                    total_net, seller_id
             FROM tbl_sales_order
             WHERE id = ?'
        );
        $orderStatement->execute([$requestedOrderId]);
        $order = $orderStatement->fetch();
        if (!$order || $order['status'] !== 'Confirmada') {
            http_response_code(422);
            echo json_encode(['error' => true, 'message' => 'Solo se pueden usar ordenes confirmadas.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $detailStatement = $pdo->prepare(
            'SELECT id, item_id, item_code, item_name, item_description,
                    item_quantity, item_unit_price, item_total
             FROM tbl_sales_order_detail
             WHERE sales_order_id = ?
             ORDER BY id'
        );
        $detailStatement->execute([$requestedOrderId]);
        echo json_encode(['error' => false, 'order' => $order, 'details' => $detailStatement->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        echo json_encode(app_query_sales_orders($pdo, $_GET), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        error_log('APPU order search error: ' . $exception->getMessage());
        app_api_error($exception, 'No se pudo buscar las ordenes de venta.');
    }
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Metodo no permitido.']);
    exit;
}

app_require_permission('sales.manage');
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

try {
    $pdo->beginTransaction();
    $userId = (int) $_SESSION['loggedInUser']['USERID'];

    if (in_array($action, ['create_direct', 'update'], true)) {
        $id = $action === 'update' ? (int) ($_POST['order_id'] ?? 0) : null;
        $result = app_save_sales_order_document($pdo, $_POST, $userId, $id);
        $pdo->commit();
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'convert_from_quotation') {
        $quotationId = filter_var($_POST['quotation_id'] ?? null, FILTER_VALIDATE_INT);
        if ($quotationId === false || $quotationId <= 0) {
            throw new InvalidArgumentException('Cotizacion invalida.');
        }

        $quotationStatement = $pdo->prepare(
            'SELECT * FROM tbl_quotation WHERE id = ? FOR UPDATE'
        );
        $quotationStatement->execute([$quotationId]);
        $quotation = $quotationStatement->fetch();

        if (!$quotation || (int) $quotation['status'] !== 1) {
            throw new DomainException('Solo se pueden convertir cotizaciones vigentes.');
        }
        app_validate_customer_reference($pdo, $quotation['customer_id']);

        $existingStatement = $pdo->prepare("SELECT id, number FROM tbl_sales_order WHERE quotation_id = ? AND status <> 'Anulada'");
        $existingStatement->execute([$quotationId]);
        if ($existingStatement->fetch()) {
            throw new DomainException('Esta cotizacion ya tiene una orden de venta.');
        }

        $detailsStatement = $pdo->prepare(
            'SELECT quotation_detail.id AS base_line_id, quotation_detail.item_id, product.code AS item_code,
                    quotation_detail.item_name, quotation_detail.item_description,
                    quotation_detail.item_quantity, quotation_detail.item_unit_price
             FROM tbl_quotation_detail quotation_detail
             JOIN tbl_product product ON product.id = quotation_detail.item_id
             WHERE quotation_detail.quotation_id = ?
             ORDER BY quotation_detail.id'
        );
        $detailsStatement->execute([$quotationId]);
        $details = $detailsStatement->fetchAll();
        if (!$details) {
            throw new DomainException('La cotizacion no tiene productos validos.');
        }
        foreach ($details as $detail) {
            app_validate_product_reference($pdo, $detail['item_id']);
        }

        $orderNumber = app_next_document_number($pdo, 'SALES_ORDER', 'OV');
        $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
        $warehouseId = $warehouseId === false || $warehouseId <= 0 ? (int) ($quotation['warehouse_id'] ?? 1) : (int) $warehouseId;
        $warehouseStatement = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id = ? AND active_status = 1');
        $warehouseStatement->execute([$warehouseId]);
        if (!$warehouseStatement->fetchColumn()) {
            throw new DomainException('El almacen seleccionado no existe o esta inactivo.');
        }
        $insertStatement = $pdo->prepare(
            'INSERT INTO tbl_sales_order
                (number, status, quotation_id, customer_id, warehouse_id, billing_address_id, shipping_address_id, ruc, customer_name, address, shipping_address,
                 reference, payment_days, issue_date, delivery_date, currency,
                 discount_rate, discount_value, total_sub, total_tax, total_net,
                 seller_id, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStatement->execute([
            $orderNumber,
            'Pendiente',
            $quotationId,
            (int) $quotation['customer_id'],
            $warehouseId,
            $quotation['billing_address_id'] ?: null,
            $quotation['shipping_address_id'] ?: null,
            (string) $quotation['ruc'],
            (string) $quotation['name'],
            (string) $quotation['address'],
            (string) ($quotation['shipping_address'] ?: $quotation['address']),
            (string) $quotation['reference'],
            (int) $quotation['payment_days'],
            $quotation['date'],
            $quotation['delivery_date'],
            (string) $quotation['currency'],
            (float) $quotation['discount_rate'],
            (float) $quotation['discount_value'],
            (float) $quotation['total_sub'],
            (float) $quotation['total_tax'],
            (float) $quotation['total_net'],
            (int) $quotation['seller_id'],
            $userId,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $detailInsert = $pdo->prepare(
            'INSERT INTO tbl_sales_order_detail
                (sales_order_id, item_id, item_code, item_name, item_description,
                 item_quantity, item_unit_price, item_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($details as $detail) {
            $quantity = (int) $detail['item_quantity'];
            $unitPrice = (float) $detail['item_unit_price'];
            if ($quantity <= 0 || $unitPrice < 0) {
                throw new DomainException('La cotizacion contiene un detalle invalido.');
            }
            $detailInsert->execute([
                $orderId,
                (int) $detail['item_id'],
                (string) $detail['item_code'],
                (string) $detail['item_name'],
                (string) $detail['item_description'],
                $quantity,
                $unitPrice,
                $quantity * $unitPrice,
            ]);
            $targetLineId=(int)$pdo->lastInsertId();
            if (app_document_engine_available($pdo)) app_apply_document_line($pdo,'quotation',(int)$quotationId,(int)$detail['base_line_id'],'sales_order',$orderId,$targetLineId,'CONVERSION',(float)$quantity,$userId);
        }

        app_copy_custom_fields($pdo, 'quotation', $quotationId, 'sales_order', $orderId);

        app_record_document_status($pdo, 'sales_order', $orderId, $orderNumber, '', 'Pendiente', 'Conversion de cotizacion', $userId, 'Cotizacion ' . $quotation['number']);
        app_record_document_flow($pdo, 'quotation', (int) $quotationId, 'sales_order', $orderId, $userId, 'CONVERSION');
        $pdo->commit();
        echo json_encode(['error' => false, 'order_id' => $orderId, 'number' => $orderNumber, 'message' => 'Orden de venta creada correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    if ($orderId === false || $orderId <= 0 || !in_array($action, ['confirm', 'cancel'], true)) {
        throw new InvalidArgumentException('Operacion de orden invalida.');
    }

    $orderStatement = $pdo->prepare('SELECT id, number, status, warehouse_id FROM tbl_sales_order WHERE id = ? FOR UPDATE');
    $orderStatement->execute([$orderId]);
    $order = $orderStatement->fetch();
    if (!$order) {
        throw new RuntimeException('Orden de venta inexistente.');
    }
    $warehouseId = (int) ($order['warehouse_id'] ?? 1);
    if ($warehouseId <= 0) {
        $warehouseId = 1;
    }

    $newStatus = $action === 'confirm' ? 'Confirmada' : 'Anulada';
    $cancelReason = trim((string) ($_POST['cancel_reason'] ?? ''));
    if ($order['status'] === $newStatus) {
        throw new DomainException('La orden ya tiene ese estado.');
    }
    if ($action === 'confirm' && $order['status'] !== 'Pendiente') {
        throw new DomainException('Solo se pueden confirmar ordenes pendientes.');
    }
    if ($action === 'cancel' && $order['status'] === 'Atendida') {
        throw new DomainException('Una orden atendida no se puede anular.');
    }
    if ($action === 'cancel' && mb_strlen($cancelReason) < 5) {
        throw new DomainException('Indica un motivo de anulacion de al menos 5 caracteres.');
    }
    if ($action === 'cancel') {
        $derivedStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tbl_document_flow flow
             LEFT JOIN tbl_invoice invoice ON flow.target_type='invoice' AND invoice.id=flow.target_id
             LEFT JOIN tbl_receipt receipt ON flow.target_type='receipt' AND receipt.id=flow.target_id
             LEFT JOIN tbl_remission_guide guide ON flow.target_type='remission_guide' AND guide.id=flow.target_id
             WHERE flow.source_type='sales_order' AND flow.source_id=?
               AND ((flow.target_type='invoice' AND invoice.status NOT IN (2,4))
                 OR (flow.target_type='receipt' AND receipt.status NOT IN (2,4))
                 OR (flow.target_type='remission_guide' AND guide.status<>'Anulada'))"
        );
        $derivedStatement->execute([$orderId]);
        if ((int) $derivedStatement->fetchColumn() > 0) {
            throw new DomainException('La orden tiene documentos derivados (factura, boleta o guía); anúlalos primero para conservar la trazabilidad.');
        }
    }

    $details = $pdo->prepare('SELECT item_id, item_quantity FROM tbl_sales_order_detail WHERE sales_order_id = ?');
    $details->execute([$orderId]);
    foreach ($details->fetchAll() as $detail) {
        if ($action === 'confirm') {
            warehouse_apply_reservation_delta($pdo, (int) $detail['item_id'], (float) $detail['item_quantity'], $warehouseId);
        } elseif ($order['status'] === 'Confirmada') {
            warehouse_apply_reservation_delta($pdo, (int) $detail['item_id'], -(float) $detail['item_quantity'], $warehouseId);
        }
    }

    $pdo->prepare('UPDATE tbl_sales_order SET status = ?, last_update = NOW() WHERE id = ?')->execute([$newStatus, $orderId]);
    if ($action==='cancel' && app_document_engine_available($pdo)) app_reverse_document_lines($pdo,'sales_order',(int)$orderId,$userId);
    $historyComment = $action === 'confirm'
        ? 'Orden confirmada.'
        : 'Motivo: ' . $cancelReason;
    app_record_document_status($pdo, 'sales_order', (int) $orderId, (string) $order['number'], (string) $order['status'], $newStatus, $action === 'confirm' ? 'Confirmacion' : 'Anulacion', $userId, $historyComment);
    $pdo->commit();
    echo json_encode(['error' => false, 'status' => $newStatus, 'message' => 'Estado actualizado correctamente.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('APPU sales order error: ' . $exception->getMessage());
    app_api_error($exception, 'No se pudo procesar la orden de venta.');
}
