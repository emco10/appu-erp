<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/global/config.php';
if (DB !== 'db_appu_qa' || !in_array(SERVER, ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Esta prueba requiere la base QA local.');
}
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
$_SESSION['loggedInUser'] = ['USERID' => 0, 'ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = '/modules/clientes/insert-update-cliente.php';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
chdir($root . '/modules/clientes');

$suffix = (string) random_int(100000000, 999999999);
$ruc = '20' . $suffix;
$businessName = 'CLIENTE QA ' . $suffix;
$customerId = null;
$base = [
    'cliente_id' => '', 'cliente_ruc' => $ruc, 'cliente_razsoc' => $businessName,
    'cliente_nomcom' => 'PRUEBA APPU', 'cliente_correo' => 'qa@appu.local',
    'cliente_telfij' => '', 'cliente_telcel' => '999999999', 'cliente_direccion' => 'Dirección de prueba',
    'cliente_departamento' => '15', 'cliente_provincia' => '1501', 'cliente_distrito' => '150131',
    'cliente_nomcont_1' => '', 'cliente_celcont_1' => '', 'cliente_nomcont_2' => '',
    'cliente_celcont_2' => '', 'cliente_fecreg' => date('Y-m-d'),
    'cliente_active_status' => '1', 'cliente_moneda' => 'USD', 'cliente_dias_pago' => '30',
    'cliente_limite_credito' => '1000.50', 'cliente_lista_precio_id' => '', 'cliente_vendedor_id' => '', 'custom_field' => [],
];

try {
    require '../../global/connection.php';
    $_POST = $base;
    ob_start(); include 'insert-update-cliente.php'; $insertResult = trim(ob_get_clean());
    if ($insertResult !== 'OK_INSERT') throw new RuntimeException('Inserción fallida: ' . $insertResult);
    $find = $pdo->prepare('SELECT client_id,business_name,commission FROM tbl_customer WHERE ruc=?');
    $find->execute([$ruc]);
    $created = $find->fetch();
    if (!$created) throw new RuntimeException('El cliente insertado no pudo recuperarse.');
    $customerId = (int) $created['client_id'];
    if ((float) $created['commission'] !== 0.0) throw new RuntimeException('El alta sin comisión debe conservar el valor predeterminado cero.');
    $pdo->prepare('UPDATE tbl_customer SET commission=? WHERE client_id=?')->execute([25, $customerId]);

    $editedName = $businessName . ' EDITADO';
    $_POST = array_merge($base, ['cliente_id' => (string) $customerId, 'cliente_razsoc' => $editedName, 'cliente_active_status' => '0']);
    ob_start(); include 'insert-update-cliente.php'; $updateResult = trim(ob_get_clean());
    if ($updateResult !== 'OK_UPDATE') throw new RuntimeException('Actualización fallida: ' . $updateResult);
    $find->execute([$ruc]);
    $updated = $find->fetch();
    if (($updated['business_name'] ?? '') !== $editedName) throw new RuntimeException('La edición no persistió.');
    if ((float) $updated['commission'] !== 25.0) throw new RuntimeException('La edición debe conservar la comisión histórica.');

    // An older page may still send the retired field.
    $_POST['cliente_pagocomision'] = '';
    ob_start(); include 'insert-update-cliente.php'; $legacyResult = trim(ob_get_clean());
    $find->execute([$ruc]);
    $legacy = $find->fetch();
    if ($legacyResult !== 'OK_UPDATE' || !$legacy || (float) $legacy['commission'] !== 25.0) {
        throw new RuntimeException('El formulario anterior debe poder editar sin modificar la comisión histórica.');
    }

    $_SERVER['SCRIPT_NAME'] = '/modules/clientes/consultar-cliente.php';
    $_POST = ['FILTER' => 'CLI-' . $customerId];
    ob_start(); include 'consultar-cliente.php'; $detail = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    if (($detail[0]['CODIGO'] ?? 0) !== $customerId) throw new RuntimeException('La búsqueda no recuperó el cliente creado.');
    if (($detail[0]['ACTIVE_STATUS'] ?? 1) !== 0) throw new RuntimeException('El estado inactivo del cliente no se recuperó en el formulario.');
    if (($detail[0]['DEFAULT_CURRENCY'] ?? '') !== 'USD' || (int)($detail[0]['PAYMENT_DAYS'] ?? -1) !== 30 || abs((float)($detail[0]['CREDIT_LIMIT'] ?? 0)-1000.50)>0.001 || empty($detail[0]['PRICE_LIST_ID'])) throw new RuntimeException('Las condiciones comerciales del cliente no persistieron.');
    if (!str_contains(file_get_contents($root . '/modules/clientes/obtener-clientes.php'), 'include_inactive')) throw new RuntimeException('La búsqueda maestra no contempla clientes inactivos.');
    echo 'PASS customer create-search-edit-state lifecycle' . PHP_EOL;
} finally {
    if (isset($pdo) && $customerId) {
        $pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='customer' AND record_id=?")->execute([$customerId]);
        $pdo->prepare('DELETE FROM tbl_customer WHERE client_id=?')->execute([$customerId]);
    }
}
