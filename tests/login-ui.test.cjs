const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
test('El acceso admite las contraseñas con símbolos permitidas al crear usuarios',()=>{
 const html=fs.readFileSync(path.join(__dirname,'../views/login.php'),'utf8').replace(/<\?php[\s\S]*?\?>/g,'');const dom=new JSDOM(html);try{
  const field=dom.window.document.querySelector('[name=txtPassword]');assert.ok(field);for(const password of ['Prueba#2026','Clave_!@#$%&*','Contraseña-segura']){field.value=password;assert.equal(field.checkValidity(),true);}
  field.value='';assert.equal(field.checkValidity(),false);
 }finally{dom.window.close();}
});
