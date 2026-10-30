<?php
function app_validate_purchase_input(array $data,array $rows,bool $services=false):void
{
    foreach(['orden_fecemision','orden_fecentrega'] as $field){$value=trim((string)($data[$field]??''));$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('Las fechas del documento no son válidas.');}
    if((string)$data['orden_fecentrega']<(string)$data['orden_fecemision'])throw new DomainException('La fecha de entrega no puede ser anterior a la emisión.');
    if(!in_array($data['orden_tipomoneda']??'', ['PEN','USD'],true))throw new DomainException('Selecciona una moneda válida.');
    $days=filter_var($data['orden_tipopago']??null,FILTER_VALIDATE_INT);if($days===false||$days<0||$days>3650)throw new DomainException('Los días de pago no son válidos.');
    if(!$rows)throw new DomainException('Agrega al menos una línea al documento.');
    $subtotal=0;$seen=[];
    foreach($rows as $row){
        $priceIndex=$services?3:5;$quantityIndex=$services?4:6;$discountIndex=$services?5:7;$totalIndex=$services?6:8;
        $price=filter_var($row[$priceIndex]??null,FILTER_VALIDATE_FLOAT);$quantity=filter_var($row[$quantityIndex]??null,FILTER_VALIDATE_INT);$discount=filter_var($row[$discountIndex]??0,FILTER_VALIDATE_FLOAT);$amount=filter_var($row[$totalIndex]??null,FILTER_VALIDATE_FLOAT);
        if($price===false||!is_finite((float)$price)||$price<0||$quantity===false||$quantity<1||$discount===false||$discount<0||$discount>100||$amount===false)throw new DomainException('Cantidad, precio o descuento de línea inválidos.');
        if(!$services){$product=(int)($row[0]??0);if($product<1||isset($seen[$product]))throw new DomainException('No repitas artículos; consolida su cantidad en una línea.');$seen[$product]=true;}
        $calculated=round($price*$quantity*(1-$discount/100),2);if(abs($calculated-$amount)>0.011)throw new DomainException('El importe de una línea no coincide con su cantidad, precio y descuento.');$subtotal+=$calculated;
    }
    $postedSubtotal=filter_var($data['orden_totcompra']??null,FILTER_VALIDATE_FLOAT);$tax=filter_var($data['orden_igv']??null,FILTER_VALIDATE_FLOAT);$total=filter_var($data['orden_totneto']??null,FILTER_VALIDATE_FLOAT);
    if($postedSubtotal===false||$tax===false||$total===false||!is_finite((float)$tax)||$tax<0||abs(round($subtotal,2)-$postedSubtotal)>0.011||abs(round($subtotal+$tax,2)-$total)>0.011)throw new DomainException('Los totales del documento no coinciden con sus líneas.');
}

function app_validate_purchase_order_document(PDO $pdo, int $orderId, int $providerId, string $currency, array $postedRows, float $subtotal, float $tax, float $total, int $currentPurchaseId = 0): array
{
    $statement=$pdo->prepare('SELECT * FROM tbl_order WHERE id=? AND type=1 FOR UPDATE'); $statement->execute([$orderId]); $order=$statement->fetch();
    if(!$order||!in_array($order['status'],['Aprobado','Convertida','Parcial','Atendido'],true)) throw new DomainException('La orden de compra debe estar aprobada y vigente.');
    if((int)$order['provider_id']!==$providerId) throw new DomainException('El proveedor no coincide con la orden de compra.');
    if(strcasecmp((string)$order['currency'],$currency)!==0) throw new DomainException('La moneda no coincide con la orden de compra.');
    $linked=$pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id=? AND id<>? AND status NOT IN ('Anulado','Anulada','Cancelada')"); $linked->execute([$orderId,$currentPurchaseId]);
    if((int)$linked->fetchColumn()>0) throw new DomainException('La orden de compra ya tiene una factura de proveedor activa.');
    $details=$pdo->prepare('SELECT item_id,item_quantity,item_unit_price FROM tbl_order_detail WHERE order_id=? ORDER BY item_id'); $details->execute([$orderId]);
    $expected=[]; foreach($details->fetchAll() as $row){$id=(int)$row['item_id'];if($id<1||isset($expected[$id]))throw new DomainException('La orden contiene articulos invalidos o duplicados.');$expected[$id]=['quantity'=>(float)$row['item_quantity'],'price'=>(float)$row['item_unit_price']];}
    $received=[]; foreach($postedRows as $row){$id=filter_var($row[0]??null,FILTER_VALIDATE_INT);$price=filter_var($row[5]??null,FILTER_VALIDATE_FLOAT);$quantity=filter_var($row[6]??null,FILTER_VALIDATE_FLOAT);if($id===false||$price===false||$quantity===false||isset($received[(int)$id]))throw new DomainException('El detalle de compra no coincide con la orden.');$received[(int)$id]=['quantity'=>(float)$quantity,'price'=>(float)$price];}
    ksort($expected);ksort($received);if(array_keys($expected)!==array_keys($received))throw new DomainException('Los articulos no coinciden con la orden de compra.');
    foreach($expected as $id=>$line){if(abs($line['quantity']-$received[$id]['quantity'])>0.000001||abs($line['price']-$received[$id]['price'])>0.009)throw new DomainException('Las cantidades o precios no coinciden con la orden de compra.');}
    if(abs((float)$order['total_purchase']-$subtotal)>0.01||abs((float)$order['total_tax']-$tax)>0.01||abs((float)$order['total_net']-$total)>0.01)throw new DomainException('Los totales no coinciden con la orden de compra.');
    return $order;
}
