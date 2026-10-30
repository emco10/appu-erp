<?php

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/master-data-validation.php';
require_once '../../global/document-numbering.php';
require_once '../../global/sales-delivery.php';
require_once '../../global/sales-fulfillment.php';
require_once '../inventory/stock.php';

header('Content-Type: application/json; charset=utf-8');

$jsonResponse = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

/** Mantiene separado el estado comercial de la orden de su cumplimiento logistico. */
$syncSalesOrderDeliveryStatus = static function (PDO $pdo, int $salesOrderId): string {
    return app_sync_sales_order_delivery_status($pdo, $salesOrderId);
};

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$sourceConfig = [
    'invoice' => [
        'table' => 'tbl_invoice',
        'detail_table' => 'tbl_invoice_detail',
        'detail_key' => 'invoice_id',
        'number' => "CONCAT(series, '-', number)",
        'customer_name' => 'name',
        'warehouse_column' => 'warehouse_id',
        'shipping_select' => ', shipping_address_id, COALESCE(NULLIF(shipping_address,\'\'),address) AS shipping_address',
        'extra_select' => ', document_subtype, sales_order_id',
        'status_allowed' => static fn ($status): bool => !in_array((int)$status,[2,4],true),
    ],
    'receipt' => [
        'table' => 'tbl_receipt',
        'detail_table' => 'tbl_receipt_detail',
        'detail_key' => 'receipt_id',
        'number' => "CONCAT(series, '-', number)",
        'customer_name' => 'name',
        'warehouse_column' => 'warehouse_id',
        'shipping_select' => ', shipping_address_id, COALESCE(NULLIF(shipping_address,\'\'),address) AS shipping_address',
        'extra_select' => ", 'STANDARD' AS document_subtype, sales_order_id",
        'status_allowed' => static fn ($status): bool => !in_array((int)$status,[2,4],true),
    ],
    'sales_order' => [
        'table' => 'tbl_sales_order',
        'detail_table' => 'tbl_sales_order_detail',
        'detail_key' => 'sales_order_id',
        'number' => 'number',
        'customer_name' => 'customer_name',
        'warehouse_column' => 'warehouse_id',
        'shipping_select' => ', shipping_address_id, COALESCE(NULLIF(shipping_address,\'\'),address) AS shipping_address',
        'extra_select' => ", 'STANDARD' AS document_subtype, id AS sales_order_id",
        'status_allowed' => static fn ($status): bool => in_array((string) $status, ['Confirmada', 'Atendida'], true),
    ],
];

$loadSource = static function (PDO $pdo, string $sourceType, int $sourceId, array $sourceConfig): array {
    $warehouseSelect = !empty($sourceConfig['warehouse_column']) ? ', ' . $sourceConfig['warehouse_column'] . ' AS warehouse_id' : ', 1 AS warehouse_id';
    $shippingSelect = $sourceConfig['shipping_select'] ?? ', NULL AS shipping_address_id, address AS shipping_address';
    $extraSelect = $sourceConfig['extra_select'] ?? ", 'STANDARD' AS document_subtype, NULL AS sales_order_id";
    $statement = $pdo->prepare(
        "SELECT id, {$sourceConfig['number']} AS document_number,
                status, customer_id, ruc, {$sourceConfig['customer_name']} AS customer_name,
                address, delivery_date{$warehouseSelect}{$shippingSelect}{$extraSelect}
         FROM {$sourceConfig['table']}
         WHERE id = ?" . ($pdo->inTransaction() ? ' FOR UPDATE' : '')
    );
    $statement->execute([$sourceId]);
    $source = $statement->fetch();
    if (!$source || !$sourceConfig['status_allowed']($source['status'])) {
        throw new DomainException('El documento no existe, esta anulado o no permite generar una guia.');
    }
    if ($sourceType === 'invoice' && ($source['document_subtype'] ?? 'STANDARD') === 'ADVANCE') {
        throw new DomainException('Una factura de anticipo no representa mercancía pendiente de entrega.');
    }

    $detailStatement = $pdo->prepare(
        "SELECT detail.id AS source_detail_id,
                detail.item_id AS product_id,
                product.code AS item_code,
                COALESCE(NULLIF(detail.item_description, ''), detail.item_name, product.name) AS description,
                detail.item_quantity AS quantity
         FROM {$sourceConfig['detail_table']} detail
         JOIN tbl_product product ON product.id = detail.item_id
         WHERE detail.{$sourceConfig['detail_key']} = ?
         ORDER BY detail.id"
    );
    $detailStatement->execute([$sourceId]);
    $details = $detailStatement->fetchAll();
    if (!$details) {
        throw new DomainException('El documento no tiene productos validos para trasladar.');
    }

    $openLines = array_column(app_document_open_lines($pdo, $sourceType, $sourceId, 'FULFILLMENT'), null, 'id');
    foreach ($details as &$detail) {
        $line = $openLines[$detail['source_detail_id']] ?? null;
        $detail['ordered_quantity'] = (float) $detail['quantity'];
        $detail['delivered_quantity'] = $line ? (float) $line['processed_quantity'] : 0;
        $detail['pending_quantity'] = $line ? (float) $line['open_quantity'] : 0;
    }
    unset($detail);

    return ['source' => $source, 'details' => $details];
};

