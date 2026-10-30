<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($maxLifetime) => 0);
app_start_session();
$_SESSION['loggedInUser'] = ['USERID'=>0,'ROLE_CODE'=>'admin','PERMISSIONS'=>[]];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = '/modules/proveedores/insert-update-proveedor.php';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
chdir($root . '/modules/proveedores');
$code = 'QA-PROV-001';
$providerId = null;
$base = [
    'proveedor_id'=>'','proveedor_numero'=>$code,'proveedor_razsoc'=>'PROVEEDOR PRUEBA APPU',
    'proveedor_pais'=>'Perú','proveedor_ciudad'=>'Lima','proveedor_distrito'=>'San Isidro','proveedor_direccion'=>'Dirección de prueba',
    'proveedor_contnom_1'=>'','proveedor_conttelef_1'=>'','proveedor_contnom_2'=>'','proveedor_conttelef_2'=>'',
    'proveedor_banco_1'=>'','proveedor_tipmoneda_1'=>'','proveedor_ctacorriente_1'=>'','proveedor_titularcta_1'=>'',
    'proveedor_banco_2'=>'','proveedor_tipmoneda_2'=>'','proveedor_ctacorriente_2'=>'','proveedor_titularcta_2'=>'',
    'proveedor_active_status'=>'1','proveedor_moneda'=>'USD','proveedor_dias_pago'=>'45','proveedor_limite_credito'=>'5000','proveedor_comprador_id'=>'','custom_field'=>[],
];
try {
    require '../../global/connection.php';
    $find=$pdo->prepare('SELECT id FROM tbl_provider WHERE code=?'); $find->execute([$code]);
    foreach($find->fetchAll(PDO::FETCH_COLUMN) as $stale){$pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='provider' AND record_id=?")->execute([$stale]);$pdo->prepare('DELETE FROM tbl_provider WHERE id=?')->execute([$stale]);}
    $_POST=$base; ob_start(); include 'insert-update-proveedor.php'; $result=trim(ob_get_clean());
    if($result!=='OK_INSERT') throw new RuntimeException('Inserción fallida: '.$result);
    $find->execute([$code]); $providerId=(int)$find->fetchColumn(); if(!$providerId) throw new RuntimeException('No se recuperó el proveedor.');
    $_POST=array_merge($base,['proveedor_id'=>(string)$providerId,'proveedor_razsoc'=>'PROVEEDOR PRUEBA APPU EDITADO','proveedor_active_status'=>'0']);
    ob_start(); include 'insert-update-proveedor.php'; $result=trim(ob_get_clean());
    if($result!=='OK_UPDATE') throw new RuntimeException('Actualización fallida: '.$result);
    $_SERVER['SCRIPT_NAME']='/modules/proveedores/consultar-proveedor.php'; $_POST=['FILTER'=>'PROV-'.$providerId];
    ob_start(); include 'consultar-proveedor.php'; $detail=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
    if(($detail[0]['RAZ_SOC']??'')!=='PROVEEDOR PRUEBA APPU EDITADO') throw new RuntimeException('La búsqueda no devolvió la edición.');
    if(($detail[0]['ACTIVE_STATUS']??1)!==0) throw new RuntimeException('El estado inactivo del proveedor no se recuperó en el formulario.');
    if(($detail[0]['DEFAULT_CURRENCY']??'')!=='USD'||(int)($detail[0]['PAYMENT_DAYS']??-1)!==45||abs((float)($detail[0]['CREDIT_LIMIT']??0)-5000)>0.001) throw new RuntimeException('Las condiciones comerciales del proveedor no persistieron.');
    $_GET=['q'=>'*','include_inactive'=>'1'];
    ob_start(); include 'obtener-proveedores.php'; $searchResults=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
    $foundInactive=array_filter($searchResults,fn($item)=>(int)($item['id']??0)===$providerId);
    if(!$foundInactive) throw new RuntimeException('La búsqueda maestra no recuperó el proveedor inactivo.');
    $_GET=[];
    if(!str_contains(file_get_contents($root.'/modules/proveedores/obtener-proveedores.php'),'include_inactive')) throw new RuntimeException('La búsqueda maestra no contempla proveedores inactivos.');
    echo 'PASS provider create-search-edit-state lifecycle'.PHP_EOL;
} finally {
    if(isset($pdo)&&$providerId){$pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='provider' AND record_id=?")->execute([$providerId]);$pdo->prepare('DELETE FROM tbl_provider WHERE id=?')->execute([$providerId]);}
}
