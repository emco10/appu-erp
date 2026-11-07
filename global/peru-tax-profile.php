<?php
declare(strict_types=1);

function app_peru_table_exists(PDO $pdo, string $table): bool
{
    $query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $query->execute([$table]); return (bool)$query->fetchColumn();
}
function app_partner_tax_profile_normalize(string $document, string $json): array
{
    if(strlen($json)>16384)throw new DomainException('El perfil tributario excede el tamaño permitido.');
    try{$data=json_decode($json,true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new DomainException('El perfil tributario no tiene un formato válido.');}
    if(!is_array($data)||!preg_match('/^(?:[0-9]{8}|[0-9]{11})$/D',$document))throw new DomainException('El perfil tributario requiere un DNI o RUC válido.');
    if(($data['document_number']??null)!==$document)throw new DomainException('El perfil consultado corresponde a otro documento. Vuelve a consultar.');
    if(isset($data['tax_profile'])&&!is_array($data['tax_profile']))throw new DomainException('Las condiciones tributarias no tienen un formato válido.');
    $flags=[];
    foreach(['retention_agent','perception_agent','good_taxpayer'] as $field){
        $value=$data['tax_profile'][$field]??null;
        if($value!==null&&!is_bool($value))throw new DomainException('Las condiciones tributarias admiten sí, no o sin verificar.');
        $flags[$field]=strlen($document)===11?$value:null;
    }
    $accountValue=$data['detraction_account']??'';
    if(!is_string($accountValue))throw new DomainException('La cuenta de detracciones no tiene un formato válido.');
    $account=trim($accountValue);
    if($account!==''&&!preg_match('/^[0-9 -]{10,30}$/D',$account))throw new DomainException('Verifica el número de cuenta de detracciones del Banco de la Nación.');
    $sourceValue=$data['source']??'manual';
    if(!is_string($sourceValue)||strlen($sourceValue)>100)throw new DomainException('El origen del perfil tributario no tiene un formato válido.');
    $source=trim($sourceValue)?:'manual';
    $consulted=$data['consulted_at']??'';
    if(!is_string($consulted))throw new DomainException('La fecha de consulta del perfil no es válida.');
    if($consulted!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',$consulted)||strtotime($consulted)===false))throw new DomainException('La fecha de consulta del perfil no es válida.');
    $evidence=[];
    foreach(array_keys($flags) as $field){
        $item=$data['tax_profile_evidence'][$field]??null;
        if(!is_array($item)||$source==='manual')continue;
        foreach(['provider','field','consulted_at'] as $key){
            if(isset($item[$key])&&is_string($item[$key])&&strlen($item[$key])<=100)$evidence[$field][$key]=$item[$key];
        }
    }
    return ['document_number'=>$document,'tax_profile'=>$flags,'source'=>$source,'consulted_at'=>$consulted?:null,'detraction_account'=>$account,'verified_by_user'=>true,'cache_hit'=>($data['cache_hit']??false)===true,'tax_profile_evidence'=>$evidence];
}
function app_partner_tax_profile_save(PDO $pdo,string $type,int $id,string $document,string $json): void
{
    if(!in_array($type,['customer','supplier','client','provider'],true)||$id<1)throw new DomainException('Socio de negocio inválido.');
    $type=['client'=>'customer','provider'=>'supplier'][$type]??$type;
    $data=app_partner_tax_profile_normalize($document,$json);
    if(!app_peru_table_exists($pdo,'tbl_partner_tax_profile'))throw new DomainException('Falta aplicar la migración de perfiles tributarios. Guarda el maestro sin aplicar una consulta o solicita la actualización.');
    $pdo->prepare('INSERT INTO tbl_partner_tax_profile (partner_type,partner_id,document_number,profile_json,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE document_number=VALUES(document_number),profile_json=VALUES(profile_json),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP')
        ->execute([$type,$id,$document,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),(int)($_SESSION['loggedInUser']['USERID']??0)]);
}
function app_partner_tax_profile_json(PDO $pdo,string $type,int $id): string
{
    $type=['client'=>'customer','provider'=>'supplier'][$type]??$type;
    if(!app_peru_table_exists($pdo,'tbl_partner_tax_profile'))return '';
    $query=$pdo->prepare('SELECT profile_json FROM tbl_partner_tax_profile WHERE partner_type=? AND partner_id=?');$query->execute([$type,$id]);
    return (string)($query->fetchColumn()?:'');
}
function app_peru_settings(PDO $pdo): array
{
    $defaults=['identity_enabled'=>0,'gre_enabled'=>0,'sire_enabled'=>0,'retention_enabled'=>0,'detraction_enabled'=>0,'company_retention_agent'=>null];
    if(!app_peru_table_exists($pdo,'tbl_peru_integration_settings'))return $defaults;
    return ($pdo->query('SELECT identity_enabled,gre_enabled,sire_enabled,retention_enabled,detraction_enabled,company_retention_agent FROM tbl_peru_integration_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:[]) + $defaults;
}

/** Canonical operation inputs make retries independent of POST field order. */
function app_peru_tax_request_inputs(array $input): array
{
    $defaults=['regime'=>'','currency'=>'PEN','operation_date'=>'','tax_obligation_date'=>'','operation_type'=>'purchase','document_reference'=>'','gross_operation'=>'','gross_payment'=>'','already_deposited'=>'0','spot_rounding'=>'cents','exclusions_reviewed'=>'','igv_taxed'=>'','buyer_retention_agent'=>'','supplier_retention_agent'=>'','supplier_perception_agent'=>'','supplier_good_taxpayer'=>'','spot_applicability'=>'unknown','fiscal_credit_document'=>'','spot_category'=>''];
    $result=[];
    foreach($defaults as $name=>$default){
        $value=$input[$name]??$default;
        if((!is_string($value)&&!is_int($value))||strlen((string)$value)>512)throw new DomainException('El campo '.$name.' no tiene un formato válido.');
        $result[$name]=$name==='document_reference'?trim((string)$value):(string)$value;
    }
    return $result;
}

/** Save once and replay the persisted result, even if settings subsequently change. */
function app_peru_tax_evaluation_signature(array $evaluation): string
{
    return hash('sha256',json_encode($evaluation,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function app_peru_tax_save_evaluation(PDO $pdo,array $input,array $settings,int $user): array
{
    $inputs=app_peru_tax_request_inputs($input);
    $reference=$inputs['document_reference'];$type=$inputs['operation_type'];
    if($user<1||!in_array($type,['purchase','sale'],true)||$reference===''||mb_strlen($reference)>80)throw new DomainException('Indica una referencia válida de compra o venta para guardar la evaluación.');
    $key=$input['request_key']??'';
    if(!is_string($key)||!preg_match('/^[a-zA-Z0-9_-]{20,80}$/D',$key))throw new DomainException('La solicitud de guardado no tiene una clave válida. Vuelve a evaluar.');
    $hash=hash('sha256',json_encode($inputs,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $load=static function()use($pdo,$key):array|false{
        $query=$pdo->prepare('SELECT id,request_sha256,created_by,evaluation_json FROM tbl_peru_tax_evaluation WHERE request_key=?');
        $query->execute([$key]);return $query->fetch(PDO::FETCH_ASSOC);
    };
    $replay=static function(array $row)use($hash,$user):array{
        if(!hash_equals((string)$row['request_sha256'],$hash)||(int)$row['created_by']!==$user)throw new DomainException('Esta clave ya corresponde a otra evaluación. Genera una solicitud nueva.');
        $record=json_decode((string)$row['evaluation_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($record['result']??null))throw new RuntimeException('La evaluación guardada no contiene un resultado válido.');
        return $record['result']+['evaluation_id'=>(int)$row['id'],'saved'=>true];
    };
    $existing=$load();
    if($existing!==false)return $replay($existing);
    $evaluation=app_peru_tax_evaluate($inputs,$settings);
    $signature=app_peru_tax_evaluation_signature($evaluation);
    if(!is_string($input['evaluation_signature']??null)||!hash_equals($signature,$input['evaluation_signature']))throw new DomainException('La evaluación cambió o ya no coincide con la vista previa. Vuelve a evaluar antes de guardar.');
    $evaluation['evaluation_signature']=$signature;
    $record=['inputs'=>$inputs,'result'=>$evaluation,'settings'=>array_intersect_key($settings,array_flip(['retention_enabled','detraction_enabled','company_retention_agent']))];
    $encoded=json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    try{
        $pdo->prepare('INSERT INTO tbl_peru_tax_evaluation(request_key,request_sha256,operation_type,document_reference,evaluation_json,created_by) VALUES(?,?,?,?,?,?)')->execute([$key,$hash,$type,$reference,$encoded,$user]);
    }catch(PDOException $error){
        if($error->getCode()!=='23000'||(int)($error->errorInfo[1]??0)!==1062)throw $error;
    }
    $saved=$load();
    if($saved===false)throw new RuntimeException('No se pudo recuperar la evaluación guardada.');
    return $replay($saved);
}
