<?php
/** ERP GRE HTTP regression with real QA database/session permissions and a local in-process API mock. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('APPU_DB_NAME')!=='db_appu_qa'||!in_array(getenv('APPU_DB_HOST')?:'localhost',['localhost','127.0.0.1'],true))throw new RuntimeException('Use solamente db_appu_qa local.');
$repo=dirname(__DIR__);require $repo.'/global/connection.php';require_once $repo.'/global/peru-tax-profile.php';
if(DB!=='db_appu_qa'||!app_peru_table_exists($pdo,'tbl_gre_submission'))throw new RuntimeException('Aplica primero la migración API003 en QA.');
$oldEnabled=(int)$pdo->query('SELECT gre_enabled FROM tbl_peru_integration_settings WHERE id=1')->fetchColumn();
$root=sys_get_temp_dir().'/appu-gre-bff-'.bin2hex(random_bytes(8));mkdir($root.'/global',0700,true);mkdir($root.'/modules/gre',0700,true);
file_put_contents($root.'/global/connection.php','<?php require '.var_export($repo.'/global/connection.php',true).';');
file_put_contents($root.'/global/peru-tax-profile.php','<?php require_once '.var_export($repo.'/global/peru-tax-profile.php',true).';');
// _common uses __DIR__, so preserve its relative imports inside the isolated fixture tree.
copy($repo.'/modules/gre/_common.php',$root.'/modules/gre/_common.php');
foreach(['listar','estado','preparar','previsualizar','enviar','consultar','artefacto']as$name)copy($repo.'/modules/gre/'.$name.'.php',$root.'/modules/gre/'.$name.'.php');
$bridge= <<<'PHP'
<?php
function app_sunat_api_request(string $method,string $path,array $headers=[],?array $payload=null):array {
    if(PHP_SAPI!=='cli-server'||!getenv('APPU_GRE_QA_ROOT'))throw new RuntimeException('QA mock only');
    file_put_contents(getenv('APPU_GRE_QA_ROOT').'/calls.jsonl',json_encode(compact('method','path','headers','payload'))."\n",FILE_APPEND|LOCK_EX);
    $scenario=(string)($_GET['qa_scenario']??$_POST['qa_scenario']??'success');
    if($scenario==='validation')return ['status'=>422,'body'=>['error'=>['code'=>'GRE_VALIDATION','message'=>'Falta peso bruto','details'=>[['path'=>'gross_weight','message'=>'Indica peso bruto']]]]];
    if(str_ends_with($path,'/preview'))return ['status'=>200,'body'=>['data'=>['validation'=>['valid'=>true,'errors'=>[]]]]];
    if(preg_match('#/(xml|cdr)$#',$path,$match)){
        $xml=$match[1]==='xml';$bytes=$xml?'<?xml version="1.0"?><DespatchAdvice>SIMULACION</DespatchAdvice>':"PK\x03\x04SIMULACION";
        return ['status'=>200,'body'=>['data'=>['filename'=>$scenario==='filename'?'../../private.php':($xml?'20000000001-09-TQ01-1.xml':'R-20000000001-09-TQ01-1.zip'),'content_base64'=>$scenario==='base64'?'!invalid!':base64_encode($bytes),'sha256'=>$scenario==='checksum'?str_repeat('0',64):hash('sha256',$bytes)]]];
    }
    return ['status'=>200,'body'=>['data'=>['record'=>['status'=>str_ends_with($path,'/submit')?'pending':'accepted','ticket'=>'00000000-0000-0000-0000-000000000001'],'replayed'=>false]]];
}
PHP;
file_put_contents($root.'/global/sunat-api.php',$bridge);
$router= <<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!getenv('APPU_GRE_QA_ROOT')){http_response_code(404);exit;}
require_once getenv('APPU_GRE_QA_REPO').'/global/config.php';header('X-APPU-QA-Database: '.DB);
$path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
if($path==='session'){app_start_session();$_SESSION['loggedInUser']=['USERID'=>(int)getenv('APPU_GRE_QA_USER')];$_SESSION['csrf_token']=str_repeat('qa-gre-',7);header('Content-Type: application/json');echo json_encode(['csrf'=>$_SESSION['csrf_token']]);exit;}
if(!in_array($path,['listar','estado','preparar','previsualizar','enviar','consultar','artefacto'],true)){http_response_code(404);exit;}
chdir(getenv('APPU_GRE_QA_ROOT').'/modules/gre');require $path.'.php';
PHP;
file_put_contents($root.'/router.php',$router);
$employee=$role=$user=$guide=null;$process=null;$cookie='';$csrf='';$count=0;
$assert=static function(bool $ok,string $message)use(&$count):void{++$count;if(!$ok)throw new RuntimeException($message);};
$callCount=static fn():int=>is_file($root.'/calls.jsonl')?count(file($root.'/calls.jsonl',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)):0;
try {
    $tag='qa_gre_'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO tbl_employee(name,last_name_1,last_name_2,address,id_doc_type,id_doc_number,civil_status,birth_date,job,admission_date,phone,email,study_level,study_career,active_status)VALUES('QA',?,'Simulación','QA','DNI',?,'Soltero','1990-01-01','QA',CURDATE(),'','','','',1)")->execute([$tag,$tag]);$employee=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_role(code,name,description)VALUES(?,?,?)')->execute([$tag,'GRE QA','Temporal']);$role=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'logistics.view']);
    $pdo->prepare('INSERT INTO tbl_user(username,password,employee_id,role_id,active_status)VALUES(?,?,?,?,1)')->execute([$tag,password_hash('Only-local-QA#2026',PASSWORD_DEFAULT),$employee,$role]);$user=(int)$pdo->lastInsertId();
    $warehouse=(int)$pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    $number='TQ01-'.random_int(1,99999999);
    $pdo->prepare("INSERT INTO tbl_remission_guide(number,source_type,source_id,warehouse_id,issue_date,transfer_date,recipient_name,created_by)VALUES(?,'invoice',1,?,CURDATE(),CURDATE(),?,?)")->execute([$number,$warehouse,'DESTINATARIO SIMULACION',$user]);$guide=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_remission_guide_detail(remission_guide_id,source_detail_id,product_id,item_code,description,quantity)VALUES(?,1,1,?,?,1)')->execute([$guide,'QA','ARTICULO SIMULACION']);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);if($socket===false)throw new RuntimeException('No loopback port');$address=stream_socket_get_name($socket,false);fclose($socket);
    $environment=array_merge(getenv(),['APPU_GRE_QA_ROOT'=>$root,'APPU_GRE_QA_REPO'=>$repo,'APPU_GRE_QA_USER'=>(string)$user]);
    $process=proc_open([PHP_BINARY,'-S',$address,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,$environment,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('Cannot start loopback HTTP server');fclose($pipes[0]);
    $started=false;for($i=0;$i<100;$i++){if($handle=@stream_socket_client('tcp://'.$address,$code,$message,0.1)){fclose($handle);$started=true;break;}usleep(50000);}$assert($started,'HTTP fixture failed to start');
    $request=static function(string $path,?array $data=null,bool $withCsrf=true)use($address,&$cookie,&$csrf):array{
        $headers=['Cookie: '.$cookie,'X-Requested-With: XMLHttpRequest','Content-Type: application/x-www-form-urlencoded'];if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
        $body=file_get_contents('http://'.$address.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]));if($body===false)throw new RuntimeException('Local server unavailable');$response=[];
        foreach($http_response_header as$header){if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$match)&&!str_contains($header,'Max-Age=0')&&!str_ends_with($match[1],'=deleted'))$cookie=$match[1];if(str_contains($header,':')){[$name,$value]=explode(':',$header,2);$response[strtolower(trim($name))]=trim($value);}}
        if(($response['x-appu-qa-database']??'')!=='db_appu_qa')throw new RuntimeException('Unexpected QA database');preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return[(int)$match[1],json_decode($body,true),$response,$body];
    };
    [$status]=$request('listar');$assert($status===401,'Unauthenticated list allowed');
    [$status,$session]=$request('session');$csrf=$session['csrf'];
    [$status,$data]=$request('listar');$assert($status===200&&$data['schema_ready']===true,'Read-only GRE list unavailable');
    [$status]=$request('preparar',['guide_id'=>$guide,'gross_weight'=>'2'],false);$assert($status===419,'Missing CSRF allowed prepare');
    [$status]=$request('enviar',['guide_id'=>$guide]);$assert($status===403,'Read-only role could submit');
    $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'logistics.manage']);
    $pdo->exec('UPDATE tbl_peru_integration_settings SET gre_enabled=0 WHERE id=1');
    foreach(['preparar','enviar','consultar']as$endpoint){[$status]=$request($endpoint,['guide_id'=>$guide,'gross_weight'=>'2']);$assert($status===503&&$callCount()===0,'Disabled GRE mutation reached API or DB');}
    [$status,$data]=$request('estado?guide_id='.$guide);$assert($status===200&&count($data['data']['lines'])===1&&$data['data']['gre_enabled']===false,'Existing guide cannot be inspected while disabled');
    [$status,$data]=$request('previsualizar?guide_id='.$guide);$assert($status===200&&$data['data']['validation']['valid']===true,'Preview bridge contract failed');
    foreach(['xml'=>'application/xml; charset=utf-8','cdr'=>'application/zip']as$kind=>$mime){[$status,,$headers,$bytes]=$request('artefacto?guide_id='.$guide.'&kind='.$kind);$assert($status===200&&$headers['content-type']===$mime&&(int)$headers['content-length']===strlen($bytes),'Artifact bytes/type/length changed');$assert(str_starts_with($headers['content-disposition'],'attachment;')&&$headers['cache-control']==='no-store, private','Artifact cache/download headers unsafe');}
    foreach(['filename','checksum','base64']as$scenario){[$status]=$request('artefacto?guide_id='.$guide.'&kind=xml&qa_scenario='.$scenario);$assert($status===502,'Invalid artifact integrity/name accepted');}
    $pdo->exec('UPDATE tbl_peru_integration_settings SET gre_enabled=1 WHERE id=1');
    [$status]=$request('enviar?guide_id='.$guide);$assert($status===405,'GET triggered submit');
    [$status]=$request('preparar',['guide_id'=>$guide,'gross_weight'=>'-1']);$assert($status===422,'Negative weight accepted');
    [$status]=$request('preparar',['guide_id'=>$guide,'gross_weight'=>'5.250','weight_unit'=>'KGM','driver_last_name'=>'PRUEBA','status'=>'Anulada']);$assert($status===200,'Prepare valid data failed');
    $query=$pdo->prepare('SELECT gross_weight,driver_last_name,status FROM tbl_remission_guide WHERE id=?');$query->execute([$guide]);$prepared=$query->fetch(PDO::FETCH_ASSOC);$assert((float)$prepared['gross_weight']===5.25&&$prepared['driver_last_name']==='PRUEBA'&&$prepared['status']==='Registrada','Prepare did not whitelist or persist valid fields');
    [$status,$data]=$request('enviar',['guide_id'=>$guide,'qa_scenario'=>'validation']);$assert($status===422&&$data['errors'][0]['path']==='gross_weight','Specific API validation error lost');
    [$status,$data]=$request('enviar',['guide_id'=>$guide]);$assert($status===200&&$data['data']['record']['ticket']!==''&&$data['data']['record']['status']==='pending','Ticket response lost');
    [$status]=$request('enviar',['guide_id'=>$guide]);$assert($status===200,'Manual replay BFF failed');
    $calls=array_map(static fn($line)=>json_decode($line,true),file($root.'/calls.jsonl',FILE_IGNORE_NEW_LINES));$submits=array_values(array_filter($calls,static fn($call)=>str_ends_with($call['path'],'/submit')));$assert(count($submits)===3&&$submits[0]['headers']===$submits[1]['headers']&&$submits[1]['headers']===$submits[2]['headers'],'Server idempotency key changed across manual retries');
    [$status,$data]=$request('consultar',['guide_id'=>$guide]);$assert($status===200&&$data['data']['record']['status']==='accepted','Ticket reconciliation response lost');
    $beforeCalls=$callCount();$pdo->prepare("UPDATE tbl_remission_guide SET status='Anulada' WHERE id=?")->execute([$guide]);[$status]=$request('enviar',['guide_id'=>$guide]);$assert($status===422&&$callCount()===$beforeCalls,'Cancelled guide reached submit bridge');
    $pdo->prepare("UPDATE tbl_remission_guide SET status='Registrada',number=? WHERE id=?")->execute(['INT-'.$tag,$guide]);[$status]=$request('enviar',['guide_id'=>$guide]);$assert($status===422&&$callCount()===$beforeCalls,'Internal series reached submit bridge');
    $pdo->prepare('UPDATE tbl_remission_guide SET number=? WHERE id=?')->execute([$number,$guide]);
    $xml='<DespatchAdvice>SIMULACION</DespatchAdvice>';
    $pdo->prepare("INSERT INTO tbl_gre_submission(source_id,basename,idempotency_key,xml_sha256,signed_xml,status)VALUES(?,?,?,?,?,'pending')")->execute([$guide,'20000000001-09-TQ01-'.$guide,'qa-gre-reserved-'.$guide,hash('sha256',$xml),$xml]);
    [$status]=$request('preparar',['guide_id'=>$guide,'gross_weight'=>'8']);$assert($status===422,'Reserved signed guide remained editable');
    $query->execute([$guide]);$assert((float)$query->fetch(PDO::FETCH_ASSOC)['gross_weight']===5.25,'Blocked prepare changed the guide');
    [$status,$data]=$request('estado?guide_id='.$guide);$assert($status===200&&$data['data']['can_edit']===false&&!isset($data['data']['submission']['signed_xml']),'Reserved status leaked signed XML or editability');
    echo 'PASS GRE BFF session/CSRF/permissions, feature off/read-only, preparation, idempotency, ticket reconciliation and artifact integrity ('.$count." assertions; zero external requests)\n";
} finally {
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    $pdo->prepare('UPDATE tbl_peru_integration_settings SET gre_enabled=? WHERE id=1')->execute([$oldEnabled]);
    if($guide){$pdo->prepare('DELETE FROM tbl_gre_submission WHERE source_id=?')->execute([$guide]);$pdo->prepare('DELETE FROM tbl_remission_guide_detail WHERE remission_guide_id=?')->execute([$guide]);$pdo->prepare('DELETE FROM tbl_remission_guide WHERE id=?')->execute([$guide]);}
    if($user)$pdo->prepare('DELETE FROM tbl_user WHERE id=?')->execute([$user]);if($employee)$pdo->prepare('DELETE FROM tbl_employee WHERE id=?')->execute([$employee]);if($role){$pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);$pdo->prepare('DELETE FROM tbl_role WHERE id=?')->execute([$role]);}
    foreach(['calls.jsonl','server.log','router.php','global/connection.php','global/sunat-api.php','global/peru-tax-profile.php','modules/gre/_common.php','modules/gre/listar.php','modules/gre/estado.php','modules/gre/preparar.php','modules/gre/previsualizar.php','modules/gre/enviar.php','modules/gre/consultar.php','modules/gre/artefacto.php']as$file)@unlink($root.'/'.$file);
    foreach(['modules/gre','modules','global','']as$directory)@rmdir($root.'/'.$directory);
}
