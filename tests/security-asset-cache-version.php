<?php
/** Render actual footer: local scripts must change cache keys when a release changes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('SCRIPT_VER', 'QA release & "version"');
$functions = new class { public function direct_sistema(): string { return '/erp'; } };
ob_start();
require dirname(__DIR__) . '/views/template/footer.php';
$html = ob_get_clean();
$previous = libxml_use_internal_errors(true);
try {
    $document = new DOMDocument();
    if (!$document->loadHTML($html, LIBXML_NONET)) throw new RuntimeException('Footer rendering failed.');
    $count = 0;
    foreach ($document->getElementsByTagName('script') as $script) {
        $url = $script->getAttribute('src');
        if (!str_starts_with($url, '/erp/')) continue;
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        if (($query['v'] ?? null) !== SCRIPT_VER) throw new RuntimeException('Unversioned local script: ' . $url);
        $count++;
    }
    if ($count === 0) throw new RuntimeException('No local scripts verified.');
    echo 'PASS release cache keys for all ' . $count . ' rendered local scripts', PHP_EOL;
} finally {
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}
