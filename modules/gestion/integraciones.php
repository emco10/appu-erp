<?php
require '../../global/connection.php';
require_once '../../global/integration-network-policy.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
app_require_permission($method === 'GET' ? 'management.view' : 'management.manage');
if($method!=='GET'&&!in_array(strtolower(trim((string)($_POST['action']??''))),['','save','test'],true)){http_response_code(422);echo json_encode(['error'=>'Acción de integración inválida.'],JSON_UNESCAPED_UNICODE);exit;}

if ($method === 'GET') {
    $integrations = $pdo->query("SELECT integration.id,integration.code,integration.name,integration.integration_type,integration.base_url,integration.active_status,integration.last_update,
        log.checked_at AS last_checked_at,log.available_status AS last_available_status,log.http_status AS last_http_status,log.elapsed_ms AS last_elapsed_ms,log.message AS last_check_message
        FROM tbl_external_integration integration
        LEFT JOIN tbl_external_integration_log log ON log.id = (SELECT latest.id FROM tbl_external_integration_log latest WHERE latest.integration_id = integration.id ORDER BY latest.checked_at DESC, latest.id DESC LIMIT 1)
        ORDER BY integration.name")->fetchAll();
    echo json_encode(['integrations' => $integrations], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtolower(trim((string) ($_POST['action'] ?? ''))) === 'test') {
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $statement = $pdo->prepare('SELECT id,code,name,base_url,active_status FROM tbl_external_integration WHERE code = ? LIMIT 1');
    $statement->execute([$code]);
    $integration = $statement->fetch();
    if (!$integration || trim((string) $integration['base_url']) === '') {
        http_response_code(422);
        echo json_encode(['error' => 'La integración no tiene una URL base configurada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if(!(int)$integration['active_status']){http_response_code(422);echo json_encode(['error'=>'Activa la integración antes de probar su disponibilidad.'],JSON_UNESCAPED_UNICODE);exit;}
    $url = trim((string) $integration['base_url']);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(422);
        echo json_encode(['error' => 'La URL base no es válida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $privateHostAllowlist = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (getenv('APPU_INTEGRATION_PRIVATE_HOST_ALLOWLIST') ?: ''))
        )));
        $destination = app_integration_public_destination($url, $privateHostAllowlist);
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!function_exists('curl_init')) {
        http_response_code(503);
        echo json_encode(['error' => 'La verificación segura de integraciones no está disponible en este servidor.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $started = microtime(true);
    $statusCode = 0;
    $curl = curl_init($url);
    if ($curl === false) {
        http_response_code(503);
        echo json_encode(['error' => 'No se pudo iniciar la verificación segura de la integración.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $resolve = app_integration_curl_resolution($destination);
    $options = [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_USERAGENT => 'APPU-ERP-Integration-Check/1.0',
        CURLOPT_PROXY => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($resolve !== null) $options[CURLOPT_RESOLVE] = [$resolve];
    if (!curl_setopt_array($curl, $options)) {
        curl_close($curl);
        http_response_code(503);
        echo json_encode(['error' => 'No se pudo configurar la verificación segura de la integración.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $available = $statusCode >= 200 && $statusCode < 500;
    $elapsedMs = (int) round((microtime(true) - $started) * 1000);
    $message = $available ? 'La URL respondió correctamente.' : 'No se obtuvo una respuesta HTTP válida.';
    $pdo->prepare('INSERT INTO tbl_external_integration_log (integration_id,available_status,http_status,elapsed_ms,message,checked_by) VALUES (?,?,?,?,?,?)')
        ->execute([(int) $integration['id'], $available ? 1 : 0, $statusCode, $elapsedMs, $message, (int) $_SESSION['loggedInUser']['USERID']]);
    echo json_encode(['available' => $available, 'http_status' => $statusCode, 'elapsed_ms' => $elapsedMs, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = strtoupper(trim((string) ($_POST['code'] ?? '')));
$name = trim((string) ($_POST['name'] ?? ''));
$type = strtoupper(trim((string) ($_POST['integration_type'] ?? 'OTHER')));
$url = trim((string) ($_POST['base_url'] ?? ''));
$active = (int) ($_POST['active_status'] ?? 0) === 1 ? 1 : 0;
if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)||parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_PASS)!==null||mb_strlen($url)>500)){http_response_code(422);echo json_encode(['error'=>'La URL base debe ser HTTP o HTTPS y no debe contener credenciales.'],JSON_UNESCAPED_UNICODE);exit;}
if (!preg_match('/^[A-Z0-9_-]{3,60}$/', $code) || $name === '' || mb_strlen($name)>150 || !in_array($type, ['MARKETPLACE', 'LOGISTICS', 'FINANCIAL', 'WEBHOOK', 'OTHER'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Datos de integración inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$pdo->prepare('INSERT INTO tbl_external_integration (code,name,integration_type,base_url,configuration_json,active_status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),integration_type=VALUES(integration_type),base_url=VALUES(base_url),active_status=VALUES(active_status)')->execute([$code, $name, $type, $url, '{}', $active]);
echo json_encode(['message' => 'Integración guardada correctamente.'], JSON_UNESCAPED_UNICODE);
