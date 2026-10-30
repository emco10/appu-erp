<?php
require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/master-data-validation.php';
require_once '../../global/document-numbering.php';

header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$json = static function (int $status, array $payload): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; };

if ($method === 'GET') {
    app_require_permission('purchases.view');
    if (isset($_GET['employees'])) {
        $employees = $pdo->query("SELECT id, CONCAT_WS(' ', name, last_name_1, last_name_2) AS text FROM tbl_employee WHERE active_status = 1 ORDER BY name, last_name_1")->fetchAll();
        array_unshift($employees, ['id' => '', 'text' => 'Seleccione un empleado']);
        $json(200, ['employees' => $employees]);
    }
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null && $id > 0) {
        $statement = $pdo->prepare("SELECT request.*, CONCAT_WS(' ', employee.name, employee.last_name_1, employee.last_name_2) AS requester_name FROM tbl_purchase_request request LEFT JOIN tbl_employee employee ON employee.id = request.requester_id WHERE request.id = ? LIMIT 1");
        $statement->execute([$id]);
        $request = $statement->fetch();
        if (!$request) $json(404, ['error' => 'Solicitud no encontrada.']);
        $details = $pdo->prepare('SELECT request_detail.*, product.unit_value AS unit_value FROM tbl_purchase_request_detail request_detail LEFT JOIN tbl_product product ON product.id = request_detail.product_id WHERE request_detail.request_id = ? ORDER BY request_detail.id');
        $details->execute([$id]);
        $request['details'] = $details->fetchAll();
        $custom=$pdo->prepare("SELECT field_definition_id,field_value FROM tbl_custom_field_value WHERE entity='purchase_request' AND record_id=?");$custom->execute([$id]);$request['custom_fields']=[];foreach($custom->fetchAll() as $value)$request['custom_fields'][(string)$value['field_definition_id']]=$value['field_value'];
        $json(200, ['request' => $request]);
    }
    $status = trim((string)($_GET['status'] ?? ''));
    $where = $status !== '' ? ' WHERE request.status = :status' : '';
    $statement = $pdo->prepare("SELECT request.id, request.number, request.status, request.issue_date, request.required_date, request.currency, request.total_estimate, request.observation, CONCAT_WS(' ', employee.name, employee.last_name_1, employee.last_name_2) AS requester_name FROM tbl_purchase_request request LEFT JOIN tbl_employee employee ON employee.id = request.requester_id{$where} ORDER BY request.id DESC");
    if ($status !== '') $statement->bindValue(':status', $status);
    $statement->execute();
    $json(200, ['requests' => $statement->fetchAll()]);
}

