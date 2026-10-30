<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$header = file_get_contents($root . '/views/template/header.php');
$css = file_get_contents($root . '/css/style-appu.css');
$home = file_get_contents($root . '/views/home.php');
$fixture = file_get_contents(__DIR__ . '/responsive-fixture.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($header, 'name="viewport" content="width=device-width, initial-scale=1"'), 'Falta viewport adaptable.');
$adminPosition = strpos($header, 'adminlte.min.css');
$appuPosition = strpos($header, '/css/style-appu.css');
$assert($adminPosition !== false && $appuPosition !== false && $appuPosition > $adminPosition, 'La hoja APPU debe cargar después de AdminLTE.');

foreach ([
    '@media (max-width: 991.98px)',
    '.content-wrapper .row > [class*="col-md-"]',
    'grid-template-columns: repeat(4, minmax(0, 1fr))',
    'width: calc(100% - 1rem)',
    '.dashboard-kpis > .col-6',
    '.card-header [style*="height: 2.2rem"]',
    '.card-header .text-right',
] as $responsiveRule) {
    $assert(str_contains($css, $responsiveRule), 'Falta regla adaptable: ' . $responsiveRule);
}

$assert(substr_count($home, 'row dashboard-kpis') === 3, 'Los tres grupos KPI deben identificarse para apilarse en celular.');
$assert(str_contains($fixture, "PHP_SAPI !== 'cli-server'"), 'El fixture visual debe estar restringido al servidor de pruebas.');
$assert(str_contains($fixture, "getenv('APPU_RESPONSIVE_TEST') !== '1'"), 'El fixture visual debe exigir el entorno de pruebas.');

echo "PASS responsive layout contract\n";
