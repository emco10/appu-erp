<?php
/** Actual endpoint HTTP tests with an in-memory PDO fixture. No database, SUNAT or production access. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$repo = dirname(__DIR__);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'appu-sunat-readonly-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/global', 0700, true);
mkdir($testRoot . '/modules/sunat', 0700, true);
$fixture = __DIR__ . '/fixtures/sunat-readonly-connection.php';
file_put_contents($testRoot . '/global/connection.php', '<?php require ' . var_export($fixture, true) . ';');
$sampleXml = '<?xml version="1.0" encoding="UTF-8"?><invoice>SIMULACION</invoice>';
file_put_contents($testRoot . '/sample.xml', $sampleXml);
file_put_contents($testRoot . '/sample.zip', "PK\x03\x04fixture-cdr");
$router = <<<'PHP'
<?php
$endpoint = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if (!in_array($endpoint, ['estado', 'artefacto'], true)) { http_response_code(404); exit; }
chdir(getenv('APPU_SUNAT_TEST_ROOT') . '/modules/sunat');
require getenv('APPU_SUNAT_TEST_REPO') . '/modules/sunat/' . $endpoint . '.php';
PHP;
file_put_contents($testRoot . '/router.php', $router);

$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if ($socket === false) throw new RuntimeException('Cannot allocate test port: ' . $socketMessage);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$environment = array_merge(getenv(), ['APPU_SUNAT_TEST_ROOT' => $testRoot, 'APPU_SUNAT_TEST_REPO' => $repo]);
$process = proc_open([PHP_BINARY, '-S', $address, $testRoot . '/router.php'], [
    0 => ['pipe', 'r'], 1 => ['file', $testRoot . '/server.log', 'a'], 2 => ['file', $testRoot . '/server.log', 'a'],
], $pipes, $testRoot, $environment, ['bypass_shell' => true]);
if (!is_resource($process)) throw new RuntimeException('Cannot start isolated HTTP server.');
fclose($pipes[0]);

function sunatReadOnlyAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function sunatReadOnlyRequest(string $url): array
{
    $http_response_header = [];
    $body = file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches);
    $headers = [];
    foreach ($http_response_header as $header) {
        if (!str_contains($header, ':')) continue;
        [$name, $value] = explode(':', $header, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }
    return [(int)($matches[1] ?? 0), $headers, $body];
}

try {
    $started = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $connection = @stream_socket_client('tcp://' . $address, $code, $message, 0.1);
        if ($connection !== false) { fclose($connection); $started = true; break; }
        usleep(50000);
    }
    sunatReadOnlyAssert($started, 'Isolated HTTP server did not start.');
    $base = 'http://' . $address;

    foreach (['invoice', 'receipt', 'credit-note'] as $type) {
        foreach (['enabled' => true, 'missing-series' => false, 'internal-series' => false] as $scenario => $enabled) {
            [$status, $headers, $body] = sunatReadOnlyRequest($base . '/estado?source_type=' . $type . '&source_id=1&scenario=' . $scenario);
            $data = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
            sunatReadOnlyAssert($status === 200, $type . ': state endpoint did not return HTTP 200.');
            sunatReadOnlyAssert($data['sunat_enabled'] === $enabled, $type . ': missing/internal series must not be enabled.');
            sunatReadOnlyAssert(array_keys($data['document']) === ['series', 'number'], $type . ': state endpoint exposed extra document fields.');
            sunatReadOnlyAssert($data['sunat']['status'] === 'accepted', $type . ': existing CPE state changed.');
            sunatReadOnlyAssert($headers['cache-control'] === 'private, no-store', 'State endpoint can be cached.');
        }
    }
    [$status, , $body] = sunatReadOnlyRequest($base . '/estado?source_type=despatch&source_id=1');
    $data = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
    sunatReadOnlyAssert($status === 200 && $data['document'] === ['number' => 'T001-0000001', 'status' => 'Registrada'], 'Despatch read-only state changed.');

    foreach (['invoice', 'receipt', 'credit-note', 'despatch'] as $type) {
        [$status, $headers, $body] = sunatReadOnlyRequest($base . '/artefacto?source_type=' . $type . '&source_id=1&kind=xml&download=1');
        sunatReadOnlyAssert($status === 200 && $body === $sampleXml, $type . ': artifact bytes changed.');
        sunatReadOnlyAssert(($headers['content-length'] ?? '') === (string)strlen($sampleXml), $type . ': invalid Content-Length.');
        sunatReadOnlyAssert(($headers['content-type'] ?? '') === 'application/xml', $type . ': incorrect XML MIME type.');
        sunatReadOnlyAssert(str_starts_with($headers['content-disposition'] ?? '', 'attachment;'), 'Download disposition changed.');
    }
    [$status, $headers, $body] = sunatReadOnlyRequest($base . '/artefacto?source_type=invoice&source_id=1&kind=cdr');
    sunatReadOnlyAssert($status === 200 && ($headers['content-type'] ?? '') === 'application/zip', 'CDR download failed.');
    sunatReadOnlyAssert(($headers['content-length'] ?? '') === (string)strlen((string)$body), 'CDR Content-Length differs from response bytes.');

    foreach (['/estado?source_type=invoice&source_id=0', '/artefacto?source_type=invoice&source_id=0&kind=xml', '/artefacto?source_type=invoice&source_id=1&kind=private'] as $path) {
        [$status] = sunatReadOnlyRequest($base . $path);
        sunatReadOnlyAssert($status === 422, 'Invalid artifact/state request was not rejected.');
    }
    [$status] = sunatReadOnlyRequest($base . '/artefacto?source_type=invoice&source_id=1&kind=xml&scenario=missing-artifact');
    sunatReadOnlyAssert($status === 404, 'Missing artifact must return HTTP 404.');
    echo "PASS SUNAT read-only state and XML/CDR HTTP regressions (no database or external calls)\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (['global/connection.php', 'router.php', 'sample.xml', 'sample.zip', 'server.log'] as $file) @unlink($testRoot . '/' . $file);
    foreach (['modules/sunat', 'modules', 'global', ''] as $directory) @rmdir($testRoot . '/' . $directory);
}
