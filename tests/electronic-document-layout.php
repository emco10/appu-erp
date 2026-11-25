<?php
require_once __DIR__ . '/../global/electronic-document-pdf.php';

$company = ['legal_name' => 'Empresa QA', 'address' => 'Lima', 'tax_id' => '20000000000'];
$document = [
    'series' => 'B901', 'number' => '1', 'date' => '2026-11-25', 'currency_label' => 'SOLES',
    'currency_symbol' => 'S/', 'name' => 'Consumidor de prueba', 'ruc' => '', 'address' => '',
    'payment_label' => 'Contado', 'total_tax' => 7.2, 'total_net' => 47.2,
];
$longCode = 'FABRICANTE-REFERENCIA-APPU';
$pdf = new AppuElectronicDocumentPdf($company, $document, 'BOLETA DE VENTA', '');
$pdf->SetCompression(false);
$pdf->render([[
    'item_code' => $longCode,
    'item_description' => 'Artículo de prueba con descripción suficientemente larga para ocupar más de una línea',
    'item_quantity' => 2,
    'item_unit_price' => 20,
    'sunat_unit_code' => 'NIU',
]], ['Op. gravadas' => 40, 'IGV' => 7.2], null);
$body = $pdf->Output('S');
preg_match_all('/\(([A-Z0-9-]+)\) Tj/', $body, $text);
$parts = array_filter($text[1], static fn(string $fragment): bool => str_contains($longCode, $fragment));
if (count($parts) < 2 || implode('', $parts) !== $longCode) {
    throw new RuntimeException('El código debe permanecer completo y repartido en varias líneas dentro de la celda.');
}
echo "PASS electronic PDF wraps a long item code without dropping characters\n";
