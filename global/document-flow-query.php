<?php
require_once __DIR__.'/document-engine.php';
require_once __DIR__.'/document-history.php';

/** Routes and permissions belong to the document, not to the screen opening the map. */
function app_document_flow_catalog(): array
{
    return [
        'quotation'=>['table'=>'tbl_quotation','label'=>'Cotización','permission'=>'sales.view','route'=>'cotizacion/registro-cotizacion','lines'=>'tbl_quotation_detail','key'=>'quotation_id'],
        'sales_order'=>['table'=>'tbl_sales_order','label'=>'Orden de venta','permission'=>'sales.view','route'=>'ventas/resumen-ordenes-venta','lines'=>'tbl_sales_order_detail','key'=>'sales_order_id'],
        'remission_guide'=>['table'=>'tbl_remission_guide','label'=>'Guía de remisión','permission'=>'logistics.view','route'=>'logistica/guias','lines'=>'tbl_remission_guide_detail','key'=>'remission_guide_id'],
        'invoice'=>['table'=>'tbl_invoice','label'=>'Factura','permission'=>'sales.view','route'=>'facturacion/registro-factura','lines'=>'tbl_invoice_detail','key'=>'invoice_id'],
        'receipt'=>['table'=>'tbl_receipt','label'=>'Boleta','permission'=>'sales.view','route'=>'facturacion/registro-boleta','lines'=>'tbl_receipt_detail','key'=>'receipt_id'],
        'credit_note'=>['table'=>'tbl_credit_note','label'=>'Nota de crédito','permission'=>'sales.view','route'=>'facturacion/registro-nota-credito','lines'=>'tbl_credit_note_detail','key'=>'credit_note_id'],
        'purchase_request'=>['table'=>'tbl_purchase_request','label'=>'Solicitud de compra','permission'=>'purchases.view','route'=>'compras/solicitudes','lines'=>'tbl_purchase_request_detail','key'=>'request_id'],
        'order'=>['table'=>'tbl_order','label'=>'Orden de compra','permission'=>'purchases.view','route'=>'ordenes/orden-de-compra','lines'=>'tbl_order_detail','key'=>'order_id'],
        'purchase'=>['table'=>'tbl_purchase','label'=>'Factura de proveedor','permission'=>'purchases.view','route'=>'compras/resumen-compras','lines'=>'tbl_purchase_detail','key'=>'purchase_id'],
        'goods_receipt'=>['table'=>'tbl_goods_receipt','label'=>'Entrada de mercancías','permission'=>'purchases.view','route'=>'compras/recepciones','parameter'=>'receipt_id','lines'=>'tbl_goods_receipt_detail','key'=>'goods_receipt_id'],
        'incoming_payment'=>['table'=>'tbl_incoming_payment','label'=>'Cobro recibido','permission'=>'finance.view','route'=>'finanzas/cuentas-por-cobrar','parameter'=>'payment_id'],
        'outgoing_payment'=>['table'=>'tbl_outgoing_payment','label'=>'Pago efectuado','permission'=>'finance.view','route'=>'finanzas/cuentas-por-pagar','parameter'=>'payment_id'],
        'landed_cost'=>['table'=>'tbl_landed_cost','label'=>'Costo de entrega','permission'=>'purchases.view','route'=>'compras/costos-entrega','parameter'=>'record_id'],
    ];
}

function app_document_flow_type(string $type): string
{
    return $type==='purchase_order'?'order':$type;
}

function app_document_flow_permission(string $type): string
{
    $catalog=app_document_flow_catalog();$type=app_document_flow_type($type);
    if(!isset($catalog[$type]))throw new InvalidArgumentException('Tipo documental no compatible.');
    return $catalog[$type]['permission'];
}

