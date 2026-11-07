const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const jquery=require('jquery');
const root=path.resolve(__dirname,'..');
function browser(html=''){
 const dom=new JSDOM('<!doctype html><html><body>'+html+'</body></html>',{url:'https://erp.test/',runScripts:'outside-only',pretendToBeVisual:true});
 const w=dom.window,$=jquery(w);w.$=w.jQuery=$;
 return {dom,w,$,load(file){w.eval(fs.readFileSync(path.join(root,file),'utf8'));}};
}
test('Editar un contacto o dirección mantiene comillas literales sin crear atributos ejecutables',()=>{
 const t=browser('<div class="appu-partner-details" data-endpoint="/partners"><div class="appu-partner-empty"></div><div class="appu-partner-content"><table><tbody class="appu-address-rows"></tbody></table><table><tbody class="appu-contact-rows"></tbody></table></div></div>');
 try{
  const pending=[],payload='Nombre " autofocus onfocus="window.__xss=1 & \' prueba';let dialog;
  t.$.getJSON=()=>{const d=t.$.Deferred();pending.push(d);return d.promise();};
  t.$.post=()=>{throw new Error('Opening or editing a suggestion must not save it.');};
  t.w.toastr={error(){},success(){}};
  t.w.Swal={fire(options){dialog=options;t.$('#dialog').remove();t.$('<div id="dialog">').html(options.html).appendTo('body');if(options.onOpen)options.onOpen();return {then(){}};},showValidationMessage(){}};
  t.load('ajax/business-partner-details.js');t.w.appuPartnerDetailsLoad(7);
  pending[0].resolve({data:{addresses:[{id:1,address_code:'A',address_name:payload,address_line:payload,active_status:1}],contacts:[{id:2,contact_name:payload,position_name:payload,active_status:1}]}});
  t.$('.appu-address-edit').trigger('click');
  assert.equal(t.$('#bp-a-name').val(),payload);assert.equal(t.$('#bp-a-line').val(),payload);
  assert.equal(t.$('#dialog [onfocus],#dialog [autofocus]').length,0);assert.equal(dialog.preConfirm().address_line,payload);
  t.$('.appu-contact-edit').trigger('click');
  assert.equal(t.$('#bp-c-name').val(),payload);assert.equal(t.$('#dialog [onfocus],#dialog [autofocus]').length,0);
  assert.equal(dialog.preConfirm().contact_name,payload);assert.equal(t.w.__xss,undefined);
 }finally{t.dom.window.close();}
});
test('NotifyJS real muestra mensajes como texto y conserva iconos y botones propios',async()=>{
 const t=browser();
 try{
  t.load('plugins/notifyjs/dist/notify.min.js');t.load('plugins/notifications/notify-metro.js');t.load('js/notifications.js');
  await new Promise(r=>setTimeout(r,15));
  const payload='<img src=x onerror="window.__xss=1"><svg onload="window.__xss=2">';
  t.$.Notification.notify('error','bottom-right',payload,payload);
  t.$.Notification.autoHideNotify('info','bottom-right',payload,payload);
  t.$.Notification.confirm('warning','bottom-right',payload);
  const $notices=t.$('.notifyjs-metro-base');assert.equal($notices.length,3);
  assert.equal($notices.find('img,svg,[onerror],[onload]').length,0);
  assert.equal($notices.find('.title').first().text(),payload);
  assert.equal($notices.filter('.notifyjs-metro-error').find('.text').text(),payload);
  assert.ok($notices.find('.image i').length>=3);assert.equal($notices.find('.yes,.no').length,2);
  assert.equal(t.w.__xss,undefined);
 }finally{t.dom.window.close();}
});
