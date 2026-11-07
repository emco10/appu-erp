<?php
declare(strict_types=1);

require '../../global/connection.php';
require_once '../../global/sunat-api.php';
require_once '../../global/peru-tax-profile.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405); header('Allow: POST');
        throw new DomainException('Utiliza el botón Consultar del maestro.');
    }
    $partner = (string) ($_POST['partner_type'] ?? '');
    if (!in_array($partner, ['customer', 'provider'], true)) {
        http_response_code(422); throw new DomainException('Selecciona un maestro válido.');
    }
    app_require_permission($partner === 'customer' ? 'customers.manage' : 'providers.manage');
    if ((int) app_peru_settings($pdo)['identity_enabled'] !== 1) {
        http_response_code(503); throw new DomainException('Las consultas de DNI/RUC todavía no están habilitadas. Puedes completar y guardar los datos manualmente.');
    }
    $number = trim((string) ($_POST['document_number'] ?? ''));
    $type = strtoupper(trim((string) ($_POST['document_type'] ?? '')));
    if (!in_array($type, ['RUC', 'DNI'], true) || !preg_match($type === 'RUC' ? '/^[0-9]{11}$/D' : '/^[0-9]{8}$/D', $number)) {
        http_response_code(422); throw new DomainException('Para consultar ingresa un DNI de 8 dígitos o un RUC de 11 dígitos.');
    }
    $actor = 'erp-user-' . (int) ($_SESSION['loggedInUser']['USERID'] ?? 0);
    // Release PHP's session lock before the provider request; other ERP tabs remain usable.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $result = app_sunat_api_request('POST', '/api/v1/identity/lookup', ['X-Actor-Id: ' . $actor], ['document_type' => $type, 'document_number' => $number]);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        $status = (int) $result['status'];
        http_response_code(in_array($status, [404, 422, 429, 503], true) ? $status : 502);
        $messages = [404 => 'No se encontró el documento consultado. Puedes registrar los datos manualmente.', 422 => 'Revisa el número de DNI/RUC ingresado.', 429 => 'Se alcanzó el límite de consultas. Espera un momento y vuelve a intentarlo.', 503 => 'La consulta no está disponible temporalmente. Puedes completar los datos manualmente.'];
        throw new DomainException($messages[$status] ?? 'El servicio de consulta no pudo responder. Puedes completar los datos manualmente.');
    }
    $data = $result['body']['data'] ?? null;
    if (!is_array($data) || ($data['document_type'] ?? '') !== $type || ($data['document_number'] ?? '') !== $number || !is_string($data['legal_name'] ?? null) || $data['legal_name'] === '') {
        throw new RuntimeException('Invalid normalized identity response');
    }
    $allowed = ['document_type','document_number','legal_name','trade_name','given_names','last_names','address','ubigeo','state','condition','department','province','district','tax_profile','tax_profile_evidence','source','consulted_at','cache_hit'];
    $data = array_intersect_key($data, array_flip($allowed));
    if ($type === 'DNI') {
        foreach (['address','ubigeo','state','condition','department','province','district'] as $field) $data[$field] = null;
        $data['tax_profile'] = ['retention_agent' => null, 'perception_agent' => null, 'good_taxpayer' => null];
        $data['tax_profile_evidence'] = [];
    }
    echo json_encode(['success' => true, 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (DomainException $exception) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    // Provider responses, credentials and personal document numbers never enter the ERP error log.
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'La consulta no está disponible. Puedes completar los datos manualmente.'], JSON_UNESCAPED_UNICODE);
}
