<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); require $root.'/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);
app_start_session(); $_SESSION['loggedInUser']=['USERID'=>0,'ROLE_CODE'=>'admin','PERMISSIONS'=>[]];
$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['SCRIPT_NAME']='/modules/productos/insert-update-producto.php'; $_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';
chdir($root.'/modules/productos'); $code='QA-PROD-001'; $productId=null;
$base=['producto_id'=>'','producto_code'=>$code,'producto_nombre'=>'ARTÍCULO PRUEBA APPU','producto_description'=>'Artículo temporal','producto_marca'=>'APPU','producto_unitvalue'=>'UND','producto_precio'=>'19.90','producto_barcode'=>'QA000001','producto_sunat_code'=>'','producto_sunat_unit'=>'NIU','producto_tax_code'=>'10','producto_osce_code'=>'','producto_manufacturer_code'=>'','producto_model'=>'','custom_field'=>[]];
try {
    require '../../global/connection.php';
    $find=$pdo->prepare('SELECT id FROM tbl_product WHERE code=?'); $find->execute([$code]);
    foreach($find->fetchAll(PDO::FETCH_COLUMN) as $stale){$pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='product' AND record_id=?")->execute([$stale]);$pdo->prepare('DELETE FROM tbl_product WHERE id=?')->execute([$stale]);}
    $_POST=$base; ob_start(); include 'insert-update-producto.php'; $result=trim(ob_get_clean());
    if($result!=='OK_INSERT') throw new RuntimeException('Inserción fallida: '.$result);
    $find->execute([$code]); $productId=(int)$find->fetchColumn(); if(!$productId) throw new RuntimeException('No se recuperó el artículo.');
    $stock=$pdo->prepare('SELECT stock_quantity FROM tbl_product WHERE id=?'); $stock->execute([$productId]);
    if((int)$stock->fetchColumn()!==0) throw new RuntimeException('El artículo no inició con stock cero.');
    $_POST=array_merge($base,['producto_id'=>(string)$productId,'producto_nombre'=>'ARTÍCULO PRUEBA APPU EDITADO','producto_precio'=>'25.50']);
    ob_start(); include 'insert-update-producto.php'; $result=trim(ob_get_clean());
    if($result!=='OK_UPDATE') throw new RuntimeException('Actualización fallida: '.$result);
    $_SERVER['SCRIPT_NAME']='/modules/productos/consultar-productos.php'; $_POST=['FILTER'=>'PROD-'.$productId,'ESTADO'=>'ALL'];
    ob_start(); include 'consultar-productos.php'; $detail=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
    if(($detail[0]['NOMBRE']??'')!=='ARTÍCULO PRUEBA APPU EDITADO') throw new RuntimeException('La búsqueda no devolvió la edición.');
    echo 'PASS product create-search-edit lifecycle'.PHP_EOL;
} finally {
    if(isset($pdo)&&$productId){$pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='product' AND record_id=?")->execute([$productId]);$pdo->prepare('DELETE FROM tbl_product WHERE id=?')->execute([$productId]);}
}
