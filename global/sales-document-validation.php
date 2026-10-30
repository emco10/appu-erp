<?php
require_once __DIR__ . '/document-engine.php';

/** Reglas comunes para los formularios comerciales anteriores al motor agregado. */
function app_validate_legacy_sales_input(PDO $pdo,array $input,&$rows,string $prefix='facturacion',bool $quotation=false):array
{
    foreach(['fecha','fecentrega'] as $field){$value=trim((string)($input[$prefix.'_'.$field]??''));$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('Las fechas del documento no son válidas.');}
    if((string)$input[$prefix.'_fecentrega']<(string)$input[$prefix.'_fecha'])throw new DomainException('La fecha de entrega no puede ser anterior a la emisión.');
    if(!in_array($input[$prefix.'_tipmon']??'', ['MN','ME','PEN','USD','EUR'],true))throw new DomainException('Selecciona una moneda válida.');
    $days=filter_var($input[$prefix.'_formpago']??null,FILTER_VALIDATE_INT);if($days===false||$days<0||$days>3650)throw new DomainException('Los días de pago no son válidos.');
    $seller=filter_var($input[$prefix.'_usuarioid']??null,FILTER_VALIDATE_INT);$user=$pdo->prepare('SELECT id FROM tbl_user WHERE id=? AND active_status=1');$user->execute([$seller?:0]);if(!$user->fetchColumn())throw new DomainException('Selecciona un vendedor activo.');
    $discount=filter_var(($input[$prefix.'_porcdesc']??'')===''?0:$input[$prefix.'_porcdesc'],FILTER_VALIDATE_FLOAT);if($discount===false||!is_finite((float)$discount)||$discount<0||$discount>100)throw new DomainException('El descuento debe estar entre 0 y 100 por ciento.');
    if(!is_array($rows)||!array_is_list($rows)||!$rows||count($rows)>500)throw new DomainException('Agrega entre 1 y 500 artículos válidos al documento.');
    $priceIndex=$quotation?3:4;$qtyIndex=$quotation?4:5;$seen=[];$subtotal=0;
    $product=$pdo->prepare('SELECT code,name,description FROM tbl_product WHERE id=? AND active_status=1');
    foreach($rows as &$row){
        if(!is_array($row))throw new DomainException('Una línea del documento no es válida.');
        $id=filter_var($row[0]??null,FILTER_VALIDATE_INT);$price=filter_var($row[$priceIndex]??null,FILTER_VALIDATE_FLOAT);$quantity=filter_var($row[$qtyIndex]??null,FILTER_VALIDATE_INT);
        if($id===false||$id<1||$price===false||!is_finite((float)$price)||$price<0||$price>999999999.99||$quantity===false||$quantity<1||$quantity>2147483647)throw new DomainException('Los artículos requieren cantidades enteras positivas y precios dentro del rango permitido.');
        if(isset($seen[$id]))throw new DomainException('No repitas artículos; consolida su cantidad en una línea.');$seen[$id]=true;
        $product->execute([$id]);$master=$product->fetch();if(!$master)throw new DomainException('Uno de los artículos no existe o está inactivo.');
        if($quotation){$row[1]=$master['name'];$row[2]=$master['description'];}else{$row[1]=$master['code'];$row[2]=$master['name'];$row[3]=$master['description'];}
        $subtotal+=round((float)$price*$quantity,2);
        if(!is_finite($subtotal)||$subtotal>999999999.99)throw new DomainException('El importe supera el máximo permitido por documento.');
    }unset($row);
    $subtotal=round($subtotal,2);$tax=round($subtotal*.18,2);$gross=round($subtotal+$tax,2);$discountValue=round($gross*$discount/100,2);
    return ['subtotal'=>$subtotal,'tax'=>$tax,'discount_rate'=>$discount,'discount'=>$discountValue,'total'=>round($gross-$discountValue,2)];
}

function app_validate_legacy_sales_number(PDO $pdo,string $type,string $series,string $number):string
{
    $tables=['INVOICE'=>'tbl_invoice','RECEIPT'=>'tbl_receipt','CREDIT_NOTE'=>'tbl_credit_note'];
    if(!isset($tables[$type]))throw new DomainException('Tipo de documento inválido.');
    $config=$pdo->prepare('SELECT id,next_number,number_padding FROM tbl_document_series WHERE document_type=? AND series=? AND active_status=1 FOR UPDATE');$config->execute([$type,$series]);$row=$config->fetch();
    if(!$row)throw new DomainException('La serie del documento no está activa.');
    $maximum=$pdo->prepare('SELECT COALESCE(MAX(CAST(number AS UNSIGNED)),0) FROM '.$tables[$type].' WHERE series=?');$maximum->execute([$series]);$next=max(1,(int)$row['next_number'],(int)$maximum->fetchColumn()+1);
    $pdo->prepare('UPDATE tbl_document_series SET next_number=? WHERE id=?')->execute([$next+1,(int)$row['id']]);
    return str_pad((string)$next,(int)$row['number_padding'],'0',STR_PAD_LEFT);
}

