<?php
require_once __DIR__.'/currency.php';

/** Integer cents and largest remainders keep every allocation nonnegative. */
function app_landed_cost_allocation(array $details,string $baseField,float $totalCost):array
{
    $denominator=array_sum(array_map(static fn(array $line):float=>(float)$line[$baseField],$details));
    if($denominator<=0)throw new DomainException('No existe una base válida para distribuir el costo.');
    $cents=(int)round($totalCost*100);$result=[];$remainders=[];$assigned=0;
    foreach($details as $index=>$line){$share=$cents*(float)$line[$baseField]/$denominator;$whole=(int)floor($share);$result[$index]=$whole;$remainders[$index]=$share-$whole;$assigned+=$whole;}
    uksort($remainders,static function($a,$b)use($remainders):int{$order=$remainders[$b]<=>$remainders[$a];return $order?:$a<=>$b;});
    $remaining=$cents-$assigned;foreach(array_keys($remainders) as $index){if($remaining--<=0)break;$result[$index]++;}
    ksort($result);return array_map(static fn(int $amount):float=>$amount/100,$result);
}

function app_landed_cost_receipt(PDO $pdo,int $receiptId,bool $lock=false):array
{
    $statement=$pdo->prepare("SELECT receipt.id,receipt.number,receipt.status,receipt.purchase_id,receipt.purchase_order_id,receipt.provider_id,receipt.warehouse_id,receipt.receipt_date,receipt.currency,receipt.valuation_currency,receipt.valuation_exchange_rate,COALESCE(NULLIF(receipt.provider_name,''),provider.business_name) AS provider_name FROM tbl_goods_receipt receipt JOIN tbl_provider provider ON provider.id=receipt.provider_id WHERE receipt.id=?".($lock?' FOR UPDATE':''));
    $statement->execute([$receiptId]);$receipt=$statement->fetch();
    if(!$receipt||$receipt['status']!=='Registrado')throw new DomainException('Selecciona una recepción de mercancías activa.');
    $companyCurrency=app_currency_code((string)$pdo->query('SELECT currency FROM tbl_company_settings WHERE id=1')->fetchColumn());$receipt['currency']=app_currency_code((string)$receipt['currency']);
    if(!$receipt['valuation_currency']){if($receipt['currency']!==$companyCurrency)throw new DomainException('La recepción extranjera histórica no conserva moneda/tasa de valorización. Requiere revisión antes de asignar nuevos costos.');$receipt['valuation_currency']=$companyCurrency;}
    $receipt['cost_currency']=app_currency_code((string)$receipt['valuation_currency']);
    $details=$pdo->prepare('SELECT receipt_detail.id,receipt_detail.product_id,product.code,product.name,receipt_detail.quantity,receipt_detail.base_inventory_value AS base_value FROM tbl_goods_receipt_detail receipt_detail JOIN tbl_product product ON product.id=receipt_detail.product_id WHERE receipt_detail.goods_receipt_id=? ORDER BY receipt_detail.id'.($lock?' FOR UPDATE':''));
    $details->execute([$receiptId]);$lines=$details->fetchAll();if(!$lines)throw new DomainException('La recepción no contiene artículos valorizables.');
    return ['receipt'=>$receipt,'details'=>$lines];
}

