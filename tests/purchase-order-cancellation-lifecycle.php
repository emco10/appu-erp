<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
require $root . '/global/connection.php';

$userId = (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
$employeeId = (int) $pdo->query('SELECT id FROM tbl_employee WHERE active_status = 1 ORDER BY id LIMIT 1')->fetchColumn();
$providerId = (int) $pdo->query('SELECT id FROM tbl_provider ORDER BY id LIMIT 1')->fetchColumn();
if (!$userId || !$employeeId || !$providerId) {
    throw new RuntimeException('Faltan datos base para probar la anulacion de orden de compra.');
}

$suffix = strtoupper(bin2hex(random_bytes(5)));
$requestId = 0;
$orderIds = [];
$previousDirectory = getcwd();

try {
    $pdo->beginTransaction();
    $requestNumber = 'QA-CANCEL-' . $suffix;
    $today = date('Y-m-d');
    $requestStatement = $pdo->prepare(
        "INSERT INTO tbl_purchase_request
            (number,status,requester_id,issue_date,required_date,currency,total_estimate,created_by)
         VALUES (?,'Convertida',?,?,?,'PEN',0,?)"
    );
    $requestStatement->execute([$requestNumber, $employeeId, $today, $today, $userId]);
    $requestId = (int) $pdo->lastInsertId();

    $orderStatement = $pdo->prepare(
        "INSERT INTO tbl_order
            (type,number,status,currency,issue_date,delivery_date,provider_id,purchase_request_id,
             payment_days,account_number,quotation,requester,approver,created_by,observation,
             total_purchase,total_tax,total_net,exchange_rate_sale,exchange_rate_purchase)
            VALUES (1,?,'Pendiente','PEN',?,?,?,?,0,'','','','',?,'QA cancellation lifecycle',0,0,0,0,0)"
    );
    for ($index = 1; $index <= 2; $index++) {
        $orderStatement->execute([
            'QC' . $suffix . $index,
            $today,
            $today,
            $providerId,
            $requestId,
            $userId,
        ]);
        $orderIds[] = (int) $pdo->lastInsertId();
    }
    $pdo->commit();

    $_SESSION['loggedInUser'] = ['USERID' => $userId, 'ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['SCRIPT_NAME'] = '/modules/ordenes/anular-orden.php';
    chdir($root . '/modules/ordenes');

    $cancelOrder = static function (int $orderId) use ($root): string {
        $_POST = ['ID_ORDEN' => (string) $orderId, 'reason' => 'Prueba de anulacion QA'];
        ob_start();
        include $root . '/modules/ordenes/anular-orden.php';
        return trim((string) ob_get_clean());
    };

    if ($cancelOrder($orderIds[0]) !== '1') {
        throw new RuntimeException('No se pudo anular la primera orden de prueba.');
    }
    $requestStatus = $pdo->prepare('SELECT status FROM tbl_purchase_request WHERE id = ?');
    $requestStatus->execute([$requestId]);
    if ($requestStatus->fetchColumn() !== 'Convertida') {
        throw new RuntimeException('La solicitud se reabrio aunque aun tiene una orden activa.');
    }

    if ($cancelOrder($orderIds[1]) !== '1') {
        throw new RuntimeException('No se pudo anular la segunda orden de prueba.');
    }
    $requestStatus->execute([$requestId]);
    if ($requestStatus->fetchColumn() !== 'Aprobada') {
        throw new RuntimeException('La solicitud no volvio a estar disponible tras anular su ultima orden.');
    }

    $reopenHistory = $pdo->prepare(
        "SELECT COUNT(*) FROM tbl_document_status_history
         WHERE document_type = 'purchase_request' AND document_id = ?
           AND previous_status = 'Convertida' AND new_status = 'Aprobada' AND action = 'Reapertura'"
    );
    $reopenHistory->execute([$requestId]);
    if ((int) $reopenHistory->fetchColumn() !== 1) {
        throw new RuntimeException('La reapertura de la solicitud no quedo auditada.');
    }

    echo 'PASS purchase-order cancellation reopens source request with audit and active-order guard' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($requestId > 0) {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM tbl_document_status_history WHERE document_type = 'purchase_request' AND document_id = ?")->execute([$requestId]);
        if ($orderIds) {
            $idPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
            $pdo->prepare(
                "DELETE FROM tbl_document_status_history
                 WHERE document_type = 'order' AND document_id IN ($idPlaceholders)"
            )->execute($orderIds);
            $pdo->prepare(
                "DELETE FROM tbl_approval_history WHERE document_type = 'order' AND document_id IN ($idPlaceholders)"
            )->execute($orderIds);
            $pdo->prepare(
                "DELETE FROM tbl_document_flow
                 WHERE (source_type = 'purchase_request' AND source_id = ? AND target_type = 'order')
                    OR (target_type = 'order' AND target_id IN ($idPlaceholders))"
            )->execute(array_merge([$requestId], $orderIds));
            $pdo->prepare("DELETE FROM tbl_order_detail WHERE order_id IN ($idPlaceholders)")->execute($orderIds);
            $pdo->prepare("DELETE FROM tbl_order WHERE id IN ($idPlaceholders)")->execute($orderIds);
        }
        $pdo->prepare('DELETE FROM tbl_purchase_request_detail WHERE request_id = ?')->execute([$requestId]);
        $pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity = 'purchase_request' AND record_id = ?")->execute([$requestId]);
        $pdo->prepare('DELETE FROM tbl_purchase_request WHERE id = ?')->execute([$requestId]);
        $pdo->commit();
    }
    if ($previousDirectory !== false) {
        chdir($previousDirectory);
    }
}
