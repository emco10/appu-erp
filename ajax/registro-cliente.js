$('#m_clientes').addClass('active');
$(document).prop('title','Clientes - APPU');
(function(){
  var locationEpoch=0;
  function field(name){return $('[name="cliente_'+name+'"]');}
  function locations(mode,data,$select,isCurrent){
    return $.ajax({url:'../../modules/ubigeo/consultar-ubigeo.php',type:'POST',data:Object.assign({MODE_UBIGEO:mode},data),dataType:'json'}).then(function(rows){if(!isCurrent||isCurrent()){$select.empty();(rows||[]).forEach(function(row){$select.append($('<option>').val(row.id).text(row.text));});$select.trigger('change.select2');}});
  }
  field('departamento').on('change.legacyLocation',function(){var current=++locationEpoch;field('provincia').empty();field('distrito').empty();locations('PROVINCES',{ID_DEPART:this.value},field('provincia'),function(){return current===locationEpoch;}).then(function(){if(current===locationEpoch)field('provincia').trigger('change');});});
  field('provincia').on('change.legacyLocation',function(){var current=++locationEpoch;field('distrito').empty();locations('DISTRICTS',{ID_PROV:this.value},field('distrito'),function(){return current===locationEpoch;});});
  window.appuClienteWorkspace=appuLegacyMasterWorkspace({
    form:'#FRM_INSERT_CLIENTE',table:'#table-clientes',save:'#btn-save-client',deactivate:'#btn-delete-client',label:'cliente',prefix:'CLI',permission:'customers.manage',
    endpoint:'../../modules/clientes/consultar-cliente.php',deleteEndpoint:'../../modules/clientes/eliminar-cliente.php',deleteId:'cliente_id',
    idField:'cliente_id',codeField:'cliente_codigo',firstField:'cliente_ruc',uniqueField:'cliente_ruc',uniqueColumn:'RUC',codeColumn:'CODIGO',
    columns:['CODIGO','RUC','RAZ_SOC','NOM_COM','TELEF','CELULAR','ESTADO'],searchColumns:[{key:'CODIGO',label:'Código'},{key:'RUC',label:'RUC'},{key:'RAZ_SOC',label:'Razón social'},{key:'NOM_COM',label:'Nombre comercial'},{key:'ESTADO',label:'Estado'}],
    fields:{cliente_razsoc:'RAZ_SOC',cliente_telfij:'TELEF',cliente_ruc:'RUC',cliente_telcel:'CELULAR',cliente_nomcom:'NOM_COM',cliente_fecreg:'FEC_REG',cliente_correo:'EMAIL',cliente_direccion:'DIRECC',cliente_departamento:'DEPARTAMENTO',cliente_provincia:'PROVINCIA',cliente_distrito:'DISTRITO',cliente_nomcont_1:'NOMCON_1',cliente_celcont_1:'CELCON_1',cliente_nomcont_2:'NOMCON_2',cliente_celcont_2:'CELCON_2',cliente_pagocomision:'PAGCOM',cliente_active_status:'ACTIVE_STATUS',cliente_moneda:'DEFAULT_CURRENCY',cliente_dias_pago:'PAYMENT_DAYS',cliente_limite_credito:'CREDIT_LIMIT',cliente_lista_precio_id:'PRICE_LIST_ID',cliente_vendedor_id:'SALES_EMPLOYEE_ID'},
    reset:function(){++locationEpoch;field('provincia').empty();field('distrito').empty();field('active_status').val('1');field('moneda').val('PEN');field('dias_pago').val('0');field('limite_credito').val('0');},
    prepare:function(record,isCurrent){var current=++locationEpoch;var valid=function(){return current===locationEpoch&&isCurrent();};return locations('DEPARTMENTS',{},field('departamento'),valid).then(function(){return locations('PROVINCES',{ID_DEPART:record.DEPARTAMENTO},field('provincia'),valid);}).then(function(){return locations('DISTRICTS',{ID_PROV:record.PROVINCIA},field('distrito'),valid);});}
  });
  var initialLocationEpoch=locationEpoch;
  locations('DEPARTMENTS',{},field('departamento'),function(){return initialLocationEpoch===locationEpoch;});
})();
