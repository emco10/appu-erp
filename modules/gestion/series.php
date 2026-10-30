<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
app_require_permission($method === 'GET' ? 'management.view' : 'management.manage');
if ($method === 'GET') { echo json_encode(['series' => $pdo->query("SELECT id,document_type,series,next_number,number_padding,active_status FROM tbl_document_series ORDER BY document_type,series")->fetchAll()], JSON_UNESCAPED_UNICODE); exit; }
$allowedTypes = ['INVOICE','RECEIPT','CREDIT_NOTE','QUOTATION','SALES_ORDER','PURCHASE','PURCHASE_REQUEST','ORDER','GOODS_RECEIPT','REMISSION_GUIDE','INCOMING_PAYMENT','OUTGOING_PAYMENT','STOCK_TRANSFER','LANDED_COST','INVENTORY_ENTRY','INVENTORY_ISSUE'];
if (strtolower(trim((string)($_POST['action'] ?? ''))) === 'create') {
    $type = strtoupper(trim((string)($_POST['document_type'] ?? '')));
    $series = strtoupper(trim((string)($_POST['series'] ?? '')));
    $next = filter_var($_POST['next_number'] ?? null, FILTER_VALIDATE_INT);
    $padding = filter_var($_POST['number_padding'] ?? null, FILTER_VALIDATE_INT);
    $active = (int)($_POST['active_status'] ?? 1) === 1 ? 1 : 0;
    if (!in_array($type, $allowedTypes, true) || !preg_match('/^[A-Z0-9-]{1,10}$/', $series) || $next === false || $padding === false || $next < 1 || $padding < 1 || $padding > 12) {
        http_response_code(422); echo json_encode(['error'=>'Datos de serie invalidos.'], JSON_UNESCAPED_UNICODE); exit;
    }
    try {
        $statement = $pdo->prepare('INSERT INTO tbl_document_series (document_type,series,next_number,number_padding,active_status) VALUES (?,?,?,?,?)');
        $statement->execute([$type, $series, $next, $padding, $active]);
    } catch (PDOException $exception) {
        if ((int)$exception->errorInfo[1] === 1062) { http_response_code(409); echo json_encode(['error'=>'La serie ya existe para ese tipo de documento.'], JSON_UNESCAPED_UNICODE); exit; }
        throw $exception;
    }
    echo json_encode(['message'=>'Serie creada correctamente.'], JSON_UNESCAPED_UNICODE); exit;
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT); $next = filter_var($_POST['next_number'] ?? null, FILTER_VALIDATE_INT); $padding = filter_var($_POST['number_padding'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $next === false || $padding === false || $id < 1 || $next < 1 || $padding < 1 || $padding > 12) { http_response_code(422); echo json_encode(['error'=>'Valores de serie inválidos.']); exit; }
try{
 $pdo->beginTransaction();$currentStatement = $pdo->prepare('SELECT next_number FROM tbl_document_series WHERE id = ? FOR UPDATE'); $currentStatement->execute([$id]); $currentNext = $currentStatement->fetchColumn();
 if ($currentNext === false) {$pdo->rollBack();http_response_code(404); echo json_encode(['error'=>'La serie no existe.'], JSON_UNESCAPED_UNICODE); exit; }
 if ($next < (int) $currentNext) {$pdo->rollBack();http_response_code(422); echo json_encode(['error'=>'El siguiente número no puede retroceder.'], JSON_UNESCAPED_UNICODE); exit; }
 $statement = $pdo->prepare('UPDATE tbl_document_series SET next_number=?, number_padding=?, active_status=? WHERE id=?'); $statement->execute([$next,$padding,((int)($_POST['active_status'] ?? 0) === 1 ? 1 : 0),$id]);$pdo->commit();echo json_encode(['message'=>'Serie actualizada correctamente.'], JSON_UNESCAPED_UNICODE);
}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();error_log('APPU series configuration: '.$exception->getMessage());http_response_code(422);echo json_encode(['error'=>'No se pudo actualizar la serie. Reintenta la operación.'],JSON_UNESCAPED_UNICODE);}
