<?php
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    require '../../global/connection.php';
    require_once '../inventory/stock.php';
    app_require_permission('inventory.manage');
    $type=trim((string)($_POST['mov_tipo']??''));
    $productId=filter_var($_POST['mov_prod_code']??null,FILTER_VALIDATE_INT);
    $warehouseId=filter_var($_POST['mov_warehouse_id']??null,FILTER_VALIDATE_INT);
    $quantity=filter_var($_POST['mov_cantidad']??null,FILTER_VALIDATE_FLOAT);
    $providerId=filter_var($_POST['mov_prov']??null,FILTER_VALIDATE_INT);$providerId=$providerId===false||$providerId<1?null:(int)$providerId;
    $reference=trim((string)($_POST['mov_guia_orden']??''));$observation=trim((string)($_POST['mov_obs']??''));
    $lotNumber=trim((string)($_POST['mov_lote']??''));$expirationDate=trim((string)($_POST['mov_fec_venc']??''));
    $movementType=$type==='Ingreso'?STOCK_MOVEMENT_ENTRY:($type==='Ajuste'?STOCK_MOVEMENT_ADJUSTMENT:0);
    if($movementType===0||$productId===false||$productId<1||$warehouseId===false||$warehouseId<1||$quantity===false||$quantity==0||($movementType===STOCK_MOVEMENT_ENTRY&&$quantity<0)){http_response_code(422);echo 'ERROR';exit;}
    if($expirationDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expirationDate)){http_response_code(422);echo 'ERROR';exit;}
    if($expirationDate!==''&&$lotNumber===''){http_response_code(422);echo 'ERROR';exit;}
    try{
        $pdo->beginTransaction();
        $warehouse=$pdo->prepare('SELECT id FROM tbl_warehouse WHERE id=? AND active_status=1');$warehouse->execute([$warehouseId]);if(!$warehouse->fetchColumn())throw new DomainException('El almacen no existe o esta inactivo.');
        $product=$pdo->prepare('SELECT id FROM tbl_product WHERE id=? AND active_status=1');$product->execute([$productId]);if(!$product->fetchColumn())throw new DomainException('El articulo no existe o esta inactivo.');
        if($movementType===STOCK_MOVEMENT_ENTRY&&$providerId===null)throw new DomainException('Selecciona el proveedor del ingreso.');
        $lotCount=$pdo->prepare('SELECT COUNT(*) FROM tbl_product_lot WHERE warehouse_id=? AND product_id=? AND quantity>0');$lotCount->execute([$warehouseId,$productId]);
        if((int)$lotCount->fetchColumn()>0&&$lotNumber==='')throw new DomainException('Este articulo controla lotes; indica el lote que se ajustara.');
        warehouse_apply_stock_delta($pdo,(int)$productId,(float)$quantity,$movementType,(int)$_SESSION['loggedInUser']['USERID'],$observation!==''?$observation:($movementType===STOCK_MOVEMENT_ENTRY?'Ingreso manual':'Ajuste manual'),null,null,$reference!==''?$reference:null,$providerId,(int)$warehouseId);
        if($lotNumber!=='')warehouse_apply_lot_delta($pdo,(int)$productId,(int)$warehouseId,$lotNumber,$expirationDate!==''?$expirationDate:null,(float)$quantity);
        $pdo->commit();echo 'OK_INSERT';
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();error_log('APPU stock movement error: '.$exception->getMessage());http_response_code(422);echo 'ERROR';}
}else{echo 'ERROR';}
