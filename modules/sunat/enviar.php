<?php

declare(strict_types=1);

require '../../global/connection.php';
require_once '../../global/sunat-api.php';

header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.manage');

$type = trim((string) ($_POST['source_type'] ?? ''));
$id = filter_var($_POST['source_id'] ?? null, FILTER_VALIDATE_INT);
$documents = [
    'invoice' => ['table' => 'tbl_invoice', 'series_type' => 'INVOICE'],
    'receipt' => ['table' => 'tbl_receipt', 'series_type' => 'RECEIPT'],
    'credit-note' => ['table' => 'tbl_credit_note', 'series_type' => 'CREDIT_NOTE'],
];

if (!isset($documents[$type]) || $id === false || $id < 1) {
    http_response_code(422);
    echo json_encode(['message' => 'Selecciona un comprobante válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $documentConfig = $documents[$type];
    $document = $pdo->prepare("SELECT series, number, status FROM {$documentConfig['table']} WHERE id=?");
    $document->execute([$id]);
    $row = $document->fetch();
    if (!$row) {
        throw new DomainException('El comprobante no existe.');
    }
    if (in_array((int) $row['status'], [2, 4], true)) {
        throw new DomainException('Un comprobante anulado o cancelado no puede enviarse a SUNAT.');
    }
    $series = $pdo->prepare('SELECT sunat_enabled FROM tbl_document_series WHERE document_type=? AND series=? AND active_status=1');
    $series->execute([$documentConfig['series_type'], $row['series']]);
    if ((int) $series->fetchColumn() !== 1) {
        throw new DomainException('La serie seleccionada es interna y no se envía a SUNAT.');
    }

    $idempotencyKey = 'appu-' . $type . '-' . $id . '-' . substr(hash('sha256', $row['series'] . '-' . $row['number']), 0, 24);
    $response = app_sunat_api_request(
        'POST',
        '/api/v1/documents/' . rawurlencode($type) . '/' . $id . '/submit',
        ['Idempotency-Key: ' . $idempotencyKey]
    );
    if ($response['status'] < 200 || $response['status'] >= 300) {
        $apiMessage = (string) ($response['body']['error']['message'] ?? 'El servicio CPE rechazó la solicitud.');
        throw new DomainException($apiMessage);
    }

    echo json_encode([
        'message' => 'SUNAT Beta procesó el comprobante.',
        'sunat' => $response['body']['data'] ?? null,
        'replayed' => (bool) ($response['body']['replayed'] ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DomainException $exception) {
    http_response_code(422);
    echo json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('APPU SUNAT API error: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode(['message' => 'No se pudo completar el envío a SUNAT Beta. Revisa el Panel SUNAT antes de reintentar.'], JSON_UNESCAPED_UNICODE);
}
