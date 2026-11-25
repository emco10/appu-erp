const test=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');const {JSDOM}=require('jsdom');const jquery=require('jquery');const root=path.resolve(__dirname,'..');
async function setup(view,scripts,manage=true){
 const html=fs.readFileSync(path.join(root,'views',view+'.php'),'utf8').replace(/<\?php[\s\S]*?\?>/g,'');const dom=new JSDOM(html,{url:'http://localhost/views/'+view,runScripts:'outside-only',pretendToBeVisual:true});const w=dom.window,$=jquery(w),requests=[],notifications=[],dialogs=[],histories=[];
 w.$=w.jQuery=$;w.alert=message=>{throw new Error(message);};w.HTMLElement.prototype.scrollIntoView=function(){};w.appuCan=()=>manage;w.appuMarkSaved=()=>{};w.appuConfirmDiscard=callback=>callback();w.appuStatusBadge=value=>$('<span class="badge">').text(value).prop('outerHTML');w.mostrarHistorialEstados=(...args)=>histories.push(args);w.toastr=Object.fromEntries(['error','success','warning','info'].map(name=>[name,message=>notifications.push({name,message})]));w.leer_cookie=()=>'';w.eliminar_cookie=()=>{};
 $.Notification={notify:(name,_position,_title,message)=>notifications.push({name,message})};w.Swal={fire:options=>{dialogs.push(options);return Promise.resolve({value:'Motivo válido QA'});},close:()=>{},showLoading:()=>{}};
 $.fn.modal=function(){return this;};$.fn.select2=function(options){if(options?.data)this.each(function(){const select=$(this).empty();options.data.forEach(row=>select.append($('<option>').val(row.id).text(row.text)));});return this;};$.fn.autocomplete=function(){return this;};
 function request(options){const d=$.Deferred(),promise=d.promise();requests.push({...options,deferred:d});promise.readyState=1;promise.abort=()=>d.reject({status:0});if(options.beforeSend)options.beforeSend();if(options.success)d.done(options.success);if(options.error)d.fail(options.error);if(options.complete)d.always(options.complete);d.always(()=>{promise.readyState=4;});return promise;}
 $.ajax=request;$.post=(url,data,success)=>{if(typeof data==='function'){success=data;data=undefined;}return request({url,data,type:'POST',success});};$.getJSON=(url,data)=>request({url,data,type:'GET'});
 w.eval(fs.readFileSync(path.join(root,'plugins/datatables/jquery.dataTables.js'),'utf8'));if(view.startsWith('facturacion/registro-')||view==='ordenes/orden-de-servicio')w.eval(fs.readFileSync(path.join(root,'js/document-ui.js'),'utf8'));for(const script of scripts)w.eval(fs.readFileSync(path.join(root,script),'utf8'));
 const flush=()=>new Promise(resolve=>setTimeout(resolve,20));await flush();const pending=predicate=>requests.filter(r=>r.deferred.state()==='pending'&&predicate(r));pending(r=>String(r.url).includes('Spanish.json')).forEach(r=>r.deferred.resolve({}));await flush();
 return{dom,w,$,requests,notifications,dialogs,histories,flush,pending};
}
const types=[{name:'facturas',table:'#table-facturas',script:'resumen-facturas',type:'INVOICE',history:'invoice',workspace:'appuFacturasList'},{name:'boletas',table:'#table-boletas',script:'resumen-boletas',type:'RECEIPT',history:'receipt',workspace:'appuBoletasList'},{name:'notas-credito',table:'#table-facturas',script:'resumen-notas-credito',type:'CREDIT_NOTE',history:'credit_note',workspace:'appuNotasCreditoList'}];
function docs(){return [1,2,3,4].map(id=>({ID:id,SERIES_NUMBER:'F001-'+id,DATE:'29/10/2026',CUSTOMER:'Cliente '+id,TOTAL_NET:'118.00',STATUS_ID:id,STATUS:({1:'Vigente',2:'Anulado',3:'Pendiente de Pago',4:'Cancelado'})[id],SELLER_NAME:'Vendedor'}));}
for(const spec of types){
 test(spec.name+': acciones visibles, historial y cierres solo para estados vigentes',async()=>{const t=await setup('facturacion/'+spec.script,['js/sales-document-list.js','ajax/'+spec.script+'.js']);try{t.pending(r=>r.data?.TIPO_DOC===spec.type)[0].deferred.resolve(docs());const {$}=t;assert.equal($(spec.table+' a[href*="record_id="]').length,4);assert.equal($(spec.table+' [data-sales-history]').length,4);assert.equal($(spec.table+' [data-document-id=1]').length,3);assert.equal($(spec.table+' [data-document-id=2]').length,0);assert.equal($(spec.table+' [data-document-id=3]').length,3);assert.equal($(spec.table+' [data-document-id=4]').length,0);$(spec.table+' [data-sales-history=1]').trigger('click');assert.deepEqual(t.histories[0],[spec.history,1]);assert.equal($(spec.table).attr('aria-busy'),'false');}finally{t.dom.window.close();}});
 test(spec.name+': consulta no expone escrituras y fallar carga permite reintentar',async()=>{const t=await setup('facturacion/'+spec.script,['js/sales-document-list.js','ajax/'+spec.script+'.js'],false);try{t.pending(r=>r.data?.TIPO_DOC===spec.type)[0].deferred.resolve(docs());assert.equal(t.$('[data-sales-status]').length,0);t.w[spec.workspace].load(false);t.pending(r=>r.data?.TIPO_DOC===spec.type).at(-1).deferred.reject({status:500});assert.equal(t.$('.sales-list-loading').hasClass('d-none'),true);assert.equal(t.$('#btn-buscar').prop('disabled'),false);assert.equal(t.$('.sales-list-error').hasClass('d-none'),false);assert.equal(t.$(spec.table+' a[href*="record_id="]').length,4);}finally{t.dom.window.close();}});
}
test('Resúmenes: bloqueo de doble acción, motivo obligatorio y errores sin falso éxito',async()=>{const spec=types[0],t=await setup('facturacion/'+spec.script,['js/sales-document-list.js','ajax/'+spec.script+'.js']);try{t.pending(r=>r.data?.TIPO_DOC==='INVOICE')[0].deferred.resolve(docs());t.w.appuFacturasList.change(1,2);t.w.appuFacturasList.change(1,2);await t.flush();assert.equal(t.dialogs.length,1);assert.match(t.dialogs[0].inputValidator('a'),/motivo/);assert.equal(t.dialogs[0].inputValidator('Motivo correcto'),undefined);const actions=t.pending(r=>String(r.url).includes('cambiar-estado'));assert.equal(actions.length,1);assert.equal(actions[0].data.COMENTARIO,'Motivo válido QA');actions[0].deferred.reject({status:422,responseJSON:{message:'Hay entregas activas'}});assert.equal(t.notifications.at(-1).message,'Hay entregas activas');assert.equal(t.$('[data-document-id=1]').first().prop('disabled'),false);assert.equal(t.notifications.filter(x=>x.name==='success').length,0);}finally{t.dom.window.close();}});
test('Mapa de relaciones: resumen reciente abre el grafo interactivo desde una sola vista',async()=>{const t=await setup('ventas/flujo-comercial',['ajax/flujo-comercial.js']);try{const {$}=t;$('#commercial-flow').data('types',{invoice:true});const report=t.pending(r=>String(r.url).includes('modules/reportes/flujo-documental.php'))[0];assert.ok(report);report.deferred.resolve({summary:[{source_type:'sales_order',target_type:'invoice',relationship_type:'BILLING',flow_count:1}],flows:[{source_type:'invoice',source_id:1,source_number:'F001-1',target_type:'sales_order',target_id:2,target_number:'OV-2',relationship_type:'BILLING',quantity_applied:2,registration_date:'2026-11-01'}]});assert.match($('#flow-summary').text(),/Orden de venta → Factura/);assert.equal($('#flow-table tbody tr').length,1);$('#flow-table .js-open-flow').trigger('click');const graph=t.pending(r=>String(r.url).includes('modules/trazabilidad/flujo.php'))[0];assert.equal(graph.data.type,'invoice');assert.equal(Number(graph.data.id),1);graph.deferred.resolve({selected:'invoice:1',nodes:[{key:'invoice:1',type:'invoice',id:1,label:'Factura',number:'F001-1',date:'2026-11-01',customer:'Cliente QA',amount:100,currency:'PEN',status:'Vigente',cancelled:false,url:'../facturacion/registro-factura?record_id=1'}],edges:[],line_applications:[],truncated:false,missing_related_documents:0});assert.match($('#commercial-flow-title').text(),/Factura F001-1/);assert.equal($('.commercial-flow-node').length,1);}finally{t.dom.window.close();}});
test('Orden de servicio: artículo completa campos reales y rechaza fracciones sin truncar',async()=>{const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js']);try{const {$}=t;$('[name=orden_producto_select]').append('<option value="42">Servicio</option>').val('42').trigger('change');t.pending(r=>String(r.url).includes('consultar-productos')).at(-1).deferred.resolve(JSON.stringify([{CODPROD:'SV42',NOMBRE:'Instalación',DESCRIPTION:'Servicio de instalación',PRECIO:10,UNITVALUE:'UND',CANTIDAD:0}]));assert.equal($('[name=orden_nombre_serv]').val(),'Instalación');assert.equal($('[name=orden_glosa]').val(),'Servicio de instalación');assert.equal($('[name=orden_tipomoneda]').val(),'PEN');$('[name=orden_cantidad]').val('1.5').trigger('change');$('#btn-add-prodtporden').trigger('click');assert.equal($('#table-ord-servicios').DataTable().rows().count(),0);$('[name=orden_cantidad]').val('2').trigger('change');$('#btn-add-prodtporden').trigger('click');assert.equal($('#table-ord-servicios').DataTable().rows().data().toArray()[0][4],2);assert.equal($('[name=orden_totneto]').val(),'23.60');assert.equal($('#btn-add-prodtporden').attr('type'),'button');}finally{t.dom.window.close();}});
test('Orden de servicio: conserva líneas, valores y clave al reintentar el guardado',async()=>{
 const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js']);
 try{
   const {$,w}=t;
   $('[name=orden_nombre_serv]').val('Consultoría');
   $('[name=orden_glosa]').val('Servicio profesional');
   $('[name=orden_precunit]').val(10);
   $('[name=orden_cantidad]').val(2);
   $('#btn-add-prodtporden').prop('disabled',false).trigger('click');
   const form=$('#FRM_INSERT_DETA_ORDSERV')[0];
   const submit=()=>form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
   const writes=()=>t.requests.filter(r=>String(r.url).includes('insert-update-orden'));
   submit();
   submit();
   assert.equal(writes().length,1);
   const payload=writes()[0].data;
   assert.ok(payload.get('request_key'));
   assert.equal(payload.get('orden_tipo'),'SERVICIO');
   assert.equal(JSON.parse(payload.get('orden_lstservicios'))[0][4],2);
   writes()[0].deferred.reject({status:0});
   assert.equal($('#table-ord-servicios').DataTable().rows().count(),1);
   assert.equal($('#btn-save-orden').prop('disabled'),false);
   assert.equal(form.inert,false);
   submit();
   assert.equal(writes()[1].data.get('request_key'),payload.get('request_key'));
   assert.equal(writes()[1].data.get('orden_lstservicios'),payload.get('orden_lstservicios'));
 }finally{t.dom.window.close();}
});
test('Orden de servicio: recupera UDF sin heredarlos y conserva proveedor ante catálogo tardío',async()=>{
 const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js']);
 try{
   const {$}=t;
   $('#FRM_INSERT_DETA_ORDSERV').append('<input name="custom_field[99]">');
   $('[name=orden_listado]').append('<option value="1">OS1</option><option value="2">OS2</option>');
   const header=id=>({IDORDEN:id,ORDNUMBER:'OS'+id,ORDESTADO:'Pendiente',TIPOMON:'PEN',PROVID:42,FECEMIS:'2026-10-29',FECDEL:'2026-10-29',PAYDAYS:0,TOTCOMP:100,TOTIGV:18,TOTNET:118,EXCVENTA:0,EXCCOMP:0,CUSTOM_FIELDS:id===1?{99:'Proyectos'}:{}});
   const lines=[{ROW_ID:1,DESCRIPTION:'Servicio',GLOSS:'Glosa',UNIT_PRICE:100,QUANTITY:1,DISCOUNT_RATE:0,DISCOUNTED_TOTAL:100}];
   $('[name=orden_listado]').val('1').trigger('change');$('#btn-select-orden').trigger('click');
   t.pending(r=>String(r.url).includes('consultar-orden.php')).at(-1).deferred.resolve([header(1)]);
   assert.equal($('[name=orden_id]').val(),'');
   t.pending(r=>String(r.url).includes('consultar-detalle')).at(-1).deferred.resolve(lines);
   assert.equal($('[name="custom_field[99]"]').val(),'Proyectos');
   t.pending(r=>String(r.url).includes('listar-proveedores')).at(-1).deferred.resolve(JSON.stringify([{id:42,text:'Proveedor QA'}]));
   assert.equal($('[name=orden_proveedor]').val(),'42');
   $('[name=orden_listado]').val('2').trigger('change');$('#btn-select-orden').trigger('click');
   t.pending(r=>String(r.url).includes('consultar-orden.php')).at(-1).deferred.resolve([header(2)]);
   t.pending(r=>String(r.url).includes('consultar-detalle')).at(-1).deferred.reject({status:503});
   assert.equal($('[name=orden_id]').val(),'1');
   assert.equal($('[name="custom_field[99]"]').val(),'Proyectos');
   $('#btn-select-orden').trigger('click');
   t.pending(r=>String(r.url).includes('consultar-orden.php')).at(-1).deferred.resolve([{...header(2),TIPOMON:'ME'}]);
   t.pending(r=>String(r.url).includes('consultar-detalle')).at(-1).deferred.resolve(lines);
   assert.equal($('[name=orden_tipomoneda]').val(),'USD');
   assert.equal($('[name="custom_field[99]"]').val(),'');
 }finally{t.dom.window.close();}
});
test('Orden de servicio: usuario de consulta conserva el selector y no puede agregar ni guardar',async()=>{
 const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js'],false);try{assert.equal(t.$('#btn-save-orden').css('display'),'none');assert.equal(t.$('[name=orden_nombre_serv]').prop('disabled'),true);assert.equal(t.$('[name=orden_listado]').prop('disabled'),false);t.$('#FRM_INSERT_DETA_ORDSERV')[0].dispatchEvent(new t.w.Event('submit',{bubbles:true,cancelable:true}));assert.equal(t.requests.filter(r=>String(r.url).includes('insert-update-orden')).length,0);}finally{t.dom.window.close();}
});

test('Orden de servicio: la anulación conserva la orden confirmada en consulta',async()=>{
 const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js']);
 try{
   const {$,w}=t;
   w.appuNewDocumentUrl=()=>w.location.href;
   $('[name=orden_listado]').append(new w.Option('OS7','7')).val('7').trigger('change');
   $('#btn-select-orden').trigger('click');
   t.pending(r=>r.url.endsWith('/consultar-orden.php'))[0].deferred.resolve([{
     IDORDEN:7,ORDNUMBER:'OS7',ORDESTADO:'Aprobado',PROVID:3,TIPOMON:'PEN',PAYDAYS:0,TOTCOMP:100,TOTIGV:18,TOTNET:118
   }]);
   t.pending(r=>r.url.endsWith('/consultar-detalle-orden.php'))[0].deferred.resolve([
     {ROW_ID:1,DESCRIPTION:'Instalación',GLOSS:'Visita',UNIT_PRICE:100,QUANTITY:1,DISCOUNT_RATE:0,DISCOUNTED_TOTAL:100}
   ]);
   $('#btn-delete-orden').trigger('click').trigger('click');
   await t.flush();
   const writes=t.pending(r=>r.url.endsWith('/eliminar-orden.php'));
   assert.equal(writes.length,1);
   assert.equal(writes[0].data.ID_ORDEN,'7');
   assert.equal(writes[0].data.reason,'Motivo válido QA');
   writes[0].deferred.resolve('1');
   assert.equal($('[name=orden_id]').val(),'7');
   assert.equal($('[name=orden_estado]').val(),'Anulado');
   assert.equal($('#FRM_INSERT_DETA_ORDSERV').attr('data-document-mode'),'VIEW');
   assert.equal($('#btn-save-orden').prop('disabled'),true);
   assert.equal($('#btn-delete-orden').prop('disabled'),true);
   assert.equal($('#table-ord-servicios').DataTable().rows().count(),1);
   assert.equal(new URL(w.location.href).searchParams.get('record_id'),'7');
 }finally{t.dom.window.close();}
});

test('Orden de servicio: nombre/glosa con etiquetas se agregan y guardan literales sin ejecutar HTML',async()=>{
 const t=await setup('ordenes/orden-de-servicio',['ajax/orden-de-servicio.js']);try{
  const {$}=t,payload='<img src=x onerror="window.appuXss=true"><svg onload="window.appuXss=true"></svg> & servicio';
  $('[name=orden_nombre_serv],[name=orden_glosa]').val(payload);$('[name=orden_precunit]').val(10);$('[name=orden_cantidad]').val(2);$('[name=orden_porcdscto]').val(0).trigger('change');$('#btn-add-prodtporden').prop('disabled',false).trigger('click');
  const table=$('#table-ord-servicios').DataTable();assert.equal(table.rows().count(),1);assert.equal($('#table-ord-servicios tbody img,#table-ord-servicios tbody svg').length,0);assert.equal($(table.cell(0,1).node()).text(),payload);assert.equal(table.row(0).data()[1],payload);assert.equal($('[name=orden_totneto]').val(),'23.60');
  $('#FRM_INSERT_DETA_ORDSERV')[0].dispatchEvent(new t.w.Event('submit',{bubbles:true,cancelable:true}));const save=t.requests.filter(r=>String(r.url).includes('insert-update-orden')).at(-1);assert.ok(save);const rows=JSON.parse(save.data.get('orden_lstservicios'));assert.equal(rows[0][1],payload);assert.equal(rows[0][2],payload);assert.equal(rows[0][4],2);
 }finally{t.dom.window.close();}
});

test('Boleta: agregar producto y guardar conserva descripción literal y totales con DataTables real',async()=>{
 const t=await setup('facturacion/registro-boleta',['ajax/registro-boleta.js']);try{
  const {$}=t,payload='<img src=x onerror="window.appuXss=true"><svg onload="window.appuXss=true"></svg> & artículo';
  $('[name=facturacion_producto]').append($('<option>').val('42').text('Producto QA')).val('42');$('[name=facturacion_codeprod]').val('QA42');$('[name=facturacion_nameprod],[name=facturacion_proddesc]').val(payload);$('[name=facturacion_prodprecio]').val('10');$('[name=facturacion_prodcant]').val('2');$('#btn-add-prodtofactura').prop('disabled',false).trigger('click');
  const table=$('#table-productsfactura').DataTable();assert.equal(table.rows().count(),1);assert.equal($('#table-productsfactura tbody img,#table-productsfactura tbody svg').length,0);assert.equal($(table.cell(0,2).node()).text(),payload);assert.equal(table.row(0).data()[3],payload);assert.equal($('[name=facturacion_total]').val(),'23.60');
  const form=$('#FRM_INSERT_FACTURA');const submit=()=>form[0].dispatchEvent(new t.w.Event('submit',{bubbles:true,cancelable:true}));submit();submit();const requests=()=>t.requests.filter(r=>r.data instanceof t.w.FormData&&r.data.has('facturacion_prods'));assert.equal(requests().length,1);const save=requests()[0];const rows=JSON.parse(save.data.get('facturacion_prods'));assert.equal(rows[0][2],payload);assert.equal(rows[0][3],payload);assert.equal(rows[0][5],2);assert.equal(save.data.get('response_format'),'json');assert.match(save.data.get('request_key'),/^[A-Za-z0-9_-]{16,64}$/);
  save.deferred.reject({status:0});assert.equal(form.attr('aria-busy'),'false');assert.equal(table.rows().count(),1);submit();const retry=requests()[1];assert.equal(retry.data.get('request_key'),save.data.get('request_key'));assert.equal(retry.data.get('facturacion_prods'),save.data.get('facturacion_prods'));
 }finally{t.dom.window.close();}
});

test('Nota de crédito: el intento de consultar un comprobante bloquea nuevas altas desde esa ficha',async()=>{
 const t=await setup('facturacion/registro-nota-credito',['ajax/registro-nota-credito.js']);try{
  const {$}=t;const form=$('#FRM_INSERT_FACTURA');$('[name=facturas_listado]').append($('<option>').val('18').text('FC-QA')).val('18').trigger('change');$('#btn-select-factura').trigger('click');
  assert.equal(form.attr('data-document-mode'),'VIEW');form[0].dispatchEvent(new t.w.Event('submit',{bubbles:true,cancelable:true}));assert.equal(t.requests.filter(r=>r.data instanceof t.w.FormData&&r.data.has('facturacion_prods')).length,0);
 }finally{t.dom.window.close();}
});

test('Boleta: consumidor final limpia el cliente y los datos fiscales son condicionales',async()=>{
 const t=await setup('facturacion/registro-boleta',['ajax/registro-boleta.js']);try{
  const {$}=t;['facturacion_cliruc','facturacion_valcliente','facturacion_clidirecc'].forEach(name=>assert.equal($('[name='+name+']').prop('required'),false));
  $('[name=facturacion_cliente]').val('27');$('[name=facturacion_cliruc]').val('20600000001');$('[name=facturacion_valcliente]').val('CLIENTE QA');$('[name=facturacion_clidirecc]').val('LIMA');const writesBefore=t.requests.filter(r=>r.method==='POST'||r.type==='POST').length;
  $('#btn-anonymous-receipt-customer').trigger('click');
  ['facturacion_cliente','facturacion_cliruc','facturacion_valcliente','facturacion_clidirecc'].forEach(name=>assert.equal($('[name='+name+']').val(),''));
  assert.match(t.notifications.at(-1).message,/consumidor final no identificado/i);
  assert.equal(t.requests.filter(r=>r.method==='POST'||r.type==='POST').length,writesBefore);
 }finally{t.dom.window.close();}
});

for(const format of ['texto','JSON decodificado'])test('Catálogos de boleta y servicio aceptan respuesta '+format,async()=>{
 const encode=value=>format==='texto'?JSON.stringify(value):value;
 for(const spec of [{view:'facturacion/registro-boleta',script:'registro-boleta',product:'facturacion_producto',name:'facturacion_nameprod',description:'facturacion_proddesc'},{view:'ordenes/orden-de-servicio',script:'orden-de-servicio',product:'orden_producto_select',name:'orden_nombre_serv',description:'orden_glosa'}]){
  const t=await setup(spec.view,['ajax/'+spec.script+'.js']);try{
   const {$}=t;const catalog=t.pending(r=>String(r.url).includes('listar-productos-xprov.php'))[0];assert.ok(catalog);catalog.deferred.resolve(encode([{id:42,text:'Producto JSON'}]));
   $('[name='+spec.product+']').val('42').trigger('change');const request=t.pending(r=>String(r.url).includes('consultar-productos.php')).at(-1);assert.ok(request);request.deferred.resolve(encode([{CODPROD:'QA42',NOMBRE:'Producto JSON',DESCRIPTION:'Descripción JSON',PRECIO:10,UNITVALUE:'UND',CANTIDAD:10}]));
   assert.equal($('[name='+spec.name+']').val(),'Producto JSON');assert.equal($('[name='+spec.description+']').val(),'Descripción JSON');
  }finally{t.dom.window.close();}
 }
});
