const {test}=require('node:test');
const assert=require('node:assert/strict');
const {setup}=require('./ui-harness.cjs');
const view='views/gestion/integraciones-peru.php';
test('La configuración se carga localmente; consultar API y evaluar solo responden a un botón',async()=>{
 const t=await setup(view,['ajax/integraciones-peru.js']);try{
  assert.equal(t.requests.length,1);assert.equal(t.requests[0].method,'GET');
  t.requests[0].deferred.resolve({settings:{},catalog:{service:{label:'Servicio',rate_bps:1200}},schema_ready:true});await t.flush();
  t.$('[name=gross_operation]').val('1180').trigger('input');assert.equal(t.requests.length,1);
  t.$('#peru-check').trigger('click');t.$('#peru-check').trigger('click');assert.equal(t.requests.length,2);
  t.requests[1].deferred.resolve({data:{identity:{enabled:false},gre:{configured:false}}});await t.flush();
  assert.match(t.$('#peru-readiness').text(),/configured/);
  t.$('#peru-tax-form').trigger('submit');assert.equal(t.requests.length,3);assert.match(t.requests[2].data,/action=evaluate/);
  t.requests[2].deferred.resolve({data:{status:'calculated',tax_cents:3540,net_to_supplier_cents:114460,reasons:['Base incluye IGV'],evaluation_signature:'a'.repeat(64)}});await t.flush();
  assert.equal(t.$('#peru-save-evaluation').prop('disabled'),false);assert.match(t.$('#peru-tax-result').text(),/35.40/);
  t.$('#peru-save-evaluation').trigger('click');t.$('#peru-save-evaluation').trigger('click');assert.equal(t.requests.length,4);
  assert.match(t.requests[3].data,/request_key=[a-f0-9]{40}/);
  assert.match(t.requests[3].data,/evaluation_signature=a{64}/);
 }finally{t.dom.window.close();}
});

test('Cambiar condiciones o configuración invalida cifras y el guardado exige guardar primero la configuración',async()=>{
 const t=await setup(view,['ajax/integraciones-peru.js']);try{
  t.requests[0].deferred.resolve({settings:{},catalog:{},schema_ready:true});await t.flush();
  t.$('#peru-tax-form').trigger('submit');
  t.requests.at(-1).deferred.resolve({data:{status:'calculated',tax_cents:3540,net_to_supplier_cents:114460,reasons:[],evaluation_signature:'a'.repeat(64)}});await t.flush();
  assert.match(t.$('#peru-tax-result').text(),/35.40/);
  t.$('[name=company_retention_agent]').val('0').trigger('change');
  assert.equal(t.$('#peru-save-evaluation').prop('disabled'),true);
  assert.doesNotMatch(t.$('#peru-tax-result').text(),/35.40/);
  const before=t.requests.length;t.$('#peru-tax-form').trigger('submit');
  assert.equal(t.requests.length,before);assert.match(t.$('#peru-message').text(),/Guarda los cambios/);
  t.$('#peru-settings-form').trigger('submit');t.requests.at(-1).deferred.resolve({message:'Configuración guardada.'});await t.flush();
  t.$('#peru-tax-form').trigger('submit');assert.equal(t.requests.length,before+2);
  t.$('[name=company_retention_agent]').val('1').trigger('change');
  t.requests.at(-1).deferred.resolve({data:{status:'excluded',tax_cents:0,net_to_supplier_cents:118000,reasons:[],evaluation_signature:'b'.repeat(64)}});await t.flush();
  assert.equal(t.$('#peru-save-evaluation').prop('disabled'),true);assert.doesNotMatch(t.$('#peru-tax-result').text(),/Estado: Excluido/);
 }finally{t.dom.window.close();}
});

test('Una edición durante el guardado de configuración no se marca guardada por una respuesta anterior',async()=>{
 const t=await setup(view,['ajax/integraciones-peru.js']);try{
  t.$('[name=company_retention_agent]').val('0').trigger('change');
  t.$('#peru-settings-form').trigger('submit');
  t.$('[name=company_retention_agent]').val('1').trigger('change');
  t.requests.at(-1).deferred.resolve({message:'Configuración guardada.'});await t.flush();
  const before=t.requests.length;t.$('#peru-tax-form').trigger('submit');
  assert.equal(t.requests.length,before);assert.match(t.$('#peru-message').text(),/Guarda los cambios/);
  t.requests[0].deferred.resolve({settings:{company_retention_agent:0},catalog:{},schema_ready:true});await t.flush();
  assert.equal(t.$('[name=company_retention_agent]').val(),'1');
 }finally{t.dom.window.close();}
});
test('Los fallos conservan importes y las respuestas tardías no habilitan guardar otra evaluación',async()=>{
 const t=await setup(view,['ajax/integraciones-peru.js']);try{
  t.$('[name=gross_operation]').val('1180').trigger('input');t.$('#peru-tax-form').trigger('submit');
  t.requests.at(-1).deferred.reject({responseJSON:{message:'Activa la asistencia en Gestión.'}});await t.flush();
  assert.equal(t.$('[name=gross_operation]').val(),'1180');assert.match(t.$('#peru-message').text(),/Gestión/);
  t.$('#peru-tax-form').trigger('submit');t.$('[name=gross_operation]').val('1200').trigger('input');
  t.requests.at(-1).deferred.resolve({data:{status:'calculated',tax_cents:3540,reasons:['<img src=x onerror=alert(1)>']}});await t.flush();
  assert.equal(t.$('#peru-save-evaluation').prop('disabled'),true);assert.equal(t.$('#peru-tax-result img').length,0);
 }finally{t.dom.window.close();}
});
