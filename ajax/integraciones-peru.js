(function ($) {
  'use strict';
  var endpoint='../../modules/gestion/integraciones-peru.php', $settings=$('#peru-settings-form'), $tax=$('#peru-tax-form'), requestKey=null, evaluationSignature=null, busy=false, taxEpoch=0, settingsEpoch=0, settingsDirty=false;
  function message(text, error){$('#peru-message').removeClass('d-none alert-info alert-danger').addClass(error?'alert-danger':'alert-info').text(text);}
  function failure(xhr){var r=xhr.responseJSON||{};message(r.message||(r.error&&r.error.message)||'No se pudo completar la solicitud.',true);}
  function request(data,done){if(busy){message('Espera a que termine la solicitud actual antes de continuar.',false);return;}busy=true;$.ajax({url:endpoint,method:'POST',data:data,dataType:'json'}).done(done).fail(failure).always(function(){busy=false;});}
  function invalidate(){taxEpoch++;requestKey=null;evaluationSignature=null;$('#peru-save-evaluation').prop('disabled',true);$('#peru-tax-result').empty().text('Los datos cambiaron. Vuelve a evaluar antes de guardar.');}
  function key(){var bytes=new Uint8Array(20);window.crypto.getRandomValues(bytes);return Array.from(bytes,function(v){return v.toString(16).padStart(2,'0');}).join('');}
  function result(data){var $out=$('#peru-tax-result').empty();$('<p>').text('Estado: '+({calculated:'Calculado',excluded:'Excluido',review_required:'Requiere revisión'}[data.status]||data.status)).appendTo($out);
    (data.reasons||[]).forEach(function(reason){$('<p>').text(reason).appendTo($out);});
    if(data.tax_cents!==null){$('<p>').text('Retención / depósito pendiente: S/ '+(data.tax_cents/100).toFixed(2)+' · Neto previsto al proveedor: S/ '+(data.net_to_supplier_cents/100).toFixed(2)).appendTo($out);}
    if(data.saved)$('<p>').text('Evaluación guardada #'+data.evaluation_id+'. Registro contable pendiente.').appendTo($out);
  }
  $.getJSON(endpoint).done(function(r){if(!settingsDirty)Object.keys(r.settings||{}).forEach(function(name){var $field=$settings.find('[name="'+name+'"]');if($field.is(':checkbox'))$field.prop('checked',Number(r.settings[name])===1);else $field.val(r.settings[name]===null?'':r.settings[name]);});
    Object.keys(r.catalog||{}).forEach(function(id){var rule=r.catalog[id];$('<option>').val(id).text(rule.label+' · '+(rule.rate_bps/100)+'%').appendTo('#spot-category');});
    if(!r.schema_ready)message('Falta aplicar la migración de integraciones Perú en la base de datos.',true);
  }).fail(failure);
  $settings.on('input change',function(){settingsDirty=true;settingsEpoch++;invalidate();});
  $settings.on('submit',function(e){e.preventDefault();var data={action:'settings'},epoch=settingsEpoch;$settings.find('[name]').each(function(){data[this.name]=this.type==='checkbox'?(this.checked?'1':'0'):this.value;});request(data,function(r){if(epoch===settingsEpoch)settingsDirty=false;message(r.message,false);});});
  $('#peru-check').on('click',function(){request({action:'check'},function(r){$('#peru-readiness').text(JSON.stringify(r.data,null,2));});});
  $tax.on('input change',invalidate);
  $tax.on('submit',function(e){e.preventDefault();if(settingsDirty){message('Guarda los cambios de configuración antes de evaluar la operación.',true);return;}var epoch=taxEpoch;request($tax.serialize()+'&action=evaluate',function(r){if(epoch!==taxEpoch)return;result(r.data);evaluationSignature=r.data.evaluation_signature;requestKey=key();$('#peru-save-evaluation').prop('disabled',false);});});
  $('#peru-save-evaluation').on('click',function(){if(!requestKey)return;var epoch=taxEpoch;request($tax.serialize()+'&action=save_evaluation&request_key='+encodeURIComponent(requestKey)+'&evaluation_signature='+encodeURIComponent(evaluationSignature||''),function(r){if(epoch!==taxEpoch){message('Se guardó la evaluación solicitada antes de los cambios actuales. Vuelve a evaluar los nuevos importes.',false);return;}result(r.data);$('#peru-save-evaluation').prop('disabled',true);});});
})(jQuery);
