<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base _qa.');
$base=getenv('APPU_QA_URL')?:'http://127.0.0.1:8769';if(!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Use loopback QA.');
$cookie='';$csrf='';$key='qa_auth_'.bin2hex(random_bytes(4));$employee=$role=$user=null;$failed=false;
$request=static function(string $path,?array $data=null,bool $withCsrf=true)use($base,&$cookie,&$csrf):array{
 $headers=['Cookie: '.$cookie,'X-Requested-With: XMLHttpRequest','Content-Type: application/x-www-form-urlencoded'];if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
 $body=file_get_contents($base.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]));
 if($body===false)throw new RuntimeException('No responde servidor QA.');$db='';foreach($http_response_header as $header){if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$header,$match)&&!str_contains($header,'Max-Age=0')&&!str_ends_with($match[1],'=deleted'))$cookie=$match[1];if(preg_match('/^X-APPU-QA-Database:\s*(\S+)/i',$header,$match))$db=$match[1];}
 if($db!==DB)throw new RuntimeException('No coincide la identidad QA del servidor.');preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return[(int)($match[1]??0),json_decode($body,true),$body];
};
function authHttpAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 $pdo->prepare("INSERT INTO tbl_employee(name,last_name_1,last_name_2,address,id_doc_type,id_doc_number,civil_status,birth_date,job,admission_date,phone,email,study_level,study_career,active_status)VALUES('QA',?,'Sesión','QA','DNI',?,'Soltero','1990-01-01','QA',CURDATE(),'','','','',1)")->execute([$key,$key]);$employee=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO tbl_role(code,name,description)VALUES(?,?,?)')->execute([$key,'Perfil sesión QA','Temporal']);$role=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'customers.view']);$pdo->prepare('INSERT INTO tbl_user(username,password,employee_id,role_id,active_status)VALUES(?,?,?,?,1)')->execute([$key,password_hash('Prueba#2026',PASSWORD_DEFAULT),$employee,$role]);$user=(int)$pdo->lastInsertId();
 [$status]=$request('/modules/gestion/configuracion.php');authHttpAssert($status===401,'El servidor no está usando autenticación real de sesión.');
 [$status,$login]=$request('/modules/login.php',['txtUsername'=>$key,'txtPassword'=>'Prueba#2026'],false);authHttpAssert($status===200&&($login['error']??true)===false,'Falló inicio de sesión QA.');
 [$status,,$html]=$request('/views/clientes/registro-cliente');authHttpAssert($status===200&&preg_match('/name="csrf-token" content="([^"]+)"/',$html,$match),'No accede a la consulta permitida.');$csrf=$match[1];
 [$status]=$request('/modules/clientes/consultar-cliente.php',['FILTER'=>'ALL'],false);authHttpAssert($status===419,'Una lectura POST sin CSRF no fue rechazada.');[$status]=$request('/modules/clientes/consultar-cliente.php',['FILTER'=>'ALL']);authHttpAssert($status===200,'No consulta con permiso y CSRF válido.');
 [$status]=$request('/modules/gestion/configuracion.php');authHttpAssert($status===403,'El perfil accede a Gestión sin permiso.');$pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'management.view']);
 [$status]=$request('/modules/gestion/configuracion.php');authHttpAssert($status===200,'Los permisos nuevos no se aplican a la sesión abierta.');$pdo->prepare('DELETE assignment FROM tbl_role_permission assignment JOIN tbl_permission permission ON permission.id=assignment.permission_id WHERE assignment.role_id=? AND permission.code=?')->execute([$role,'management.view']);
 [$status]=$request('/modules/gestion/configuracion.php');authHttpAssert($status===403,'El permiso revocado sigue activo en la sesión.');$pdo->prepare('UPDATE tbl_user SET active_status=0 WHERE id=?')->execute([$user]);[$status]=$request('/modules/clientes/consultar-cliente.php',['FILTER'=>'ALL']);authHttpAssert($status===401,'La sesión del usuario desactivado mantiene acceso.');
 echo "PASS HTTP real login CSRF live permission grant/revoke and inactive session invalidation\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL session auth HTTP: '.$error->getMessage().PHP_EOL);$failed=true;}
finally{if($user)$pdo->prepare('DELETE FROM tbl_user WHERE id=?')->execute([$user]);if($employee)$pdo->prepare('DELETE FROM tbl_employee WHERE id=?')->execute([$employee]);if($role){$pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);$pdo->prepare('DELETE FROM tbl_role WHERE id=?')->execute([$role]);}}
exit($failed?1:0);
