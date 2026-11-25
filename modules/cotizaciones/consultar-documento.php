<?php

require '../../global/connection.php';
require_once '../../global/quotation-document.php';
require_once '../../global/validation.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'La consulta requiere el método GET.'], JSON_UNESCAPED_UNICODE);
    return;
}

try {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new DomainException('Selecciona una cotización válida.');
    }

    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    $document = app_quotation_snapshot($pdo, $id);
    if ($document === null) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => true, 'message' => 'La cotización seleccionada no existe.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $document['custom_fields'] = app_quotation_custom_fields($pdo);
    $document['custom_values'] = (object) array_intersect_key(
        (array) $document['custom_values'],
        array_column($document['custom_fields'], 'id', 'id')
    );
    $pdo->commit();
    echo json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$exception instanceof DomainException) {
        error_log('APPU quotation read error: ' . $exception->getMessage());
    }
    app_api_error($exception, 'No se pudo consultar la cotización.');
}
