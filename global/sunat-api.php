<?php

declare(strict_types=1);

function app_sunat_api_config(): array
{
    $config = [];
    $privatePath = getenv('APPU_SUNAT_CONFIG');
    if ($privatePath === false || $privatePath === '') {
        $privatePath = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . '.appu-erp' . DIRECTORY_SEPARATOR . 'sunat.php';
    }
    if (is_file($privatePath)) {
        $loaded = require $privatePath;
        if (!is_array($loaded)) {
            throw new RuntimeException('La configuración privada de SUNAT debe devolver un arreglo.');
        }
        $config = $loaded;
    }

    $baseUrl = trim((string) (getenv('APPU_SUNAT_API_URL') ?: ($config['base_url'] ?? '')));
    $apiKey = trim((string) (getenv('APPU_SUNAT_API_KEY') ?: ($config['api_key'] ?? '')));
    if ($baseUrl === '' || $apiKey === '') {
        throw new RuntimeException('La integración SUNAT todavía no está configurada.');
    }
    $parts = parse_url($baseUrl);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $loopback = in_array($host, ['127.0.0.1', 'localhost'], true);
    if (($scheme !== 'https' && !($scheme === 'http' && $loopback)) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('La URL configurada para SUNAT no es segura.');
    }

    return ['base_url' => rtrim($baseUrl, '/'), 'api_key' => $apiKey];
}

function app_sunat_api_request(string $method, string $path, array $headers = []): array
{
    $config = app_sunat_api_config();
    $curl = curl_init($config['base_url'] . '/' . ltrim($path, '/'));
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la conexión con SUNAT.');
    }
    $requestHeaders = array_merge([
        'Accept: application/json',
        'X-API-Key: ' . $config['api_key'],
        'X-Request-Id: erp-' . bin2hex(random_bytes(12)),
    ], $headers);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) {
        throw new RuntimeException('No se pudo contactar el servicio CPE: ' . $error);
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('El servicio CPE devolvió una respuesta inválida.');
    }

    return ['status' => $status, 'body' => $decoded];
}
