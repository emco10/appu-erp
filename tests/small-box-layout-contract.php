<?php

declare(strict_types=1);

$css = file_get_contents(dirname(__DIR__) . '/css/style-appu.css');
if ($css === false) throw new RuntimeException('No se pudo leer la hoja de estilos APPU.');
foreach ([
    '.small-box .inner {' => 'reserva interior',
    'padding: .75rem 4.75rem .75rem .75rem' => 'espacio para icono',
    '.small-box .icon > i,' => 'tamaño de icono',
    '#sunat-summary .small-box .inner' => 'tarjetas SUNAT',
    'overflow-wrap: anywhere' => 'texto adaptable',
] as $rule => $label) {
    if (!str_contains($css, $rule)) throw new RuntimeException('Falta la regla responsive: ' . $label);
}
echo "PASS responsive small-box text/icon layout contract\n";
