<?php
require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/sales-document-validation.php';

app_require_permission('sales.manage');

$quotationId = filter_var($_POST['ID_COTIZ'] ?? null, FILTER_VALIDATE_INT);
$cancellationReason = trim((string) ($_POST['reason'] ?? ''));
if ($quotationId === false || $quotationId < 1) { http_response_code(422); echo false; exit; }
if (mb_strlen($cancellationReason) < 5 || mb_strlen($cancellationReason) > 500) { http_response_code(422); echo false; exit; }

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT number, status FROM tbl_quotation WHERE id = ? FOR UPDATE');
    $statement->execute([$quotationId]);
    $quotation = $statement->fetch();
    if (!$quotation) throw new RuntimeException('Cotizacion inexistente.');
    $previousStatus = (int) $quotation['status'];
    if(app_quotation_has_active_sale($pdo,(int)$quotationId))throw new DomainException('Anula primero el documento de venta relacionado con esta cotización.');
    $derivedStatement = $pdo->prepare("SELECT COUNT(*) FROM tbl_document_flow flow INNER JOIN tbl_sales_order sales_order ON sales_order.id=flow.target_id WHERE flow.source_type='quotation' AND flow.source_id=? AND flow.target_type='sales_order' AND sales_order.status<>'Anulada'");
    $derivedStatement->execute([$quotationId]);
    if ((int) $derivedStatement->fetchColumn() > 0) {
        throw new DomainException('La cotizacion ya genero una orden de venta; anula primero la orden para conservar la trazabilidad.');
    }
    if ($previousStatus !== 2) {
        $pdo->prepare("UPDATE tbl_quotation SET status = 2, last_update = NOW() WHERE id = ?")->execute([$quotationId]);
        app_record_document_status($pdo, 'quotation', (int) $quotationId, (string) $quotation['number'], app_sales_status_label($previousStatus), 'Anulado', 'Anulacion', (int) $_SESSION['loggedInUser']['USERID'], $cancellationReason);
    }
    $pdo->commit();
    echo true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU quotation cancellation error: ' . $exception->getMessage());
    http_response_code(422);
    echo false;
}
