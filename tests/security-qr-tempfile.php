<?php
/** Real QR/FPDF integration in an isolated temporary directory, without SQL or SUNAT. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')==='--worker'){
 require __DIR__.'/../global/electronic-document-pdf.php';
 $company=['tax_id'=>'20123456789','legal_name'=>'SIMULACION QA','address'=>'QA'];
 $doc=['series'=>'F001','number'=>'1','date'=>'2026-11-07','name'=>'QA','ruc'=>'12345678','address'=>'QA','payment_label'=>'Contado','currency_label'=>'SOLES','currency_symbol'=>'S/','total_net'=>118.0,'total_tax'=>18.0];
 $sunat=['status'=>'accepted','digest_value'=>str_repeat('LOCAL-TEST-DIGEST',50),'sunat_code'=>'0','sunat_description'=>'LOCAL FIXTURE'];
 $images=new ReflectionProperty(FPDF::class,'images');$paths=[];
 foreach(['FACTURA','BOLETA DE VENTA','NOTA DE CREDITO']as$label){
  $pdf=new AppuElectronicDocumentPdf($company,$doc,$label,'');$pdf->render([],['IGV'=>18.0],$sunat);
  $bytes=$pdf->Output('S');if(!str_starts_with($bytes,'%PDF-')||!str_contains($bytes,'/Subtype /Image'))throw new RuntimeException('QR not embedded in real PDF.');
  $keys=array_keys($images->getValue($pdf));if(count($keys)!==1)throw new RuntimeException('Expected one QR image.');$paths[]=$keys[0];
 }
 // Force a failure inside qrPayload, after tempnam has reserved the file.
 $pdf=new AppuElectronicDocumentPdf($company,$doc,'FACTURA','');$pdf->AddPage();
 $company['tax_id']=new class{public function __toString():string{throw new RuntimeException('Intentional QR failure');}};
 (new ReflectionProperty(AppuElectronicDocumentPdf::class,'company'))->setValue($pdf,$company);
 try{(new ReflectionMethod(AppuElectronicDocumentPdf::class,'drawQr'))->invoke($pdf,$sunat);throw new LogicException('Expected failure.');}catch(RuntimeException $error){if($error->getMessage()!=='Intentional QR failure')throw $error;}
 echo json_encode(['paths'=>$paths,'failure_cleanup'=>true],JSON_THROW_ON_ERROR);exit;
}
$root=sys_get_temp_dir().'/appu-qr-security-'.bin2hex(random_bytes(8));mkdir($root,0700);
$sentinel=$root.'/do-not-touch.txt';file_put_contents($sentinel,'OUTSIDE QR CONTENT');$observed=[];$checks=0;$process=null;
$assert=static function(bool $value,string $message)use(&$checks):void{$checks++;if(!$value)throw new RuntimeException($message);};
try{
 $command=[PHP_BINARY,'-d','sys_temp_dir="'.str_replace('\\','/',$root).'"'];
 if(PHP_OS_FAMILY==='Windows'&&!extension_loaded('gd')&&is_file(ini_get('extension_dir').'/php_gd.dll'))array_push($command,'-d','extension=gd');
 array_push($command,__FILE__,'--worker');
 $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),null,['bypass_shell'=>true]);
 if(!is_resource($process))throw new RuntimeException('Cannot start QR test process.');fclose($pipes[0]);
 do{
  foreach(glob($root.'/*')?:[]as$file){if($file===$sentinel)continue;clearstatcache(true,$file);$stat=@stat($file);if($stat!==false)$observed[strtolower(basename($file))]=['mode'=>$stat['mode']&0777,'size'=>$stat['size']];}
  $state=proc_get_status($process);if($state['running'])usleep(1000);
 }while($state['running']);
 $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);$process=null;
 $assert(($exit===0||$state['exitcode']===0)&&$stderr==='','Real QR/FPDF worker failed: '.$stderr);
 $data=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);$assert(count($data['paths'])===3&&$data['failure_cleanup']===true,'Invoice/receipt/credit-note and exceptional cleanup must all run.');
 foreach($data['paths']as$file){
  $assert(dirname($file)===realpath($root),'QR cannot use a supplied path outside its private fixture.');
  $assert(!str_ends_with($file,'.png')&&!file_exists($file),'The allocated temporary file itself is consumed, then deleted.');
  $observedPath=strtolower(basename($file));
  $assert(isset($observed[$observedPath]),'The reserved QR file must exist while QR encoding is running.');
  if(PHP_OS_FAMILY!=='Windows')$assert($observed[$observedPath]['mode']===0600,'QR must retain tempnam0600 permissions until cleanup.');
 }
 $assert(file_get_contents($sentinel)==='OUTSIDE QR CONTENT','QR cleanup cannot touch unrelated files.');
 $assert(count(glob($root.'/*')?:[])===1,'Successful and exceptional QR paths cannot leave temporary files.');
 echo 'PASS '.$checks." QR reserved-file, explicit PNG embedding and cleanup invariants (real FPDF/GD; no database/network)\n";
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 foreach(glob($root.'/*')?:[]as$file)if(is_file($file))unlink($file);rmdir($root);
}
