const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
async function setup(url){
 const dom=new JSDOM('<nav class="main-header"><div class="dropdown"><button class="dropdown-toggle">Navegar</button><div class="dropdown-menu"><a href="/views/compras/recepciones">Entradas</a></div></div></nav><aside class="main-sidebar"><div class="sidebar"><ul class="nav-sidebar"><li class="has-treeview menu-close"><a id="purchases" href="/views/compras/resumen-compras">Compras<i class="right"></i></a><ul class="nav-treeview"><li><a href="/views/compras/recepciones">Entradas</a></li></ul></li></ul></div></aside><div class="content-header"><h1>Entrada de mercancías</h1></div><form><input id="goods-receipt-id" name="goods_receipt_id" value="12"><select name="goods_receipt_list"><option value="12">EM-12</option><option value="13">EM-13</option></select><button type="button" id="btn-select-goods-receipt">Seleccionar</button><button type="button" id="btn-save-goods-receipt">Guardar</button><button type="button" id="btn-anular-goods-receipt">Anular</button></form>',{url,runScripts:'outside-only',pretendToBeVisual:true});
 const w=dom.window,$=jquery(w);w.$=w.jQuery=$;w.APPU_AUTH={role:'admin'};w.toastr={info(){},error(){},success(){},warning(){}};$.fx.off=true;
 $.fn.select2=function(){return this;};$.fn.select2.defaults={set(){}};$.fn.DataTable=function(){return {};};w.eval(fs.readFileSync(path.join(__dirname,'../js/footer-functions.js'),'utf8'));
 await new Promise(resolve=>setTimeout(resolve,30));return{dom,w,$};
}
test('Rutas limpias de Apache detectan cada tipo y conservan relaciones correctas',async()=>{
 const t=await setup('http://localhost/APPU%20ERP/views/compras/recepciones?receipt_id=12');try{
  assert.match(t.w.appuCurrentRoute(),/views\/compras\/recepciones/);
  for(const [route,type] of [['facturacion/registro-boleta','receipt'],['facturacion/registro-nota-credito','credit_note'],['facturacion/registro-factura','invoice'],['ordenes/orden-de-compra','order'],['compras/recepciones','goods_receipt'],['cotizacion/registro-cotizacion','quotation']])assert.equal(t.w.appuDocumentTypeForRoute(route),type);
  assert.match(t.$('.appu-doc-relations').attr('href'),/trazabilidad\/flujo\?type=goods_receipt&id=12$/);
  let saves=0;t.$('#btn-save-goods-receipt').on('click',()=>saves++);t.$('.appu-doc-save').trigger('click');assert.equal(saves,1);
 }finally{t.dom.window.close();}
});
test('Nuevo descarta solo contexto de registro y conserva la ruta del módulo',async()=>{
 const t=await setup('http://localhost/APPU%20ERP/index.php?url=views/compras/recepciones&receipt_id=12&record_id=4&base_id=2&base_type=ORDER#old');try{
  const url=new URL(t.w.appuNewDocumentUrl());assert.equal(url.searchParams.get('url'),'views/compras/recepciones');assert.equal(url.searchParams.get('mode'),'create');assert.equal(url.searchParams.has('receipt_id'),false);assert.equal(url.searchParams.has('record_id'),false);assert.equal(url.searchParams.has('base_id'),false);assert.equal(url.hash,'');
 }finally{t.dom.window.close();}
});
test('Navegar y módulos laterales se despliegan por clic sin navegar a # ni a Inicio',async()=>{
 const t=await setup('http://localhost/views/compras/recepciones');try{
  const {$}=t;const initial=t.w.location.href;const toggle=$('.main-header .dropdown-toggle');toggle.trigger('click');assert.equal(toggle.attr('aria-expanded'),'true');assert.equal($('.dropdown-menu').hasClass('show'),true);toggle.trigger('click');assert.equal(toggle.attr('aria-expanded'),'false');
  const click=$.Event('click');$('#purchases').trigger(click);assert.equal(click.isDefaultPrevented(),true);assert.equal($('#purchases').attr('aria-expanded'),'true');assert.equal($('#purchases').parent().hasClass('menu-open'),true);$('#purchases').trigger('click');assert.equal($('#purchases').attr('aria-expanded'),'false');assert.equal(t.w.location.href,initial);
 }finally{t.dom.window.close();}
});