if ($method === 'GET') {
    try { app_require_document_engine($pdo); } catch (Throwable $error) { app_api_error($error,'No se pudo consultar las entregas.'); exit; }
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
    app_require_permission('logistics.view');

    if ($action === 'sources') {
        $sources = [];
        $queries = [
            "SELECT 'invoice' AS source_type, id, CONCAT(series, '-', number) AS document_number, name AS customer_name, status FROM tbl_invoice WHERE status NOT IN (2,4) AND document_subtype <> 'ADVANCE' ORDER BY id DESC LIMIT 50",
            "SELECT 'receipt' AS source_type, id, CONCAT(series, '-', number) AS document_number, name AS customer_name, status FROM tbl_receipt WHERE status NOT IN (2,4) ORDER BY id DESC LIMIT 50",
            "SELECT 'sales_order' AS source_type, id, number AS document_number, customer_name, status FROM tbl_sales_order orders WHERE status='Confirmada'
             AND NOT EXISTS (SELECT 1 FROM tbl_invoice invoice WHERE invoice.sales_order_id=orders.id AND invoice.status NOT IN (2,4) AND invoice.document_subtype<>'ADVANCE' AND invoice.inventory_origin<>'DELIVERY')
             AND NOT EXISTS (SELECT 1 FROM tbl_receipt receipt WHERE receipt.sales_order_id=orders.id AND receipt.status NOT IN (2,4)) ORDER BY id DESC LIMIT 50",
        ];
        foreach ($queries as $query) {
            foreach ($pdo->query($query)->fetchAll() as $source) {
                $sources[] = $source;
            }
        }
        usort($sources, static fn ($left, $right): int => (int) $right['id'] <=> (int) $left['id']);
        $jsonResponse(200, ['sources' => array_slice($sources, 0, 100), 'custom_fields'=>app_custom_field_definitions($pdo,'remission_guide'), 'custom_values'=>(object)app_custom_field_defaults($pdo,'remission_guide',[])]);
    }

    if ($action === 'source') {
        $sourceType = strtolower(trim((string) ($_GET['source_type'] ?? '')));
        $sourceId = filter_var($_GET['source_id'] ?? null, FILTER_VALIDATE_INT);
        if (!isset($sourceConfig[$sourceType]) || $sourceId === false || $sourceId < 1) {
            $jsonResponse(422, ['error' => 'Selecciona un documento valido.']);
        }
        try {
            $jsonResponse(200, $loadSource($pdo, $sourceType, $sourceId, $sourceConfig[$sourceType]));
        } catch (Throwable $exception) {
            error_log('APPU delivery error: ' . $exception->getMessage());
        app_api_error($exception, 'No se pudo procesar la entrega.');
        exit;
        }
    }

    if ($action === 'detail') {
        $guideId = filter_var($_GET['guide_id'] ?? null, FILTER_VALIDATE_INT);
        if ($guideId === false || $guideId < 1) {
            $jsonResponse(422, ['error' => 'Guia invalida.']);
        }
        $guideStatement = $pdo->prepare('SELECT * FROM tbl_remission_guide WHERE id = ?');
        $guideStatement->execute([$guideId]);
        $guide = $guideStatement->fetch();
        if (!$guide) {
            $jsonResponse(404, ['error' => 'Guia no encontrada.']);
        }
        $source=app_document_header($pdo,$guide['source_type'],(int)$guide['source_id']);
        $guide['source_number']=isset($source['series'])?$source['series'].'-'.$source['number']:$source['number'];
        $detailStatement = $pdo->prepare('SELECT * FROM tbl_remission_guide_detail WHERE remission_guide_id = ? ORDER BY id');
        $detailStatement->execute([$guideId]);
        $jsonResponse(200, ['guide' => $guide, 'details' => $detailStatement->fetchAll()]);
    }

    $statement = $pdo->query(
        "SELECT guide.id, guide.number, guide.status, guide.guide_type,
                guide.source_type, guide.source_id, guide.warehouse_id, warehouse.code AS warehouse_code, warehouse.name AS warehouse_name, guide.transfer_reason,
                guide.transport_mode, guide.transfer_date,
                guide.recipient_name, guide.destination_address,
                guide.vehicle_plate, guide.driver_name,
                guide.registration_date, guide.inventory_posted,
                (SELECT COALESCE(SUM(detail.quantity),0) FROM tbl_remission_guide_detail detail WHERE detail.remission_guide_id=guide.id) -
                (SELECT COALESCE(SUM(application.quantity),0) FROM tbl_document_line_application application WHERE application.source_type='remission_guide' AND application.source_id=guide.id AND application.stage='BILLING' AND application.status='ACTIVE') AS unbilled_quantity
         FROM tbl_remission_guide guide
         LEFT JOIN tbl_warehouse warehouse ON warehouse.id = guide.warehouse_id
         ORDER BY guide.id DESC"
    );
    $jsonResponse(200, ['guides' => $statement->fetchAll()]);
}

