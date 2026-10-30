<?php
require_once __DIR__ . '/sales-fulfillment.php';
require_once __DIR__ . '/../modules/finanzas/sync.php';
require_once __DIR__ . '/sales-advance.php';

function app_order_billing_context(PDO $pdo,int $orderId):array
{
    $lock=$pdo->inTransaction()?' FOR UPDATE':'';
    $q=$pdo->prepare('SELECT id,item_quantity FROM tbl_sales_order_detail WHERE sales_order_id=? ORDER BY id'.$lock);$q->execute([$orderId]);$ordered=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $q=$pdo->prepare("SELECT detail.source_detail_id,application.quantity FROM tbl_document_line_application application
      JOIN tbl_remission_guide guide ON guide.id=application.source_id AND application.source_type='remission_guide'
      JOIN tbl_remission_guide_detail detail ON detail.id=application.source_line_id AND detail.remission_guide_id=guide.id
      WHERE guide.source_type='sales_order' AND guide.source_id=? AND application.stage='BILLING' AND application.status='ACTIVE'".$lock);$q->execute([$orderId]);$billed=[];$count=0;
    foreach($q->fetchAll() as $row){$billed[$row['source_detail_id']]=($billed[$row['source_detail_id']]??0)+(float)$row['quantity'];$count++;}
    $q=$pdo->prepare("SELECT total_net FROM tbl_invoice WHERE sales_order_id=? AND inventory_origin='DELIVERY' AND status NOT IN (2,4)".$lock);$q->execute([$orderId]);$totals=$q->fetchAll(PDO::FETCH_COLUMN);
    return ['ordered_quantities'=>(object)$ordered,'billed_quantities'=>(object)$billed,'previous_total'=>(float)array_sum($totals),'invoice_count'=>count($totals),'line_count'=>$count];
}

function app_delivery_invoice_totals(array $order,array $lines,array $context):array
{
    $subtotal=0;$quantities=(array)$context['billed_quantities'];
    foreach($lines as $line){$subtotal+=round($line['quantity']*$line['unit_price'],2);$quantities[$line['order_line_id']]=($quantities[$line['order_line_id']]??0)+$line['quantity'];}
    $subtotal=round($subtotal,2);$tax=round($subtotal*.18,2);$discount=round(($subtotal+$tax)*(float)$order['discount_rate']/100,2);$natural=round($subtotal+$tax-$discount,2);$complete=true;
    foreach((array)$context['ordered_quantities'] as $line=>$ordered)if(($quantities[$line]??0)+.000001<(float)$ordered)$complete=false;
    $rounding=$complete?round((float)$order['total_net']-$context['previous_total']-$natural,2):0.0;
    if(abs($rounding)>.01*($context['line_count']+count($lines)+$context['invoice_count']+1)+.000001)throw new DomainException('Los importes de la orden no concilian con sus líneas. Revisa el documento de origen antes de completar su facturación.');
    return ['subtotal'=>$subtotal,'tax'=>$tax,'discount'=>$discount,'rounding_adjustment'=>$rounding,'total'=>round($natural+$rounding,2)];
}

function app_delivery_billing_preview(PDO $pdo, array $guideIds): array
{
    app_require_document_engine($pdo);
    foreach($guideIds as $value)if(filter_var($value,FILTER_VALIDATE_INT)===false || (int)$value<1)throw new DomainException('Seleccione entregas con identificadores válidos.');
    $ids = array_values(array_unique(array_map('intval',$guideIds))); sort($ids);
    if (!$ids || count($ids)>50 || min($ids)<1) throw new DomainException('Seleccione entre 1 y 50 entregas válidas.');
    $order = null; $lines = []; $guides = [];
    foreach ($ids as $id) {
        $guide = app_document_header($pdo,'remission_guide',$id);
        if ($guide['source_type'] !== 'sales_order' || !(int)$guide['inventory_posted']) throw new DomainException('Solo se facturan entregas físicas de órdenes. Las guías históricas o de facturas de reserva no generan otra factura.');
        $sourceOrder = app_document_header($pdo,'sales_order',(int)$guide['source_id'],$pdo->inTransaction());
        $guide = app_document_header($pdo,'remission_guide',$id,$pdo->inTransaction());
        if ($guide['status']==='Anulada' || $sourceOrder['status']==='Anulada') throw new DomainException('La entrega o su orden están anuladas.');
        if ($order && (int)$order['id'] !== (int)$sourceOrder['id']) throw new DomainException('Las entregas deben pertenecer a la misma orden de venta.');
        $order = $sourceOrder;
        if ($pdo->inTransaction() && app_order_has_final_invoice($pdo,(int)$order['id'])) throw new DomainException('La orden tiene una factura directa o de reserva activa.');
        $orderLines = $pdo->prepare('SELECT * FROM tbl_sales_order_detail WHERE sales_order_id=? ORDER BY id'); $orderLines->execute([$order['id']]);
        $byId = array_column($orderLines->fetchAll(),null,'id');
        foreach (app_document_open_lines($pdo,'remission_guide',$id,'BILLING') as $line) {
            $base = $byId[$line['source_detail_id']] ?? null;
            if (!$base || (int)$base['item_id'] !== (int)$line['product_id']) throw new DomainException('La entrega no conserva una línea válida de la orden.');
            $lines[] = ['guide_id'=>$id,'guide_number'=>$guide['number'],'guide_line_id'=>(int)$line['id'],'order_line_id'=>(int)$base['id'],'product_id'=>(int)$base['item_id'],'item_code'=>$base['item_code'],'item_name'=>$base['item_name'],'description'=>$base['item_description'],'unit_price'=>(float)$base['item_unit_price'],'quantity'=>(float)$line['original_quantity'],'billed_quantity'=>(float)$line['processed_quantity'],'open_quantity'=>(float)$line['open_quantity']];
        }
        $guides[] = ['id'=>$id,'number'=>$guide['number'],'status'=>$guide['status']];
    }
    $fields = app_custom_field_definitions($pdo, 'invoice');
    $series=$pdo->query("SELECT series FROM tbl_document_series WHERE document_type='INVOICE' AND active_status=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $values=app_custom_field_defaults($pdo,'invoice',app_custom_field_copy_values($pdo,'sales_order',(int)$order['id'],'invoice'));
    return ['order'=>$order,'guides'=>$guides,'lines'=>$lines,'custom_fields'=>$fields,'custom_values'=>(object)$values,'series'=>$series,'billing_context'=>app_order_billing_context($pdo,(int)$order['id']),'fiscal_status'=>'NO_APLICA'];
}

function app_next_invoice_number(PDO $pdo, string $seriesCode = ''): array
{
    if (!$pdo->inTransaction()) throw new LogicException('La numeración requiere una transacción.');
    $q=$pdo->prepare("SELECT * FROM tbl_document_series WHERE document_type='INVOICE' AND active_status=1" . ($seriesCode!==''?' AND series=?':'') . ' ORDER BY id LIMIT 1 FOR UPDATE'); $q->execute($seriesCode!==''?[$seriesCode]:[]); $series=$q->fetch();
    if (!$series) throw new DomainException('Configura una serie activa para facturas internas.');
    $q=$pdo->prepare('SELECT COALESCE(MAX(CAST(number AS UNSIGNED)),0) FROM tbl_invoice WHERE series=?'); $q->execute([$series['series']]);
    $number=max(1,(int)$series['next_number'],(int)$q->fetchColumn()+1);
    $pdo->prepare('UPDATE tbl_document_series SET next_number=? WHERE id=?')->execute([$number+1,$series['id']]);
    return [$series['series'],str_pad((string)$number,(int)$series['number_padding'],'0',STR_PAD_LEFT)];
}

function app_sync_delivery_order_billing(PDO $pdo, int $orderId, int $userId): void
{
    $order=app_document_header($pdo,'sales_order',$orderId,true);
    if ($order['status']==='Anulada') return;
    $q=$pdo->prepare('SELECT COALESCE(SUM(item_quantity),0) FROM tbl_sales_order_detail WHERE sales_order_id=?'); $q->execute([$orderId]); $ordered=(float)$q->fetchColumn();
    $q=$pdo->prepare("SELECT application.quantity FROM tbl_document_line_application application
      JOIN tbl_remission_guide guide ON application.source_type='remission_guide' AND guide.id=application.source_id
      WHERE guide.source_type='sales_order' AND guide.source_id=? AND application.stage='BILLING' AND application.status='ACTIVE' FOR UPDATE");
    $q->execute([$orderId]); $billed=array_sum($q->fetchAll(PDO::FETCH_COLUMN));
    $status=$ordered>0 && $billed+.000001 >= $ordered ? 'Atendida' : 'Confirmada';
    if ($status !== $order['status']) {
        $pdo->prepare('UPDATE tbl_sales_order SET status=?,last_update=NOW() WHERE id=?')->execute([$status,$orderId]);
        app_record_document_status($pdo,'sales_order',$orderId,$order['number'],$order['status'],$status,'Facturacion de entregas',$userId,'Recalculado por cantidades facturadas de las entregas.');
    }
}

function app_invoice_from_deliveries(PDO $pdo, array $input, int $userId): array
{
    if (!$pdo->inTransaction()) throw new LogicException('La factura requiere una transacción.');
    $requested=$input['lines'] ?? [];
    if (is_string($requested)) $requested=json_decode($requested,true);
    if (!is_array($requested) || !array_is_list($requested) || !$requested || count($requested)>500) throw new AppValidationException([app_field_error('lines','REQUIRED','Seleccione al menos una línea de entrega pendiente de facturar.')]);
    $errors=app_validate_fields($input,['document_date'=>['label'=>'Fecha documento','required'=>true,'customValidator'=>fn($value)=>app_valid_date($value)],'reference'=>['label'=>'Referencia','maxLength'=>300]]);
    $guideIds=[];
    foreach ($requested as $index=>$line) {
        if (!is_array($line) || !(filter_var($line['guide_id']??null,FILTER_VALIDATE_INT)>0)) $errors[]=app_field_error('lines['.$index.'].guide_id','INVALID_REFERENCE','Seleccione una entrega válida.');
        else $guideIds[]=(int)$line['guide_id'];
        if (!is_array($line) || !(filter_var($line['guide_line_id']??null,FILTER_VALIDATE_INT)>0)) $errors[]=app_field_error('lines['.$index.'].quantity','INVALID_REFERENCE','Seleccione una línea de entrega válida.');
    }
    if ($errors) throw new AppValidationException($errors);
    $preview=app_delivery_billing_preview($pdo,$guideIds); $order=$preview['order'];
    $available=[]; foreach ($preview['lines'] as $line) $available[$line['guide_id'].':'.$line['guide_line_id']]=$line;
    $normalized=[]; $seen=[]; $subtotal=0;
    foreach ($requested as $index=>$row) {
        $key=(int)$row['guide_id'].':'.(int)($row['guide_line_id']??0); $base=$available[$key]??null;
        $quantity=filter_var($row['quantity']??null,FILTER_VALIDATE_INT);
        if (!$base || isset($seen[$key])) { $errors[]=app_field_error('lines['.$index.'].quantity','INVALID_REFERENCE','La línea no pertenece a la entrega o está repetida.'); continue; }
        $seen[$key]=true;
        if (!$quantity || $quantity<1) $errors[]=app_field_error('lines['.$index.'].quantity','INTEGER','La cantidad debe ser un entero mayor que cero.');
        elseif ($quantity>$base['open_quantity']+.000001) $errors[]=app_field_error('lines['.$index.'].quantity','OPEN_QUANTITY','Solo quedan '. $base['open_quantity'].' unidades pendientes de facturar.');
        $base['quantity']=$quantity; $normalized[]=$base;
        $subtotal+=round((float)$quantity*$base['unit_price'],2);
    }
    if ($errors) throw new AppValidationException($errors);
    $totals=app_delivery_invoice_totals($order,$normalized,app_order_billing_context($pdo,(int)$order['id']));
    $subtotal=$totals['subtotal'];$tax=$totals['tax'];$discount=$totals['discount'];$total=$totals['total'];
    [$series,$number]=app_next_invoice_number($pdo,is_string($input['series']??null)?$input['series']:'');
    $date=$input['document_date'];
    $q=$pdo->prepare("INSERT INTO tbl_invoice(series,number,status,document_subtype,inventory_origin,quotation_id,sales_order_id,customer_id,warehouse_id,billing_address_id,shipping_address_id,ruc,name,address,shipping_address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id) VALUES(?,?,1,'STANDARD','DELIVERY',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $q->execute([$series,$number,$order['quotation_id']?:0,$order['id'],$order['customer_id'],$order['warehouse_id'],$order['billing_address_id'],$order['shipping_address_id'],$order['ruc'],$order['customer_name'],$order['address'],$order['shipping_address'],trim((string)($input['reference']??'')),$order['payment_days'],$date,$order['delivery_date'],$order['currency'],$order['discount_rate'],$discount,$subtotal,$tax,$total,$order['seller_id'],$userId]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE tbl_invoice SET rounding_adjustment=? WHERE id=?')->execute([$totals['rounding_adjustment'],$id]);
    $insert=$pdo->prepare("INSERT INTO tbl_invoice_detail(invoice_id,item_id,item_code,item_name,item_description,item_quantity,item_unit_price,base_document_type,base_document_id,base_line_id) VALUES(?,?,?,?,?,?,?,'remission_guide',?,?)");
    foreach ($normalized as $line) {
        $insert->execute([$id,$line['product_id'],$line['item_code'],$line['item_name'],$line['description'],$line['quantity'],$line['unit_price'],$line['guide_id'],$line['guide_line_id']]); $lineId=(int)$pdo->lastInsertId();
        app_apply_document_line($pdo,'remission_guide',$line['guide_id'],$line['guide_line_id'],'invoice',$id,$lineId,'BILLING',(float)$line['quantity'],$userId);
        app_record_document_flow($pdo,'remission_guide',$line['guide_id'],'invoice',$id,$userId,'DELIVERY_BILLING');
    }
    app_record_document_flow($pdo,'sales_order',(int)$order['id'],'invoice',$id,$userId,'DELIVERY_BILLING');
    app_copy_custom_fields($pdo,'sales_order',(int)$order['id'],'invoice',$id,is_array($input['custom_field']??null)?$input['custom_field']:[]);
    app_record_document_status($pdo,'invoice',$id,$series.'-'.$number,'','Vigente','Creacion',$userId,'Factura interna basada en entrega: no genera otra salida de inventario.');
    app_sync_delivery_order_billing($pdo,(int)$order['id'],$userId);
    finance_sync_receivable($pdo,$id);
    finance_apply_available_sales_advances($pdo,$id,$userId);
    return ['error'=>false,'invoice_id'=>$id,'number'=>$series.'-'.$number,'total'=>$total,'rounding_adjustment'=>$totals['rounding_adjustment'],'currency'=>$order['currency'],'fiscal_status'=>'NO_APLICA','message'=>'Factura interna creada desde la entrega, sin duplicar la salida de inventario.'];
}
