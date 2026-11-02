<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/global/sunat-api.php';
foreach (['/health' => 'ok', '/ready' => 'ready'] as $path => $expected) {
    $response = app_sunat_api_request('GET', $path);
    $actual = (string) ($response['body']['status'] ?? '');
    if ($response['status'] !== 200 || $actual !== $expected) {
        throw new RuntimeException("SUNAT API {$path} is not ready.");
    }
}
echo "PASS ERP to CPE authenticated readiness\n";
