<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/integration-network-policy.php';

function integrationPolicyAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    'http://127.0.0.1:8080' => 'loopback IPv4',
    'https://[::1]/' => 'loopback IPv6',
    'http://192.0.2.1/' => 'reserved IPv4',
    'http://localhost/' => 'localhost hostname',
] as $url => $label) {
    try {
        app_integration_public_destination($url);
        throw new RuntimeException('Se permitió un destino ' . $label . '.');
    } catch (InvalidArgumentException $exception) {
        integrationPolicyAssert($exception->getMessage() !== '', 'La política no explicó el rechazo de ' . $label . '.');
    }
}

$trustedDestination = app_integration_public_destination('http://127.0.0.1/', ['127.0.0.1']);
integrationPolicyAssert($trustedDestination['host'] === '127.0.0.1', 'No se admitió el host interno explícitamente permitido.');

$destination = app_integration_public_destination('https://8.8.8.8:8443/path');
integrationPolicyAssert($destination['address'] === '8.8.8.8' && $destination['port'] === 8443, 'No se reconoció el destino público.');
integrationPolicyAssert(app_integration_curl_resolution(['host' => 'api.example.com', 'port' => 443, 'address' => '203.0.113.8']) === 'api.example.com:443:203.0.113.8', 'La resolución fijada no preserva host y puerto.');
integrationPolicyAssert(app_integration_curl_resolution(['host' => 'api.example.com', 'port' => 443, 'address' => '2001:4860:4860::8888']) === 'api.example.com:443:[2001:4860:4860::8888]', 'La resolución IPv6 fijada no es válida.');
echo "PASS integration destination policy rejects local/private/reserved targets and pins public DNS results\n";
