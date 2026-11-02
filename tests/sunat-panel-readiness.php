<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/global/connection.php';

$required = [
    'tbl_document_series' => ['document_type','series','next_number','number_padding','sunat_enabled','active_status'],
    'tbl_sunat_document' => ['id','source_type','source_id','status','sunat_code','sunat_description','xml_path','cdr_path','digest_value','submitted_at','accepted_at','created_at'],
    'tbl_sunat_document_event' => ['id','sunat_document_id','event_type','created_at'],
];
$query = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE()');
$actual = [];
foreach ($query->fetchAll() as $column) $actual[$column['TABLE_NAME']][$column['COLUMN_NAME']] = true;
$missing = [];
foreach ($required as $table => $columns) foreach ($columns as $column) if (!isset($actual[$table][$column])) $missing[] = $table . '.' . $column;
if ($missing) throw new RuntimeException('Missing SUNAT schema: ' . implode(', ', $missing));

$union = "SELECT 'invoice' source_type,'INVOICE' series_type,id source_id,series,number,date document_date,name customer_name,total_net FROM tbl_invoice WHERE status NOT IN(2,4) UNION ALL SELECT 'receipt','RECEIPT',id,series,number,date,name,total_net FROM tbl_receipt WHERE status NOT IN(2,4) UNION ALL SELECT 'credit-note','CREDIT_NOTE',id,series,number,date,name,total_net FROM tbl_credit_note WHERE status NOT IN(2,4)";
$sql = "SELECT d.*,COALESCE(ds.sunat_enabled,0) sunat_enabled,s.id sunat_id,s.status,s.sunat_code,s.sunat_description,s.xml_path,s.cdr_path,s.digest_value,s.submitted_at,s.accepted_at,s.created_at FROM ({$union}) d LEFT JOIN tbl_document_series ds ON ds.document_type=d.series_type AND ds.series=d.series LEFT JOIN tbl_sunat_document s ON s.source_type=d.source_type AND s.source_id=d.source_id WHERE d.document_date BETWEEN ? AND ? ORDER BY d.document_date DESC,d.source_id DESC LIMIT 1";
$statement = $pdo->prepare($sql);
$statement->execute([date('Y-m-d', strtotime('-29 days')), date('Y-m-d')]);
$statement->fetchAll();
echo "PASS SUNAT panel schema and query readiness\n";
