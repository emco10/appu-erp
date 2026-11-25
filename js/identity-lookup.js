(function (global, $) {
  'use strict';
  // All network lookup starts at the explicit Consultar click. Editing a field only invalidates local suggestions.
  global.appuIdentityLookup = function (options) {
    var $form = $(options.form), $number = $form.find('[name="' + options.documentField + '"]');
    var $button = $form.find('[data-identity-consult]'), $hidden = $form.find('[name="partner_tax_profile_json"]');
    var epoch = 0, busy = false, context = '', profileText = null;
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
    function invalidate() { ++epoch; busy = false; $preview.empty().prop('hidden', true); }
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
      if (context !== next) { invalidate(); context = next; $status.text('Pulsa Consultar para completar automáticamente los datos disponibles.'); }
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
    function applyResult(data, requestContext, requestEpoch) {
      var applied = [];
      Object.keys(options.fields).forEach(function (key) {
        if (data.document_type === 'DNI' && key !== 'legal_name') return;
        var target = options.fields[key], value = data[key];
        if (typeof value !== 'string' || !value.trim()) return;
        field(target.name).val(value).trigger('change');
        applied.push(target.label);
      });
      if (options.applyLocation && /^[0-9]{6}$/.test(data.ubigeo || '')) {
        options.applyLocation(data, function () { return requestEpoch === epoch && requestContext === currentContext() && editable(); });
        applied.push('Ubigeo');
      }
      if (data.document_type === 'RUC') {
        var existing = profile() || {}, tax = {}, raw = data.tax_profile || {};
        Object.keys(flags).forEach(function (name) { tax[name] = typeof raw[name] === 'boolean' ? raw[name] : null; });
        saveProfile({ document_number: number(), tax_profile: tax, source: data.source, consulted_at: data.consulted_at || null, cache_hit: data.cache_hit === true, tax_profile_evidence: data.tax_profile_evidence || {}, detraction_account: existing.detraction_account || '' });
        applied.push('Condiciones tributarias');
      }
      $preview.empty().prop('hidden', false)
        .append($('<p class="small text-muted mb-1">').text(provenance(data) + ' · Estado: ' + (data.state || 'Sin verificar') + ' · Condición: ' + (data.condition || 'Sin verificar')))
        .append($('<p class="small mb-0">').text('Datos completados: ' + (applied.length ? applied.join(', ') : 'ningún campo disponible') + '. Revisa la ficha y utiliza Guardar para persistirla.'));
      if (global.appuMarkDirty) global.appuMarkDirty();
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
        applyResult(data, requestContext, requestEpoch); $status.text('Consulta completada. Los datos disponibles se aplicaron automáticamente; revisa la ficha antes de guardar.');
      }).fail(function (xhr) {
        if (requestEpoch !== epoch || currentContext() !== requestContext || !editable()) return;
        $status.text((xhr.responseJSON || {}).message || 'No se pudo consultar. Puedes completar y guardar los datos manualmente.');
      }).always(function () { if (requestEpoch === epoch) { busy = false; render(); } });
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