if ($method !== 'POST') {
    $jsonResponse(405, ['error' => 'Metodo no permitido.']);
}

app_require_permission('logistics.manage');
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

if ($action === 'advance') {
    $guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT);
    $nextStatus = trim((string) ($_POST['status'] ?? ''));
    $statusOrder = ['Registrada' => 'En transito', 'En transito' => 'Entregada'];
    if ($guideId === false || $guideId < 1 || !in_array($nextStatus, array_values($statusOrder), true)) {
        $jsonResponse(422, ['error' => 'Estado de guia invalido.']);
    }

    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT number, status, source_type, source_id FROM tbl_remission_guide WHERE id = ? FOR UPDATE');
        $statement->execute([$guideId]);
        $guide = $statement->fetch();
        if (!$guide || ($statusOrder[$guide['status']] ?? null) !== $nextStatus) {
            throw new DomainException('La guia debe avanzar al siguiente estado.');
        }
        $pdo->prepare('UPDATE tbl_remission_guide SET status = ?, last_update = NOW() WHERE id = ?')->execute([$nextStatus, $guideId]);
        app_record_document_status($pdo, 'remission_guide', (int) $guideId, (string) $guide['number'], (string) $guide['status'], $nextStatus, 'Cambio de estado', (int) $_SESSION['loggedInUser']['USERID']);
        $pdo->commit();
        $jsonResponse(200, ['message' => 'Estado de la guia actualizado.']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('APPU delivery error: ' . $exception->getMessage());
        app_api_error($exception, 'No se pudo procesar la entrega.');
        exit;
    }
}

if ($action === 'cancel') {
    try {
        $pdo->beginTransaction();
        $result = app_cancel_sales_delivery($pdo, (int) ($_POST['guide_id'] ?? 0), (int) $_SESSION['loggedInUser']['USERID'], (string) ($_POST['cancellation_reason'] ?? ''));
        $pdo->commit();
        $jsonResponse(200, $result);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('APPU delivery cancellation error: ' . $exception->getMessage());
        app_api_error($exception, 'No se pudo anular la entrega.');
        exit;
    }
}

if ($action !== 'create') {
    $jsonResponse(400, ['error' => 'Accion no valida.']);
}

$sourceType = strtolower(trim((string) ($_POST['source_type'] ?? '')));
$sourceId = filter_var($_POST['source_id'] ?? null, FILTER_VALIDATE_INT);
$sourceErrors=(!isset($sourceConfig[$sourceType]) || !$sourceId || $sourceId<1) ? [app_field_error('source_key','REQUIRED','Seleccione un documento de origen válido.')] : [];

