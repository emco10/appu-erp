<?php

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../finanzas/sync.php';
require_once '../../global/purchase-receipt.php';

header('Content-Type: application/json; charset=utf-8');

$documentType = strtolower(trim((string) ($_POST['document_type'] ?? '')));
$documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
$action = strtolower(trim((string) ($_POST['approval_action'] ?? '')));
$comment = trim((string) ($_POST['comment'] ?? ''));

$documents = [
    'order' => ['table' => 'tbl_order', 'label' => 'orden'],
    'purchase' => ['table' => 'tbl_purchase', 'label' => 'compra'],
    'purchase_request' => ['table' => 'tbl_purchase_request', 'label' => 'solicitud de compra'],
];

if (!isset($documents[$documentType]) || $documentId === false || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => 'Solicitud de aprobacion invalida.']);
    exit;
}

app_require_permission('purchases.approve');

if ($action === 'reject' && $comment === '') {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => 'Indica el motivo del rechazo.']);
    exit;
}

$config = $documents[$documentType];
$userId = (int) $_SESSION['loggedInUser']['USERID'];

try {
    $pdo->beginTransaction();

    $documentStatement = $pdo->prepare(
        "SELECT id, number, status FROM {$config['table']} WHERE id = ? FOR UPDATE"
    );
    $documentStatement->execute([$documentId]);
    $document = $documentStatement->fetch();

    if (!$document) {
        throw new RuntimeException('Documento inexistente.');
    }

    if ($document['status'] !== 'Pendiente') {
        throw new DomainException('Solo se pueden aprobar o rechazar documentos pendientes.');
    }

    if ($documentType === 'purchase_request') {
        $newStatus = $action === 'approve' ? 'Aprobada' : 'Rechazada';
        $updateStatement = $pdo->prepare("UPDATE {$config['table']} SET status = ?, last_update = NOW() WHERE id = ?");
        $updateStatement->execute([$newStatus, $documentId]);
    } elseif ($action === 'approve') {
        $employeeStatement = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(CONCAT_WS(' ', employee.name, employee.last_name_1, employee.last_name_2)), ''), user.username)
             FROM tbl_user user
             JOIN tbl_employee employee ON employee.id = user.employee_id
             WHERE user.id = ?"
        );
        $employeeStatement->execute([$userId]);
        $approverName = trim((string) $employeeStatement->fetchColumn());
        $newStatus = 'Aprobado';

        $updateStatement = $pdo->prepare(
            "UPDATE {$config['table']}
             SET status = ?, approver = ?, approved_by = ?, approved_at = NOW(),
                 rejected_by = NULL, rejected_at = NULL, approval_comment = ?, last_update = NOW()
             WHERE id = ?"
        );
        $updateStatement->execute([$newStatus, $approverName, $userId, $comment, $documentId]);
    } else {
        $newStatus = 'Rechazado';
        $updateStatement = $pdo->prepare(
            "UPDATE {$config['table']}
             SET status = ?, approver = '', rejected_by = ?, rejected_at = NOW(),
                 approved_by = NULL, approved_at = NULL, approval_comment = ?, last_update = NOW()
             WHERE id = ?"
        );
        $updateStatement->execute([$newStatus, $userId, $comment, $documentId]);
    }

    $historyStatement = $pdo->prepare(
        'INSERT INTO tbl_approval_history
            (document_type, document_id, previous_status, new_status, comment, user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $historyStatement->execute([
        $documentType,
        $documentId,
        $document['status'],
        $newStatus,
        substr($comment, 0, 500),
        $userId,
    ]);

    app_record_document_status(
        $pdo,
        $documentType,
        (int) $documentId,
        (string) $document['number'],
        (string) $document['status'],
        $newStatus,
        $action === 'approve' ? 'Aprobacion' : 'Rechazo',
        $userId,
        $comment
    );
    if ($documentType === 'purchase' && $action === 'approve') {
        $subtypeStatement=$pdo->prepare('SELECT document_subtype FROM tbl_purchase WHERE id=?');$subtypeStatement->execute([$documentId]);
        $isReserve=$subtypeStatement->fetchColumn()==='RESERVE';
        $receiptStatus=app_sync_purchase_receipt_status($pdo,(int)$documentId);
        if($isReserve||$receiptStatus==='Atendido')finance_sync_payable($pdo,(int)$documentId);
        if($receiptStatus!==$newStatus)app_record_document_status($pdo,'purchase',(int)$documentId,(string)$document['number'],$newStatus,$receiptStatus,'Recepción previa',$userId,'Se conservan las entradas de mercancías de la orden de compra.');
        $newStatus=$receiptStatus;
    }

    $pdo->commit();
    echo json_encode([
        'error' => false,
        'status' => $newStatus,
        'message' => ucfirst($config['label']) . ' ' . $document['number'] . ' actualizada a ' . $newStatus . '.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('APPU approval workflow error: ' . $exception->getMessage());
    http_response_code(422);
    echo json_encode([
        'error' => true,
        'message' => $exception instanceof DomainException
            ? $exception->getMessage()
            : 'No se pudo actualizar la aprobacion.',
    ], JSON_UNESCAPED_UNICODE);
}
