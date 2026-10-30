<?php
/**
 * Adaptador de compatibilidad para pantallas antiguas de Compras.
 * Toda entrada de mercancías se procesa en goods-receipts.php para evitar
 * reglas divergentes de inventario, cuentas por pagar y trazabilidad.
 */
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
    if(isset($_GET['purchase_id'])&&!isset($_GET['base_id'])){
        $_GET['base_type']='PURCHASE';
        $_GET['base_id']=$_GET['purchase_id'];
    }
}
if($method==='POST'&&strtolower(trim((string)($_POST['action']??'create')))==='create'){
    if(isset($_POST['purchase_id'])&&!isset($_POST['base_id'])){
        $_POST['base_type']='PURCHASE';
        $_POST['base_id']=$_POST['purchase_id'];
    }
    $items=json_decode((string)($_POST['items']??''),true);
    if(is_array($items)){
        foreach($items as &$item){
            if(!isset($item['base_line_id'])&&isset($item['purchase_detail_id']))$item['base_line_id']=$item['purchase_detail_id'];
        }
        unset($item);
        $_POST['items']=json_encode($items,JSON_UNESCAPED_UNICODE);
    }
}
require __DIR__.'/goods-receipts.php';
