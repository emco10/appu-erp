(function (global, $) {
  'use strict';
  // All network lookup starts at the explicit Consultar click. Editing a field only invalidates local suggestions.
  global.appuIdentityLookup = function (options) {
    var $form = $(options.form), $number = $form.find('[name="' + options.documentField + '"]');
    var $button = $form.find('[data-identity-consult]'), $hidden = $form.find('[name="partner_tax_profile_json"]');
    var epoch = 0, busy = false, context = '', profileText = null, suggestion = null;
    var flags = { retention_agent: 'Agente de retención', perception_agent: 'Agente de percepción', good_taxpayer: 'Buen contribuyente' };
    var $panel = $('<div class="card card-outline card-info identity-lookup-panel"><div class="card-header"><h3 class="card-title">Consulta y condiciones tributarias</h3></div><div class="card-body"><p data-identity-status role="status" aria-live="polite" class="mb-2 text-muted"></p><div data-identity-preview class="mb-3" hidden></div><div data-identity-profile class="row"></div><p class="text-muted small mb-0 mt-2">Sin verificar significa que no hay una confirmación disponible. Estas condiciones no determinan por sí solas si una operación tiene retención, percepción o detracción.</p></div></div>');
    $form.children('.card').first().after($panel);
    var $status = $panel.find('[data-identity-status]'), $preview = $panel.find('[data-identity-preview]');
    Object.keys(flags).forEach(function (key) {
      var id = options.partnerType + '-tax-' + key;
      var $col = $('<div class="col-md-4 form-group">').append($('<label>').attr('for', id).text(flags[key]));
      $('<select class="form-control" data-identity-tax>').attr({ id: id, 'data-identity-tax': key }).append('<option value="">Sin verificar</option><option value="yes">Sí</option><option value="no">No</option>').appendTo($col);
      $panel.find('[data-identity-profile]').append($col);
    });
    $panel.find('[data-identity-profile]').append('<div class="col-md-6 form-group"><label>Cuenta de detracciones (Banco de la Nación)</label><input type="text" class="form-control" data-identity-account maxlength="30" inputmode="numeric" placeholder="Opcional; verifica con el titular"><small class="form-text text-muted">Se registra manualmente. No se obtiene del RUC.</small></div>');
    var $provenance = $('<div class="col-md-6 form-group"><label>Origen del perfil</label><p class="form-control-plaintext text-muted" data-identity-provenance></p></div>').appendTo($panel.find('[data-identity-profile]')).find('[data-identity-provenance]');

    function number() { return String($number.val() || '').trim(); }
    function editable() { return ['CREATE', 'EDIT'].includes($form.attr('data-mode')) && $form.attr('aria-busy') !== 'true' && !$number.prop('disabled') && (!global.appuCan || global.appuCan(options.permission)); }
    function currentContext() { return [$form.attr('data-mode'), $form.attr('aria-busy'), $form.find('[name="' + options.idField + '"]').val(), number()].join('|'); }
    function profile() { try { var data = JSON.parse($hidden.val() || 'null'); return data && data.document_number === number() ? data : null; } catch (_) { return null; } }
    function invalidate() { ++epoch; busy = false; suggestion = null; $preview.empty().prop('hidden', true); }
    function provenance(data) {
      if (!data) return 'Sin verificar';
      var text = data.source === 'manual' ? 'Registrado manualmente' : 'Fuente: ' + String(data.source || 'Sin verificar');
      if (data.consulted_at) text += ' · Consulta: ' + String(data.consulted_at);
      if (data.cache_hit === true) text += ' · Resultado en caché';
      return text;
    }
    function syncProfile() {
      var text = String($hidden.val() || '');
      if (profileText === text) return;
      profileText = text; var data = profile(), tax = data && data.tax_profile || {};
      Object.keys(flags).forEach(function (key) { $panel.find('[data-identity-tax="' + key + '"]').val(tax[key] === true ? 'yes' : tax[key] === false ? 'no' : ''); });
      $panel.find('[data-identity-account]').val(data && data.detraction_account || '');
      $provenance.text(provenance(data));
    }
    function render() {
      var next = currentContext();
      if (context !== next) { invalidate(); context = next; $status.text('Consulta opcional: revisa los resultados antes de aplicarlos.'); }
      syncProfile();
      var enabled = editable(), ruc = /^[0-9]{11}$/.test(number());
      $button.prop('disabled', !enabled || busy).text(busy ? 'Consultando…' : 'Consultar DNI/RUC');
      $panel.find('[data-identity-tax],[data-identity-account]').prop('disabled', !enabled || !ruc);
      $preview.find(':input').prop('disabled', !enabled || busy);
    }
    function field(name) { return $form.find('[name]').filter(function () { return this.name === name; }); }
    function saveProfile(data) {
      $hidden.val(JSON.stringify(data)); profileText = null; syncProfile();
      if (global.appuMarkDirty) global.appuMarkDirty();
    }
    function row(key, label, value, checked) {
      var $check = $('<input type="checkbox" class="mr-2" data-identity-select>').attr('data-identity-select', key).prop('checked', checked);
      var $label = $('<label class="d-flex align-items-start mb-2 font-weight-normal">').append($check);
      $label.append($('<span>').append($('<strong>').text(label + ': ')).append(global.document.createTextNode(String(value))));
      $preview.append($label);
    }
    function preview(data, requestContext, requestEpoch) {
      suggestion = { data: data, context: requestContext, epoch: requestEpoch, fields: {} }; $preview.empty().prop('hidden', false);
      $preview.append($('<p class="small text-muted">').text(provenance(data) + ' · Estado: ' + (data.state || 'Sin verificar') + ' · Condición: ' + (data.condition || 'Sin verificar')));
      Object.keys(options.fields).forEach(function (key) {
        if (data.document_type === 'DNI' && key !== 'legal_name') return;
        var target = options.fields[key], value = data[key];
        if (typeof value !== 'string' || !value.trim()) return;
        suggestion.fields[key] = { name: target.name, value: value };
        row(key, target.label, value, !String(field(target.name).val() || '').trim());
      });
      if (options.applyLocation && /^[0-9]{6}$/.test(data.ubigeo || '')) row('location', 'Ubigeo', data.ubigeo + ' · ' + [data.department, data.province, data.district].filter(Boolean).join(' / '), false);
      if (data.document_type === 'RUC') {
        var tax = data.tax_profile || {};
        row('tax_profile', 'Condiciones tributarias', Object.keys(flags).map(function (key) { return flags[key] + ': ' + (tax[key] === true ? 'Sí' : tax[key] === false ? 'No' : 'Sin verificar'); }).join(' · '), false);
      }
      $preview.append('<button type="button" class="btn btn-sm btn-primary" data-identity-apply>Aplicar selección</button><p class="small text-muted mt-2 mb-0">Aplicar completa la ficha. Para persistir los cambios utiliza Guardar.</p>');
    }
    $button.on('click.identityLookup', function () {
      render();
      if (!editable() || busy) return;
      var value = number(), type = /^[0-9]{11}$/.test(value) ? 'RUC' : /^[0-9]{8}$/.test(value) ? 'DNI' : '';
      if (!type) { $status.text('Para consultar ingresa un DNI de 8 dígitos o un RUC de 11 dígitos.'); return; }
      invalidate(); var requestEpoch = epoch, requestContext = currentContext(); busy = true; render();
      $status.text('Consultando. Tus datos actuales se conservan.');
      $.ajax({ url: options.endpoint || '../../modules/identity/consultar.php', type: 'POST', dataType: 'json', data: { partner_type: options.partnerType, document_type: type, document_number: value } }).done(function (response) {
        if (requestEpoch !== epoch || currentContext() !== requestContext || !editable()) return;
        var data = response && response.data;
        if (!response.success || !data || data.document_number !== value || data.document_type !== type || typeof data.legal_name !== 'string') { $status.text('La respuesta no corresponde al documento. Revisa los datos manualmente.'); return; }
        preview(data, requestContext, requestEpoch); $status.text('Selecciona los datos que deseas aplicar. Los campos existentes solo se reemplazan si los seleccionas.');
      }).fail(function (xhr) {
        if (requestEpoch !== epoch || currentContext() !== requestContext || !editable()) return;
        $status.text((xhr.responseJSON || {}).message || 'No se pudo consultar. Puedes completar y guardar los datos manualmente.');
      }).always(function () { if (requestEpoch === epoch) { busy = false; render(); } });
    });
    $preview.on('click.identityLookup', '[data-identity-apply]', function () {
      var selected = suggestion;
      if (!selected || !editable() || selected.epoch !== epoch || selected.context !== currentContext()) return;
      var keys = $preview.find('[data-identity-select]:checked').map(function () { return $(this).attr('data-identity-select'); }).get();
      if (!keys.length) { $status.text('Selecciona al menos un dato para aplicarlo.'); return; }
      keys.forEach(function (key) {
        if (selected.fields[key]) field(selected.fields[key].name).val(selected.fields[key].value).trigger('change');
        if (key === 'tax_profile') {
          var existing = profile() || {}, tax = {}, raw = selected.data.tax_profile || {};
          Object.keys(flags).forEach(function (name) { tax[name] = typeof raw[name] === 'boolean' ? raw[name] : null; });
          saveProfile({ document_number: number(), tax_profile: tax, source: selected.data.source, consulted_at: selected.data.consulted_at || null, cache_hit: selected.data.cache_hit === true, tax_profile_evidence: selected.data.tax_profile_evidence || {}, detraction_account: existing.detraction_account || '' });
        }
      });
      if (keys.includes('location')) options.applyLocation(selected.data, function () { return selected.epoch === epoch && selected.context === currentContext() && editable(); });
      if (global.appuMarkDirty) global.appuMarkDirty();
      $status.text('Selección aplicada. Revisa la ficha y utiliza Guardar para persistirla.'); $preview.empty().prop('hidden', true); suggestion = null; render();
    });
    $number.on('input.identityLookup change.identityLookup', function () {
      invalidate(); $hidden.val(''); profileText = null; context = currentContext();
      $status.text('El documento cambió. Consulta nuevamente si necesitas verificarlo.'); render();
    });
    $panel.on('change.identityLookup', '[data-identity-tax],[data-identity-account]', function () {
      if (!editable() || !/^[0-9]{11}$/.test(number())) return;
      var tax = {};
      Object.keys(flags).forEach(function (key) { var value = $panel.find('[data-identity-tax="' + key + '"]').val(); tax[key] = value === 'yes' ? true : value === 'no' ? false : null; });
      saveProfile({ document_number: number(), tax_profile: tax, source: 'manual', consulted_at: null, detraction_account: String($panel.find('[data-identity-account]').val() || '').trim() });
    });
    render();
    return { render: render, reset: function () { invalidate(); profileText = null; context = ''; } };
  };
})(window, jQuery);
