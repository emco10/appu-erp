<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
if(!str_ends_with(DB,'_qa'))throw new RuntimeException('Use una base _qa.');
function sessionAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$original=$_SESSION;$pdo->beginTransaction();
try{
 $key='QA-AUTH-'.bin2hex(random_bytes(4));$pdo->prepare("INSERT INTO tbl_employee(name,last_name_1,last_name_2,address,id_doc_type,id_doc_number,civil_status,birth_date,job,admission_date,phone,email,study_level,study_career,active_status)VALUES('QA',?,'Sesión','QA','DNI',?,'Soltero','1990-01-01','QA',CURDATE(),'','','','',1)")->execute([$key,$key]);$employee=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO tbl_role(code,name,description)VALUES(?,?,?)')->execute([strtolower($key),'Perfil QA','Temporal']);$role=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO tbl_user(username,password,employee_id,role_id,active_status)VALUES(?,?,?, ?,1)")->execute([$key,password_hash('Temporal#2026',PASSWORD_DEFAULT),$employee,$role]);$user=(int)$pdo->lastInsertId();
 $_SESSION['loggedInUser']=['USERID'=>$user,'ROLE_CODE'=>'admin','PERMISSIONS'=>['sales.manage']];
 sessionAssert(app_refresh_logged_in_user($pdo),'No recuperó sesión activa.');sessionAssert(!app_can('sales.manage')&&!app_can('finance.manage'),'Conservó privilegios antiguos de administrador.');
 $pdo->prepare('INSERT INTO tbl_role_permission(role_id,permission_id)SELECT ?,id FROM tbl_permission WHERE code=?')->execute([$role,'sales.view']);sessionAssert(app_refresh_logged_in_user($pdo)&&app_can('sales.view')&&!app_can('sales.manage'),'No aplicó permisos nuevos del perfil.');
 $pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$role]);sessionAssert(app_refresh_logged_in_user($pdo)&&!app_can('sales.view'),'La sesión conserva un permiso revocado.');
 $pdo->prepare('UPDATE tbl_user SET active_status=0 WHERE id=?')->execute([$user]);sessionAssert(!app_refresh_logged_in_user($pdo)&&!isset($_SESSION['loggedInUser']),'El usuario desactivado conserva sesión.');
 $pdo->prepare('UPDATE tbl_user SET active_status=1 WHERE id=?')->execute([$user]);$_SESSION['loggedInUser']=['USERID'=>$user];$pdo->prepare('UPDATE tbl_employee SET active_status=0 WHERE id=?')->execute([$employee]);sessionAssert(!app_refresh_logged_in_user($pdo),'El empleado desactivado conserva acceso.');
 echo "PASS session role refresh grant revoke stale admin and inactive user/employee invalidation\n";$pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL session permission lifecycle: '.$error->getMessage().PHP_EOL);exit(1);}finally{$_SESSION=$original;}
