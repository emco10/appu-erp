(function ($) {
  'use strict';
  var $root=$('#gre-workspace');if(!$root.length)return;
  var base='../../modules/gre/',id=0,version=0,busy=false,dirty=false,valid=false,state=null,enabled=false;
  var manage=$root.attr('data-can-manage')==='1',$form=$('#gre-prepare-form');
  var labels={not_submitted:'Sin envío',queued:'Reservada',sending:'Envío en curso',processing:'Procesando en SUNAT',uncertain:'Resultado incierto: no retransmitir',technical_error:'Consulta pendiente',accepted:'Aceptada',observed:'Aceptada con observaciones',rejected:'Rechazada'};
  function message(text,error){$('#gre-message').removeClass('alert-info alert-danger').addClass(error?'alert-danger':'alert-info').text(text);}
  function csrf(){return $('meta[name="csrf-token"]').attr('content')||'';}
  function controls(){
    var reserved=!!(state&&state.submission),terminal=reserved&&['accepted','observed','rejected'].indexOf(state.submission.status)!==-1;
    var edit=manage&&enabled&&state&&state.can_edit&&!reserved;
    $('#gre-guide,#gre-refresh-list').prop('disabled',busy);
    $('#gre-fields').prop('disabled',busy||!edit);
    $('#gre-preview,#gre-refresh-state').prop('disabled',busy||!id);
    $('#gre-save').prop('disabled',busy||!edit);
    $('#gre-submit').prop('disabled',busy||!id||!state||!manage||!enabled||!valid||dirty||reserved);
    $('#gre-consult').prop('disabled',busy||!id||!manage||!enabled||!reserved||!state.submission.ticket||terminal);
  }
  function validation(errors){
    var $out=$('#gre-validation').empty();
    if(!errors.length){$('<p>').addClass('text-success').text('Datos completos. El envío aún no se ha realizado.').appendTo($out);return;}
    $('<p>').text('Completa los campos indicados:').appendTo($out);
    var $list=$('<ul>').appendTo($out);
    errors.forEach(function(error){$('<li>').text((error.field?error.field+': ':'')+(error.message||'Valor inválido.')).appendTo($list);});
  }
  function failure(xhr){
    var response=xhr.responseJSON||{};message(response.message||(response.error&&response.error.message)||'No se pudo completar la operación GRE.',true);
    if(Array.isArray(response.errors)&&response.errors.length)validation(response.errors);
    valid=false;controls();
  }
  function request(file,method,data,done,after){
    if(busy)return;busy=true;controls();var current=version,succeeded=false;
    $.ajax({url:base+file+'.php',method:method,data:data,dataType:'json',headers:method==='POST'?{'X-CSRF-Token':csrf()}: {}})
      .done(function(response){if(current===version){succeeded=true;done(response);}})
      .fail(function(xhr){if(current===version)failure(xhr);})
      .always(function(){if(current===version){busy=false;controls();if(after&&succeeded)after();}});
  }
  function downloadLink(selector,kind,active){
    var $link=$(selector);$link.toggleClass('disabled',!active).attr('aria-disabled',active?'false':'true');
    if(active)$link.attr('href',base+'artefacto.php?guide_id='+id+'&kind='+kind);else $link.removeAttr('href');
  }
  function render(response){
    state=response.data;enabled=!!state.gre_enabled;valid=false;dirty=false;
    var guide=state.guide,submission=state.submission;
    $('#gre-number').text(guide.number);
    $('#gre-submission').text('Estado: '+(submission?(labels[submission.status]||submission.status):labels.not_submitted)+(submission&&submission.sunat_code?' · Código SUNAT '+submission.sunat_code:''));
    $('#gre-ticket').text(submission?((submission.ticket?'Ticket: '+submission.ticket+'. ':'')+(submission.sunat_description||'')):'No se ha reservado un envío GRE.');
    $form.find('[name]').each(function(){var value=this.name==='guide_id'?id:guide[this.name];$(this).val(value==null?'':value);});
    if(!guide.weight_unit)$form.find('[name="weight_unit"]').val('KGM');
    $('#gre-lines').empty();(state.lines||[]).forEach(function(line){
      var $row=$('<tr>');['item_code','description','unit_code','quantity'].forEach(function(field){$('<td>').text(line[field]).appendTo($row);});$row.appendTo('#gre-lines');
    });
    $('#gre-validation').empty();downloadLink('#gre-xml','xml',!!submission);downloadLink('#gre-cdr','cdr',!!(submission&&Number(submission.cdr_available)===1));
    if(!enabled)message('GRE está desactivada. Puedes validar datos y descargar archivos existentes; los envíos y consultas SUNAT requieren habilitación.',false);
    else if(submission&&submission.status==='uncertain')message('El resultado del envío es incierto. Conserva esta guía y revisa su estado; no generes otro envío para reemplazarla.',true);
    else message('Guía seleccionada. Valida los datos antes de solicitar su envío.',false);
    controls();
  }
  function loadGuide(value){
    if(busy)return;
    id=Number(value)||0;version++;state=null;valid=false;dirty=false;downloadLink('#gre-xml','xml',false);downloadLink('#gre-cdr','cdr',false);controls();
    if(!id)return;
    request('estado','GET',{guide_id:id},render);
  }
  function list(){
    request('listar','GET',{},function(response){
      var selected=id,$select=$('#gre-guide').empty().append($('<option>').val('').text('Selecciona una guía'));
      (response.data||[]).forEach(function(guide){$('<option>').val(guide.id).text(guide.number+' · '+guide.recipient_name+' · '+(labels[guide.gre_status]||guide.gre_status||'Sin envío')).appendTo($select);});
      $select.val(selected||'');enabled=!!response.gre_enabled;
      if(!response.schema_ready)message('Falta aplicar la migración GRE. Los documentos operativos existentes se conservan.',true);
    });
  }
  $('#gre-guide').on('change',function(){if(dirty&&!window.confirm('Hay cambios de datos GRE sin guardar. ¿Descartarlos?')){$(this).val(id);return;}loadGuide(this.value);});
  $('#gre-refresh-list').on('click',function(){if(!dirty||window.confirm('Actualizar la lista no guardará los cambios pendientes. ¿Continuar?'))list();});
  $('#gre-refresh-state').on('click',function(){if(!dirty||window.confirm('¿Descartar los datos sin guardar y actualizar el estado?'))loadGuide(id);});
  $form.on('input change','[name]',function(){dirty=true;valid=false;$('#gre-validation').empty();controls();});
  $form.on('submit',function(event){
    event.preventDefault();if(!id||busy||!state||!state.can_edit||!enabled)return;
    var data=$form.serialize();
    request('preparar','POST',data,function(response){message(response.message,false);dirty=false;},function(){if(!dirty)loadGuide(id);});
  });
  $('#gre-preview').on('click',function(){
    if(dirty){message('Guarda primero los datos modificados para validar la misma versión que se enviará.',true);return;}
    request('previsualizar','GET',{guide_id:id},function(response){var result=response.data;valid=!!(result&&result.validation&&result.validation.valid);validation(result&&result.validation?result.validation.errors||[]:[]);controls();});
  });
  $('#gre-submit').on('click',function(){
    if(!valid||dirty||busy||!enabled||!manage||!id||!state||state.submission)return;
    if(!window.confirm('Se solicitará el envío electrónico real de esta guía a SUNAT. Un ticket todavía no acredita aceptación; deberás consultar su CDR. ¿Enviar?'))return;
    request('enviar','POST',{guide_id:id},function(response){message(response.message,false);},function(){loadGuide(id);});
  });
  $('#gre-consult').on('click',function(){
    if(busy||!enabled||!manage||!id||!state||!state.submission||!state.submission.ticket)return;
    request('consultar','POST',{guide_id:id},function(response){message(response.message,false);},function(){loadGuide(id);});
  });
  $('#gre-xml,#gre-cdr').on('click',function(event){if($(this).attr('aria-disabled')!=='false')event.preventDefault();});
  list();
})(jQuery);
