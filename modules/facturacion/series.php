<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.view');
$type = strtoupper(trim((string)($_GET['document_type'] ?? '')));
if (!in_array($type, ['INVOICE','RECEIPT','CREDIT_NOTE'], true)) { http_response_code(422); echo json_encode(['error'=>'Tipo de documento invalido.']); exit; }
$statement = $pdo->prepare('SELECT series,next_number,number_padding,sunat_enabled FROM tbl_document_series WHERE document_type=? AND active_status=1 ORDER BY sunat_enabled DESC,series'); $statement->execute([$type]);
echo json_encode(['series'=>$statement->fetchAll()], JSON_UNESCAPED_UNICODE);