$rules = [];
foreach (['sender_name'=>'Remitente','sender_address'=>'Direccion remitente','recipient_name'=>'Destinatario','recipient_address'=>'Direccion destinatario','origin_address'=>'Direccion de origen','destination_address'=>'Direccion destino'] as $name=>$label) $rules[$name]=['label'=>$label,'required'=>true,'maxLength'=>str_ends_with($name,'name')?200:300];
$rules['transfer_date']=['label'=>'Fecha de traslado','required'=>true,'customValidator'=>fn($value)=>app_valid_date($value)];
$errors=array_merge($sourceErrors,app_validate_fields($_POST,$rules));
$transferDate=is_string($_POST['transfer_date']??null)?$_POST['transfer_date']:'';
$requestedItems=$_POST['items']??[];
if (is_string($requestedItems)) $requestedItems=json_decode($requestedItems,true);
if (!is_array($requestedItems) || !array_is_list($requestedItems) || !$requestedItems) {
    $errors[]=app_field_error('items','REQUIRED','Seleccione al menos un producto y una cantidad.'); $requestedItems=[];
}
$requestedQuantities=[];
foreach ($requestedItems as $index=>$item) {
    $item=is_array($item)?$item:[];
    $line=filter_var($item['source_detail_id']??null,FILTER_VALIDATE_INT);
    $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_FLOAT);
    if (!$line || $line<1) $errors[]=app_field_error('items['.$index.'].source_detail_id','INVALID_REFERENCE','Seleccione una linea de origen valida.');
    if ($quantity===false || !is_finite((float)$quantity) || $quantity<=0 || $quantity>999999999) $errors[]=app_field_error('lines['.($line?:0).'].quantity','MIN','La cantidad debe ser mayor que cero y estar dentro del rango permitido.');
    else $requestedQuantities[$line]=($requestedQuantities[$line]??0)+(float)$quantity;
}
if ($errors) { app_api_error(new AppValidationException($errors),'No se pudo validar la entrega.'); exit; }

