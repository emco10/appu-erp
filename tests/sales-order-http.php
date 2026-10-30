<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/connection.php';
if (!str_ends_with(DB, '_qa')) throw new RuntimeException('Esta prueba requiere una base _qa.');
$base = getenv('APPU_QA_URL') ?: 'http://127.0.0.1:8765';
if (!in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1','localhost','::1'], true)) throw new RuntimeException('Use un servidor QA local.');
$cookie = ''; $csrf = ''; $qaDatabase = '';
function orderHttp(string $path, ?array $data = null, bool $secure = true): array {
    global $base, $cookie, $csrf, $qaDatabase;
    $headers = ['Cookie: ' . $cookie, 'Content-Type: application/x-www-form-urlencoded'];
    if ($secure) $headers[] = 'X-CSRF-Token: ' . $csrf;
    $context = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $data === null ? '' : http_build_query($data), 'ignore_errors' => true, 'timeout' => 15]]);
    $body = file_get_contents($base . $path, false, $context);
    if ($body === false) throw new RuntimeException('El servidor QA no respondió.');
    foreach ($http_response_header as $header) if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $match) && !str_contains($header, 'Max-Age=0') && !str_ends_with($match[1], '=deleted')) $cookie = $match[1];
    foreach ($http_response_header as $header) if (preg_match('/^X-APPU-QA-Database:\s*(\S+)/i', $header, $match)) $qaDatabase = $match[1];
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) ($match[1] ?? 0), json_decode($body, true), $body];
}
function httpAssert(bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); }
$id = $fieldId = $listId = null;
$key = 'qa_http_' . bin2hex(random_bytes(5)); $listCode = strtoupper($key);
try {
    [$status, , $html] = orderHttp('/views/ventas/resumen-ordenes-venta');
    httpAssert($qaDatabase === DB, 'El servidor HTTP no acredita la misma base QA; no se ejecutarán escrituras.');
    httpAssert($status === 200 && preg_match('/name="csrf-token" content="([^"]+)"/', $html, $match), 'No se renderizó la orden con CSRF.'); $csrf = $match[1];
    [$status] = orderHttp('/modules/ventas/ordenes.php', ['action' => 'create_direct'], false); httpAssert($status === 419, 'Se aceptó una escritura sin CSRF.');
    [$status, $error] = orderHttp('/modules/ventas/ordenes.php', ['action' => 'create_direct']);
    httpAssert($status === 422 && $error['code'] === 'VALIDATION_ERROR' && count($error['errors']) >= 5, 'La API no devolvió todos los errores: ' . $status . ' ' . json_encode($error));
    [$status] = orderHttp('/modules/gestion/configuracion.php', ['action' => 'value_list','code' => $listCode,'name' => 'Canales HTTP QA']); httpAssert($status === 200, 'No se creó la lista desde Administración.');
    $q = $pdo->prepare('SELECT id FROM tbl_app_value_list WHERE code=?'); $q->execute([$listCode]); $listId = (int) $q->fetchColumn();
    foreach (['CORPORATIVO','RETAIL','MARKETPLACE'] as $value) { [$status] = orderHttp('/modules/gestion/configuracion.php', ['action' => 'value_option','list_id' => $listId,'value_code' => $value,'label' => $value]); httpAssert($status === 200, 'No se creó la opción.'); }
    [$status, $result] = orderHttp('/modules/gestion/configuracion.php', ['action' => 'custom_field','entity' => 'sales_order','field_key' => $key,'label' => 'Canal de venta HTTP QA','field_type' => 'select','value_list_code' => $listCode,'required_status' => 1,'searchable' => 1,'copy_to_target' => 1]);
    httpAssert($status === 200, 'No se configuró el campo: ' . json_encode($result));
    $q = $pdo->prepare("SELECT id FROM tbl_custom_field_definition WHERE entity='sales_order' AND field_key=?"); $q->execute([$key]); $fieldId = (int) $q->fetchColumn();
    [$status, $catalog] = orderHttp('/modules/ventas/ordenes.php?catalog=1');
    httpAssert($status === 200 && in_array($fieldId, array_column($catalog['custom_fields'], 'id')), 'El campo no apareció en los metadatos del formulario.');
    $input = ['action' => 'create_direct','customer_id' => $catalog['customers'][0]['id'],'warehouse_id' => $catalog['warehouses'][0]['id'],'delivery_date' => '2026-10-30','currency' => 'PEN','reference' => $key,'details' => json_encode([['product_id' => $catalog['products'][0]['id'],'quantity' => 2,'unit_price' => 10]])];
    foreach ($catalog['custom_fields'] as $field) {
        if ((int) $field['id'] === $fieldId || !(int) $field['required_status']) continue;
        $input['custom_field'][$field['id']] = ['text'=>'QA','number'=>'1','date'=>'2026-10-30','checkbox'=>'1','select'=>$field['options'][0]['value_code'] ?? ''][$field['field_type']];
    }
    [$status, $error] = orderHttp('/modules/ventas/ordenes.php', $input);
    httpAssert($status === 422 && in_array('custom_field[' . $fieldId . ']', array_column($error['errors'], 'path')), 'No se rechazó Canal obligatorio vacío.');
    $input['custom_field'][$fieldId] = 'MARKETPLACE';
    [$status, $saved] = orderHttp('/modules/ventas/ordenes.php', $input); httpAssert($status === 200 && $saved['order_id'] > 0, 'No se guardó la orden.'); $id = (int) $saved['order_id'];
    [$status, $snapshot] = orderHttp('/modules/ventas/ordenes.php?view_id=' . $id);
    httpAssert($status === 200 && $snapshot['custom_fields'][$fieldId] === 'MARKETPLACE' && abs($snapshot['order']['total_net'] - 23.6) < .001, 'No permanecen el campo y el total al consultar.');
    $query = http_build_query(['search'=>1,'custom_field_id'=>$fieldId,'custom_field_value'=>'MARKETPLACE','q'=>$key,'per_page'=>1]);
    [$status, $found] = orderHttp('/modules/ventas/ordenes.php?' . $query);
    httpAssert($status === 200 && count($found['orders']) === 1 && (int) $found['orders'][0]['id'] === $id && $found['pagination']['total'] === 1, 'La búsqueda por UDF no encontró la orden.');
    [$status, $found] = orderHttp('/modules/ventas/ordenes.php?' . str_replace('MARKETPLACE', 'RETAIL', $query)); httpAssert($status === 200 && !$found['orders'], 'El filtro UDF devuelve resultados ajenos.');
    $input['action'] = 'update'; $input['order_id'] = $id; $input['version'] = $snapshot['version']; $input['reference'] = $key . '_edit';
    [$status] = orderHttp('/modules/ventas/ordenes.php', $input); httpAssert($status === 200, 'No se editó la orden por HTTP.');
    [$status, $conflict] = orderHttp('/modules/ventas/ordenes.php', $input); httpAssert($status === 409 && $conflict['code'] === 'CONFLICT', 'Se aceptó una versión obsoleta.');
    [$status, $snapshot] = orderHttp('/modules/ventas/ordenes.php?view_id=' . $id); httpAssert($snapshot['order']['reference'] === $key . '_edit', 'El conflicto alteró la edición válida.');
    echo "PASS HTTP CSRF aggregate errors admin UDF configuration required validation persistence exact search edit and version conflict\n";
} catch (Throwable $error) { fwrite(STDERR, 'FAIL sales order HTTP: ' . $error->getMessage() . PHP_EOL); $failed = true; }
finally {
    $pdo->beginTransaction();
    if ($id) {
        $pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='sales_order' AND record_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tbl_document_status_history WHERE document_type='sales_order' AND document_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM tbl_sales_order_detail WHERE sales_order_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM tbl_sales_order WHERE id=?')->execute([$id]);
    }
    if ($fieldId) $pdo->prepare('DELETE FROM tbl_custom_field_definition WHERE id=?')->execute([$fieldId]);
    if ($listId) { $pdo->prepare('DELETE FROM tbl_app_value_option WHERE list_id=?')->execute([$listId]); $pdo->prepare('DELETE FROM tbl_app_value_list WHERE id=?')->execute([$listId]); }
    $pdo->commit();
}
exit(!empty($failed) ? 1 : 0);
