<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$helper = file_get_contents($root . '/global/sunat-api.php');
$endpoint = file_get_contents($root . '/modules/sunat/enviar.php');
$ui = file_get_contents($root . '/js/sunat-artifacts.js');
if ($helper === false || $endpoint === false || $ui === false) throw new RuntimeException('Faltan archivos de integración SUNAT.');
foreach (['CURLOPT_SSL_VERIFYPEER', 'CURLOPT_FOLLOWLOCATION => false', 'X-API-Key:', 'APPU_SUNAT_API_KEY'] as $required) {
    if (!str_contains($helper, $required)) throw new RuntimeException('Falta el control SUNAT: ' . $required);
}
foreach (['sales.manage', 'Idempotency-Key:', "'invoice' =>", "'receipt' =>", "'credit-note' =>"] as $required) {
    if (!str_contains($endpoint, $required)) throw new RuntimeException('Falta el contrato de envío: ' . $required);
}
if (!str_contains($ui, 'Enviar a SUNAT Beta') || !str_contains($ui, '../../modules/sunat/enviar.php')) {
    throw new RuntimeException('La interfaz no expone el envío manual a Beta.');
}
echo "PASS SUNAT API backend contract and manual Beta action\n";
