$('#m_proveedores').addClass('active');
$(document).prop('title','Proveedores - APPU');
$('[name="proveedor_banco_1"],[name="proveedor_banco_2"]').select2({data:[{id:'',text:'Seleccione'},{id:'BCP',text:'BCP'},{id:'BBVA',text:'BBVA'},{id:'INTERBANK',text:'INTERBANK'},{id:'SCOTIABANK',text:'SCOTIABANK'},{id:'BANBIF',text:'BANBIF'},{id:'BN',text:'BANCO DE LA NACIÓN'}]});
$('[name="proveedor_tipmoneda_1"],[name="proveedor_tipmoneda_2"]').select2({data:[{id:'',text:'Seleccione'},{id:'MN',text:'Moneda Nacional (PEN)'},{id:'ME',text:'Moneda Extranjera (USD)'}]});
window.appuProveedorWorkspace=appuLegacyMasterWorkspace({
  form:'#FRM_INSERT_PROVEEDOR',table:'#table-proveedores',save:'#btn-save-proveedor',deactivate:'#btn-delete-proveedor',label:'proveedor',prefix:'PROV',permission:'providers.manage',
  endpoint:'../../modules/proveedores/consultar-proveedor.php',deleteEndpoint:'../../modules/proveedores/eliminar-proveedor.php',deleteId:'proveedor_id',
  idField:'proveedor_id',codeField:'proveedor_codigo',firstField:'proveedor_numero',uniqueField:'proveedor_numero',uniqueColumn:'NUMERO',codeColumn:'CODIGO',
  columns:['CODIGO','NUMERO','RAZ_SOC','DIRECC','CIUDAD','PAIS','FECREG','ESTADO'],searchColumns:[{key:'CODIGO',label:'Código'},{key:'NUMERO',label:'RUC / código'},{key:'RAZ_SOC',label:'Razón social'},{key:'ESTADO',label:'Estado'}],
  fields:{proveedor_numero:'NUMERO',proveedor_pais:'PAIS',proveedor_ciudad:'CIUDAD',proveedor_distrito:'DISTRITO',proveedor_razsoc:'RAZ_SOC',proveedor_fecreg:'FEC_REG',proveedor_direccion:'DIRECC',proveedor_active_status:'ACTIVE_STATUS',proveedor_moneda:'DEFAULT_CURRENCY',proveedor_dias_pago:'PAYMENT_DAYS',proveedor_limite_credito:'CREDIT_LIMIT',proveedor_comprador_id:'BUYER_EMPLOYEE_ID',proveedor_contnom_1:'CONT1_NAME',proveedor_conttelef_1:'CONT1_PHONE',proveedor_contnom_2:'CONT2_NAME',proveedor_conttelef_2:'CONT2_PHONE',proveedor_banco_1:'BANK1_NAME',proveedor_tipmoneda_1:'BANK1_CURR',proveedor_ctacorriente_1:'BANK1_ACCNUM',proveedor_titularcta_1:'BANK1_ACCHOL',proveedor_banco_2:'BANK2_NAME',proveedor_tipmoneda_2:'BANK2_CURR',proveedor_ctacorriente_2:'BANK2_ACCNUM',proveedor_titularcta_2:'BANK2_ACCHOL'},
  reset:function(){$('[name="proveedor_active_status"]').val('1');$('[name="proveedor_moneda"]').val('PEN');$('[name="proveedor_dias_pago"],[name="proveedor_limite_credito"]').val('0');}
});
