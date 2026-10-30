<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require $root.'/global/config.php';session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);app_start_session();$_SESSION['loggedInUser']=['USERID'=>0,'ROLE_CODE'=>'admin','PERMISSIONS'=>[]];$_SERVER['REQUEST_METHOD']='GET';$_GET=[];chdir($root.'/modules/reportes');ob_start();include 'inventario.php';$raw=ob_get_clean();$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
foreach(['summary','stock','movements','expirations','warehouses','integrity_issues'] as $key){if(!array_key_exists($key,$data))throw new RuntimeException('Falta bloque de inventario: '.$key);}
if(!array_key_exists('total_inventory_value',$data['summary']))throw new RuntimeException('Falta valorización total del inventario.');
if($data['stock'] && (!array_key_exists('average_unit_cost',$data['stock'][0]) || !array_key_exists('inventory_value',$data['stock'][0])))throw new RuntimeException('Faltan costos de inventario por artículo.');
echo 'PASS inventory report JSON integrity_issues='.count($data['integrity_issues']).PHP_EOL;
