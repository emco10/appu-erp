<?php

require '../../global/connection.php';
require_once '../../global/document-history.php';

app_require_permission('purchases.manage');

$orderId = filter_var($_POST['ID_ORDEN'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId < 1) {
    http_response_code(422);
    echo false;
    exit;
}
$cancellationReason = trim((string) ($_POST['reason'] ?? ''));
if (mb_strlen($cancellationReason) < 5 || mb_strlen($cancellationReason) > 500) {
    http_response_code(422);
    echo false;
    exit;
}

try {
    $pdo->beginTransaction();
    $requestPreview = $pdo->prepare('SELECT purchase_request_id FROM tbl_order WHERE id = ?');
    $requestPreview->execute([$orderId]);
    $requestIdValue = $requestPreview->fetchColumn();
    if ($requestIdValue === false) {
        throw new RuntimeException('Orden inexistente.');
    }
    $requestId = (int) $requestIdValue;

    $purchaseRequest = null;
    if ($requestId > 0) {
        $requestStatement = $pdo->prepare('SELECT id, number, status FROM tbl_purchase_request WHERE id = ? FOR UPDATE');
        $requestStatement->execute([$requestId]);
        $purchaseRequest = $requestStatement->fetch();
        if (!$purchaseRequest) {
            throw new RuntimeException('Solicitud de compra origen inexistente.');
        }
    }

    $statusStatement = $pdo->prepare('SELECT number, status, purchase_request_id FROM tbl_order WHERE id = ? FOR UPDATE');
    $statusStatement->execute([$orderId]);
    $document = $statusStatement->fetch();

    if (!$document) {
        throw new RuntimeException('Orden inexistente.');
    }
    if ((int) ($document['purchase_request_id'] ?? 0) !== $requestId) {
        throw new DomainException('La solicitud de origen cambió. Vuelve a intentar la anulación.');
    }

    $previousStatus = $document['status'];
    $purchaseStatement = $pdo->prepare("SELECT number FROM tbl_purchase WHERE purchase_order_id = ? AND status NOT IN ('Anulado', 'Cancelada') LIMIT 1");
    $purchaseStatement->execute([$orderId]);
    if ($purchaseStatement->fetchColumn()) {
        throw new DomainException('La orden de compra está vinculada a una compra activa; anula primero la compra relacionada.');
    }
    if ($previousStatus === 'Aprobado') {
        app_require_permission('purchases.approve');
    }

    if ($previousStatus !== 'Anulado') {
        $pdo->prepare("UPDATE tbl_order SET status = 'Anulado', last_update = NOW() WHERE id = ?")->execute([$orderId]);
        $historyStatement = $pdo->prepare(
            "INSERT INTO tbl_approval_history
                (document_type,document_id,previous_status,new_status,comment,user_id)
             VALUES ('order',?,?,'Anulado',?,?)"
        );
        $historyStatement->execute([$orderId, $previousStatus, $cancellationReason, (int) $_SESSION['loggedInUser']['USERID']]);
        app_record_document_status($pdo, 'order', (int) $orderId, (string) $document['number'], (string) $previousStatus, 'Anulado', 'Anulacion', (int) $_SESSION['loggedInUser']['USERID'], $cancellationReason);
    }

    if ($purchaseRequest && $purchaseRequest['status'] === 'Convertida') {
        $activeOrderStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM tbl_order
             WHERE type = 1 AND purchase_request_id = ? AND id <> ?
               AND status NOT IN ('Anulado', 'Cancelada')"
        );
        $activeOrderStatement->execute([$requestId, $orderId]);
        if ((int) $activeOrderStatement->fetchColumn() === 0) {
            $reopenRequest = $pdo->prepare("UPDATE tbl_purchase_request SET status = 'Aprobada', last_update = NOW() WHERE id = ? AND status = 'Convertida'");
            $reopenRequest->execute([$requestId]);
            if ($reopenRequest->rowCount() === 1) {
                app_record_document_status(
                    $pdo,
                    'purchase_request',
                    $requestId,
                    (string) $purchaseRequest['number'],
                    'Convertida',
                    'Aprobada',
                    'Reapertura',
                    (int) $_SESSION['loggedInUser']['USERID'],
                    'Orden de compra anulada: ' . $cancellationReason
                );
            }
        }
    }

    $pdo->commit();
    echo true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('APPU order cancellation error: ' . $exception->getMessage());
    http_response_code(422);
    echo false;
}