function app_quotation_has_active_sale(PDO $pdo,int $quotationId):bool
{
    $q=$pdo->prepare("SELECT (SELECT COUNT(*) FROM tbl_invoice WHERE quotation_id=? AND status NOT IN(2,4))+(SELECT COUNT(*) FROM tbl_receipt WHERE quotation_id=? AND status NOT IN(2,4))");$q->execute([$quotationId,$quotationId]);return (int)$q->fetchColumn()>0;
}

function app_validate_quotation_sale(PDO $pdo,int $id,string $currency,array $rows,float $subtotal,float $tax,float $total):void
{
    $q=$pdo->prepare('SELECT * FROM tbl_quotation WHERE id=? FOR UPDATE');$q->execute([$id]);$quote=$q->fetch();
    if(!$quote||strcasecmp((string)$quote['currency'],$currency)!==0)throw new DomainException('La moneda no coincide con la cotización.');
    $activeOrder=$pdo->prepare("SELECT id FROM tbl_sales_order WHERE quotation_id=? AND status<>'Anulada' FOR UPDATE");$activeOrder->execute([$id]);if($activeOrder->fetchColumn())throw new DomainException('La cotización ya tiene una orden de venta; utiliza esa orden como documento base.');
    if(app_quotation_has_active_sale($pdo,$id))throw new DomainException('La cotización ya tiene un documento de venta activo.');
    $q=$pdo->prepare('SELECT item_id,item_quantity,item_unit_price FROM tbl_quotation_detail WHERE quotation_id=? ORDER BY item_id');$q->execute([$id]);$expected=[];foreach($q->fetchAll() as $line)$expected[(int)$line['item_id']]=[(float)$line['item_quantity'],(float)$line['item_unit_price']];
    $actual=[];foreach($rows as $row)$actual[(int)$row[0]]=[(float)$row[5],(float)$row[4]];ksort($expected);ksort($actual);
    if($expected!==$actual||abs((float)$quote['total_sub']-$subtotal)>.011||abs((float)$quote['total_tax']-$tax)>.011||abs((float)$quote['total_net']-$total)>.011)throw new DomainException('Las líneas o totales no coinciden con la cotización.');
}

function app_release_order_for_billing(PDO $pdo,int $orderId,int $warehouseId):void
{
    $q=$pdo->prepare('SELECT item_id,item_quantity FROM tbl_sales_order_detail WHERE sales_order_id=? ORDER BY id FOR UPDATE');$q->execute([$orderId]);
    foreach($q->fetchAll() as $line)warehouse_apply_reservation_delta($pdo,(int)$line['item_id'],-(float)$line['item_quantity'],$warehouseId);
}

function app_record_order_billing_line(PDO $pdo,int $orderId,int $productId,string $type,int $id,int $lineId,float $quantity,int $userId,bool $physical=true):void
{
    if(!app_document_engine_available($pdo))return;
    $q=$pdo->prepare('SELECT id FROM tbl_sales_order_detail WHERE sales_order_id=? AND item_id=? ORDER BY id FOR UPDATE');$q->execute([$orderId,$productId]);$base=(int)$q->fetchColumn();
    if(!$base)throw new DomainException('El artículo no pertenece a la orden de venta.');
    app_apply_document_line($pdo,'sales_order',$orderId,$base,$type,$id,$lineId,'BILLING',$quantity,$userId);
    if($physical){
        $lines=array_column(app_document_open_lines($pdo,'sales_order',$orderId,'FULFILLMENT'),null,'id');
        $open=min($quantity,(float)($lines[$base]['open_quantity']??0));
        if($open>0)app_apply_document_line($pdo,'sales_order',$orderId,$base,$type,$id,$lineId,'FULFILLMENT',$open,$userId);
    }
}

/**
 * Valida que un documento de venta generado desde una orden sea una copia
 * consistente de su cabecera y detalle. La fase actual factura la orden completa.
 */
