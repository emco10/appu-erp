<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('reports.view');

$numberCase = "CASE flow.source_type
    WHEN 'quotation' THEN (SELECT number FROM tbl_quotation WHERE id = flow.source_id)
    WHEN 'sales_order' THEN (SELECT number FROM tbl_sales_order WHERE id = flow.source_id)
    WHEN 'invoice' THEN (SELECT CONCAT(series, '-', number) FROM tbl_invoice WHERE id = flow.source_id)
    WHEN 'receipt' THEN (SELECT CONCAT(series, '-', number) FROM tbl_receipt WHERE id = flow.source_id)
    WHEN 'credit_note' THEN (SELECT CONCAT(series, '-', number) FROM tbl_credit_note WHERE id = flow.source_id)
    WHEN 'purchase' THEN (SELECT number FROM tbl_purchase WHERE id = flow.source_id)
    WHEN 'purchase_request' THEN (SELECT number FROM tbl_purchase_request WHERE id = flow.source_id)
    WHEN 'purchase_order' THEN (SELECT number FROM tbl_order WHERE id = flow.source_id)
    WHEN 'order' THEN (SELECT number FROM tbl_order WHERE id = flow.source_id)
    WHEN 'goods_receipt' THEN (SELECT number FROM tbl_goods_receipt WHERE id = flow.source_id)
    WHEN 'remission_guide' THEN (SELECT number FROM tbl_remission_guide WHERE id = flow.source_id)
    WHEN 'incoming_payment' THEN (SELECT payment_number FROM tbl_incoming_payment WHERE id = flow.source_id)
    WHEN 'outgoing_payment' THEN (SELECT payment_number FROM tbl_outgoing_payment WHERE id = flow.source_id)
    WHEN 'landed_cost' THEN (SELECT number FROM tbl_landed_cost WHERE id = flow.source_id)
    ELSE NULL END";
$targetNumberCase = str_replace(['flow.source_type', 'flow.source_id'], ['flow.target_type', 'flow.target_id'], $numberCase);
$flows = $pdo->query("SELECT flow.id, flow.source_type, flow.source_id, {$numberCase} AS source_number,
    flow.target_type, flow.target_id, {$targetNumberCase} AS target_number,
    flow.relationship_type, flow.quantity_applied, flow.created_by, flow.registration_date
    FROM tbl_document_flow flow ORDER BY flow.id DESC LIMIT 500")->fetchAll();
$summary = $pdo->query("SELECT source_type, target_type, relationship_type, COUNT(*) AS flow_count
    FROM tbl_document_flow GROUP BY source_type, target_type, relationship_type ORDER BY flow_count DESC")->fetchAll();
echo json_encode(['flows' => $flows, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