function app_create_landed_cost(PDO $pdo,int $receiptId,string $concept,string $method,float $totalCost,string $costDate,string $notes,int $userId):array
{
    $concept=trim($concept);$method=strtoupper(trim($method));$validDate=DateTimeImmutable::createFromFormat('!Y-m-d',$costDate);
    if($userId<1||$receiptId<1||$concept===''||mb_strlen($concept)>100||!in_array($method,['VALUE','QUANTITY'],true)||!is_finite($totalCost)||$totalCost<0.01||$totalCost>999999999999.99||abs($totalCost-round($totalCost,2))>0.000001||!$validDate||$validDate->format('Y-m-d')!==$costDate)throw new InvalidArgumentException('Completa correctamente los datos del costo de entrega: importe positivo de hasta dos decimales y fecha válida.');
    $totalCost=round($totalCost,2);
    $source=app_landed_cost_receipt($pdo,$receiptId,true);$receipt=$source['receipt'];$details=$source['details'];
    $baseField=$method==='VALUE'?'base_value':'quantity';$denominator=array_sum(array_map(static fn($line)=>(float)$line[$baseField],$details));if($denominator<=0)throw new DomainException('No existe una base válida para distribuir el costo.');
    $allocations=app_landed_cost_allocation($details,$baseField,$totalCost);
    $number=app_next_document_number($pdo,'LANDED_COST','CE');$movementWatermark=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM tbl_warehouse_movement')->fetchColumn();
    $pdo->prepare("INSERT INTO tbl_landed_cost(number,goods_receipt_id,provider_id,warehouse_id,cost_date,concept,allocation_method,currency,total_cost,notes,created_by,movement_watermark_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$number,$receiptId,(int)$receipt['provider_id'],(int)$receipt['warehouse_id'],$costDate,$concept,$method,(string)$receipt['cost_currency'],$totalCost,substr(trim($notes),0,500),$userId,$movementWatermark]);$id=(int)$pdo->lastInsertId();
    $insert=$pdo->prepare('INSERT INTO tbl_landed_cost_detail(landed_cost_id,goods_receipt_detail_id,product_id,quantity,base_value,allocation_factor,allocated_cost) VALUES(?,?,?,?,?,?,?)');$allocated=0.0;$last=count($details)-1;
    foreach($details as $index=>$line){$factor=(float)$line[$baseField]/$denominator;$amount=$allocations[$index];$insert->execute([$id,(int)$line['id'],(int)$line['product_id'],(float)$line['quantity'],(float)$line['base_value'],$factor,$amount]);if($amount>0)warehouse_apply_value_adjustment($pdo,(int)$line['product_id'],(int)$receipt['warehouse_id'],$amount,$userId,'Costo de entrega '.$number,'landed_cost',$id,$number);}
    app_record_document_status($pdo,'landed_cost',$id,$number,'','Registrado','Creacion',$userId,$concept);app_record_document_flow($pdo,'goods_receipt',$receiptId,'landed_cost',$id,$userId,'LANDED_COST',$totalCost);
    return ['id'=>$id,'number'=>$number,'total_cost'=>$totalCost,'lines'=>count($details)];
}

function app_cancel_landed_cost(PDO $pdo,int $id,string $reason,int $userId):array
{
    $reason=trim($reason);if($id<1||$userId<1||mb_strlen($reason)<5||mb_strlen($reason)>500)throw new InvalidArgumentException('Indica un motivo de anulación de al menos 5 caracteres.');
    $statement=$pdo->prepare('SELECT * FROM tbl_landed_cost WHERE id=? FOR UPDATE');$statement->execute([$id]);$cost=$statement->fetch();if(!$cost)throw new RuntimeException('Costo de entrega inexistente.');if($cost['status']==='Anulado')return ['already_cancelled'=>true,'id'=>$id];
    $details=$pdo->prepare('SELECT product_id,allocated_cost FROM tbl_landed_cost_detail WHERE landed_cost_id=? FOR UPDATE');$details->execute([$id]);$lines=$details->fetchAll();
    $movement=$pdo->prepare('SELECT COUNT(*) FROM tbl_warehouse_movement WHERE warehouse_id=? AND product_id=? AND quantity<0 AND id>?');
    foreach($lines as $line){$movement->execute([(int)$cost['warehouse_id'],(int)$line['product_id'],(int)$cost['movement_watermark_id']]);if((int)$movement->fetchColumn()>0)throw new DomainException('No se puede anular el costo porque uno de sus artículos tuvo salidas posteriores.');}
    foreach($lines as $line)if((float)$line['allocated_cost']>0)warehouse_apply_value_adjustment($pdo,(int)$line['product_id'],(int)$cost['warehouse_id'],-(float)$line['allocated_cost'],$userId,'Anulación costo '.$cost['number'],'landed_cost',$id,(string)$cost['number']);
    $pdo->prepare("UPDATE tbl_landed_cost SET status='Anulado',cancelled_by=?,cancelled_at=NOW(),cancellation_reason=? WHERE id=?")->execute([$userId,$reason,$id]);app_record_document_status($pdo,'landed_cost',$id,(string)$cost['number'],'Registrado','Anulado','Anulacion',$userId,$reason);
    return ['already_cancelled'=>false,'id'=>$id];
}
