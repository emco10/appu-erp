<?php
/** Actual endpoint HTTP responses with malicious inputs and a fake PDO boundary. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$repo=dirname(__DIR__);$root=sys_get_temp_dir().'/appu-input-security-'.bin2hex(random_bytes(8));mkdir($root.'/global',0700,true);
$endpoints=['compras/consultar-compra.php','compras/listar-compras.php','cotizaciones/consultar-cotizacion.php','cotizaciones/listar-cotizaciones.php','facturacion/consultar-boleta.php','facturacion/consultar-factura.php','facturacion/consultar-nota-credito.php','facturacion/listar-boletas.php','facturacion/listar-facturas.php','facturacion/listar-notas-credito.php','ordenes/consultar-orden.php','ordenes/listar-ordenes.php','productos/consultar-productos.php','productos/listar-productos-xprov.php','reportes/cargar-reporte-gastos-compras.php'];
foreach($endpoints as$file){$directory=$root.'/modules/'.dirname($file);if(!is_dir($directory))mkdir($directory,0700,true);}
file_put_contents($root.'/global/connection.php','<?php require '.var_export(__DIR__.'/fixtures/security-input-connection.php',true).';');
file_put_contents($root.'/global/reporting.php','<?php require_once '.var_export($repo.'/global/reporting.php',true).';');
file_put_contents($root.'/router.php', <<<'PHP'
<?php
$file=ltrim((string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
$allowed=json_decode(getenv('APPU_SECURITY_INPUT_ENDPOINTS'),true);
if(!in_array($file,$allowed,true)||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
chdir(getenv('APPU_SECURITY_INPUT_ROOT').'/modules/'.dirname($file));require getenv('APPU_SECURITY_INPUT_REPO').'/modules/'.$file;
PHP);
$socket=stream_socket_server('tcp://127.0.0.1:0',$code,$message);if($socket===false)throw new RuntimeException('No loopback port.');$address=stream_socket_get_name($socket,false);fclose($socket);
$process=null;$checks=0;
$assert=static function(bool $value,string $message)use(&$checks):void{$checks++;if(!$value)throw new RuntimeException($message);};
try{
 $env=array_merge(getenv(),['APPU_SECURITY_INPUT_ROOT'=>$root,'APPU_SECURITY_INPUT_REPO'=>$repo,'APPU_SECURITY_INPUT_ENDPOINTS'=>json_encode($endpoints)]);
 $process=proc_open([PHP_BINARY,'-S',$address,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,$env,['bypass_shell'=>true]);
 if(!is_resource($process))throw new RuntimeException('Cannot start isolated HTTP fixture.');fclose($pipes[0]);
 $started=false;for($i=0;$i<100;$i++){if($handle=@stream_socket_client('tcp://'.$address,$code,$message,0.1)){fclose($handle);$started=true;break;}usleep(50000);}$assert($started,'Local fixture failed.');
 $request=static function(string $file,array $data)use($address,$root):array{
  file_put_contents($root.'/queries.jsonl','');$http_response_header=[];
  $body=file_get_contents('http://'.$address.'/'.$file,false,stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>http_build_query($data),'ignore_errors'=>true,'timeout'=>5]]));
  if($body===false)throw new RuntimeException('Local HTTP failed.');$headers=[];foreach($http_response_header as$line)if(str_contains($line,':')){[$name,$value]=explode(':',$line,2);$headers[strtolower(trim($name))]=trim($value);}
  preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);$trace=array_map(static fn(string $line):array=>json_decode($line,true,512,JSON_THROW_ON_ERROR),file($root.'/queries.jsonl',FILE_IGNORE_NEW_LINES));
  return[(int)($m[1]??0),$headers,json_decode($body,true,512,JSON_THROW_ON_ERROR),$trace];
 };
 $attack="1 OR 1=1; SELECT SLEEP(5)--";
 foreach(array_slice($endpoints,0,14)as$file){
  $consult=str_starts_with(basename($file),'consultar-');$base=['FILTER'=>'ALL','ESTADO'=>$consult||str_contains($file,'xprov')?'ALL':'1'];
  [$status,$headers,,$trace]=$request($file,$base);
  $assert($status===200&&str_starts_with($headers['content-type']??'','application/json'),$file.': success must have a JSON media type.');
  $assert(($headers['x-content-type-options']??'')==='nosniff',$file.': JSON must not be sniffed as HTML.');
  foreach([$attack,[$attack]]as$invalid){[$status,$headers,,$trace]=$request($file,array_replace($base,['ESTADO'=>$invalid]));$assert($status===422&&str_starts_with($headers['content-type']??'','application/json'),$file.': SQL/array status rejected as a typed JSON response.');foreach($trace as$q)$assert(!str_contains($q['sql'],$attack),$file.': attack cannot enter SQL syntax.');}
  if($consult){[$status,,$body,$trace]=$request($file,array_replace($base,['FILTER'=>$attack,'TIPO_ORDEN'=>$attack]));$assert($status===200&&$body===[],$file.': raw ID payload cannot expose rows.');$bound=false;foreach($trace as$q){$assert(!str_contains($q['sql'],$attack),$file.': ID/type payload cannot enter SQL syntax.');$bound=$bound||in_array($attack,$q['params'],true);}$assert($bound,$file.': raw ID must remain a prepared parameter.');}
 }
 foreach(['productos/consultar-productos.php','productos/listar-productos-xprov.php']as$file){
  [$status,$headers,$body]=$request($file,['FILTER'=>'ALL','ESTADO'=>'ALL','_qa_product_row'=>'1']);
  $rows=$body['data']??$body;$encoded=json_encode($rows,JSON_UNESCAPED_UNICODE);
  $assert($status===200&&str_contains($encoded,'<img src=x onerror=alert(1)>')&&str_starts_with($headers['content-type']??'','application/json')&&($headers['x-content-type-options']??'')==='nosniff',$file.': stored HTML characters remain JSON data with the correct HTTP boundary.');
 }
 $xss='</script><img src=x onerror=alert(1)>';
 [$status,$headers,$body,$trace]=$request('reportes/cargar-reporte-gastos-compras.php',['YEAR'=>'2026','party'=>$xss]);
 $assert($status===200&&$body['filters']['party']===$xss&&str_starts_with($headers['content-type']??'','application/json')&&($headers['x-content-type-options']??'')==='nosniff','Reported echoed-request sink returns attacker text as JSON data with nosniff.');
 $assert(count($trace)===1&&!str_contains($trace[0]['sql'],$xss)&&in_array('%'.$xss.'%',$trace[0]['params'],true),'Reported echo input is also bound to the report SQL, never interpolated.');
 echo 'PASS '.$checks." PHP SQL-parameter and JSON HTTP boundary assertions (15 real endpoints, fake PDO, no database/external requests)\n";
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 foreach(['global/connection.php','global/reporting.php','router.php','queries.jsonl','server.log']as$file)@unlink($root.'/'.$file);
 foreach(array_unique(array_map('dirname',$endpoints))as$directory)@rmdir($root.'/modules/'.$directory);
 foreach(['modules','global','']as$directory)@rmdir($root.'/'.$directory);
}