function app_query_document_flow(PDO $pdo,string $type,int $id,bool $filterPermissions=false): array
{
    app_require_document_engine($pdo);$type=app_document_flow_type($type);$catalog=app_document_flow_catalog();
    if(!isset($catalog[$type]))throw new InvalidArgumentException('Tipo documental no compatible.');
    if($id<1)throw new DomainException('Seleccione un documento válido.');
    if($filterPermissions)$catalog=array_filter($catalog,static fn(array $item):bool=>app_can($item['permission']));
    if(!isset($catalog[$type]))throw new DomainException('No tienes permiso para consultar este documento.');
    $pending=[[$type,$id]];$nodes=[];$edges=[];$applications=[];$visited=[];$truncated=false;$missing=0;
    while($pending){
        [$current,$record]=array_shift($pending);$key=$current.':'.$record;
        if(isset($visited[$key])||!isset($catalog[$current]))continue;$visited[$key]=true;
        if(count($nodes)>=100){$truncated=true;break;}
        $config=$catalog[$current];$query=$pdo->prepare('SELECT * FROM '.$config['table'].' WHERE id=?');$query->execute([$record]);$header=$query->fetch();
        if(!$header){if($current===$type&&$record===$id)throw new DomainException('El documento no existe.');$missing++;continue;}
        $status=(string)$header['status'];$cancelled=in_array($status,['2','4','Anulada','Anulado','Cancelada','Cancelado','Rechazada'],true);
        $number=isset($header['series'])?$header['series'].'-'.$header['number']:($header['number']??$header['payment_number']??('#'.$record));
        $party=$header['customer_name']??$header['name']??$header['recipient_name']??$header['provider_name']??'';
        if($party===''&&!empty($header['provider_id'])){$q=$pdo->prepare('SELECT business_name FROM tbl_provider WHERE id=?');$q->execute([$header['provider_id']]);$party=$q->fetchColumn()?:'';}
        if($party===''&&!empty($header['customer_id'])){$q=$pdo->prepare('SELECT business_name FROM tbl_customer WHERE client_id=?');$q->execute([$header['customer_id']]);$party=$q->fetchColumn()?:'';}
        $label=$config['label'];if($current==='purchase'&&($header['document_subtype']??'')==='RESERVE')$label='Factura de reserva de proveedor';
        $nodes[$key]=['key'=>$key,'type'=>$current,'id'=>$record,'label'=>$label,'number'=>$number,'date'=>$header['date']??$header['issue_date']??$header['receipt_date']??$header['payment_date']??$header['cost_date']??'','customer'=>$party,'amount'=>$header['total_net']??$header['total_amount']??$header['total_estimate']??$header['total_cost']??null,'currency'=>$header['currency']??'','status'=>$current==='sales_order'?app_order_domain_status($header):(is_numeric($status)?app_sales_status_label((int)$status):$status),'cancelled'=>$cancelled,'url'=>'../'.$config['route'].'?'.($config['parameter']??'record_id').'='.$record];
        $aliases=$current==='order'?['order','purchase_order']:[$current];$placeholders=implode(',',array_fill(0,count($aliases),'?'));
        $params=array_merge($aliases,[$record],$aliases,[$record]);
        $q=$pdo->prepare("SELECT source_type,source_id,target_type,target_id,relationship_type,quantity_applied FROM tbl_document_flow WHERE(source_type IN({$placeholders}) AND source_id=?) OR(target_type IN({$placeholders}) AND target_id=?)");$q->execute($params);
        foreach($q->fetchAll() as $flow){
            $source=app_document_flow_type($flow['source_type']);$target=app_document_flow_type($flow['target_type']);
            if(!isset($catalog[$source],$catalog[$target]))continue;
            $from=$source.':'.$flow['source_id'];$to=$target.':'.$flow['target_id'];
            $edges[$from.'>'.$to]??=['source'=>$from,'target'=>$to,'relationship'=>$flow['relationship_type'],'quantity'=>0,'reversed_quantity'=>0,'amount'=>in_array($flow['relationship_type'],['PAYMENT_APPLICATION','LANDED_COST'],true)?(float)$flow['quantity_applied']:null];
            foreach([[$source,(int)$flow['source_id']],[$target,(int)$flow['target_id']]] as $next)if(!isset($visited[$next[0].':'.$next[1]]))$pending[]=$next;
        }
        $q=$pdo->prepare("SELECT * FROM tbl_document_line_application WHERE(source_type IN({$placeholders}) AND source_id=?) OR(target_type IN({$placeholders}) AND target_id=?) ORDER BY id");$q->execute($params);
        foreach($q->fetchAll() as $application){
            $application['source_type']=app_document_flow_type($application['source_type']);$application['target_type']=app_document_flow_type($application['target_type']);
            if(!isset($catalog[$application['source_type']],$catalog[$application['target_type']]))continue;
            $applications[$application['id']]=$application;
            $from=$application['source_type'].':'.$application['source_id'];$to=$application['target_type'].':'.$application['target_id'];
            $edges[$from.'>'.$to]??=['source'=>$from,'target'=>$to,'relationship'=>$application['stage'],'quantity'=>0,'reversed_quantity'=>0,'amount'=>null];
            foreach([[$application['source_type'],(int)$application['source_id']],[$application['target_type'],(int)$application['target_id']]] as $next)if(!isset($visited[$next[0].':'.$next[1]]))$pending[]=$next;
        }
    }
    foreach($applications as $application){$edge=$application['source_type'].':'.$application['source_id'].'>'.$application['target_type'].':'.$application['target_id'];$field=$application['status']==='ACTIVE'?'stage_quantities':'reversed_stage_quantities';$stage=$application['stage'];if(isset($edges[$edge]))$edges[$edge][$field][$stage]=($edges[$edge][$field][$stage]??0)+(float)$application['quantity'];}
    foreach($edges as &$edge)foreach(['quantity'=>'stage_quantities','reversed_quantity'=>'reversed_stage_quantities'] as $field=>$stages)foreach(['BILLING','FULFILLMENT','RECEPTION','CONVERSION','CREDIT'] as $stage)if(isset($edge[$stages][$stage])){$edge[$field]=$edge[$stages][$stage];break;}unset($edge);
    $edges=array_values(array_filter($edges,fn(array $edge):bool=>isset($nodes[$edge['source']],$nodes[$edge['target']])));
    $applications=array_values(array_filter($applications,fn(array $line):bool=>isset($nodes[$line['source_type'].':'.$line['source_id']],$nodes[$line['target_type'].':'.$line['target_id']])&&(($line['source_type']===$type&&(int)$line['source_id']===$id)||($line['target_type']===$type&&(int)$line['target_id']===$id))));$lineNumbers=[];
    foreach($applications as &$application){foreach(['source','target'] as $side){$documentKey=$application[$side.'_type'].':'.$application[$side.'_id'];if(!isset($lineNumbers[$documentKey])){$adapter=$catalog[$application[$side.'_type']];$lineNumbers[$documentKey]=[];if(isset($adapter['lines'])){$q=$pdo->prepare('SELECT id FROM '.$adapter['lines'].' WHERE '.$adapter['key'].'=? ORDER BY id');$q->execute([$application[$side.'_id']]);$lineNumbers[$documentKey]=array_flip($q->fetchAll(PDO::FETCH_COLUMN));}}$application[$side.'_line_number']=isset($lineNumbers[$documentKey][$application[$side.'_line_id']])?$lineNumbers[$documentKey][$application[$side.'_line_id']]+1:null;$application[$side.'_number']=$nodes[$documentKey]['number']??$documentKey;}}unset($application);
    return ['selected'=>$type.':'.$id,'nodes'=>array_values($nodes),'edges'=>$edges,'line_applications'=>$applications,'truncated'=>$truncated,'missing_related_documents'=>$missing];
}
