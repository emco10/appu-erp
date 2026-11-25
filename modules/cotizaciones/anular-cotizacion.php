<?php

require '../../global/connection.php';
require_once '../../global/quotation-document.php';
require_once '../../global/validation.php';

app_require_permission('sales.manage');

$jsonResponse = ($_POST['response_format'] ?? '') === 'json';
try {
    $quotationId = filter_var($_POST['ID_COTIZ'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($quotationId === false) {
        throw new DomainException('Selecciona una cotización válida.');
    }
    $reason = $_POST['reason'] ?? '';
    if (!is_string($reason) || mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 500) {
        throw new DomainException('Indica un motivo de anulación de 5 a 500 caracteres.');
    }
    $reason = trim($reason);

    $pdo->beginTransaction();
    $snapshot = app_quotation_snapshot($pdo, $quotationId, true);
    if ($snapshot === null) {
        throw new DomainException('La cotización no existe.');
    }
    $quotation = $snapshot['quotation'];
    $previousStatus = (int) $quotation['status'];
    if ($previousStatus !== 2) {
        $version = $_POST['version'] ?? '';
        if (($jsonResponse || $version !== '') && (!is_string($version) || !hash_equals($snapshot['version'], $version))) {
            throw new DomainException('La cotización cambió desde que la abriste. Vuelve a consultarla antes de anular.', 409);
        }
        if (app_quotation_has_active_documents($pdo, $quotationId, true)) {
            throw new DomainException('Anula primero el documento de venta relacionado con esta cotización.');
        }
        $pdo->prepare('UPDATE tbl_quotation SET status = 2, last_update = NOW() WHERE id = ?')->execute([$quotationId]);
        app_record_document_status($pdo, 'quotation', $quotationId, $quotation['number'], app_sales_status_label($previousStatus), 'Anulado', 'Anulacion', (int) $_SESSION['loggedInUser']['USERID'], $reason);
    }
    $pdo->commit();

    if ($jsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => false]);
        return;
    }
    echo true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$exception instanceof DomainException) {
        error_log('APPU quotation cancellation error: ' . $exception->getMessage());
    }
    if ($jsonResponse) {
        app_api_error($exception, 'No se pudo anular la cotización.');
        return;
    }
    http_response_code(422);
    echo false;
}
