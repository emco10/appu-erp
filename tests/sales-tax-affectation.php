<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/connection.php';
require_once __DIR__ . '/../global/sales-document-validation.php';
if (!str_ends_with(DB, '_qa')) throw new RuntimeException('Use una base _qa.');

$productId = (int) $pdo->query('SELECT id FROM tbl_product WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM tbl_user WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
if (!$productId || !$userId) throw new RuntimeException('QA requiere un articulo y un usuario activos.');
$input = [
    'facturacion_fecha' => date('Y-m-d'),
    'facturacion_fecentrega' => date('Y-m-d'),
    'facturacion_tipmon' => 'PEN',
    'facturacion_formpago' => '0',
    'facturacion_usuarioid' => (string) $userId,
    'facturacion_porcdesc' => '0',
];
$rows = [[$productId, '', '', '', 100, 1]];

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE tbl_product SET tax_affectation_code=? WHERE id=?');
    $update->execute(['20', $productId]);
    $exoneratedRows = $rows;
    $exonerated = app_validate_legacy_sales_input($pdo, $input, $exoneratedRows);
    if ($exonerated['subtotal'] !== 100.0 || $exonerated['tax'] !== 0.0 || $exonerated['total'] !== 100.0) {
        throw new RuntimeException('Una operacion exonerada calculo IGV.');
    }
    $update->execute(['10', $productId]);
    $taxableRows = $rows;
    $taxable = app_validate_legacy_sales_input($pdo, $input, $taxableRows);
    if ($taxable['subtotal'] !== 100.0 || $taxable['tax'] !== 18.0 || $taxable['total'] !== 118.0) {
        throw new RuntimeException('Una operacion gravada no calculo el IGV esperado.');
    }
    $pdo->rollBack();
    echo "PASS sales tax affectation: code 20 => IGV 0; code 10 => IGV 18\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
