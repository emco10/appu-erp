<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('APPU_DB_NAME') !== 'db_appu_qa' || !in_array(getenv('APPU_DB_HOST') ?: 'localhost', ['localhost','127.0.0.1'], true)) throw new RuntimeException('Use solamente db_appu_qa local.');
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($lifetime)=>0);
app_start_session();
$_SESSION['loggedInUser'] = ['USERID'=>0,'ROLE_CODE'=>'admin','PERMISSIONS'=>[]];
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
require $root . '/global/connection.php';
require_once $root . '/global/peru-tax-profile.php';
if (DB !== 'db_appu_qa' || !app_peru_table_exists($pdo, 'tbl_partner_tax_profile')) throw new RuntimeException('Falta la migración en QA.');
$tag = 'QA-IDENTITY-' . bin2hex(random_bytes(5));
$created = [];
$assertions = 0;
function identityPartnerAssert(bool $value, string $message): void { global $assertions; ++$assertions; if (!$value) throw new RuntimeException($message); }
function identityPartnerInvoke(string $section, string $file, array $payload): string {
    global $pdo, $root; $_POST=$payload; $_SERVER['SCRIPT_NAME']='/modules/'.$section.'/'.$file;
    $previous=getcwd(); chdir($root.'/modules/'.$section); http_response_code(200); ob_start();
    try { include $file; return trim((string)ob_get_contents()); } finally { ob_end_clean(); chdir($previous); }
}
$profile = static fn(string $document): string => json_encode(['document_number'=>$document,'tax_profile'=>['retention_agent'=>false,'perception_agent'=>null,'good_taxpayer'=>true],'source'=>'decolecta','consulted_at'=>'2026-11-06T12:00:00Z','cache_hit'=>true,'detraction_account'=>''], JSON_THROW_ON_ERROR);
try {
    foreach (['customer','provider'] as $type) {
        $document = '20' . str_pad((string) random_int(1,99999999), 8, '0', STR_PAD_LEFT) . '1';
        $newDocument = '20' . str_pad((string) random_int(1,99999999), 8, '0', STR_PAD_LEFT) . '2';
        if ($type === 'customer') {
            $section='clientes'; $save='insert-update-cliente.php'; $get='consultar-cliente.php'; $table='tbl_customer'; $key='client_id'; $documentKey='ruc'; $idField='cliente_id'; $nameField='cliente_razsoc'; $numberField='cliente_ruc'; $prefix='CLI-';
            $base=['cliente_id'=>'','cliente_ruc'=>$document,'cliente_razsoc'=>$tag.' CUSTOMER','cliente_nomcom'=>'','cliente_correo'=>'','cliente_telfij'=>'','cliente_telcel'=>'','cliente_direccion'=>'PRUEBA QA','cliente_departamento'=>'15','cliente_provincia'=>'1501','cliente_distrito'=>'150131','cliente_nomcont_1'=>'','cliente_celcont_1'=>'','cliente_nomcont_2'=>'','cliente_celcont_2'=>'','cliente_pagocomision'=>'0','cliente_fecreg'=>date('Y-m-d'),'cliente_active_status'=>'1','cliente_moneda'=>'PEN','cliente_dias_pago'=>'0','cliente_limite_credito'=>'0','custom_field'=>[]];
        } else {
            $section='proveedores'; $save='insert-update-proveedor.php'; $get='consultar-proveedor.php'; $table='tbl_provider'; $key='id'; $documentKey='code'; $idField='proveedor_id'; $nameField='proveedor_razsoc'; $numberField='proveedor_numero'; $prefix='PROV-';
            $base=['proveedor_id'=>'','proveedor_numero'=>$document,'proveedor_razsoc'=>$tag.' PROVIDER','proveedor_active_status'=>'1','proveedor_moneda'=>'PEN','proveedor_dias_pago'=>'0','proveedor_limite_credito'=>'0','custom_field'=>[]];
        }
        $payload=$base+['partner_tax_profile_json'=>$profile($document)];
        identityPartnerAssert(identityPartnerInvoke($section,$save,$payload)==='OK_INSERT',$type.': create with profile');
        $query=$pdo->prepare("SELECT $key FROM $table WHERE $documentKey=?"); $query->execute([$document]); $id=(int)$query->fetchColumn();
        identityPartnerAssert($id>0,$type.': created identity'); $created[]=[$type,$table,$key,$id];
        $detail=json_decode(identityPartnerInvoke($section,$get,['FILTER'=>$prefix.$id]),true,512,JSON_THROW_ON_ERROR);
        $persisted=json_decode($detail[0]['TAX_PROFILE_JSON']??'null',true,512,JSON_THROW_ON_ERROR);
        identityPartnerAssert($persisted['tax_profile']===['retention_agent'=>false,'perception_agent'=>null,'good_taxpayer'=>true],$type.': tri-state persistence');
        identityPartnerAssert($persisted['source']==='decolecta'&&$persisted['consulted_at']==='2026-11-06T12:00:00Z',$type.': provenance persistence');
        $payload[$idField]=(string)$id; $payload[$nameField]=$tag.' INVALID UPDATE'; $payload['partner_tax_profile_json']='{invalid';
        $failed=json_decode(identityPartnerInvoke($section,$save,$payload),true,512,JSON_THROW_ON_ERROR);
        identityPartnerAssert(($failed['error']??false)===true,$type.': invalid JSON rejects save');
        $check=$pdo->prepare("SELECT business_name FROM $table WHERE $key=?"); $check->execute([$id]);
        identityPartnerAssert($check->fetchColumn()===$base[$nameField],$type.': invalid JSON rolls back master update');
        $payload['partner_tax_profile_json']=$profile($newDocument);
        $failed=json_decode(identityPartnerInvoke($section,$save,$payload),true,512,JSON_THROW_ON_ERROR);
        identityPartnerAssert(($failed['error']??false)===true,$type.': profile for another document rejected');
        $payload[$nameField]=$base[$nameField]; $payload[$numberField]=$newDocument; $payload['partner_tax_profile_json']='';
        identityPartnerAssert(identityPartnerInvoke($section,$save,$payload)==='OK_UPDATE',$type.': document can change without lookup');
        identityPartnerAssert(app_partner_tax_profile_json($pdo,$type,$id)==='',$type.': old document profile invalidated');
    }
    echo 'PASS identity partner create/read profile, nullable flags, malformed/mismatched profile rollback and document change ('.$assertions." assertions)\n";
} finally {
    foreach (array_reverse($created) as [$type,$table,$key,$id]) {
        $pdo->prepare('DELETE FROM tbl_partner_tax_profile WHERE partner_type=? AND partner_id=?')->execute([$type==='provider'?'supplier':'customer',$id]);
        $pdo->prepare('DELETE FROM tbl_custom_field_value WHERE entity=? AND record_id=?')->execute([$type,$id]);
        $pdo->prepare("DELETE FROM $table WHERE $key=?")->execute([$id]);
    }
}
