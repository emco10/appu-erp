const { test }=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const net=require('node:net');
const {spawn}=require('node:child_process');
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const repo=path.resolve(__dirname,'..');
const endpoints=['compras/consultar-compra.php','compras/listar-compras.php','cotizaciones/consultar-cotizacion.php','cotizaciones/listar-cotizaciones.php','facturacion/consultar-boleta.php','facturacion/consultar-factura.php','facturacion/consultar-nota-credito.php','facturacion/listar-boletas.php','facturacion/listar-facturas.php','facturacion/listar-notas-credito.php','ordenes/consultar-orden.php','ordenes/listar-ordenes.php','productos/consultar-productos.php','productos/listar-productos-xprov.php'];
const phpString=value=>"'"+value.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+"'";

test('jQuery real interpreta los 14 endpoints PHP como JSON y el catálogo acepta JSON y texto heredado',async()=>{
 const temp=fs.mkdtempSync(path.join(os.tmpdir(),'appu-json-http-'));
 let process,logs='',port;
 try{
  fs.mkdirSync(path.join(temp,'global'));
  fs.writeFileSync(path.join(temp,'global/connection.php'),'<?php require '+phpString(path.join(repo,'tests/fixtures/security-input-connection.php'))+';');
  for(const file of endpoints)fs.mkdirSync(path.join(temp,'modules',path.dirname(file)),{recursive:true});
  fs.writeFileSync(path.join(temp,'router.php'),`<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){http_response_code(403);exit;}
$file=ltrim((string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
if($file==='__ready'){echo 'ready';exit;}
$allowed=json_decode(getenv('APPU_SECURITY_INPUT_ENDPOINTS'),true);
if(!str_starts_with($file,'modules/')||!in_array(substr($file,8),$allowed,true)){http_response_code(404);exit;}
$endpoint=substr($file,8);
if($endpoint==='productos/listar-productos-xprov.php')$_POST['_qa_product_row']='1';
chdir(getenv('APPU_SECURITY_INPUT_ROOT').'/modules/'.dirname($endpoint));
require getenv('APPU_SECURITY_INPUT_REPO').'/modules/'.$endpoint;
`);
  const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));port=listener.address().port;await new Promise(resolve=>listener.close(resolve));
  process=spawn(processEnvironmentPhp(),['-S','127.0.0.1:'+port,path.join(temp,'router.php')],{cwd:temp,windowsHide:true,env:{...global.process.env,APPU_SECURITY_INPUT_ROOT:temp,APPU_SECURITY_INPUT_REPO:repo,APPU_SECURITY_INPUT_ENDPOINTS:JSON.stringify(endpoints)}});
  process.stdout.on('data',data=>{logs=(logs+data).slice(-8192);});process.stderr.on('data',data=>{logs=(logs+data).slice(-8192);});
  let started=false;for(let attempt=0;attempt<100;attempt++){try{if((await fetch('http://127.0.0.1:'+port+'/__ready')).ok){started=true;break;}}catch(_){}await new Promise(resolve=>setTimeout(resolve,30));}
  assert.ok(started,'PHP fixture no inició: '+logs);
  const dom=new JSDOM('<select id="catalog"></select>',{url:'http://127.0.0.1:'+port+'/',runScripts:'outside-only'}),$=jquery(dom.window);
  try{
   for(const file of endpoints){
    const status=file.includes('/consultar-')||file.includes('xprov')?'ALL':'1';
    const result=await new Promise((resolve,reject)=>$.post('/modules/'+file,{FILTER:'ALL',ESTADO:status}).done((data,_status,xhr)=>resolve({data,xhr})).fail(xhr=>reject(new Error(file+' HTTP '+xhr.status+' '+xhr.responseText))));
    assert.equal(typeof result.data,'object',file+' no se decodificó automáticamente');
    assert.match(result.xhr.getResponseHeader('Content-Type'),/^application\/json/);
    assert.equal(result.xhr.getResponseHeader('X-Content-Type-Options'),'nosniff');
   }
  }finally{dom.window.close();}
  for(const responseType of ['json','text']){
   const html=fs.readFileSync(path.join(repo,'views/reportes/productos.php'),'utf8').replace(/<\?php[\s\S]*?\?>/g,'');
   const browser=new JSDOM(html,{url:'http://127.0.0.1:'+port+'/views/reportes/productos',runScripts:'outside-only'}),w=browser.window,$=jquery(w);
   try{
    w.$=w.jQuery=$;w.toastr={error(){},warning(){},info(){}};
    let resolveCatalog;const ready=new Promise(resolve=>{resolveCatalog=resolve;});
    $.fn.select2=function(options){for(const row of options.data||[])this.append($('<option>').val(row.id).text(row.text));resolveCatalog();return this;};
    $.ajaxPrefilter(function(options){if(String(options.url).includes('listar-productos-xprov.php'))options.dataTypes=[responseType];});
    w.eval(fs.readFileSync(path.join(repo,'ajax/productos.js'),'utf8'));
    let deadline;try{await Promise.race([ready,new Promise((_resolve,reject)=>{deadline=setTimeout(()=>reject(new Error('Catalog timeout '+responseType+' '+logs)),4000);})]);}finally{clearTimeout(deadline);}
    assert.match($('#inventory-product-select option[value="1"]').text(),/<img src=x onerror=alert\(1\)>/);
    assert.equal($('#inventory-product-select img').length,0);
   }finally{browser.window.close();}
  }
 }finally{
  if(process&&!process.killed){process.kill();await new Promise(resolve=>process.once('exit',resolve));}
  const safe=path.dirname(path.resolve(temp))===path.resolve(os.tmpdir())&&path.basename(temp).startsWith('appu-json-http-');
  assert.ok(safe,'Ruta temporal fuera del directorio de pruebas');
  fs.rmSync(temp,{recursive:true,force:true});
 }
});
function processEnvironmentPhp(){return process.env.PHP_BINARY||(process.platform==='win32'?'C:\\xampp\\php\\php.exe':'php');}
