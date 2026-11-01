$('#m_usuarios').addClass('active');
$(document).prop('title','Usuarios - APPU');
(function(){
  var $userForm=$('#FRM_INSERT_USUARIO');
  $('<div class="alert alert-info appu-user-profile-notice" role="note"><strong>Administración de accesos</strong><br>Asocia cada cuenta a un empleado y a un perfil. El perfil determina qué módulos y acciones podrá utilizar.</div>').insertAfter('.content-header');
  var $customFields=$userForm.find('.card-outline').first();
  var $accountFields=$userForm.find('[name="usuario_active_status"]').closest('.row');
  if($customFields.length&&$accountFields.length)$customFields.insertAfter($accountFields);
  $userForm.find('[name="usuario_rol_id"]').after('<small class="form-text text-muted">Los permisos del perfil se administran en Gestión → Perfiles de usuario.</small>');
  function catalog(url,data,$field){return $.ajax({url:url,type:data?'POST':'GET',data:data,dataType:'json'}).then(function(rows){var selected=$field.val();$field.empty().append($('<option>').val('').text('Seleccione'));(rows||[]).forEach(function(row){$field.append($('<option>').val(row.id).text(row.text));});$field.val(selected).trigger('change.select2');});}
  var $employees=$('[name="usuario_empleado_id"]'),$roles=$('[name="usuario_rol_id"]');
  catalog('../../modules/empleados/consultar-empleado.php',{FILTER:'SELECT_LIST'},$employees);
  catalog('../../modules/usuarios/listar-roles.php',null,$roles);
  window.appuUsuarioWorkspace=appuLegacyMasterWorkspace({
    form:'#FRM_INSERT_USUARIO',table:'#table-usuarios',save:'#btn-save-user',deactivate:'#btn-delete-user',label:'usuario',prefix:'USR',permission:'users.manage',
    endpoint:'../../modules/usuarios/consultar-usuario.php',deleteEndpoint:'../../modules/usuarios/eliminar-usuario.php',deleteId:'user_id',
    idField:'usuario_id',codeField:'usuario_codigo',firstField:'usuario_nombre',uniqueField:'usuario_nombre',uniqueColumn:'USERNAME',codeColumn:'CODIGO_USR',
    columns:['CODIGO_USR','USERNAME','CODIGO_EMP','NOMBRE_EMP','CARGO_EMP','ROL','FEC_REG','ULTIMA_ACTIVIDAD','ESTADO'],searchColumns:[{key:'CODIGO_USR',label:'Código'},{key:'USERNAME',label:'Usuario'},{key:'NOMBRE_EMP',label:'Empleado'},{key:'ROL',label:'Perfil'},{key:'ESTADO',label:'Estado'}],
    fields:{usuario_nombre:'USERNAME',usuario_empleado_id:'CODIGO_EMP',usuario_rol_id:'ROLE_ID',usuario_fecreg:'FEC_REG',usuario_active_status:'ACTIVE_STATUS'},
    reset:function(){$('[name="usuario_pass"],[name="usuario_pass_conf"]').val('');$('[name="usuario_active_status"]').val('1');},
    render:function(mode){var existing=mode==='VIEW'||mode==='EDIT';$('#usuario_pass,#usuario_pass_conf').prop('required',!existing);$('#password-card').toggleClass('collapsed-card',mode==='VIEW');$('#password-card-header font').text(existing?'Cambiar contraseña':'Contraseña');$('#pass-label-1 font').text(existing?'Nueva contraseña (en blanco conserva la actual)':'Contraseña');$('#pass-label-2 font').text('Confirmar contraseña');},
    prepare:function(record){return $.when(catalog('../../modules/empleados/consultar-empleado.php',{FILTER:'SELECT_LIST',current_id:record.CODIGO_EMP},$employees),catalog('../../modules/usuarios/listar-roles.php',null,$roles));},
    validate:function(){if($('#usuario_pass').val()!==$('#usuario_pass_conf').val()){toastr.error('Las contraseñas no coinciden.');return false;}return true;}
  });
})();
