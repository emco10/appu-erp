<?php

declare(strict_types=1);

require '../../global/connection.php';
require_once '../../global/sunat-api.php';

header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.manage');

$type = trim((string) ($_POST['source_type'] ?? ''));
$id = filter_var($_POST['source_id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($type, ['invoice', 'receipt', 'credit-note', 'despatch'], true) || $id === false || $id < 1) {
    http_response_code(422);
    echo json_encode(['message' => 'Selecciona un comprobante válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = app_sunat_submit_document($pdo, $type, (int) $id);
    if (!$result['automatic']) {
        throw new DomainException('La serie seleccionada es interna y no se envía a SUNAT.');
    }
    $response = $result['response'];
    echo json_encode([
        'message' => 'SUNAT Beta procesó el comprobante.',
        'sunat' => $response['data'] ?? null,
        'replayed' => (bool) ($response['replayed'] ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DomainException $exception) {
    http_response_code(422);
    echo json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('APPU SUNAT API error: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode(['message' => 'No se pudo completar el envío a SUNAT Beta. Revisa el Panel SUNAT antes de reintentar.'], JSON_UNESCAPED_UNICODE);
}
