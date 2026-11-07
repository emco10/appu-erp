<?php
/** Loopback HTTP and real ERP session/CSRF/permissions; the upstream API is mocked. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('APPU_DB_NAME')!=='db_appu_qa'||!in_array(getenv('APPU_DB_HOST')?:'localhost',['localhost','127.0.0.1'],true))throw new RuntimeException('Use solamente db_appu_qa local.');
$repo=dirname(__DIR__);require $repo.'/global/connection.php';require_once $repo.'/global/peru-tax-profile.php';
if(DB!=='db_appu_qa'||!app_peru_table_exists($pdo,'tbl_peru_integration_settings'))throw new RuntimeException('Falta migración QA.');
$oldEnabled=(int)$pdo->query('SELECT sire_enabled FROM tbl_peru_integration_settings WHERE id=1')->fetchColumn();
$root=sys_get_temp_dir().'/appu-sire-bff-'.bin2hex(random_bytes(8));mkdir($root.'/global',0700,true);mkdir($root.'/modules/sire',0700,true);
foreach(['connection.php','peru-tax-profile.php']as$file)file_put_contents($root.'/global/'.$file,'<?php require_once '.var_export($repo.'/global/'.$file,true).';');
foreach(['_common.php','periods.php','export.php','tickets.php','proposal.php','download.php']as$file)copy($repo.'/modules/sire/'.$file,$root.'/modules/sire/'.$file);
file_put_contents($root.'/global/sunat-api.php', <<<'PHP'
<?php
function app_sunat_api_request(string $method,string $path,array $headers=[],?array $payload=null):array{
 if(PHP_SAPI!=='cli-server'||!getenv('APPU_SIRE_QA_ROOT'))throw new RuntimeException('QA mock only');
 file_put_contents(getenv('APPU_SIRE_QA_ROOT').'/calls.jsonl',json_encode(compact('method','path','headers','payload'))."\n",FILE_APPEND|LOCK_EX);
 if(str_starts_with($path,'/api/v1/sire/download?')){
  $body="PK\x03\x04ONLY LOCAL FIXTURE";$scenario=$_GET['qa_scenario']??'';
  return ['status'=>200,'body'=>['data'=>['filename'=>$scenario==='path'?'../PRIVATE.zip':'SIRE-local.zip','content_base64'=>base64_encode($body),'sha256'=>$scenario==='hash'?str_repeat('0',64):hash('sha256',$body)]]];
 }
 return ['status'=>200,'body'=>['data'=>['book'=>'140000','period'=>'202610','ticket'=>'20260100000001','status'=>'processing','items'=>[]]]];
}
PHP);
file_put_contents($root.'/router.php', <<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!getenv('APPU_SIRE_QA_ROOT')){http_response_code(404);exit;}
require_once getenv('APPU_SIRE_QA_REPO').'/global/config.php';header('X-APPU-QA-Database: '.DB);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/session'){app_start_session();$_SESSION['loggedInUser']=['USERID'=>(int)getenv('APPU_SIRE_QA_USER')];$_SESSION['csrf_token']=str_repeat('qa-sire-',8);header('Content-Type: application/json');echo json_encode(['csrf'=>$_SESSION['csrf_token']]);exit;}
$file=ltrim((string)$path,'/');if(!in_array($file,['periods','export','tickets','proposal','download'],true)){http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']='/modules/sire/'.$file.'.php';chdir(getenv('APPU_SIRE_QA_ROOT').'/modules/sire');require $file.'.php';
PHP);
$process=null;$employee=$role=$user=null;$cookie='';$csrf='';$count=0;
$assert=static function(bool $condition,string $message)use(&$count):void{++$count;if(!$condition)throw new RuntimeException($message);};
try{
 $tag='qa_sire_'.bin2hex(random_bytes(4));
 $pdo->prepare("INSERT INTO tbl_employee(name,last_name_1,last_name_2,address,id_doc_type,id_doc_number,civil_status,birth_date,job,admission_date,phone,email,study_level,study_career,active_status)VALUES('QA',?,'Simulación','QA','DNI',?,'Soltero','1990-01-01','QA',CURDATE(),'','','','',1)")->execute([$tag,$tag]);$employee=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO tbl_role(code,name,description)VALUES(?,?,?)')->execute([$tag,'SIRE QA','Temporal']);$role=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'management.view']);
 $pdo->prepare('INSERT INTO tbl_user(username,password,employee_id,role_id,active_status)VALUES(?,?,?,?,1)')->execute([$tag,password_hash('Only-local-QA#2026',PASSWORD_DEFAULT),$employee,$role]);$user=(int)$pdo->lastInsertId();
 $socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);if($socket===false)throw new RuntimeException('Cannot allocate loopback port');$address=stream_socket_get_name($socket,false);fclose($socket);
 $environment=array_merge(getenv(),['APPU_SIRE_QA_ROOT'=>$root,'APPU_SIRE_QA_REPO'=>$repo,'APPU_SIRE_QA_USER'=>(string)$user]);
 $process=proc_open([PHP_BINARY,'-S',$address,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,$environment,['bypass_shell'=>true]);
 if(!is_resource($process))throw new RuntimeException('Cannot start local SIRE fixture');fclose($pipes[0]);
 $started=false;for($i=0;$i<100;$i++){if($handle=@stream_socket_client('tcp://'.$address,$code,$message,0.1)){fclose($handle);$started=true;break;}usleep(50000);}$assert($started,'Local SIRE fixture did not start');
 $request=static function(string $path,?array $data=null,bool $withCsrf=true)use($address,&$cookie,&$csrf):array{
  $headers=['Cookie: '.$cookie,'Content-Type: application/x-www-form-urlencoded','X-Requested-With: XMLHttpRequest'];if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
  $body=file_get_contents('http://'.$address.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]));
  if($body===false)throw new RuntimeException('Local SIRE HTTP failed');$responseHeaders=[];
  foreach($http_response_header as$header){if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$match)&&!str_contains($header,'Max-Age=0')&&!str_ends_with($match[1],'=deleted'))$cookie=$match[1];if(str_contains($header,':')){[$name,$value]=explode(':',$header,2);$responseHeaders[strtolower(trim($name))]=trim($value);}}
  if(($responseHeaders['x-appu-qa-database']??'')!=='db_appu_qa')throw new RuntimeException('Unexpected database');
  preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return[(int)$match[1],json_decode($body,true),$responseHeaders,$body];
 };
 $calls=static fn():array=>is_file($root.'/calls.jsonl')?array_map(static fn(string $line):array=>json_decode($line,true,512,JSON_THROW_ON_ERROR),file($root.'/calls.jsonl',FILE_IGNORE_NEW_LINES)):[];
 [$status]=$request('/periods?book=140000');$assert($status===401,'Unauthenticated SIRE access must fail');
 [$status,$session]=$request('/session');$csrf=$session['csrf'];$assert($status===200,'Real QA session missing');
 [$status]=$request('/export',['book'=>'140000','period'=>'202610'],false);$assert($status===419,'Export needs normal CSRF');
 [$status]=$request('/export',['book'=>'140000','period'=>'202610']);$assert($status===403,'Read-only role must not request an export');
 $pdo->exec('UPDATE tbl_peru_integration_settings SET sire_enabled=0 WHERE id=1');
 [$status,$data]=$request('/periods?book=140000');$assert($status===503&&$data['code']==='SIRE_DISABLED'&&count($calls())===0,'Disabled ERP gate must stop before the API bridge');
 $pdo->exec('UPDATE tbl_peru_integration_settings SET sire_enabled=1 WHERE id=1');
 [$status,,$headers]=$request('/periods?book=140000');$assert($status===200&&count($calls())===1,'Enabled manual consultation must work for view permission');
 $assert(str_contains($headers['cache-control']??'','no-store'),'SIRE responses must not be cached');
 $assert($calls()[0]===['method'=>'GET','path'=>'/api/v1/sire/periods?book=140000','headers'=>[],'payload'=>null],'Periods BFF must use API fixed route contract');
 $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'management.manage']);
 [$status]=$request('/export?book=140000&period=202610');$assert($status===405&&count($calls())===1,'GET must not trigger an export');
 [$status]=$request('/export',['book'=>'080000','period'=>'202610','url'=>'https://not-allowed.example']);$assert($status===200,'Manage permission should refresh without a new login');
 $assert($calls()[1]===['method'=>'POST','path'=>'/api/v1/sire/proposal/export','headers'=>[],'payload'=>['book'=>'080000','period'=>'202610']],'Export must forward only approved body parameters');
 $before=count($calls());foreach(['/periods?book=bad','/proposal?book=140000&period=202613&ticket=20260100000001','/proposal?book=140000&period=202610&ticket=..%2Fprivate','/tickets?book=140000&from=202611&to=202610','/proposal?book=140000&period=202610&ticket=20260100000001&page=1abc','/download?book=140000&period=202610&ticket=20260100000001&file_index=10']as$invalid){[$status]=$request($invalid);$assert($status===422&&count($calls())===$before,'Invalid SIRE input must not reach the API');}
 [$status]=$request('/tickets?book=140000&from=202610&to=202611&ticket=20260100000001&page=2&per_page=25');$assert($status===200&&str_contains($calls()[2]['path'],'/api/v1/sire/tickets?book=140000&from=202610&to=202611&ticket=20260100000001&page=2&per_page=25'),'Ticket parameters must match API route');
 [$status]=$request('/proposal?book=140000&period=202610&ticket=20260100000001&page=3&per_page=25');$assert($status===200&&$calls()[3]['method']==='GET'&&str_contains($calls()[3]['path'],'/api/v1/sire/proposal?'),'Proposal must remain a manual read');
 $download='/download?book=140000&period=202610&ticket=20260100000001';
 [$status,,$headers,$bytes]=$request($download);$assert($status===200&&$headers['content-type']==='application/zip'&&(int)$headers['content-length']===strlen($bytes)&&$headers['content-disposition']==='attachment; filename="SIRE-local.zip"','Verified ZIP must download with exact safe headers');
 foreach(['hash','path']as$scenario){[$status,,,$raw]=$request($download.'&qa_scenario='.$scenario);$assert($status===502&&!str_contains($raw,'PRIVATE')&&!str_starts_with($raw,"PK\x03\x04"),'Unsafe archive or bad hash must not be downloaded');}
 $pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);$before=count($calls());[$status]=$request('/periods?book=140000');$assert($status===403&&count($calls())===$before,'Revoked view permission must apply immediately');
 $pdo->prepare('UPDATE tbl_user SET active_status=0 WHERE id=?')->execute([$user]);[$status]=$request('/periods?book=140000');$assert($status===401,'Inactive user must lose SIRE access');
 echo 'PASS SIRE BFF real session/CSRF/permissions/feature gate, fixed API routes and verified ZIP ('.$count." assertions; no external requests)\n";
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 $pdo->prepare('UPDATE tbl_peru_integration_settings SET sire_enabled=? WHERE id=1')->execute([$oldEnabled]);
 if($user)$pdo->prepare('DELETE FROM tbl_user WHERE id=?')->execute([$user]);if($employee)$pdo->prepare('DELETE FROM tbl_employee WHERE id=?')->execute([$employee]);
 if($role){$pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);$pdo->prepare('DELETE FROM tbl_role WHERE id=?')->execute([$role]);}
 foreach(['calls.jsonl','server.log','router.php','global/connection.php','global/sunat-api.php','global/peru-tax-profile.php','modules/sire/_common.php','modules/sire/periods.php','modules/sire/export.php','modules/sire/tickets.php','modules/sire/proposal.php','modules/sire/download.php']as$file)@unlink($root.'/'.$file);
 foreach(['modules/sire','modules','global','']as$directory)@rmdir($root.'/'.$directory);
}
