<?php
/** Deliberately fake database boundary; no database connection is ever created. */
if(PHP_SAPI!=='cli-server'||!getenv('APPU_SECURITY_INPUT_ROOT')){http_response_code(404);exit;}
ini_set('display_errors','0');
require_once getenv('APPU_SECURITY_INPUT_REPO').'/global/security.php';app_send_security_headers();
function app_require_permission(string $permission):void{}
final class SecurityInputStatement extends PDOStatement
{
 private array $bound=[];private array $rows=[];private int $position=0;
 public function __construct(private string $sql){
  if(str_starts_with($sql,'SHOW COLUMNS'))$this->rows=[['Field'=>'rounding_adjustment'],['Field'=>'inventory_origin']];
  elseif(($_POST['_qa_product_row']??'')==='1'&&str_contains($sql,'FROM tbl_product')){
   $xss='<img src=x onerror=alert(1)>';
   $this->rows=[['ID'=>1,'NAME'=>$xss,'CODE'=>'QA-1','IDPROD'=>1,'DESCPROD'=>$xss,'NOMPROD'=>$xss,'MARCA'=>$xss,'CANTIDAD'=>0,'PRECIO'=>1,'VALORMEDIDA'=>'NIU','FECREG'=>'2026-11-07','ESTADO'=>1]];
  }
 }
 public function bindParam(string|int $param,mixed &$var,int $type=PDO::PARAM_STR,int $maxLength=0,mixed $driverOptions=null):bool{$this->bound[$param]=&$var;return true;}
 public function execute(?array $params=null):bool{file_put_contents(getenv('APPU_SECURITY_INPUT_ROOT').'/queries.jsonl',json_encode(['sql'=>$this->sql,'params'=>$params??$this->bound],JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX);return true;}
 public function rowCount():int{return count($this->rows);}
 public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->rows[$this->position++]??false;}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $mode===PDO::FETCH_COLUMN?array_column($this->rows,'Field'):$this->rows;}
 public function getIterator():Iterator{return new ArrayIterator($this->rows);}
}
final class SecurityInputPDO extends PDO
{
 public function __construct(){}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return new SecurityInputStatement($query);}
 public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{$statement=$this->prepare($query);$statement->execute();return $statement;}
}
$pdo=new SecurityInputPDO();
