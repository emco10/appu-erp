<?php
/** Explicit release manifest: syntax checks only; no database, secrets or external services. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$files = [
    'bin/verify-peru-integrations.php',
    'global/config.php',
    'global/document-numbering.php',
    'global/gre-lifecycle.php',
    'global/peru-tax-profile.php',
    'global/peru-tax-rules.php',
    'global/sales-fulfillment.php',
    'global/sunat-api.php',
    'modules/gestion/integraciones-peru.php',
    'modules/identity/consultar.php',
    'modules/clientes/consultar-cliente.php',
    'modules/clientes/insert-update-cliente.php',
    'modules/proveedores/consultar-proveedor.php',
    'modules/proveedores/insert-update-proveedor.php',
    'modules/logistica/guias.php',
    'views/clientes/registro-cliente.php',
    'views/proveedores/registro-proveedor.php',
    'views/gestion/index.php',
    'views/gestion/integraciones-peru.php',
    'views/gestion/sire.php',
    'views/logistica/guias.php',
    'views/logistica/gre.php',
    'views/template/footer.php',
    'tests/peru-tax-rules.php',
    'tests/peru-integrations-schema.php',
    'tests/peru-tax-evaluation-idempotency.php',
    'tests/gre-numbering.php',
    'tests/gre-bff-http.php',
    'tests/identity-bff-http.php',
    'tests/identity-partner-lifecycle.php',
    'tests/migration-byte-audit.php',
    'tests/migration-byte-integrity.php',
];
foreach (['modules/gre/*.php', 'modules/sire/*.php'] as $pattern) {
    $matches = glob($root . '/' . $pattern);
    if (!$matches) throw new RuntimeException('Missing integration endpoints: ' . $pattern);
    foreach ($matches as $path) $files[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
}
$files = array_values(array_unique($files)); sort($files);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing release file: ' . $file);
    $process = proc_open([PHP_BINARY, '-l', $root . '/' . $file], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Could not start PHP syntax validation.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        fwrite(STDERR, 'FAIL syntax: ' . $file . PHP_EOL . $output . $errors);
        exit(1);
    }
}
echo 'PASS PHP integration release manifest (' . count($files) . ' files; no database or network)' . PHP_EOL;
