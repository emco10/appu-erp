const {test}=require('node:test');const assert=require('node:assert/strict');const {setup}=require('./ui-harness.cjs');
test('Orden de compra muestra los campos faltantes y conserva el formulario al guardar con errores',async()=>{
 const t=await setup('views/ordenes/orden-de-compra.php',['ajax/orden-de-compra.js'],'',{beforeScripts(w){w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};w.Swal={fire:()=>Promise.resolve({}),close:()=>{},showLoading:()=>{}};}});try{
  const {$,window}=t;
  const form=$('#FRM_INSERT_DETA_ORDCOMPRA')[0];
  const submit=()=>form.dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
  const pendingSaves=()=>t.pending(r=>String(r.url).includes('insert-update-orden.php'));
  submit();
  submit();
  assert.equal(pendingSaves().length,1);
  const save=pendingSaves()[0];
  const key=save.data.get('request_key');
  assert.match(key,/^[A-Za-z0-9_-]{16,64}$/);
  assert.equal(save.data.get('response_format'),'json');
  save.deferred.reject({status:422,responseJSON:{message:'Corrige los campos indicados antes de guardar.',errors:[{path:'orden_fecemision',message:'Fecha de emisión es obligatoria.'},{path:'orden_lstprods',message:'Agrega al menos un artículo o servicio antes de guardar.'}]}});
  assert.match($('#FRM_INSERT_DETA_ORDCOMPRA .appu-validation-summary').text(),/Fecha de emisión es obligatoria/);assert.match($('#FRM_INSERT_DETA_ORDCOMPRA .appu-validation-summary').text(),/Agrega al menos un artículo/);assert.equal($('[name=orden_fecemision]').attr('aria-invalid'),'true');
  submit();
  assert.equal(pendingSaves()[0].data.get('request_key'),key);
 }finally{t.dom.window.close();}
});

test('Recepción conserva el intento y confirma el ID aunque falle la lectura posterior',async()=>{
 const t=await setup('views/compras/recepciones.php',['ajax/recepciones.js'],'',{
  beforeScripts(w){w.appuNewDocumentUrl=()=>w.location.href;}
 });
 try{
  const {$,window}=t;
  const pendingSaves=()=>t.pending(r=>r.method==='POST');
  const submit=()=>$('#goods-receipt-form').trigger('submit');
  t.pending(r=>r.data?.action==='eligible')[0].deferred.resolve({
   bases:[{base_type:'ORDER',id:8,number:'OC8',pending_quantity:5}]
  });
  $('#goods-receipt-base').val('ORDER:8').trigger('change');
  assert.equal($('#btn-save-goods-receipt').prop('disabled'),true);
  t.pending(r=>r.data?.base_id==='8')[0].deferred.resolve({
   base:{base_type:'ORDER',id:8,currency:'PEN',valuation_currency:'PEN',suggested_valuation_exchange_rate:1},
   warehouses:[{id:3,code:'QA',name:'Almacén'}],
   details:[{id:12,ordered_quantity:5,pending_quantity:5,received_quantity:0,item_unit_price:2}]
  });
  $('#goods-receipt-warehouse').val('3');
  $('.receipt-quantity').val('2').trigger('input');
  submit();
  submit();
  assert.equal(pendingSaves().length,1);
  const data=pendingSaves()[0].data;
  assert.ok(new URLSearchParams(data).get('request_key'));
  pendingSaves()[0].deferred.reject({status:0});
  assert.equal($('.receipt-quantity').val(),'2');
  submit();
  assert.equal(pendingSaves()[0].data,data);
  pendingSaves()[0].deferred.resolve({receipt_id:19,receipt_number:'EM19',message:'Guardada'});
  t.pending(r=>r.data?.action==='receipt')[0].deferred.reject({status:503});
  assert.equal($('#goods-receipt-id').val(),'19');
  assert.equal($('#goods-receipt-number').val(),'EM19');
  assert.equal($('#btn-save-goods-receipt').prop('disabled'),true);
  assert.equal(new URL(window.location.href).searchParams.get('record_id'),'19');
  submit();
  assert.equal(pendingSaves().length,0);
 }finally{t.dom.window.close();}
});