app_require_permission('purchases.manage');
$action = strtolower(trim((string)($_POST['action'] ?? 'save')));
try {
    if ($action === 'cancel') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $cancellationReason = trim((string) ($_POST['reason'] ?? ''));
        if ($id === false || $id < 1) throw new InvalidArgumentException('Solicitud inválida.');
        if (mb_strlen($cancellationReason) < 5 || mb_strlen($cancellationReason) > 500) throw new InvalidArgumentException('Indica un motivo de cancelacion de al menos 5 caracteres.');
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT number,status FROM tbl_purchase_request WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $request = $statement->fetch();
        if (!$request) throw new RuntimeException('Solicitud no encontrada.');
        if ($request['status'] === 'Convertida') {
            throw new DomainException('La solicitud ya fue convertida en una orden de compra y no se puede cancelar desde aquí.');
        }
        if ($request['status'] !== 'Cancelada') {
            $pdo->prepare("UPDATE tbl_purchase_request SET status = 'Cancelada', last_update = NOW() WHERE id = ?")->execute([$id]);
            app_record_document_status($pdo, 'purchase_request', (int)$id, (string)$request['number'], (string)$request['status'], 'Cancelada', 'Cancelacion', (int)$_SESSION['loggedInUser']['USERID'], $cancellationReason);
        }
        $pdo->commit();
        $json(200, ['message' => 'Solicitud cancelada correctamente.']);
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $requesterId = filter_var($_POST['requester_id'] ?? 0, FILTER_VALIDATE_INT);
    $issueDate = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
    $requiredDate = trim((string)($_POST['required_date'] ?? $issueDate));
    $currency = trim((string)($_POST['currency'] ?? 'PEN')) ?: 'PEN';
    $observation = trim((string)($_POST['observation'] ?? ''));
    $details = json_decode((string)($_POST['details'] ?? ''), true);
    if (!is_array($details) || $details === []) throw new InvalidArgumentException('Agrega al menos un artículo a la solicitud.');
    $issueObject=DateTimeImmutable::createFromFormat('!Y-m-d',$issueDate);$requiredObject=DateTimeImmutable::createFromFormat('!Y-m-d',$requiredDate);
    if(!$issueObject||$issueObject->format('Y-m-d')!==$issueDate||!$requiredObject||$requiredObject->format('Y-m-d')!==$requiredDate)throw new InvalidArgumentException('Las fechas de la solicitud no son validas.');
    if($requiredObject<$issueObject)throw new InvalidArgumentException('La fecha requerida no puede ser anterior a la fecha de solicitud.');
    $currency=strtoupper($currency);if(!in_array($currency,['PEN','USD'],true))throw new InvalidArgumentException('La moneda seleccionada no es valida.');
    if ($requesterId === false || $requesterId < 1) throw new InvalidArgumentException('Selecciona un solicitante válido.');
    $requesterStatement = $pdo->prepare('SELECT id FROM tbl_employee WHERE id = ? AND active_status=1 LIMIT 1');
    $requesterStatement->execute([$requesterId]);
    if (!$requesterStatement->fetchColumn()) throw new InvalidArgumentException('El solicitante seleccionado no existe.');
    $normalized = [];
    $total = 0.0;
    $seenProducts=[];$productStatement=$pdo->prepare('SELECT code,name,description FROM tbl_product WHERE id=? AND active_status=1');
    foreach ($details as $detail) {
        $productId = filter_var($detail['product_id'] ?? 0, FILTER_VALIDATE_INT); $productId = $productId === false ? 0 : $productId;
        $quantity = filter_var($detail['quantity'] ?? null, FILTER_VALIDATE_INT);
        $price = filter_var($detail['estimated_unit_price'] ?? 0, FILTER_VALIDATE_FLOAT); $price = $price === false ? 0 : $price;
        $name = trim((string)($detail['item_name'] ?? ''));
        if ($productId < 1 || $name === '' || $quantity === false || $quantity <= 0 || $price < 0) throw new InvalidArgumentException('Detalle de solicitud inválido.');
        if(isset($seenProducts[$productId]))throw new InvalidArgumentException('No repitas un articulo; consolida la cantidad en una sola linea.');$seenProducts[$productId]=true;
        app_validate_product_reference($pdo, $productId);$productStatement->execute([$productId]);$product=$productStatement->fetch();
        $line = $quantity * $price; $total += $line;
        $normalized[] = [$productId, (string)$product['code'], (string)$product['name'], (string)$product['description'], $quantity, $price, $line];
    }
    $pdo->beginTransaction();
    if ($id === false || $id === null || $id < 1) {
        $number = app_next_document_number($pdo, 'PURCHASE_REQUEST', 'SC');
        $statement = $pdo->prepare("INSERT INTO tbl_purchase_request (number,status,requester_id,issue_date,required_date,currency,observation,total_estimate,created_by) VALUES (?, 'Pendiente', ?, ?, ?, ?, ?, ?, ?)");
        $statement->execute([$number, $requesterId ?: 0, $issueDate, $requiredDate, $currency, $observation, $total, (int)$_SESSION['loggedInUser']['USERID']]);
        $id = (int)$pdo->lastInsertId();
        $previous = '';
    } else {
        $lock = $pdo->prepare('SELECT number,status FROM tbl_purchase_request WHERE id = ? FOR UPDATE'); $lock->execute([$id]); $existing = $lock->fetch();
        if (!$existing) throw new RuntimeException('Solicitud no encontrada.');
        if ($existing['status'] !== 'Pendiente') throw new DomainException('Solo se pueden editar solicitudes pendientes.');
        $number = $existing['number']; $previous = $existing['status'];
        $pdo->prepare('UPDATE tbl_purchase_request SET requester_id=?,issue_date=?,required_date=?,currency=?,observation=?,total_estimate=?,last_update=NOW() WHERE id=?')->execute([$requesterId ?: 0, $issueDate, $requiredDate, $currency, $observation, $total, $id]);
        $pdo->prepare('DELETE FROM tbl_purchase_request_detail WHERE request_id = ?')->execute([$id]);
    }
    $insert = $pdo->prepare('INSERT INTO tbl_purchase_request_detail (request_id,product_id,item_code,item_name,description,quantity,estimated_unit_price,line_total) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($normalized as $row) $insert->execute(array_merge([$id], $row));
    app_save_custom_fields($pdo, 'purchase_request', (int) $id, is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : []);
    if ($previous === '') app_record_document_status($pdo, 'purchase_request', (int)$id, $number, '', 'Pendiente', 'Creacion', (int)$_SESSION['loggedInUser']['USERID'], 'Solicitud creada.');
    $pdo->commit();
    $json(200, ['id' => $id, 'number' => $number, 'message' => 'Solicitud de compra guardada correctamente.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU purchase request error: ' . $exception->getMessage());
    $json(422, ['error' => $exception->getMessage()]);
}
