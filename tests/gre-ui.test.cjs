const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const root=path.resolve(__dirname,'..');

function setup(manage=true){
 const html=fs.readFileSync(path.join(root,'views/logistica/gre.php'),'utf8').replace(/<\?php[\s\S]*?\?>/g,'');
 const dom=new JSDOM('<meta name="csrf-token" content="fixture-csrf">'+html,{url:'http://localhost/views/logistica/gre',runScripts:'outside-only'});
 const w=dom.window,$=jquery(w),requests=[];w.$=w.jQuery=$;w.confirm=()=>true;
 $('#gre-workspace').attr('data-can-manage',manage?'1':'0');
 // PHP renders these named inputs inside the view's field loop.
 $('#gre-fields').append('<input name="driver_name"><input name="driver_last_name">');
 $.ajax=function(options){const d=$.Deferred();requests.push({...options,deferred:d});return d.promise();};
 w.eval(fs.readFileSync(path.join(root,'ajax/gre.js'),'utf8'));
 const pending=file=>requests.filter(r=>r.url.endsWith('/'+file+'.php')&&r.deferred.state()==='pending').at(-1);
 function listed(enabled=true){pending('listar').deferred.resolve({success:true,gre_enabled:enabled,schema_ready:true,data:[{id:15,number:'T002-15',recipient_name:'CLIENTE',gre_status:null}]});}
 function selected(enabled=true,submission=null){
   $('#gre-guide').val('15').trigger('change');
   pending('estado').deferred.resolve({success:true,data:{gre_enabled:enabled,can_edit:manage&&!submission,guide:{number:'T002-15',gross_weight:'25.300',weight_unit:'KGM',transport_mode:'PRIVADO',transfer_reason_code:'VENTA',driver_name:'JUAN',driver_last_name:'PEREZ'},lines:[{item_code:'P1',description:'SIMULACION',unit_code:'NIU',quantity:2}],submission}});
 }
 function validate(){ $('#gre-preview').trigger('click');pending('previsualizar').deferred.resolve({success:true,data:{validation:{valid:true,errors:[]}}});}
 return{dom,w,$,requests,pending,listed,selected,validate};
}

test('GRE desactivada permite consulta y preflight, pero bloquea escrituras y envío',()=>{
 const t=setup();try{t.listed(false);t.selected(false);t.validate();assert.equal(t.$('#gre-submit').prop('disabled'),true);assert.equal(t.$('#gre-fields').prop('disabled'),true);t.$('#gre-submit').trigger('click');t.$('#gre-prepare-form').trigger('submit');assert.equal(t.requests.filter(r=>r.method==='POST').length,0);assert.match(t.$('#gre-message').text(),/desactivada/);}finally{t.dom.window.close();}
});

test('GRE muestra campos que fallan, conserva datos y no habilita enviar',()=>{
 const t=setup();try{t.listed();t.selected();t.$('#gre-preview').trigger('click');t.pending('previsualizar').deferred.reject({status:422,responseJSON:{message:'Completa la guía',errors:[{field:'shipment.gross_weight',message:'Falta peso'},{field:'shipment.driver_license',message:'Falta licencia'}]}});assert.match(t.$('#gre-validation').text(),/Falta peso/);assert.match(t.$('#gre-validation').text(),/Falta licencia/);assert.equal(t.$('[name=gross_weight]').val(),'25.300');assert.equal(t.$('#gre-submit').prop('disabled'),true);}finally{t.dom.window.close();}
});

test('GRE exige guardar cambios tras preflight y fallo de guardado conserva edición',()=>{
 const t=setup();try{t.listed();t.selected();t.validate();assert.equal(t.$('#gre-submit').prop('disabled'),false);t.$('[name=gross_weight]').val('35.2').trigger('input');assert.equal(t.$('#gre-submit').prop('disabled'),true);t.$('#gre-prepare-form').trigger('submit');t.$('#gre-prepare-form').trigger('submit');assert.equal(t.requests.filter(r=>r.url.endsWith('/preparar.php')).length,1);assert.equal(t.pending('preparar').headers['X-CSRF-Token'],'fixture-csrf');assert.match(t.pending('preparar').data,/gross_weight=35.2/);t.pending('preparar').deferred.reject({status:422,responseJSON:{message:'Dato inválido'}});assert.equal(t.$('[name=gross_weight]').val(),'35.2');assert.match(t.$('#gre-message').text(),/Dato inválido/);assert.equal(t.$('#gre-submit').prop('disabled'),true);}finally{t.dom.window.close();}
});

test('GRE bloquea doble envío y conserva un error de red sin ocultarlo al recargar',()=>{
 const t=setup();try{t.listed();t.selected();t.validate();t.$('#gre-submit').trigger('click');t.$('#gre-submit').trigger('click');const sends=t.requests.filter(r=>r.url.endsWith('/enviar.php'));assert.equal(sends.length,1);assert.equal(sends[0].headers['X-CSRF-Token'],'fixture-csrf');sends[0].deferred.reject({status:502,responseJSON:{message:'Resultado incierto. Consulta el ticket.'}});assert.match(t.$('#gre-message').text(),/Resultado incierto/);assert.equal(t.$('#gre-submit').prop('disabled'),true);assert.equal(t.pending('estado'),undefined);}finally{t.dom.window.close();}
});

test('GRE con ticket pendiente bloquea edición y retransmisión, habilita consultar sin falsear aceptación',()=>{
 const t=setup();try{t.listed();t.selected(true,{status:'processing',ticket:'ticket-fixture',cdr_available:0});t.validate();assert.equal(t.$('#gre-fields').prop('disabled'),true);assert.equal(t.$('#gre-submit').prop('disabled'),true);assert.equal(t.$('#gre-consult').prop('disabled'),false);assert.equal(t.$('#gre-cdr').attr('href'),undefined);assert.match(t.$('#gre-submission').text(),/Procesando/);assert.doesNotMatch(t.$('#gre-submission').text(),/Aceptada/);t.$('#gre-consult').trigger('click');assert.equal(t.pending('consultar').method,'POST');}finally{t.dom.window.close();}
});

test('GRE final muestra CDR y XML descargables y bloquea consultar o reenviar',()=>{
 const t=setup();try{t.listed();t.selected(true,{status:'observed',ticket:'ticket-fixture',sunat_code:'0',cdr_available:1,sunat_description:'Aceptada con observaciones'});assert.match(t.$('#gre-submission').text(),/Aceptada con observaciones/);assert.match(t.$('#gre-cdr').attr('href'),/guide_id=15&kind=cdr/);assert.match(t.$('#gre-xml').attr('href'),/kind=xml/);assert.equal(t.$('#gre-consult').prop('disabled'),true);assert.equal(t.$('#gre-submit').prop('disabled'),true);}finally{t.dom.window.close();}
});

test('GRE de consulta nunca crea POST y representa contenido como texto',()=>{
 const t=setup(false);try{t.listed();t.selected(true,{status:'processing',ticket:'<img src=x onerror=alert(1)>',sunat_description:'<script>malicious()</script>',cdr_available:0});t.$('#gre-consult').trigger('click');t.$('#gre-submit').trigger('click');t.$('#gre-prepare-form').trigger('submit');assert.equal(t.requests.filter(r=>r.method==='POST').length,0);assert.equal(t.$('#gre-ticket script,#gre-ticket img').length,0);assert.match(t.$('#gre-ticket').text(),/<script>/);}finally{t.dom.window.close();}
});
