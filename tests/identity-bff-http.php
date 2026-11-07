<?php
/** Real PHP session/CSRF/permission boundary + a mocked API bridge. Only db_appu_qa and loopback HTTP are used. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('APPU_DB_NAME') !== 'db_appu_qa' || !in_array(getenv('APPU_DB_HOST') ?: 'localhost', ['localhost','127.0.0.1'], true)) throw new RuntimeException('Use solamente db_appu_qa local.');
$repo=dirname(__DIR__); require $repo.'/global/connection.php';
require_once $repo.'/global/peru-tax-profile.php';
if (DB!=='db_appu_qa'||!app_peru_table_exists($pdo,'tbl_peru_integration_settings')) throw new RuntimeException('Falta migración QA.');
$oldEnabled=(int)$pdo->query('SELECT identity_enabled FROM tbl_peru_integration_settings WHERE id=1')->fetchColumn();
$root=sys_get_temp_dir().'/appu-identity-bff-'.bin2hex(random_bytes(8));
mkdir($root.'/global',0700,true); mkdir($root.'/modules/identity',0700,true);
file_put_contents($root.'/global/connection.php','<?php require '.var_export($repo.'/global/connection.php',true).';');
file_put_contents($root.'/global/peru-tax-profile.php','<?php require_once '.var_export($repo.'/global/peru-tax-profile.php',true).';');
$bridge= <<<'PHP'
<?php
function app_sunat_api_request(string $method,string $path,array $headers=[],?array $payload=null):array {
    if (PHP_SAPI!=='cli-server'||!getenv('APPU_IDENTITY_QA_ROOT'))throw new RuntimeException('QA mock only');
    file_put_contents(getenv('APPU_IDENTITY_QA_ROOT').'/calls.jsonl',json_encode(compact('method','path','headers','payload'))."\n",FILE_APPEND|LOCK_EX);
    $scenario=(string)($_POST['qa_scenario']??'success');
    if(ctype_digit($scenario))return ['status'=>(int)$scenario,'body'=>['error'=>['message'=>'PRIVATE PROVIDER TOKEN AND PERSONAL DATA']]];
    $type=$payload['document_type'];$number=$payload['document_number'];
    $data=['document_type'=>$type,'document_number'=>$scenario==='mismatch'?'20000000010':$number,'legal_name'=>'PERSONA O EMPRESA SIMULACION','trade_name'=>null,'given_names'=>'SIMULACION','last_names'=>'PRUEBA','address'=>'EXTRA PERSONAL ADDRESS','ubigeo'=>'150131','state'=>'ACTIVO','condition'=>'HABIDO','department'=>'LIMA','province'=>'LIMA','district'=>'SAN ISIDRO','source'=>'decolecta','consulted_at'=>'2026-11-06T12:00:00Z','cache_hit'=>true,'tax_profile'=>['retention_agent'=>false,'perception_agent'=>null,'good_taxpayer'=>true],'private_phone'=>'PRIVATE PHONE','tax_profile_evidence'=>[]];
    return ['status'=>200,'body'=>['data'=>$data]];
}
PHP;
file_put_contents($root.'/global/sunat-api.php',$bridge);
$router= <<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)||!getenv('APPU_IDENTITY_QA_ROOT')){http_response_code(404);exit;}
require_once getenv('APPU_IDENTITY_QA_REPO').'/global/config.php';
header('X-APPU-QA-Database: '.DB);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/session'){
    app_start_session(); $_SESSION['loggedInUser']=['USERID'=>(int)getenv('APPU_IDENTITY_QA_USER')]; $_SESSION['csrf_token']=str_repeat('qa-identity-',5);
    header('Content-Type: application/json');echo json_encode(['csrf'=>$_SESSION['csrf_token']]);exit;
}
if($path!=='/identity'){http_response_code(404);exit;}
chdir(getenv('APPU_IDENTITY_QA_ROOT').'/modules/identity');
require getenv('APPU_IDENTITY_QA_REPO').'/modules/identity/consultar.php';
PHP;
file_put_contents($root.'/router.php',$router);
$process=null;$employee=$role=$user=null;$cookie='';$csrf='';$count=0;
$assert=static function(bool $condition,string $message)use(&$count):void{++$count;if(!$condition)throw new RuntimeException($message);};
try {
    $tag='qa_identity_'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO tbl_employee(name,last_name_1,last_name_2,address,id_doc_type,id_doc_number,civil_status,birth_date,job,admission_date,phone,email,study_level,study_career,active_status)VALUES('QA',?,'Simulación','QA','DNI',?,'Soltero','1990-01-01','QA',CURDATE(),'','','','',1)")->execute([$tag,$tag]); $employee=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_role(code,name,description)VALUES(?,?,?)')->execute([$tag,'Identity QA','Temporal']);$role=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'customers.view']);
    $pdo->prepare('INSERT INTO tbl_user(username,password,employee_id,role_id,active_status)VALUES(?,?,?,?,1)')->execute([$tag,password_hash('Only-local-QA#2026',PASSWORD_DEFAULT),$employee,$role]);$user=(int)$pdo->lastInsertId();
    $socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);if($socket===false)throw new RuntimeException('Cannot allocate loopback port');$address=stream_socket_get_name($socket,false);fclose($socket);
    $environment=array_merge(getenv(),['APPU_IDENTITY_QA_ROOT'=>$root,'APPU_IDENTITY_QA_REPO'=>$repo,'APPU_IDENTITY_QA_USER'=>(string)$user]);
    $process=proc_open([PHP_BINARY,'-S',$address,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,$environment,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Cannot start isolated loopback server');fclose($pipes[0]);
    $started=false;for($i=0;$i<100;$i++){if($handle=@stream_socket_client('tcp://'.$address,$code,$message,0.1)){fclose($handle);$started=true;break;}usleep(50000);}$assert($started,'HTTP fixture did not start');
    $request=static function(string $path,?array $data=null,bool $withCsrf=true)use($address,&$cookie,&$csrf):array {
        $headers=['Cookie: '.$cookie,'Content-Type: application/x-www-form-urlencoded','X-Requested-With: XMLHttpRequest'];if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
        $body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]));
        if($body===false)throw new RuntimeException('Local HTTP fixture failed');$responseHeaders=[];
        foreach($http_response_header as $header){if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$match)&&!str_contains($header,'Max-Age=0')&&!str_ends_with($match[1],'=deleted'))$cookie=$match[1];if(str_contains($header,':')){[$name,$value]=explode(':',$header,2);$responseHeaders[strtolower(trim($name))]=trim($value);}}
        if(($responseHeaders['x-appu-qa-database']??'')!=='db_appu_qa')throw new RuntimeException('Unexpected DB identity');
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return[(int)$match[1],json_decode($body,true),$responseHeaders,$body];
    };
    $payload=['partner_type'=>'customer','document_type'=>'RUC','document_number'=>'20000000001'];
    [$status]=$request('/identity',$payload);$assert($status===401,'Unauthenticated lookup was not rejected');
    [$status,$session]=$request('/session');$csrf=$session['csrf'];$assert($status===200,'QA session failed');
    [$status]=$request('/identity',$payload,false);$assert($status===419,'Lookup without CSRF was not rejected');
    [$status]=$request('/identity',$payload);$assert($status===403,'Read-only customer role could consult');
    $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'customers.manage']);
    [$status]=$request('/identity');$assert($status===405,'GET could trigger lookup');
    $pdo->exec('UPDATE tbl_peru_integration_settings SET identity_enabled=0 WHERE id=1');
    [$status]=$request('/identity',$payload);$assert($status===503&&!is_file($root.'/calls.jsonl'),'Feature off reached API');
    $pdo->exec('UPDATE tbl_peru_integration_settings SET identity_enabled=1 WHERE id=1');
    [$status]=$request('/identity',array_replace($payload,['document_number'=>'invalid']));$assert($status===422&&!is_file($root.'/calls.jsonl'),'Invalid document reached API');
    [$status,$data,$headers]=$request('/identity',$payload+['actor'=>'spoofed']);
    $assert($status===200&&$data['data']['tax_profile']['retention_agent']===false&&$data['data']['tax_profile']['perception_agent']===null,'Normalized profile did not survive BFF');
    $assert(($headers['cache-control']??'')==='no-store, private','Identity result is cacheable by browser');
    $assert(!isset($data['data']['private_phone']),'Unknown private upstream field disclosed');
    $calls=array_map(static fn(string $line):array=>json_decode($line,true,512,JSON_THROW_ON_ERROR),file($root.'/calls.jsonl',FILE_IGNORE_NEW_LINES));
    $assert($calls[0]['method']==='POST'&&$calls[0]['path']==='/api/v1/identity/lookup'&&$calls[0]['payload']['document_number']==='20000000001','Incorrect bridge contract');
    $assert($calls[0]['headers']===['X-Actor-Id: erp-user-'.$user],'Actor must come from authenticated server session');
    [$status,$data]=$request('/identity',array_replace($payload,['document_type'=>'DNI','document_number'=>'12345678']));
    $assert($status===200&&$data['data']['address']===null&&$data['data']['ubigeo']===null&&$data['data']['tax_profile']['retention_agent']===null,'DNI private data was not dropped');
    foreach([404=>404,429=>429,503=>503,500=>502]as$upstream=>$expected){[$status,,$headers,$raw]=$request('/identity',$payload+['qa_scenario'=>(string)$upstream]);$assert($status===$expected&&!str_contains($raw,'PRIVATE PROVIDER'),'Upstream error was not mapped safely');}
    [$status]=$request('/identity',$payload+['qa_scenario'=>'mismatch']);$assert($status===502,'Mismatched provider identity accepted');
    [$status]=$request('/identity',array_replace($payload,['partner_type'=>'provider']));$assert($status===403,'Customer permission allowed supplier lookup');
    $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'providers.manage']);
    [$status]=$request('/identity',array_replace($payload,['partner_type'=>'provider']));$assert($status===200,'Supplier manage permission was not refreshed');
    $pdo->prepare('UPDATE tbl_user SET active_status=0 WHERE id=?')->execute([$user]);[$status]=$request('/identity',$payload);$assert($status===401,'Inactive user could still query');
    echo 'PASS identity BFF real session/CSRF/permission/feature gate, safe mapped errors and mocked API bridge ('.$count." assertions; zero external requests)\n";
} finally {
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    $pdo->prepare('UPDATE tbl_peru_integration_settings SET identity_enabled=? WHERE id=1')->execute([$oldEnabled]);
    if($user)$pdo->prepare('DELETE FROM tbl_user WHERE id=?')->execute([$user]);
    if($employee)$pdo->prepare('DELETE FROM tbl_employee WHERE id=?')->execute([$employee]);
    if($role){$pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);$pdo->prepare('DELETE FROM tbl_role WHERE id=?')->execute([$role]);}
    foreach(['calls.jsonl','server.log','router.php','global/connection.php','global/sunat-api.php','global/peru-tax-profile.php']as$file)@unlink($root.'/'.$file);
    foreach(['modules/identity','modules','global','']as$directory)@rmdir($root.'/'.$directory);
}
