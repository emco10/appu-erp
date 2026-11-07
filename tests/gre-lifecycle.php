<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/gre-lifecycle.php';
$database='appu_gre_lifecycle_'.bin2hex(random_bytes(5));
if(!preg_match('/^appu_gre_lifecycle_[a-f0-9]{10}$/D',$database))throw new RuntimeException('Unsafe temporary database.');
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('APPU_TEST_MYSQL_USER')?:'root',getenv('APPU_TEST_MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$created=false;$assertions=0;
$assert=static function(bool $condition,string $message)use(&$assertions):void{++$assertions;if(!$condition)throw new RuntimeException($message);};
$blocked=static function(callable $action)use($assert):void{try{$action();throw new RuntimeException('GRE guard was bypassed.');}catch(DomainException $error){$assert(str_contains($error->getMessage(),'GRE'),'A specific GRE explanation is required.');}};
try{
 $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4");$created=true;$pdo->exec("USE `{$database}`");
 $pdo->beginTransaction();
 app_gre_require_local_cancellation($pdo,1);app_gre_require_transit_ready($pdo,1);
 $assert($pdo->inTransaction(),'Missing optional migration must not end the caller transaction.');$pdo->rollBack();
 $pdo->exec('CREATE TABLE tbl_gre_submission (source_id INT PRIMARY KEY,status VARCHAR(20)) ENGINE=InnoDB');
 $pdo->beginTransaction();
 app_gre_require_local_cancellation($pdo,1);app_gre_require_transit_ready($pdo,1);
 $assert(true,'Internal guides without GRE preserve their lifecycle.');
 foreach(['queued','sending','uncertain','processing','technical_error','rejected','accepted','observed']as$status){
  $pdo->prepare('REPLACE INTO tbl_gre_submission VALUES (?,?)')->execute([1,$status]);
  $blocked(static fn()=>app_gre_require_local_cancellation($pdo,1));
  if(in_array($status,['accepted','observed'],true)){app_gre_require_transit_ready($pdo,1);$assert(true,'Final CDR permits transit.');}
  else $blocked(static fn()=>app_gre_require_transit_ready($pdo,1));
 }
 $pdo->rollBack();
 try{app_gre_require_transit_ready($pdo,1);throw new RuntimeException('Missing lifecycle transaction accepted.');}
 catch(LogicException){$assert(true,'Lifecycle requires caller-owned transaction.');}
 echo 'PASS GRE lifecycle: all reserved states block local cancellation, only accepted/observed permit transit, internal guides preserved ('.$assertions." assertions)\n";
}finally{if($pdo->inTransaction())$pdo->rollBack();if($created)$pdo->exec("DROP DATABASE `{$database}`");}
