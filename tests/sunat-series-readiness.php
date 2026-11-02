<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/global/connection.php';
$column = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tbl_document_series' AND column_name='sunat_enabled'")->fetchColumn();
$sunatSelect = (int) $column === 1 ? 'sunat_enabled' : 'NULL AS sunat_enabled';
$series = $pdo->query("SELECT id,document_type,series,next_number,number_padding,{$sunatSelect},active_status FROM tbl_document_series WHERE document_type IN ('INVOICE','RECEIPT','CREDIT_NOTE') ORDER BY document_type,series")->fetchAll();
$usage = [];
foreach (['INVOICE' => 'tbl_invoice', 'RECEIPT' => 'tbl_receipt', 'CREDIT_NOTE' => 'tbl_credit_note'] as $type => $table) {
    $query = $pdo->query("SELECT series,COUNT(*) document_count,MAX(CAST(number AS UNSIGNED)) max_number FROM {$table} GROUP BY series ORDER BY series");
    $usage[$type] = $query->fetchAll();
}
echo json_encode(['sunat_enabled_column' => (int) $column === 1, 'series' => $series, 'usage' => $usage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