test('Factura de proveedor reabre la compra de servicio con sus referencias y espera el detalle',async()=>{
 const t=await setup('views/compras/compra-interna.php',['ajax/compra-interna.js'],'?record_id=47',{
  beforeScripts(w,$){
   w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};
   w.Swal={fire:()=>Promise.resolve({}),close(){},showLoading(){}};
   w.appuOpenRequestedRecord=(list,button)=>{$(list).val('47').trigger('change');$(button).trigger('click');};
   $('#FRM_INSERT_DETA_ORDCOMPRA').append($('<input>').attr('name','custom_field[12]'));
  }
 });
 try{
  const {$}=t;
  const resolve=(file,data)=>t.pending(r=>r.url.endsWith('/'+file))[0].deferred.resolve(data);
  resolve('listar-compras.php',[{id:47,text:'CP-47'}]);
  resolve('listar-ordenes-disponibles.php',[{id:'',text:'Sin orden'}]);
  assert.equal(t.pending(r=>r.url.endsWith('/consultar-compra.php')).length,0);
  resolve('listar-proveedores.php',[{id:1,text:'Proveedor'}]);
  await t.flush();
  resolve('consultar-compra.php',[{
   IDORDEN:47,TIPOORDEN:2,PROVID:1,IMPORT_RECEIPT_ID:31,TIPOMON:'PEN',
   ORDESTADO:'Pendiente',DOCUMENT_SUBTYPE:'RESERVE',SUPPLIER_REFERENCE:'FACT-QA',
   PAYDAYS:15,CUSTOM_FIELDS:{12:'Dato guardado'}
  }]);
  assert.equal($('#btn-save-orden').prop('disabled'),true);
  resolve('consultar-detalle-compra.php',[{ROW_ID:1,CODE:'SERV',DESCRIPTION:'Servicio',UNIT_PRICE:12,QUANTITY:1,DISCOUNT_RATE:0,DISCOUNTED_TOTAL:12}]);
  assert.equal($('[name=orden_id]').val(),'47');
  assert.equal($('[name=orden_naturaleza]').val(),'SERVICIO');
  assert.equal($('[name=orden_import_receipt_id]').val(),'31');
  assert.equal($('[name=orden_tipomoneda]').val(),'MN');
  assert.equal($('[name="custom_field[12]"]').val(),'Dato guardado');
  assert.equal(t.rows[0][2],'Servicio');
  assert.equal($('#btn-save-orden').prop('disabled'),false);
 }finally{t.dom.window.close();}
});
test('Aprobar/rechazar OC pendiente funciona aunque el estado se renderice como badge HTML',async()=>{
 const calls=[];const t=await setup('views/ordenes/resumen-ordenes.php',['ajax/resumen-ordenes.js'],'',{rowData:{0:7,5:'<span class="badge">Pendiente</span>',6:1},beforeScripts(w){w.procesarAprobacion=(type,id,action)=>calls.push({type,id,action});}});try{const context=t.contextMenus[0];assert.ok(context);context.callback.call(t.$('<tr>')[0],'approve',{});context.callback.call(t.$('<tr>')[0],'reject',{});const $actions=t.$('<div>').html(t.window.accionesOrden({IDORDEN:7,ORDESTADO:'Pendiente'}));assert.match($actions.text(),/Aprobar/);assert.match($actions.text(),/Rechazar/);t.$('body').append($actions);t.$('.js-order-approve').trigger('click');t.$('.js-order-reject').trigger('click');assert.deepEqual(calls,[{type:'order',id:7,action:'approve'},{type:'order',id:7,action:'reject'},{type:'order',id:7,action:'approve'},{type:'order',id:7,action:'reject'}]);}finally{t.dom.window.close();}
});
test('El campo Aprobado por no solicita nombre manual y explica el flujo',async()=>{
 const t=await setup('views/ordenes/orden-de-compra.php',['ajax/orden-de-compra.js'],'',{beforeScripts(w){w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};w.Swal={fire:()=>Promise.resolve({}),close:()=>{},showLoading:()=>{}};}});try{
  const approver=t.$('[name=orden_autorizador]');assert.equal(approver.prop('readonly'),true);assert.equal(approver.prop('required'),false);assert.match(t.$('.form-text').text(),/Resumen de Órdenes/);
 }finally{t.dom.window.close();}
});
test('Costo de entrega ignora recepción anterior y bloquea doble guardado sin perder datos al fallar',async()=>{
 const t=await setup('views/compras/costos-entrega.php',['ajax/costos-entrega.js']);try{const {$}=t;t.pending(r=>r.data?.action==='receipts')[0].deferred.resolve({receipts:[{id:1,number:'EM1',provider_name:'A'},{id:2,number:'EM2',provider_name:'B'}]});$('[name=goods_receipt_id]').val('1').trigger('change');$('[name=goods_receipt_id]').val('2').trigger('change');t.pending(r=>r.data?.receipt_id==='2')[0].deferred.resolve({receipt:{cost_currency:'PEN',valuation_currency:'PEN'},details:[{code:'SEGUNDO',name:'Correcto',quantity:2,base_value:20}]});t.pending(r=>r.data?.receipt_id==='1')[0].deferred.resolve({receipt:{cost_currency:'PEN',valuation_currency:'PEN'},details:[{code:'ANTERIOR',name:'Tardío',quantity:9,base_value:90}]});assert.match($('#landed-cost-preview').text(),/SEGUNDO/);assert.doesNotMatch($('#landed-cost-preview').text(),/ANTERIOR/);$('[name=total_cost]').val('15').trigger('input');$('#landed-cost-form').trigger('submit').trigger('submit');const writes=t.pending(r=>r.method==='POST');assert.equal(writes.length,1);writes[0].deferred.reject({responseJSON:{error:'Fallo controlado'}});assert.equal($('[name=total_cost]').val(),'15');assert.equal($('#landed-cost-form [type=submit]').prop('disabled'),false);assert.match(t.notifications.at(-1).text,/Fallo controlado/);}finally{t.dom.window.close();}
});
test('Solicitudes conserva solicitante, navegación e ignora apertura tardía; guarda una sola vez',async()=>{
 const t=await setup('views/compras/solicitudes.php',['ajax/solicitudes.js'],'',{beforeScripts(w,$){w.scrollTo=()=>{};$('body').prepend('<div class="appu-document-toolbar"><button class="appu-doc-search"></button><button class="appu-doc-first"></button><button class="appu-doc-prev"></button><button class="appu-doc-next"></button><button class="appu-doc-last"></button><button class="appu-doc-save"></button><button class="appu-doc-history"></button></div>');}});try{
  const {$}=t;const rows=[{id:1,number:'SC1',status:'Pendiente'},{id:2,number:'SC2',status:'Pendiente'}];t.pending(r=>!r.data&&r.method==='GET')[0].deferred.resolve({requests:rows});$('#purchase-request-table tbody tr').eq(0).find('button').first().trigger('click');$('#purchase-request-table tbody tr').eq(1).find('button').first().trigger('click');const detail=id=>({request:{id,number:'SC'+id,status:'Pendiente',requester_id:id,issue_date:'2026-10-29',required_date:'2026-10-29',currency:'PEN',details:[{product_id:id,item_code:'A'+id,item_name:'Artículo'+id,quantity:2,estimated_unit_price:10}]}});t.pending(r=>r.data?.id===2)[0].deferred.resolve(detail(2));t.pending(r=>r.data?.id===1)[0].deferred.resolve(detail(1));t.pending(r=>r.data?.employees===1)[0].deferred.resolve({employees:[{id:1,text:'Empleado1'},{id:2,text:'Empleado2'}]});assert.equal($('#purchase-request-form [name=id]').val(),'2');assert.equal($('#purchase-request-requester').val(),'2');$('.appu-doc-prev').trigger('click');t.pending(r=>r.data?.id===1)[0].deferred.resolve(detail(1));assert.equal($('#purchase-request-form [name=id]').val(),'1');$('#purchase-request-form').trigger('submit').trigger('submit');const writes=t.pending(r=>r.url.endsWith('/solicitudes.php')&&r.method==='POST');assert.equal(writes.length,1);writes[0].deferred.reject({responseJSON:{error:'Reintenta'}});assert.equal($('#purchase-request-form [name=id]').val(),'1');assert.equal($('#btn-save-purchase-request').prop('disabled'),false);
 }finally{t.dom.window.close();}
});
