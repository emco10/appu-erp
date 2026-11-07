<?php
declare(strict_types=1);

/** Assistance for reviewed operations in PEN; no journal or payment is posted here. */
function app_peru_spot_catalog(): array
{
    $items=[
        'annex3_labour'=>['Intermediación laboral y tercerización',1200],
        'annex3_rental'=>['Arrendamiento de bienes',1000],
        'annex3_maintenance'=>['Mantenimiento y reparación de bienes muebles',1200],
        'annex3_cargo_handling'=>['Movimiento de carga',1000],
        'annex3_business'=>['Otros servicios empresariales',1200],
        'annex3_commission'=>['Comisión mercantil',1000],
        'annex3_manufacturing'=>['Fabricación de bienes por encargo',1000],
        'annex3_person_transport'=>['Servicio de transporte de personas',1000],
        'annex3_construction'=>['Contratos de construcción',400],
        'annex3_other_services'=>['Demás servicios gravados con el IGV',1200],
    ];
    $catalog=[];
    foreach($items as $id=>[$label,$rate])$catalog[$id]=['label'=>$label,'rate_bps'=>$rate,'threshold_cents'=>70000,'annex'=>'3'];
    return $catalog;
}
function app_peru_money_cents(mixed $value,string $label): int
{
    if(!is_scalar($value)||!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',(string)$value))throw new DomainException($label.': ingresa un importe positivo con hasta dos decimales.');
    $parts=explode('.',(string)$value);return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
}
function app_peru_tax_evaluate(array $input,array $settings): array
{
    $regime=(string)($input['regime']??'');
    if(!in_array($regime,['retention','detraction'],true))throw new DomainException('Selecciona retención o detracción para evaluar la operación.');
    if(!in_array((string)($input['operation_type']??'purchase'),['purchase','sale'],true))throw new DomainException('Selecciona compra o venta.');
    if(!in_array((string)($input['spot_applicability']??'unknown'),['unknown','applicable','not_applicable'],true))throw new DomainException('La condición SPOT no es válida.');
    if((int)($settings[$regime.'_enabled']??0)!==1)throw new DomainException('Activa la asistencia de este régimen en Gestión antes de evaluarlo.');
    if(($input['currency']??'PEN')!=='PEN')throw new DomainException('Esta evaluación requiere importes en soles. Verifica la conversión y fecha aplicable antes de continuar.');
    $gross=app_peru_money_cents($input['gross_operation']??'','Importe total de operación');
    $payment=app_peru_money_cents($input['gross_payment']??'','Importe del pago');
    if($gross<=0||$payment<=0||$payment>$gross)throw new DomainException('La operación y el pago deben ser mayores a cero; el pago no puede superar la operación.');
    $date=(string)($input['operation_date']??'');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new DomainException('Indica una fecha de operación válida.');
    $obligationDate=(string)($input['tax_obligation_date']??'');
    $obligation=DateTimeImmutable::createFromFormat('!Y-m-d',$obligationDate);
    if(!$obligation||$obligation->format('Y-m-d')!==$obligationDate)throw new DomainException('Indica la fecha de nacimiento de la obligación tributaria del IGV según el documento y la operación.');
    $result=['status'=>'review_required','regime'=>$regime,'currency'=>'PEN','operation_date'=>$date,'tax_obligation_date'=>$obligationDate,'gross_operation_cents'=>$gross,'gross_payment_cents'=>$payment,'tax_cents'=>null,'net_to_supplier_cents'=>null,'reasons'=>[],'accounting_posted'=>false,'source_version'=>'SUNAT orientación / revisión 2026-11-06'];
    $review=[];
    if($date>date('Y-m-d')||$obligationDate>date('Y-m-d'))$review[]='Las fechas futuras requieren revisar la normativa vigente al realizar la operación y el pago.';
    $earliest=$regime==='retention'?'2014-03-01':'2018-04-01';
    if($obligationDate<$earliest)$review[]='La obligación del IGV es anterior al alcance de las tasas actuales de esta asistencia; revisa la norma histórica aplicable.';
    if(($input['exclusions_reviewed']??'')!=='1')$review[]='Confirma las exclusiones legales y el documento de sustento.';
    if(($input['igv_taxed']??'')!=='1')$review[]='Verifica que la operación esté gravada con IGV; esta evaluación cubre ese supuesto.';
    if($regime==='retention'){
        $result['source']='https://orientacion.sunat.gob.pe/06-preguntas-frecuentes-regimen-de-retenciones';
        $agent=$settings['company_retention_agent']??null;
        // For sales the buyer's designation is an explicit operation input.
        if(($input['operation_type']??'purchase')==='sale')$agent=$input['buyer_retention_agent']??null;
        if((!is_string($agent)&&!is_int($agent))||!in_array((string)$agent,['0','1'],true))$review[]='La condición de agente de retención del comprador está sin verificar.';
        if(($input['spot_applicability']??'')==='unknown'||!isset($input['spot_applicability']))$review[]='Primero determina si corresponde detracción.';
        foreach(['supplier_retention_agent','supplier_perception_agent','supplier_good_taxpayer'] as $field){
            if(!in_array((string)($input[$field]??''),['0','1'],true))$review[]='Verifica '.$field.' al momento del pago.';
        }
        if($review){$result['reasons']=$review;return $result;}
        $excluded=(string)$agent!=='1'||($input['spot_applicability']??'')==='applicable'||$gross<=70000&&$payment<=70000;
        foreach(['supplier_retention_agent','supplier_perception_agent','supplier_good_taxpayer'] as $field)$excluded=$excluded||($input[$field]??'')==='1';
        if($excluded){$result['status']='excluded';$result['tax_cents']=0;$result['net_to_supplier_cents']=$payment;$result['reasons']=['La operación está excluida por la condición declarada del comprador/proveedor, detracción o umbral de pago y operación.'];return $result;}
        $tax=intdiv($payment*300+5000,10000);$result['rate_bps']=300;
        $result['reasons']=['3% sobre el pago, incluido IGV. La suma de comprobantes de esta operación debe estar incluida en la base declarada.'];
    }else{
        $result['source']='https://emprender.sunat.gob.pe/principales-impuestos/impuesto-general-las-ventas-igv/sistema-detracciones-igv';
        $rounding=$input['spot_rounding']??'cents';
        if(!in_array($rounding,['cents','soles'],true))throw new DomainException('Selecciona el criterio de redondeo del depósito SPOT.');
        $catalog=app_peru_spot_catalog();$category=(string)($input['spot_category']??'');
        if(!isset($catalog[$category]))$review[]='Selecciona un servicio del Anexo 3. Bienes, transporte terrestre de carga y otros regímenes requieren revisión específica.';
        if(($input['spot_applicability']??'')!=='applicable')$review[]='Confirma que la operación está sujeta al SPOT.';
        if(($input['fiscal_credit_document']??'')!=='1')$review[]='Confirma que el documento permite crédito fiscal o sustento de costo/gasto y revisa las demás exclusiones del Anexo 3.';
        if($review){$result['reasons']=$review;return $result;}
        $rule=$catalog[$category];$result['spot_category']=$category;$result['rate_bps']=$rule['rate_bps'];
        if($gross<=$rule['threshold_cents']){$result['status']='excluded';$result['tax_cents']=0;$result['net_to_supplier_cents']=$payment;$result['reasons']=['El importe de la operación no supera S/ 700 para el servicio seleccionado del Anexo 3.'];return $result;}
        // Round directly from the integer basis: rounding cents first could
        // incorrectly turn an exact S/141.4992 deposit into S/142.
        $basis=$gross*$rule['rate_bps'];
        $tax=$rounding==='soles'?intdiv($basis+500000,1000000)*100:intdiv($basis+5000,10000);
        $result['spot_rounding']=$rounding;
        if($rounding==='soles')$result['rounding_source']='https://orientacion.sunat.gob.pe/preguntas-frecuentes-detracciones-0';
        $deposited=app_peru_money_cents($input['already_deposited']??'0','Detracción ya depositada');
        if($deposited>$tax)throw new DomainException('El depósito previo supera la detracción calculada; revisa la constancia.');
        $result['total_detraction_cents']=$tax;$result['already_deposited_cents']=$deposited;$tax-=$deposited;
        if($tax>$payment){$result['reasons']=['El depósito pendiente supera el pago indicado. Revisa el cronograma y el momento del depósito antes de registrar el pago.'];return $result;}
        $result['reasons']=['Detracción sobre el importe total de la operación, incluido IGV; se descuenta el depósito previo declarado. No corresponde retención IGV a esta operación.'];
        if($rounding==='soles')$result['reasons'][]='Depósito redondeado a soles según la opción declarada y la R.S. 178-2005/SUNAT; verifica el mismo criterio en la constancia.';
    }
    $result['status']='calculated';$result['tax_cents']=$tax;$result['net_to_supplier_cents']=$payment-$tax;
    return $result;
}