try {
    $pdo->beginTransaction();
    $cached=app_document_request_start($pdo,'create_delivery',$_POST,(int)$_SESSION['loggedInUser']['USERID']);
    if ($cached!==null) { $pdo->commit(); $jsonResponse(201,$cached); }
    app_lock_delivery_source($pdo, $sourceType, (int) $sourceId);
    $sourceData = $loadSource($pdo, $sourceType, $sourceId, $sourceConfig[$sourceType]);
    $source = $sourceData['source'];
    $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
    $warehouseId = $warehouseId === false || $warehouseId <= 0 ? (int) ($source['warehouse_id'] ?? 1) : (int) $warehouseId;
    $warehouseStatement = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id = ? AND active_status = 1');
    $warehouseStatement->execute([$warehouseId]);
    if (!$warehouseStatement->fetchColumn()) {
        throw new DomainException('El almacen seleccionado no existe o esta inactivo.');
    }
    app_validate_customer_reference($pdo, $source['customer_id']);
    $details = $sourceData['details'];
    $detailsBySourceId = [];
    foreach ($details as $detail) {
        $detailsBySourceId[(int) $detail['source_detail_id']] = $detail;
    }
    foreach ($requestedQuantities as $sourceDetailId => $quantity) {
        if (!isset($detailsBySourceId[$sourceDetailId])) {
            throw new DomainException('El producto no pertenece al documento de origen.');
        }
        if ($quantity > (float) $detailsBySourceId[$sourceDetailId]['pending_quantity']) {
            throw new AppValidationException([app_field_error('lines['.$sourceDetailId.'].quantity','OPEN_QUANTITY','Solo quedan ' . (float)$detailsBySourceId[$sourceDetailId]['pending_quantity'] . ' unidades pendientes.')]);
        }
    }
    $userId = (int) $_SESSION['loggedInUser']['USERID'];
    $guideNumber = app_next_document_number($pdo, 'REMISSION_GUIDE', 'GI');
    $insert = $pdo->prepare(
        'INSERT INTO tbl_remission_guide
            (number, status, guide_type, source_type, source_id, warehouse_id, destination_address_id, transfer_reason_code,
             transfer_reason, transport_mode, issue_date, transfer_date, sender_ruc,
             sender_name, sender_address, sender_ubigeo, recipient_ruc, recipient_name,
             recipient_address, recipient_ubigeo, origin_address, origin_ubigeo,
             origin_establishment_code, destination_address, destination_ubigeo,
             destination_establishment_code, carrier_ruc, carrier_name, vehicle_plate,
             vehicle_brand, driver_doc_type, driver_doc_number, driver_name, driver_license,
             notes, created_by)
        VALUES (:number, :status, :guide_type, :source_type, :source_id, :warehouse_id, :destination_address_id, :reason_code,
                 :reason, :transport_mode, CURDATE(), :transfer_date, :sender_ruc,
                 :sender_name, :sender_address, :sender_ubigeo, :recipient_ruc,
                 :recipient_name, :recipient_address, :recipient_ubigeo, :origin_address,
                 :origin_ubigeo, :origin_establishment_code, :destination_address,
                 :destination_ubigeo, :destination_establishment_code, :carrier_ruc,
                 :carrier_name, :vehicle_plate, :vehicle_brand, :driver_doc_type,
                 :driver_doc_number, :driver_name, :driver_license, :notes, :created_by)'
    );
    $insert->execute([
        'number' => $guideNumber,
        'status' => 'Registrada',
        'guide_type' => 'REMITENTE',
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'warehouse_id' => $warehouseId,
        'destination_address_id' => $source['shipping_address_id'] ?: null,
        'reason_code' => trim((string) ($_POST['transfer_reason_code'] ?? 'VENTA')),
        'reason' => trim((string) ($_POST['transfer_reason'] ?? 'Venta')),
        'transport_mode' => trim((string) ($_POST['transport_mode'] ?? 'PRIVADO')),
        'transfer_date' => $transferDate,
        'sender_ruc' => trim((string) ($_POST['sender_ruc'] ?? '')),
        'sender_name' => trim((string) $_POST['sender_name']),
        'sender_address' => trim((string) $_POST['sender_address']),
        'sender_ubigeo' => trim((string) ($_POST['sender_ubigeo'] ?? '')),
        'recipient_ruc' => trim((string) ($_POST['recipient_ruc'] ?? $source['ruc'])),
        'recipient_name' => trim((string) $_POST['recipient_name']),
        'recipient_address' => trim((string) $_POST['recipient_address']),
        'recipient_ubigeo' => trim((string) ($_POST['recipient_ubigeo'] ?? '')),
        'origin_address' => trim((string) $_POST['origin_address']),
        'origin_ubigeo' => trim((string) ($_POST['origin_ubigeo'] ?? '')),
        'origin_establishment_code' => trim((string) ($_POST['origin_establishment_code'] ?? '')),
        'destination_address' => trim((string) $_POST['destination_address']),
        'destination_ubigeo' => trim((string) ($_POST['destination_ubigeo'] ?? '')),
        'destination_establishment_code' => trim((string) ($_POST['destination_establishment_code'] ?? '')),
        'carrier_ruc' => trim((string) ($_POST['carrier_ruc'] ?? '')),
        'carrier_name' => trim((string) ($_POST['carrier_name'] ?? '')),
        'vehicle_plate' => trim((string) ($_POST['vehicle_plate'] ?? '')),
        'vehicle_brand' => trim((string) ($_POST['vehicle_brand'] ?? '')),
        'driver_doc_type' => trim((string) ($_POST['driver_doc_type'] ?? '')),
        'driver_doc_number' => trim((string) ($_POST['driver_doc_number'] ?? '')),
        'driver_name' => trim((string) ($_POST['driver_name'] ?? '')),
        'driver_license' => trim((string) ($_POST['driver_license'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'created_by' => $userId,
    ]);
    $guideId = (int) $pdo->lastInsertId();
    $detailInsert = $pdo->prepare(
        'INSERT INTO tbl_remission_guide_detail
            (remission_guide_id, source_detail_id, product_id, item_code, description, unit_code, quantity)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($requestedQuantities as $sourceDetailId => $quantity) {
        $detail = $detailsBySourceId[$sourceDetailId];
        if ((int) $detail['product_id'] <= 0 || $quantity <= 0) {
            throw new DomainException('La guia contiene un producto invalido.');
        }
        $detailInsert->execute([$guideId, $sourceDetailId, (int) $detail['product_id'], (string) $detail['item_code'], (string) $detail['description'], 'NIU', $quantity]);
    }

    $reserveSalesOrderId = $sourceType === 'invoice' && ($source['document_subtype'] ?? 'STANDARD') === 'RESERVE'
        ? (int) ($source['sales_order_id'] ?? 0) : 0;
    app_post_sales_delivery($pdo, $guideId, $userId);

    app_save_custom_fields($pdo, 'remission_guide', $guideId, is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : []);
    app_record_document_status($pdo, 'remission_guide', $guideId, $guideNumber, '', 'Registrada', 'Creacion', $userId, 'Origen: ' . $source['document_number']);
    app_record_document_flow($pdo, $sourceType, (int) $sourceId, 'remission_guide', $guideId, $userId, 'DELIVERY', array_sum($requestedQuantities));
    $deliveryStatus = $sourceType === 'sales_order'
        ? $syncSalesOrderDeliveryStatus($pdo, (int) $sourceId)
        : ($reserveSalesOrderId > 0 ? $syncSalesOrderDeliveryStatus($pdo, $reserveSalesOrderId) : null);
    $result=['guide_id' => $guideId, 'number' => $guideNumber, 'delivery_status' => $deliveryStatus, 'message' => 'Entrega registrada correctamente.'];
    app_document_request_finish($pdo,'create_delivery',$_POST,$userId,'remission_guide',$guideId,$result);
    $pdo->commit();
    $jsonResponse(201, $result);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU remission guide error: ' . $exception->getMessage());
    app_api_error($exception, 'No se pudo procesar la entrega.');
    exit;
}
