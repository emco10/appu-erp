<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require $root.'/global/config.php';session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);app_start_session();require $root.'/global/connection.php';require_once $root.'/modules/inventory/stock.php';
$pdo->beginTransaction();
try{
 $userId=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();$warehouseId=(int)$pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();if(!$userId||!$warehouseId)throw new RuntimeException('Faltan datos base.');
 $code='QA-INV-'.substr(uniqid(),-7);$pdo->prepare('INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES (?,?,?,?,0,?,?,1)')->execute([$code,'APPU','ARTICULO INVENTARIO QA','Temporal',10,'UND']);$productId=(int)$pdo->lastInsertId();
 warehouse_apply_stock_delta($pdo,$productId,10,STOCK_MOVEMENT_ENTRY,$userId,'Ingreso QA','goods_receipt',999001,'QA-IN',null,$warehouseId);warehouse_apply_lot_delta($pdo,$productId,$warehouseId,'LOTE-QA',date('Y-m-d',strtotime('+90 days')),10);
 warehouse_apply_reservation_delta($pdo,$productId,4,$warehouseId);
 $pdo->exec('SAVEPOINT before_overdraw');$overdrawRejected=false;try{warehouse_apply_stock_delta($pdo,$productId,-7,STOCK_MOVEMENT_SALE,$userId,'Salida invalida','invoice',999002,'QA-OUT',null,$warehouseId);}catch(DomainException $expected){$overdrawRejected=true;}finally{$pdo->exec('ROLLBACK TO SAVEPOINT before_overdraw');}if(!$overdrawRejected)throw new RuntimeException('Permitio consumir stock reservado.');
 warehouse_apply_stock_delta($pdo,$productId,-6,STOCK_MOVEMENT_SALE,$userId,'Salida QA','invoice',999003,'QA-OUT',null,$warehouseId);
 $stock=$pdo->prepare('SELECT quantity,reserved_quantity FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?');$stock->execute([$warehouseId,$productId]);$afterSale=$stock->fetch();if((float)$afterSale['quantity']!==4.0||(float)$afterSale['reserved_quantity']!==4.0)throw new RuntimeException('Salida o reserva inconsistente.');
 warehouse_apply_stock_delta($pdo,$productId,6,STOCK_MOVEMENT_DOCUMENT_REVERSAL,$userId,'Reversion QA','invoice',999003,'QA-OUT',null,$warehouseId);warehouse_apply_reservation_delta($pdo,$productId,-4,$warehouseId);
 $stock->execute([$warehouseId,$productId]);$final=$stock->fetch();$master=$pdo->prepare('SELECT stock_quantity FROM tbl_product WHERE id=?');$master->execute([$productId]);$lot=$pdo->prepare('SELECT quantity FROM tbl_product_lot WHERE warehouse_id=? AND product_id=? AND lot_number=?');$lot->execute([$warehouseId,$productId,'LOTE-QA']);
 if((float)$final['quantity']!==10.0||(float)$final['reserved_quantity']!==0.0||(float)$master->fetchColumn()!==10.0||(float)$lot->fetchColumn()!==10.0)throw new RuntimeException('La reversion no reconcilio stock maestro, almacen y lote.');
 echo 'PASS inventory stock reservation lot reversal lifecycle'.PHP_EOL;$pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL inventory lifecycle: '.$e->getMessage().PHP_EOL);exit(1);}
