<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../global/commercial-line-dimensions.php';

$empty = app_commercial_line_dimensions([]);
if ($empty !== ['project_code'=>'','cost_center_code'=>'','lot_number'=>'','serial_number'=>'','expiration_date'=>null]) {
    throw new RuntimeException('Los datos opcionales no conservan valores vacíos.');
}

$filled = app_commercial_line_dimensions([
    'project_code' => ' PR-001 ',
    'cost_center_code' => ' CC-LIMA ',
    'lot_number' => ' LOTE-9 ',
    'serial_number' => ' SERIE-7 ',
    'expiration_date' => '2030-12-31',
], 'Línea 2');
if ($filled['project_code'] !== 'PR-001' || $filled['cost_center_code'] !== 'CC-LIMA' || $filled['expiration_date'] !== '2030-12-31') {
    throw new RuntimeException('No se normalizaron las dimensiones de línea.');
}

foreach ([
    ['project_code' => str_repeat('P', 51)],
    ['expiration_date' => '31/12/2030'],
] as $invalid) {
    try {
        app_commercial_line_dimensions($invalid, 'Línea 3');
        throw new RuntimeException('Se aceptó una dimensión inválida.');
    } catch (DomainException $expected) {
        if (!str_contains($expected->getMessage(), 'Línea 3')) throw $expected;
    }
}

echo "PASS optional commercial line dimensions\n";
