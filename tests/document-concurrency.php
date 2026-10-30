<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/../global/sales-fulfillment.php';
if (!str_ends_with(DB,'_qa')) throw new RuntimeException('Use una base _qa.');

if (($argv[1]??'')==='worker') {
    try {
        $orderId=(int)$argv[2];$quantity=(int)$argv[3];$userId=(int)$argv[4];$hold=($argv[5]??'')==='hold';
        $pdo->beginTransaction();
        // Establish an old REPEATABLE READ snapshot before waiting for the root lock.
        $pdo->query('SELECT COUNT(*) FROM tbl_document_line_application')->fetchColumn();
        echo "READY\n";fflush(STDOUT);
        $order=app_document_header($pdo,'sales_order',$orderId);
        $q=$pdo->prepare('SELECT id,item_id FROM tbl_sales_order_detail WHERE sales_order_id=? ORDER BY id LIMIT 1');$q->execute([$orderId]);$line=$q->fetch();
        $pdo->prepare("INSERT INTO tbl_remission_guide(number,status,source_type,source_id,warehouse_id,issue_date,transfer_date,created_by) VALUES(?,'Registrada','sales_order',?,?,CURDATE(),CURDATE(),?)")->execute(['QA-CONCURRENT-'.bin2hex(random_bytes(4)),$orderId,$order['warehouse_id'],$userId]);$guide=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO tbl_remission_guide_detail(remission_guide_id,source_detail_id,product_id,quantity) VALUES(?,?,?,?)')->execute([$guide,$line['id'],$line['item_id'],$quantity]);
        app_post_sales_delivery($pdo,$guide,$userId);
        if ($hold) {echo "POSTED\n";fflush(STDOUT);stream_set_timeout(STDIN,15);if(trim((string)fgets(STDIN))!=='commit')throw new RuntimeException('No se recibió commit del coordinador.');}
        $pdo->commit();echo "COMMITTED\n";exit(0);
    }catch(AppValidationException $error){if($pdo->inTransaction())$pdo->rollBack();echo 'REJECTED '.$error->errors[0]['message']."\n";exit(0);}
    catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'ERROR '.$error->getMessage()."\n");exit(2);}
}

function concurrentAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function concurrencyWorker(int $order,int $quantity,int $user,string $hold=''):array {
    $pipes=[];$handle=proc_open([PHP_BINARY,__FILE__,'worker',(string)$order,(string)$quantity,(string)$user,$hold],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),null,['bypass_shell'=>true]);
    if(!is_resource($handle))throw new RuntimeException('No se pudo iniciar el proceso concurrente.');
    stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],5);
    return ['handle'=>$handle,'pipes'=>$pipes];
}
$order=$product=null;$workers=[];$testFailed=false;
try{
    $user=(int)$pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();$customer=(int)$pdo->query('SELECT client_id FROM tbl_customer WHERE active_status=1 ORDER BY client_id LIMIT 1')->fetchColumn();$warehouse=(int)$pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    $suffix=bin2hex(random_bytes(4));$today=date('Y-m-d');$pdo->beginTransaction();
    $pdo->prepare("INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES(?,'QA','Concurrencia QA','Temporal',0,10,'UND',1)")->execute(['QA-CONC-'.$suffix]);$product=(int)$pdo->lastInsertId();
    warehouse_apply_stock_delta($pdo,$product,10,STOCK_MOVEMENT_ENTRY,$user,'Inicial QA','goods_receipt',999007,'QA-CONC',null,$warehouse);warehouse_apply_lot_delta($pdo,$product,$warehouse,'QA-CONC-'.$suffix,'2028-01-01',10);warehouse_apply_reservation_delta($pdo,$product,10,$warehouse);
    $pdo->prepare("INSERT INTO tbl_sales_order(number,status,customer_id,warehouse_id,customer_name,issue_date,delivery_date,currency,total_sub,total_tax,total_net,user_id) VALUES(?,'Confirmada',?,?,'Cliente QA',?,?,'PEN',100,18,118,?)")->execute(['QA-CONC-'.$suffix,$customer,$warehouse,$today,$today,$user]);$order=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_sales_order_detail(sales_order_id,item_id,item_code,item_name,item_description,item_quantity,item_unit_price,item_total) VALUES(?,?,'QA','Concurrencia','Temporal',10,10,100)")->execute([$order,$product]);$pdo->commit();
    $workers[]=concurrencyWorker($order,6,$user,'hold');$first=$workers[0];
    concurrentAssert(trim((string)fgets($first['pipes'][1]))==='READY','El primer proceso no inició.');
    concurrentAssert(trim((string)fgets($first['pipes'][1]))==='POSTED','El primer proceso no retuvo la entrega.');
    $workers[]=concurrencyWorker($order,5,$user);$second=$workers[1];
    concurrentAssert(trim((string)fgets($second['pipes'][1]))==='READY','El segundo proceso no creó su snapshot anterior.');
    fwrite($first['pipes'][0],"commit\n");fflush($first['pipes'][0]);
    concurrentAssert(trim((string)fgets($first['pipes'][1]))==='COMMITTED','La primera entrega no se confirmó.');
    $outcome=trim((string)fgets($second['pipes'][1]));
    concurrentAssert(str_starts_with($outcome,'REJECTED') && str_contains($outcome,'4 unidades pendientes'),'La segunda entrega utilizó saldo obsoleto: '.$outcome);
    foreach($workers as &$worker){foreach($worker['pipes'] as $pipe)fclose($pipe);$code=proc_close($worker['handle']);$worker['handle']=null;concurrentAssert($code===0,'Un proceso concurrente falló.');}unset($worker);
    $q=$pdo->prepare('SELECT quantity,reserved_quantity FROM tbl_warehouse_stock WHERE warehouse_id=? AND product_id=?');$q->execute([$warehouse,$product]);$stock=$q->fetch();
    concurrentAssert((float)$stock['quantity']===4.0 && (float)$stock['reserved_quantity']===4.0,'Las entregas concurrentes sobrevendieron el stock.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tbl_remission_guide WHERE source_type='sales_order' AND source_id=?");$q->execute([$order]);concurrentAssert((int)$q->fetchColumn()===1,'Quedó una entrega parcial tras el rechazo concurrente.');
    concurrentAssert(app_document_open_lines($pdo,'sales_order',$order,'FULFILLMENT')[0]['open_quantity']===4.0,'El saldo no concilia tras concurrencia.');
    echo "PASS two-process concurrent delivery uses current locked balances despite an older snapshot; only 6 of 10 posted\n";
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL document concurrency: '.$error->getMessage().PHP_EOL);$testFailed=true;}
finally{
    foreach($workers as $worker)if(is_resource($worker['handle'])){proc_terminate($worker['handle']);foreach($worker['pipes'] as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($worker['handle']);}
    $pdo->beginTransaction();
    if($order){$q=$pdo->prepare("SELECT id FROM tbl_remission_guide WHERE source_type='sales_order' AND source_id=?");$q->execute([$order]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $guide){$pdo->prepare('DELETE FROM tbl_remission_guide_detail WHERE remission_guide_id=?')->execute([$guide]);$pdo->prepare('DELETE FROM tbl_remission_guide WHERE id=?')->execute([$guide]);}$pdo->prepare("DELETE FROM tbl_document_line_application WHERE source_type='sales_order' AND source_id=?")->execute([$order]);$pdo->prepare('DELETE FROM tbl_sales_order_detail WHERE sales_order_id=?')->execute([$order]);$pdo->prepare('DELETE FROM tbl_sales_order WHERE id=?')->execute([$order]);}
    if($product){foreach(['tbl_inventory_lot_application','tbl_warehouse_movement','tbl_product_lot','tbl_warehouse_stock'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?')->execute([$product]);$pdo->prepare('DELETE FROM tbl_product WHERE id=?')->execute([$product]);}
    $pdo->commit();
}
exit($testFailed?1:0);
