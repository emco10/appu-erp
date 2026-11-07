<?php
declare(strict_types=1);
require_once __DIR__ . '/../../global/connection.php';
require_once __DIR__ . '/../../global/sunat-api.php';
require_once __DIR__ . '/../../global/peru-tax-profile.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function app_sire_require_method(string $method, string $permission): void
{
    app_require_permission($permission);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        http_response_code(405);
        header('Allow: ' . $method);
        echo json_encode(['error' => true, 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function app_sire_scalar(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('El campo ' . $key . ' no tiene un formato válido.');
    return trim((string)$value);
}

function app_sire_parameters(string $operation, array $input): array
{
    $book = app_sire_scalar($input, 'book');
    if (!in_array($book, ['140000', '080000'], true)) throw new InvalidArgumentException('Selecciona RVIE o RCE.');
    $parameters = ['book' => $book];
    if ($operation === 'periods') return $parameters;
    $periodKeys = $operation === 'tickets' ? ['from', 'to'] : ['period'];
    foreach ($periodKeys as $key) {
        $value = app_sire_scalar($input, $key);
        if (!preg_match('/^(?:19|20)[0-9]{2}(?:0[1-9]|1[0-2])$/D', $value)) throw new InvalidArgumentException('El período debe tener el formato AAAAMM y un mes válido.');
        $parameters[$key] = $value;
    }
    if ($operation === 'tickets' && $parameters['from'] > $parameters['to']) throw new InvalidArgumentException('El período inicial debe ser menor o igual al final.');
    if (in_array($operation, ['tickets', 'proposal', 'download'], true)) {
        $ticket = app_sire_scalar($input, 'ticket');
        if ($ticket !== '' && !preg_match('/^[0-9]{12,14}$/D', $ticket)) throw new InvalidArgumentException('El número de ticket SIRE no es válido.');
        if ($ticket === '' && $operation !== 'tickets') throw new InvalidArgumentException('Indica el ticket de la exportación SIRE.');
        if ($ticket !== '') $parameters['ticket'] = $ticket;
    }
    if (in_array($operation, ['tickets', 'proposal'], true)) {
        foreach (['page' => [1, 10000, 1], 'per_page' => [1, 200, 50]] as $key => [$min, $max, $default]) {
            $value = filter_var(app_sire_scalar($input, $key, (string)$default), FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException('La paginación SIRE está fuera del rango permitido.');
            $parameters[$key] = $value;
        }
    }
    if ($operation === 'download') {
        $index = filter_var(app_sire_scalar($input, 'file_index', '0'), FILTER_VALIDATE_INT);
        if ($index === false || $index < 0 || $index > 9) throw new InvalidArgumentException('Selecciona un archivo válido del ticket.');
        $parameters['file_index'] = $index;
    }
    return $parameters;
}

function app_sire_forward(string $method, string $operation, array $parameters): array
{
    global $pdo;
    if ((int)(app_peru_settings($pdo)['sire_enabled'] ?? 0) !== 1) {
        http_response_code(503);
        echo json_encode(['error' => true, 'code' => 'SIRE_DISABLED', 'message' => 'SIRE está desactivado. Habilítalo en Gestión → Integraciones Perú después de configurar sus credenciales.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $allowed = ['periods', 'proposal/export', 'tickets', 'proposal', 'download'];
    if (!in_array($operation, $allowed, true)) throw new LogicException('Unsupported SIRE operation.');
    $path = '/api/v1/sire/' . $operation;
    if ($method === 'GET') $path .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    $response = app_sunat_api_request($method, $path, [], $method === 'POST' ? $parameters : null);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        $body = $response['body'];
        http_response_code($response['status'] >= 400 && $response['status'] <= 599 ? $response['status'] : 502);
        $message = $body['error']['message'] ?? 'No se pudo completar la consulta SIRE.';
        $code = $body['error']['code'] ?? 'SIRE_UNAVAILABLE';
        echo json_encode(['error' => true, 'code' => is_string($code) ? $code : 'SIRE_UNAVAILABLE', 'message' => is_string($message) ? $message : 'No se pudo completar la consulta SIRE.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $response['body'];
}

function app_sire_error(Throwable $error): never
{
    $known = $error instanceof InvalidArgumentException;
    http_response_code($known ? 422 : 502);
    if (!$known) error_log('APPU SIRE bridge failure: ' . get_class($error));
    echo json_encode(['error' => true, 'message' => $known ? $error->getMessage() : 'No se pudo contactar el servicio SIRE. Intenta consultar nuevamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}
