<?php
require '../../global/connection.php';
require_once '../../global/business-partner-details.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $partnerId = filter_var($_REQUEST['partner_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$partnerId) throw new InvalidArgumentException('Selecciona primero un cliente guardado.');
    $action = (string) ($_REQUEST['action'] ?? 'list');
    if ($action === 'list') $result = app_partner_details($pdo, 'customer', $partnerId);
    elseif ($action === 'save_address') $result = ['id' => app_partner_save_address($pdo, 'customer', $partnerId, $_POST)];
    elseif ($action === 'save_contact') $result = ['id' => app_partner_save_contact($pdo, 'customer', $partnerId, $_POST)];
    else throw new InvalidArgumentException('Acción no válida.');
    echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