function app_validate_sales_order_document(PDO $pdo, int $salesOrderId, int $customerId, int $warehouseId, string $currency, array $postedRows, float $subtotal, float $tax, float $total, string $documentSubtype = 'STANDARD', float $advancePercentage = 0): array
{
    $statement = $pdo->prepare('SELECT * FROM tbl_sales_order WHERE id = ? FOR UPDATE');
    $statement->execute([$salesOrderId]);
    $order = $statement->fetch();
    if (!$order || $order['status'] !== 'Confirmada') {
        throw new DomainException('La orden de venta debe estar confirmada.');
    }
    if ((int) $order['customer_id'] !== $customerId) throw new DomainException('El cliente no coincide con la orden de venta.');
    if ((int) $order['warehouse_id'] !== $warehouseId) throw new DomainException('El almacen no coincide con la orden de venta.');
    if (strcasecmp((string) $order['currency'], $currency) !== 0) throw new DomainException('La moneda no coincide con la orden de venta.');
    if (app_document_engine_available($pdo) && $documentSubtype !== 'ADVANCE') {
        $physical = $pdo->prepare("SELECT id FROM tbl_remission_guide WHERE source_type='sales_order' AND source_id=? AND inventory_posted=1 AND status<>'Anulada' FOR UPDATE");
        $physical->execute([$salesOrderId]);
        if ($physical->fetch()) throw new DomainException('La orden ya tiene entregas físicas. Utiliza Copiar a factura desde las entregas para evitar otra salida de stock.');
    }

    $existing = $pdo->prepare(
        "SELECT COUNT(*) FROM tbl_document_flow flow
         LEFT JOIN tbl_invoice invoice ON flow.target_type='invoice' AND invoice.id=flow.target_id
         LEFT JOIN tbl_receipt receipt ON flow.target_type='receipt' AND receipt.id=flow.target_id
         WHERE flow.source_type='sales_order' AND flow.source_id=?
           AND flow.target_type IN ('invoice','receipt')
           AND ((flow.target_type='invoice' AND invoice.status NOT IN (2,4) AND invoice.document_subtype <> 'ADVANCE')
             OR (flow.target_type='receipt' AND receipt.status NOT IN (2,4)))"
    );
    $existing->execute([$salesOrderId]);
    if ((int) $existing->fetchColumn() > 0) throw new DomainException('La orden de venta ya tiene un documento final asociado.');

    if ($documentSubtype === 'ADVANCE') {
        if ($advancePercentage <= 0 || $advancePercentage > 100) throw new DomainException('El porcentaje de anticipo debe ser mayor que 0 y no superar 100.');
        $used = $pdo->prepare("SELECT COALESCE(SUM(advance_percentage),0) FROM tbl_invoice WHERE sales_order_id=? AND document_subtype='ADVANCE' AND status NOT IN (2,4)");
        $used->execute([$salesOrderId]);
        if ((float)$used->fetchColumn() + $advancePercentage > 100.0001) throw new DomainException('Los anticipos activos no pueden superar el 100% de la orden de venta.');
    }

    $detailStatement = $pdo->prepare('SELECT item_id,item_quantity,item_unit_price FROM tbl_sales_order_detail WHERE sales_order_id=? ORDER BY item_id');
    $detailStatement->execute([$salesOrderId]);
    $expected = [];
    foreach ($detailStatement->fetchAll() as $row) {
        $productId = (int) $row['item_id'];
        if (isset($expected[$productId])) throw new DomainException('La orden contiene lineas duplicadas que deben consolidarse antes de facturar.');
        $expected[$productId] = ['quantity'=>(float)$row['item_quantity'], 'price'=>(float)$row['item_unit_price']];
    }
    $received = [];
    foreach ($postedRows as $row) {
        $productId = filter_var($row[0] ?? null, FILTER_VALIDATE_INT);
        $price = filter_var($row[4] ?? null, FILTER_VALIDATE_FLOAT);
        $quantity = filter_var($row[5] ?? null, FILTER_VALIDATE_FLOAT);
        if ($productId === false || $price === false || $quantity === false || isset($received[(int)$productId])) {
            throw new DomainException('El detalle del documento no coincide con la orden de venta.');
        }
        $received[(int)$productId] = ['quantity'=>(float)$quantity, 'price'=>(float)$price];
    }
    ksort($expected);
    ksort($received);
    if (array_keys($expected) !== array_keys($received)) throw new DomainException('Los articulos no coinciden con la orden de venta.');
    foreach ($expected as $productId => $line) {
        if (abs($line['quantity'] - $received[$productId]['quantity']) > 0.000001 || abs($line['price'] - $received[$productId]['price']) > 0.009) {
            throw new DomainException('Las cantidades o precios no coinciden con la orden de venta.');
        }
    }
    if ($documentSubtype !== 'ADVANCE' && (abs((float)$order['total_sub']-$subtotal)>0.01 || abs((float)$order['total_tax']-$tax)>0.01 || abs((float)$order['total_net']-$total)>0.01)) {
        throw new DomainException('Los totales no coinciden con la orden de venta.');
    }
    return $order;
}

