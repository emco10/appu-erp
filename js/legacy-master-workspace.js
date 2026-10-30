(function (global, $) {
  'use strict';
  // Shared lifecycle for the four existing master endpoints. IDs come from
  // DataTables data, never from detached or only currently rendered row nodes.
  global.appuLegacyMasterWorkspace = function (options) {
    var $form=$(options.form),$table=$(options.table),$bar=$('.appu-master-toolbar[data-table="'+options.table+'"]');
    var canManage=appuCan(options.permission),mode=canManage?'CREATE':'LIST',epoch=0,saving=false,loading=false,snapshot=null,currentCode='',records=[],cursor=-1;
    var $save=$(options.save),$deactivate=$(options.deactivate),fields=options.fields;
    $bar.attr('data-page-nav','true');
    $bar.find('.appu-master-save').before('<button type="button" class="btn btn-sm btn-light appu-master-edit"><i class="fas fa-pen"></i><span>Editar</span></button><button type="button" class="btn btn-sm btn-light appu-master-cancel"><i class="fas fa-times"></i><span>Cancelar edición</span></button>');
    var searchId='appu-master-search-'+options.prefix.toLowerCase();
    var $search=$('<div class="modal fade" tabindex="-1" role="dialog" aria-modal="true">').attr('id',searchId).append('<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar">&times;</button></div><div class="modal-body"><input type="search" class="form-control mb-3" data-master-query placeholder="Buscar por código, nombre o documento; * muestra todos"><p class="text-muted" data-master-search-status></p><div class="table-responsive"><table class="table table-hover table-sm"><thead></thead><tbody></tbody></table></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div></div></div>');
    $search.find('.modal-title').text('Buscar '+options.label);$search.appendTo('body');
    var $head=$('<tr>');options.searchColumns.forEach(function(col){$('<th>').text(col.label).appendTo($head);});$head.append('<th></th>');$search.find('thead').append($head);
    var dt=$table.DataTable({ajax:{url:options.endpoint,type:'POST',data:{FILTER:'ALL'}},columns:options.columns.map(function(name){return {data:name};}),order:[[0,'desc']],dom:'Bfrtip',buttons:[{extend:'csv',text:'Descargar CSV'},{extend:'excel',text:'Descargar Excel'},{extend:'print',text:'Imprimir'}],language:{url:'../../plugins/datatables/Spanish.json'}});
    function input(name){return $form.find('[name]').filter(function(){return this.name===name;});}
    function clean(){if(global.appuMarkSaved)global.appuMarkSaved();}
    function guard(callback){if(!saving)appuConfirmDiscard(callback);}
    function rows(){return dt.rows({search:'applied',order:'applied'}).data().toArray();}
    function render(){
      records=rows();cursor=records.findIndex(function(row){return String(row[options.codeColumn])===String(currentCode);});
      var editable=canManage&&!loading&&!saving&&(mode==='CREATE'||mode==='EDIT');
      $form.attr('data-mode',mode).attr('aria-busy',loading||saving?'true':'false');
      $form.find(':input').not(':button,[type=submit]').each(function(){$(this).prop('disabled',!editable);});
      $form.find('button[type=submit]').prop('disabled',!editable).toggle(editable);
      $bar.find('.appu-master-save').toggle(canManage&&(mode==='CREATE'||mode==='EDIT')).prop('disabled',!editable);
      $bar.find('.appu-master-edit').toggle(canManage&&mode==='VIEW').prop('disabled',loading||saving);
      $bar.find('.appu-master-cancel').toggle(mode==='EDIT'||mode==='CREATE').prop('disabled',loading||saving);
      $bar.find('.appu-master-new').toggle(canManage).prop('disabled',saving);
      $bar.find('.appu-master-find').prop('disabled',saving);
      $bar.find('.appu-master-position').text((cursor<0?0:cursor+1)+' de '+records.length);
      $bar.find('.appu-master-first,.appu-master-prev').prop('disabled',loading||saving||!records.length||cursor===0);
      $bar.find('.appu-master-next,.appu-master-last').prop('disabled',loading||saving||!records.length||cursor===records.length-1);
      $bar.find('.appu-document-toolbar__eyebrow').text(loading?'CARGANDO':saving?'GUARDANDO':({CREATE:'NUEVO',EDIT:'EDITAR',VIEW:'CONSULTA',LIST:'LISTA'})[mode]);
      $save.find('font').text(mode==='EDIT'?'Actualizar '+options.label:'Guardar '+options.label);
      $deactivate.parent().toggle(canManage&&mode==='VIEW'&&!!snapshot).end().prop('disabled',loading||saving);
      if(options.render)options.render(mode,editable,snapshot);
    }
    function clear(keepCatalogs){
      $form[0].reset();$form.find('input[type=hidden]').val('');
      $form.find('[name]').filter(function(){return this.name.indexOf('custom_field[')===0;}).each(function(){if(this.type==='checkbox')$(this).prop('checked',false);else $(this).val('');});
      $form.find('select').trigger('change.select2');
      if(global.appuPartnerDetailsLoad)global.appuPartnerDetailsLoad(0);
      if(!keepCatalogs&&options.reset)options.reset();
    }
    function apply(record){
      clear(true);snapshot=record;currentCode=options.prefix+'-'+record.CODIGO;
      Object.keys(fields).forEach(function(name){var value=record[fields[name]];var $field=input(name);if(value===null||value===undefined)value='';if($field.is('select')&&value!==''&&!$field.find('option').filter(function(){return String(this.value)===String(value);}).length)$field.append($('<option>').val(value).text(value));$field.val(value).trigger('change.select2');});
      input(options.idField).val(record.CODIGO);input(options.codeField).val(currentCode);
      Object.keys(record.CUSTOM_FIELDS||{}).forEach(function(id){var $field=input('custom_field['+id+']');if($field.is(':checkbox'))$field.prop('checked',String(record.CUSTOM_FIELDS[id])==='1');else $field.val(record.CUSTOM_FIELDS[id]).trigger('change.select2');});
      mode='VIEW';loading=false;render();clean();if(global.appuPartnerDetailsLoad)global.appuPartnerDetailsLoad(record.CODIGO);
    }
    function open(code){
      var previousDirty=!!global.appuFormDirty;
      guard(function(){var request=++epoch;loading=true;render();
        $.ajax({url:options.endpoint,type:'POST',data:{FILTER:code},dataType:'json'}).done(function(data){
          if(request!==epoch)return;if(!Array.isArray(data)||!data.length){loading=false;render();return toastr.warning('No se encontró el registro seleccionado.');}
          var record=data[0];var prepare=options.prepare?options.prepare(record,function(){return request===epoch;}):$.Deferred().resolve().promise();
          $.when(prepare).done(function(){if(request===epoch){apply(record);$search.modal('hide');$form[0].scrollIntoView({behavior:'smooth',block:'start'});}}).fail(function(){if(request===epoch){loading=false;render();toastr.error('No se pudieron cargar los catálogos de la ficha. Reintenta.');}});
        }).fail(function(){if(request===epoch){loading=false;render();if(previousDirty&&global.appuMarkDirty)global.appuMarkDirty();toastr.error('No se pudo recuperar el registro. Los datos actuales se conservan.');}});
      });
    }
    function fresh(){++epoch;loading=false;snapshot=null;currentCode='';clear();mode=canManage?'CREATE':'LIST';render();clean();input(options.firstField).trigger('focus');}
    function results(){
      var query=String($search.find('[data-master-query]').val()||'').trim().toLowerCase().replace(/\*/g,'');var $body=$search.find('tbody').empty();
      var found=dt.rows({order:'applied'}).data().toArray().filter(function(row){return !query||options.searchColumns.some(function(col){return String(row[col.key]||'').toLowerCase().indexOf(query)!==-1;});});
      found.forEach(function(row){var $tr=$('<tr>');options.searchColumns.forEach(function(col){$('<td>').text(row[col.key]||'').appendTo($tr);});$('<td>').append($('<button type="button" class="btn btn-sm btn-primary" data-master-select>').attr('data-master-select',row[options.codeColumn]).text('Consultar')).appendTo($tr);$body.append($tr);});
      if(!found.length)$body.append($('<tr>').append($('<td class="text-center text-muted">').attr('colspan',options.searchColumns.length+1).text('No se encontraron registros.')));
      $search.find('[data-master-search-status]').text(found.length+' registros. Incluye activos e inactivos.');
    }
    function find(){if(saving)return;$search.modal('show');results();$search.find('[data-master-query]').trigger('focus');}
    function navigate(direction){records=rows();if(!records.length)return toastr.info('No hay registros para navegar.');var index=direction==='first'?0:direction==='last'?records.length-1:cursor<0?0:cursor+(direction==='prev'?-1:1);open(records[Math.max(0,Math.min(records.length-1,index))][options.codeColumn]);}
    $bar.find('.appu-master-new').on('click.legacyMaster',function(){guard(fresh);});
    $bar.find('.appu-master-find').on('click.legacyMaster',find);
    ['first','prev','next','last'].forEach(function(direction){$bar.find('.appu-master-'+direction).on('click.legacyMaster',function(){navigate(direction);});});
    $bar.find('.appu-master-edit').on('click.legacyMaster',function(){if(canManage&&snapshot&&!loading&&!saving){mode='EDIT';render();input(options.firstField).trigger('focus');}});
    $bar.find('.appu-master-cancel').on('click.legacyMaster',function(){guard(function(){++epoch;if(snapshot)apply(snapshot);else{fresh();mode='LIST';render();}});});
    $bar.find('.appu-master-save').on('click.legacyMaster',function(){$form[0].requestSubmit();});
    $table.on('click.legacyMaster','tbody tr',function(){var row=dt.row(this).data();if(row)open(row[options.codeColumn]);});
    $table.on('draw.dt.legacyMaster',function(){render();if($search.hasClass('show'))results();});
    $search.on('input.legacyMaster','[data-master-query]',results).on('click.legacyMaster','[data-master-select]',function(){open($(this).attr('data-master-select'));});
    $form.on('input.legacyMaster change.legacyMaster',':input:not([type=hidden])',function(event){if(!event.isTrigger&&(mode==='CREATE'||mode==='EDIT')&&global.appuMarkDirty)global.appuMarkDirty();});
    $form.on('submit.legacyMaster',function(event){
      event.preventDefault();if(!canManage||saving||loading||!['CREATE','EDIT'].includes(mode))return;if(!$form[0].reportValidity())return;
      if(options.validate&&!options.validate())return;var payload=new FormData($form[0]),savedCode=currentCode,unique=input(options.uniqueField).val();saving=true;render();
      $.ajax({url:$form.attr('action'),method:'POST',data:payload,contentType:false,processData:false}).done(function(response){
        var result=String(response).trim();if(!/^OK_(INSERT|UPDATE)$/.test(result)){toastr.error(result==='EXISTE'?'Ya existe un registro con esos datos.':'No se pudo guardar. Revisa los datos obligatorios.');return;}
        saving=false;clean();dt.ajax.reload(function(data){var saved=(data.data||[]).find(function(row){return savedCode?String(row[options.codeColumn])===String(savedCode):String(row[options.uniqueColumn])===String(unique);});if(saved)open(saved[options.codeColumn]);else fresh();},false);toastr.success('Registro guardado correctamente.');
      }).fail(function(xhr){var messages={ERROR_USERNAME:'Use de 3 a 50 letras, números, guion o guion bajo.',ERROR_PASSWORD:'La contraseña debe tener al menos 8 caracteres.',ERROR_PASSWORD_CONFIRMATION:'Las contraseñas no coinciden.',ERROR_SELF_DEACTIVATION:'No puedes desactivar tu propio usuario.',EMPLOYEE_ASSIGNED:'El empleado ya tiene un usuario.',EMPLOYEE_HAS_ACTIVE_USER:'Desactiva primero el usuario de acceso de este empleado.',ERROR_CUSTOM_FIELDS:'Revisa los campos de usuario obligatorios.',ERROR_VALIDATION:'Revisa los campos obligatorios, las fechas y el correo.'};toastr.error((xhr.responseJSON||{}).message||messages[String(xhr.responseText).trim()]||'No se pudo guardar. Los datos ingresados se conservan.');}).always(function(){saving=false;render();});
    });
    $deactivate.on('click.legacyMaster',function(){if(!canManage||!snapshot||loading||saving)return;Swal.fire({title:'Desactivar '+options.label,text:'El registro y su historial se conservarán.',type:'warning',showCancelButton:true,confirmButtonText:'Desactivar',cancelButtonText:'Cancelar'}).then(function(result){if(!result.value)return;var data={};data[options.deleteId]=snapshot.CODIGO;$.post(options.deleteEndpoint,data).done(function(response){if(String(response).trim()!=='1')return toastr.error('No se pudo desactivar el registro.');clean();dt.ajax.reload(null,false);open(currentCode);toastr.success('Registro desactivado.');}).fail(function(xhr){toastr.error((xhr.responseJSON||{}).message||'No se permite desactivar este registro.');});});});
    var initialId=new URLSearchParams(global.location.search).get('record_id');
    fresh();if(initialId)open(options.prefix+'-'+initialId);
    return {open:open,find:find,newRecord:function(){guard(fresh);},mode:function(){return mode;},refresh:render};
  };
})(window,jQuery);
