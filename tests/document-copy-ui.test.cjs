const {test}=require('node:test');
const assert=require('node:assert/strict');
const {setup}=require('./ui-harness.cjs');

test('Cotización vigente ofrece Copiar a orden y muestra errores del servidor',async()=>{
 const t=await setup('views/cotizacion/resumen-cotizaciones.php',['ajax/resumen-cotizaciones.js'],'',{beforeScripts(w){w.Swal={close:()=>{},showLoading:()=>{}};w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};}});
 try{
  assert.match(t.window.accionesCotizacion({CODIGOID:12,ESTADO:1}),/js-copy-quotation-order/);
  assert.doesNotMatch(t.window.accionesCotizacion({CODIGOID:12,ESTADO:0}),/js-copy-quotation-order/);
  t.window.copiarCotizacionAOrden(12);
  const request=t.pending(r=>String(r.url).includes('modules/ventas/ordenes.php'))[0];
  assert.ok(request);
  assert.equal(request.data.quotation_id,12);
  request.deferred.reject({responseJSON:{message:'La cotización ya tiene una orden de venta.'}});
  assert.equal(t.notifications.at(-1).name,'error');
  assert.match(t.notifications.at(-1).text,/ya tiene una orden de venta/);
 }finally{t.dom.window.close();}
});

test('Orden de compra ofrece copiar a entrada y factura según permisos y estado',async()=>{
 const t=await setup('views/ordenes/resumen-ordenes.php',['ajax/resumen-ordenes.js']);try{
  const html=t.window.accionesOrden({IDORDEN:25,TIPOORDEN:1,ORDESTADO:'Aprobado',LINKED_PURCHASE_COUNT:0});
  assert.match(html,/recepciones\.php\?base_type=ORDER&base_id=25/);
  assert.match(html,/compra-interna\.php\?copy_from=order&source_id=25/);
  assert.doesNotMatch(t.window.accionesOrden({IDORDEN:25,TIPOORDEN:1,ORDESTADO:'Pendiente',LINKED_PURCHASE_COUNT:0}),/Copiar a/);
  assert.doesNotMatch(t.window.accionesOrden({IDORDEN:25,TIPOORDEN:1,ORDESTADO:'Atendido',LINKED_PURCHASE_COUNT:1}),/compra-interna\.php/);
  const restricted=await setup('views/ordenes/resumen-ordenes.php',['ajax/resumen-ordenes.js'],'',{can:permission=>permission==='purchases.view'});
  try{
   assert.doesNotMatch(restricted.window.accionesOrden({IDORDEN:25,TIPOORDEN:1,ORDESTADO:'Aprobado',LINKED_PURCHASE_COUNT:0}),/recepciones\.php|compra-interna\.php/);
  }finally{restricted.dom.window.close();}
 }finally{t.dom.window.close();}
});

test('La acción de copiar una orden preselecciona el origen de la factura',async()=>{
 const t=await setup('views/compras/compra-interna.php',['ajax/compra-interna.js'],'?copy_from=order&source_id=25',{beforeScripts(w){w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};}});try{
  const list=t.pending(r=>String(r.url).includes('listar-ordenes-disponibles.php'))[0];
  assert.ok(list);
  list.deferred.resolve([{id:'',text:'Sin orden de compra'},{id:25,text:'OC-25'}]);
  await t.flush();
  assert.equal(t.$('#purchase-order-source').val(),'25');
  const source=t.pending(r=>String(r.url).includes('consultar-orden.php'))[0];
  assert.ok(source);
  assert.equal(source.data.FILTER,'25');
  source.deferred.resolve(JSON.stringify([]));
 }finally{t.dom.window.close();}
});
