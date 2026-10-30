<?php

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/purchase-receipt.php';

app_require_permission('purchases.manage');

$purchaseId = filter_var($_POST['ID_ORDEN'] ?? null, FILTER_VALIDATE_INT);
$cancellationReason = trim((string) ($_POST['reason'] ?? ''));
if ($purchaseId === false) {
    http_response_code(422);
    echo false;
    exit;
}
if (mb_strlen($cancellationReason) < 5 || mb_strlen($cancellationReason) > 500) {
    http_response_code(422);
    echo false;
    exit;
}

try {
    $pdo->beginTransaction();
    $statusStatement = $pdo->prepare('SELECT number, status, purchase_order_id FROM tbl_purchase WHERE id = ? FOR UPDATE');
    $statusStatement->execute([$purchaseId]);
    $document = $statusStatement->fetch();

    if (!$document) {
        throw new RuntimeException('Compra inexistente.');
    }
    $previousStatus = $document['status'];
    if (in_array($previousStatus, ['Anulado', 'Cancelada'], true)) {
        $pdo->commit();
        echo true;
        exit;
    }
    if ($previousStatus === 'Aprobado') {
        app_require_permission('purchases.approve');
    }

    $receiptStatement = $pdo->prepare("SELECT COUNT(*) FROM tbl_goods_receipt WHERE purchase_id = ? AND status <> 'Anulada'");
    $receiptStatement->execute([$purchaseId]);
    if ((int) $receiptStatement->fetchColumn() > 0) {
        throw new DomainException('La compra tiene recepciones de mercancía activas; anula primero las recepciones relacionadas.');
    }

    $paymentStatement = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_accounts_payable WHERE document_type = 'purchase' AND document_id = ?");
    $paymentStatement->execute([$purchaseId]);
    if ((float) $paymentStatement->fetchColumn() > 0) {
        throw new DomainException('La compra tiene pagos aplicados; no se puede anular sin reversar primero la aplicación financiera.');
    }

    if ($previousStatus !== 'Anulado') {
        $pdo->prepare("UPDATE tbl_purchase SET status = 'Anulado', last_update = NOW() WHERE id = ?")->execute([$purchaseId]);
        $pdo->prepare("UPDATE tbl_accounts_payable SET status = 'Anulada', balance_amount = 0 WHERE document_type = 'purchase' AND document_id = ?")
            ->execute([$purchaseId]);
        $historyStatement = $pdo->prepare(
            "INSERT INTO tbl_approval_history
                (document_type,document_id,previous_status,new_status,comment,user_id)
             VALUES ('purchase',?,?,'Anulado',?,?)"
        );
        $historyStatement->execute([$purchaseId, $previousStatus, $cancellationReason, (int) $_SESSION['loggedInUser']['USERID']]);
        app_record_document_status($pdo, 'purchase', (int) $purchaseId, (string) $document['number'], (string) $previousStatus, 'Anulado', 'Anulacion', (int) $_SESSION['loggedInUser']['USERID'], $cancellationReason);
        $purchaseOrderId = (int) ($document['purchase_order_id'] ?? 0);
        if ($purchaseOrderId > 0) {
            $otherPurchase = $pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id = ? AND id <> ? AND status NOT IN ('Anulado', 'Cancelada')");
            $otherPurchase->execute([$purchaseOrderId, $purchaseId]);
            if ((int) $otherPurchase->fetchColumn() === 0) {
                $orderStatement = $pdo->prepare('SELECT number, status FROM tbl_order WHERE id = ? FOR UPDATE');
                $orderStatement->execute([$purchaseOrderId]);
                $order = $orderStatement->fetch();
                if ($order && $order['status'] === 'Convertida') {
                    $pdo->prepare("UPDATE tbl_order SET status = 'Aprobado', last_update = NOW() WHERE id = ?")->execute([$purchaseOrderId]);
                    app_record_document_status($pdo, 'order', $purchaseOrderId, (string) $order['number'], 'Convertida', 'Aprobado', 'Desvinculacion', (int) $_SESSION['loggedInUser']['USERID'], 'Compra anulada; orden disponible nuevamente.');
                }
            }
            app_sync_purchase_order_receipt_status($pdo,$purchaseOrderId);
        }
    }

    $pdo->commit();
    echo true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('APPU purchase cancellation error: ' . $exception->getMessage());
    http_response_code(422);
    echo false;
}

