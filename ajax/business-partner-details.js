(function ($) {
  'use strict';
  var panel = $('.appu-partner-details');
  if (!panel.length) return;
  var endpoint = panel.data('endpoint'), partnerId = 0, addresses = [], contacts = [], loadEpoch = 0;
  var typeLabels = { billing: 'Fiscal', shipping: 'Entrega', other: 'Otra' };

  function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }
  function badge(active, preferred) { return (preferred ? '<span class="badge badge-primary mr-1">Predeterminada</span>' : '') + '<span class="badge ' + (Number(active) === 1 ? 'badge-success' : 'badge-secondary') + '">' + (Number(active) === 1 ? 'Activa' : 'Inactiva') + '</span>'; }
  function render() {
    var addressRows = panel.find('.appu-address-rows').empty(), contactRows = panel.find('.appu-contact-rows').empty();
    addresses.forEach(function (row, index) { addressRows.append('<tr><td>' + escapeHtml(row.address_code) + '</td><td>' + escapeHtml(typeLabels[row.address_type] || row.address_type) + '</td><td>' + escapeHtml(row.address_name) + '</td><td>' + escapeHtml(row.address_line) + '</td><td>' + badge(row.active_status, Number(row.is_default) === 1) + '</td><td class="text-right"><button type="button" class="btn btn-xs btn-outline-primary appu-address-edit" data-index="' + index + '"><i class="fas fa-edit"></i></button></td></tr>'); });
    contacts.forEach(function (row, index) { contactRows.append('<tr><td>' + escapeHtml(row.contact_name) + '</td><td>' + escapeHtml(row.position_name || '-') + '</td><td>' + escapeHtml(row.mobile || row.phone || '-') + '</td><td>' + escapeHtml(row.email || '-') + '</td><td>' + badge(row.active_status, Number(row.is_default) === 1) + '</td><td class="text-right"><button type="button" class="btn btn-xs btn-outline-primary appu-contact-edit" data-index="' + index + '"><i class="fas fa-edit"></i></button></td></tr>'); });
    if (!addresses.length) addressRows.append('<tr><td colspan="6" class="text-center text-muted">No hay direcciones registradas.</td></tr>');
    if (!contacts.length) contactRows.append('<tr><td colspan="6" class="text-center text-muted">No hay contactos registrados.</td></tr>');
  }
  function load(id) {
    var currentEpoch = ++loadEpoch;
    partnerId = Number(id) || 0;
    panel.find('.appu-partner-empty').toggleClass('d-none', !!partnerId);
    panel.find('.appu-partner-content').toggleClass('d-none', !partnerId);
    if (!partnerId) { addresses = []; contacts = []; return render(); }
    $.getJSON(endpoint, { action: 'list', partner_id: partnerId }).done(function (response) { if (currentEpoch !== loadEpoch) return; addresses = response.data.addresses || []; contacts = response.data.contacts || []; render(); }).fail(function (xhr) { if (currentEpoch === loadEpoch) showError(xhr); });
  }
  function showError(xhr) { var response = xhr && xhr.responseJSON; toastr.error(response && response.message ? response.message : 'No se pudo completar la operación.'); }
  function checked(value) { return Number(value) === 1 ? ' checked' : ''; }
  function addressDialog(row) {
    row = row || {};
    Swal.fire({ title: row.id ? 'Editar dirección' : 'Nueva dirección', width: 760, html: '<div class="text-left"><div class="form-row"><div class="form-group col-md-4"><label>Código</label><input id="bp-a-code" class="form-control" maxlength="30" value="' + escapeHtml(row.address_code || '') + '"></div><div class="form-group col-md-4"><label>Tipo</label><select id="bp-a-type" class="form-control"><option value="billing">Fiscal</option><option value="shipping">Entrega</option><option value="other">Otra</option></select></div><div class="form-group col-md-4"><label>Nombre</label><input id="bp-a-name" class="form-control" value="' + escapeHtml(row.address_name || '') + '"></div></div><div class="form-group"><label>Dirección</label><input id="bp-a-line" class="form-control" value="' + escapeHtml(row.address_line || '') + '"></div><div class="form-row"><div class="form-group col-md-4"><label>País</label><input id="bp-a-country" class="form-control" value="' + escapeHtml(row.country || 'Perú') + '"></div><div class="form-group col-md-4"><label>Ciudad</label><input id="bp-a-city" class="form-control" value="' + escapeHtml(row.city || '') + '"></div><div class="form-group col-md-4"><label>Referencia</label><input id="bp-a-reference" class="form-control" value="' + escapeHtml(row.reference || '') + '"></div></div><label class="mr-3"><input type="checkbox" id="bp-a-default"' + checked(row.is_default) + '> Predeterminada</label><label><input type="checkbox" id="bp-a-active"' + checked(row.id ? row.active_status : 1) + '> Activa</label></div>', showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar', onOpen: function () { $('#bp-a-type').val(row.address_type || 'billing'); }, preConfirm: function () { var data={action:'save_address',partner_id:partnerId,id:row.id||'',address_code:$('#bp-a-code').val(),address_type:$('#bp-a-type').val(),address_name:$('#bp-a-name').val(),address_line:$('#bp-a-line').val(),country:$('#bp-a-country').val(),city:$('#bp-a-city').val(),reference:$('#bp-a-reference').val(),is_default:$('#bp-a-default').is(':checked')?1:0,active_status:$('#bp-a-active').is(':checked')?1:0}; if(!data.address_code||!data.address_name||!data.address_line){Swal.showValidationMessage('Completa código, nombre y dirección.');return false;} return data; } }).then(function(result){if(result.value) save(result.value);});
  }
  function contactDialog(row) {
    row=row||{};
    Swal.fire({title:row.id?'Editar contacto':'Nuevo contacto',width:760,html:'<div class="text-left"><div class="form-row"><div class="form-group col-md-6"><label>Nombre</label><input id="bp-c-name" class="form-control" value="'+escapeHtml(row.contact_name||'')+'"></div><div class="form-group col-md-6"><label>Cargo</label><input id="bp-c-position" class="form-control" value="'+escapeHtml(row.position_name||'')+'"></div></div><div class="form-row"><div class="form-group col-md-4"><label>Teléfono</label><input id="bp-c-phone" class="form-control" value="'+escapeHtml(row.phone||'')+'"></div><div class="form-group col-md-4"><label>Celular</label><input id="bp-c-mobile" class="form-control" value="'+escapeHtml(row.mobile||'')+'"></div><div class="form-group col-md-4"><label>Correo</label><input id="bp-c-email" type="email" class="form-control" value="'+escapeHtml(row.email||'')+'"></div></div><label class="mr-3"><input type="checkbox" id="bp-c-default"'+checked(row.is_default)+'> Predeterminado</label><label><input type="checkbox" id="bp-c-active"'+checked(row.id?row.active_status:1)+'> Activo</label></div>',showCancelButton:true,confirmButtonText:'Guardar',cancelButtonText:'Cancelar',preConfirm:function(){var data={action:'save_contact',partner_id:partnerId,id:row.id||'',contact_name:$('#bp-c-name').val(),position_name:$('#bp-c-position').val(),phone:$('#bp-c-phone').val(),mobile:$('#bp-c-mobile').val(),email:$('#bp-c-email').val(),is_default:$('#bp-c-default').is(':checked')?1:0,active_status:$('#bp-c-active').is(':checked')?1:0};if(!data.contact_name){Swal.showValidationMessage('Ingresa el nombre del contacto.');return false;}return data;}}).then(function(result){if(result.value)save(result.value);});
  }
  function save(data) { $.post(endpoint, data).done(function () { toastr.success('Datos relacionados guardados.'); load(partnerId); }).fail(showError); }
  panel.on('click','.appu-address-new',function(){addressDialog();});
  panel.on('click','.appu-contact-new',function(){contactDialog();});
  panel.on('click','.appu-address-edit',function(){addressDialog(addresses[Number($(this).data('index'))]);});
  panel.on('click','.appu-contact-edit',function(){contactDialog(contacts[Number($(this).data('index'))]);});
  window.appuPartnerDetailsLoad = load;
  load(0);
})(jQuery);
