<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('POST');
try {
    app_gre_require_enabled($pdo);
    $id=app_gre_id();
    if(!app_peru_table_exists($pdo,'tbl_gre_submission'))app_gre_reply(['success'=>false,'message'=>'Falta aplicar la migración de persistencia GRE.'],503);
    $values=[];
    $lengths=['transport_mode'=>30,'transfer_reason_code'=>30,'transfer_reason'=>150,'origin_address'=>300,'origin_ubigeo'=>6,'origin_establishment_code'=>10,
        'destination_address'=>300,'destination_ubigeo'=>6,'destination_establishment_code'=>10,'recipient_ruc'=>11,'recipient_name'=>200,'recipient_address'=>300,
        'carrier_ruc'=>11,'carrier_name'=>200,'vehicle_plate'=>20,'driver_doc_type'=>10,'driver_doc_number'=>20,'driver_name'=>200,'driver_last_name'=>100,'driver_license'=>10];
    foreach($lengths as $field=>$max) {
        if(!array_key_exists($field,$_POST))continue;
        if(!is_string($_POST[$field]))throw new DomainException('Uno de los campos GRE tiene un valor inválido.');
        $value=trim($_POST[$field]);
        if(mb_strlen($value)>$max)throw new DomainException('El campo '.$field.' excede '.$max.' caracteres.');
        $values[$field]=$value;
    }
    if(array_key_exists('gross_weight',$_POST)) {
        $weight=trim((string)$_POST['gross_weight']);
        if($weight!==''&&(!is_numeric($weight)||!is_finite((float)$weight)||(float)$weight<=0||(float)$weight>99999999999.999))throw new DomainException('El peso bruto debe ser un número positivo.');
        $values['gross_weight']=$weight===''?null:$weight;
    }
    if(array_key_exists('weight_unit',$_POST)) {
        $unit=strtoupper(trim((string)$_POST['weight_unit']));
        if(!in_array($unit,['KGM','TNE'],true))throw new DomainException('Selecciona KGM o TNE para el peso bruto.');
        $values['weight_unit']=$unit;
    }
    if(!$values)throw new DomainException('No hay datos GRE que actualizar.');
    $pdo->beginTransaction();
    $guide=app_gre_guide($pdo,$id,true);
    if($guide['status']!=='Registrada')throw new DomainException('Sólo se pueden preparar guías en estado Registrada.');
    $reserved=$pdo->prepare('SELECT id FROM tbl_gre_submission WHERE source_id=? FOR UPDATE');$reserved->execute([$id]);
    if($reserved->fetchColumn())throw new DomainException('Esta guía ya tiene un envío reservado. Su XML e identidad son inmutables; consulta el ticket existente.');
    $set=implode(',',array_map(static fn(string $field):string=>$field.'=?',array_keys($values)));
    $pdo->prepare('UPDATE tbl_remission_guide SET '.$set.',last_update=CURRENT_TIMESTAMP WHERE id=?')->execute([...array_values($values),$id]);
    $pdo->commit();
    app_gre_reply(['success'=>true,'message'=>'Datos GRE guardados. Valida la guía antes de solicitar su envío.']);
} catch(Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    if($error instanceof PDOException&&in_array((string)$error->getCode(),['42S02','42S22'],true))app_gre_reply(['success'=>false,'message'=>'Falta actualizar la estructura de datos GRE.'],503);
    app_gre_failure($error);
}
