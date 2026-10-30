$('#m_empleados').addClass('active');
$(document).prop('title','Empleados - APPU');
window.appuEmpleadoWorkspace=appuLegacyMasterWorkspace({
  form:'#FRM_INSERT_EMPLEADO',table:'#table-empleados',save:'#btn-save-employee',deactivate:'#btn-delete-employee',label:'empleado',prefix:'EMP',permission:'employees.manage',
  endpoint:'../../modules/empleados/consultar-empleado.php',deleteEndpoint:'../../modules/empleados/eliminar-empleado.php',deleteId:'empleado_id',
  idField:'empleado_id',codeField:'empleado_codigo',firstField:'empleado_nombres',uniqueField:'empleado_numdoc',uniqueColumn:'NUM_DOC',codeColumn:'CODIGO',
  columns:['CODIGO','NOMBRES','APELLIDOS','CARGO','TIPO_DOC','NUM_DOC','FEC_NAC','FEC_ING','ESTADO'],searchColumns:[{key:'CODIGO',label:'Código'},{key:'NOMBRES',label:'Nombres'},{key:'APELLIDOS',label:'Apellidos'},{key:'NUM_DOC',label:'Documento'},{key:'ESTADO',label:'Estado'}],
  fields:{empleado_nombres:'NOMBRES',empleado_apepat:'APE_PAT',empleado_apemat:'APE_MAT',empleado_direccion:'DIRECCION',empleado_tipodoc:'TIPO_DOC',empleado_numdoc:'NUM_DOC',empleado_estado_civ:'ESTADO_CIV',empleado_fecnac:'FEC_NAC',empleado_cargo:'CARGO',empleado_fecing:'FEC_ING',empleado_active_status:'ACTIVE_STATUS',empleado_telefono:'TELEFONO',empleado_correo:'EMAIL',empleado_grado_est:'GRADO_EST',empleado_carrera:'CARRERA'},
  reset:function(){$('[name="empleado_active_status"]').val('1');}
});
