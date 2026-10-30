<?php

require '../../global/connection.php';

header('Content-Type: application/json; charset=utf-8');

$documentType = strtolower(trim((string) ($_GET['document_type'] ?? '')));
$documentId = filter_var($_GET['document_id'] ?? null, FILTER_VALIDATE_INT);
$permissions = [
    'quotation' => 'sales.view',
    'purchase_request' => 'purchases.view',
    'order' => 'purchases.view',
    'purchase' => 'purchases.view',
    'sales_order' => 'sales.view',
    'invoice' => 'sales.view',
    'receipt' => 'sales.view',
    'credit_note' => 'sales.view',
    'goods_receipt' => 'inventory.view',
    'remission_guide' => 'logistics.view',
    'incoming_payment' => 'finance.view',
    'outgoing_payment' => 'finance.view',
    'stock_transfer' => 'inventory.view',
    'inventory_document' => 'inventory.view',
    'landed_cost' => 'purchases.view',
];

if (!isset($permissions[$documentType]) || $documentId === false || $documentId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => 'Documento invalido.']);
    exit;
}

app_require_permission($permissions[$documentType]);

$statement = $pdo->prepare(
    "SELECT history.id,
            history.document_number,
            history.previous_status,
            history.new_status,
            history.action,
            history.comment,
            DATE_FORMAT(history.registration_date, '%d/%m/%Y %H:%i') AS registration_date,
            COALESCE(NULLIF(TRIM(CONCAT(employee.name, ' ', employee.last_name_1, ' ', employee.last_name_2)), ''), user.username, CONCAT('Usuario ', history.user_id)) AS user_name
     FROM tbl_document_status_history history
     LEFT JOIN tbl_user user ON user.id = history.user_id
     LEFT JOIN tbl_employee employee ON employee.id = user.employee_id
     WHERE history.document_type = ? AND history.document_id = ?
     ORDER BY history.registration_date DESC, history.id DESC"
);
$statement->execute([$documentType, $documentId]);

echo json_encode([
    'error' => false,
    'history' => $statement->fetchAll(),
], JSON_UNESCAPED_UNICODE);
