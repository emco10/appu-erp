const {test}=require('node:test');
const assert=require('node:assert/strict');
const {setup}=require('./ui-harness.cjs');

test('Gestión organiza secciones y perfiles con permisos agrupados y selección por módulo',async()=>{
 const t=await setup('views/gestion/index.php',['ajax/index.js']);try{
  const config=t.pending(r=>String(r.url).includes('configuracion.php'))[0];
  assert.ok(config);
  config.deferred.resolve({
   company:{},lists:[],fields:[],financial_accounts:[],price_lists:[],connected_users:[],
   roles:[{id:3,code:'sales',name:'Ventas',description:'Equipo comercial',permission_ids:[1]}],
   permissions:[{id:1,code:'sales.view',name:'Consultar ventas'},{id:2,code:'sales.manage',name:'Gestionar ventas'},{id:3,code:'finance.view',name:'Consultar finanzas'}]
  });
  await t.flush();
  assert.equal(t.$('.appu-management-nav a').length,8);
  assert.ok(t.$('#management-profiles').length);
  assert.equal(t.$('#roles-list').closest('.col-lg-7').length,0);
  assert.match(t.$('.appu-profile-help').text(),/permisos por módulo/i);
  assert.equal(t.$('.appu-permission-group').length,2);
  const $sales=t.$('.appu-permission-group').filter(function(){return t.$(this).find('legend span').text()==='Ventas';});
  assert.equal($sales.find('.js-role-permission:checked').length,1);
  $sales.find('.js-role-category-all').prop('checked',true).trigger('change');
  assert.equal($sales.find('.js-role-permission:checked').length,2);
  assert.match(t.$('.appu-role-card__header .badge').text(),/2 permisos/);
 }finally{t.dom.window.close();}
});
