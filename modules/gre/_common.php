<?php
declare(strict_types=1);

require_once __DIR__ . '/../../global/connection.php';
require_once __DIR__ . '/../../global/sunat-api.php';
require_once __DIR__ . '/../../global/peru-tax-profile.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function app_gre_reply(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function app_gre_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        app_gre_reply(['success'=>false,'message'=>'Método no permitido.'],405);
    }
    app_require_permission($method === 'GET' ? 'logistics.view' : 'logistics.manage');
}
function app_gre_id(): int
{
    $input = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
    $id = filter_var($input['guide_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) app_gre_reply(['success'=>false,'message'=>'Selecciona una guía válida.'],422);
    return (int)$id;
}
function app_gre_enabled(PDO $pdo): bool { return (int)app_peru_settings($pdo)['gre_enabled'] === 1; }
function app_gre_require_enabled(PDO $pdo): void
{
    if (!app_gre_enabled($pdo)) app_gre_reply(['success'=>false,'code'=>'GRE_DISABLED','message'=>'La integración GRE todavía no está habilitada. Puedes revisar los documentos y sus archivos existentes.'],503);
}
function app_gre_guide(PDO $pdo, int $id, bool $lock = false): array
{
    $query = $pdo->prepare('SELECT * FROM tbl_remission_guide WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]); $guide = $query->fetch(PDO::FETCH_ASSOC);
    if (!$guide) throw new DomainException('La guía seleccionada no existe.');
    return $guide;
}
function app_gre_record(PDO $pdo, int $id): ?array
{
    if (!app_peru_table_exists($pdo,'tbl_gre_submission')) return null;
    $query=$pdo->prepare('SELECT source_id,basename,status,ticket,received_at,send_attempts,sunat_code,sunat_description,sunat_notes_json,last_error_code,created_at,updated_at,(cdr_zip IS NOT NULL) AS cdr_available FROM tbl_gre_submission WHERE source_id=?');
    $query->execute([$id]); $row=$query->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['sunat_notes']=json_decode((string)($row['sunat_notes_json']??'[]'),true)??[]; unset($row['sunat_notes_json']);
    return $row;
}
function app_gre_api(string $method, string $path, array $headers = []): array
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $result=app_sunat_api_request($method,$path,$headers);
    if ($result['status'] < 200 || $result['status'] >= 300) {
        $body=$result['body']; $status=(int)$result['status'];
        $message=(string)($body['error']['message']??$body['message']??'No se pudo consultar el servicio GRE.');
        app_gre_reply(['success'=>false,'message'=>$message,'code'=>$body['error']['code']??'GRE_API_ERROR',
            'errors'=>$body['error']['details']??$body['data']['validation']['errors']??[],'data'=>$body['data']??null],
            in_array($status,[400,401,403,404,409,422,429,503],true)?$status:502);
    }
    return $result['body']['data']??[];
}
function app_gre_failure(Throwable $error): never
{
    if ($error instanceof DomainException) app_gre_reply(['success'=>false,'message'=>$error->getMessage()],422);
    app_gre_reply(['success'=>false,'message'=>'No se pudo completar la operación GRE. Consulta su estado antes de reintentar.'],502);
}
