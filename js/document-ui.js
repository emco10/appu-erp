(function (global) {
  'use strict';
  var modes = ['LIST', 'SEARCH', 'CREATE', 'VIEW', 'EDIT'];
  global.appuRequestKey = function () {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') return global.crypto.randomUUID();
    return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + '-' + Math.random().toString(36).slice(2);
  };
  global.appuDocumentState = function (initial, onChange) {
    var mode = initial;
    return {
      get: function () { return mode; },
      set: function (next) {
        if (modes.indexOf(next) === -1) throw new Error('Modo de documento inválido');
        mode = next;
        if (onChange) onChange(next);
      }
    };
  };
  global.appuClearValidation = function (form) {
    $(form).find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
    $(form).find('.appu-field-error,.appu-validation-summary').remove();
  };
  global.appuRenderCustomFields = function (host, definitions, values, prefix) {
    var $host = $(host).empty(), section = null, $section = $host;
    (definitions || []).slice().sort(function (a, b) { return Number(a.display_order || 0) - Number(b.display_order || 0) || Number(a.id) - Number(b.id); }).forEach(function (field) {
      if (Number(field.visible) === 0) return;
      if (field.section && field.section !== section) {
        section = field.section;
        $section = $('<fieldset class="border rounded p-3 mb-2">').appendTo($host);
        $('<legend class="h6">').text(section).appendTo($section);
      } else if (!field.section) { section = null; $section = $host; }
      var $group = $('<div class="form-group">').appendTo($section), $input;
      var id = (prefix || 'custom-field-') + field.id;
      $('<label>').attr('for', id).text(field.label + (Number(field.required_status) ? ' *' : '')).appendTo($group);
      if (field.field_type === 'select' || field.field_type === 'checkbox') {
        $input = $('<select class="form-control">').append($('<option>').val('').text('Seleccione'));
        var options = field.field_type === 'checkbox' ? [{ value_code: '1', label: 'Sí' }, { value_code: '0', label: 'No' }] : field.options;
        (options || []).forEach(function (option) { $input.append($('<option>').val(option.value_code).text(option.label)); });
      } else $input = $('<input class="form-control">').attr('type', { number: 'number', date: 'date' }[field.field_type] || 'text').attr('step', 'any');
      if (field.max_length) $input.attr('maxlength', field.max_length);
      if (field.min_value != null) $input.attr('min', field.min_value);
      if (field.max_value != null) $input.attr('max', field.max_value);
      var value = values && values[field.id] != null ? values[field.id] : (field.default_value == null ? '' : field.default_value);
      $input.attr({ id: id, name: 'custom_field[' + field.id + ']', 'aria-required': Number(field.required_status) === 1 ? 'true' : 'false' }).val(value).appendTo($group);
    });
  };
  global.appuShowValidation = function (form, response) {
    global.appuClearValidation(form);
    var $summary = $('<div class="alert alert-danger appu-validation-summary" role="alert" tabindex="-1">');
    $('<strong>').text(response.message || 'Corrige los siguientes campos.').appendTo($summary);
    var $list = $('<ul class="mb-0">').appendTo($summary), first = null;
    (response.errors || []).forEach(function (error) {
      $('<li>').text(error.message).appendTo($list);
      var $field = $(form).find('[data-field-path], [name]').filter(function () {
        return this.getAttribute('data-field-path') === error.path || this.name === error.path;
      }).first();
      if (!$field.length) return;
      $field.addClass('is-invalid').attr('aria-invalid', 'true');
      var $target = $field.next('.select2').length ? $field.next('.select2') : $field;
      $('<div class="invalid-feedback d-block appu-field-error">').text(error.message).insertAfter($target);
      if (!first) first = $field[0];
    });
    var $host = $(form).find('.modal-body').first();
    ($host.length ? $host : $(form)).prepend($summary);
    $summary[0].scrollIntoView({ block: 'nearest' });
    if (first) {
      var focusTarget = $(first).next('.select2').find('.select2-selection')[0] || first;
      focusTarget.focus();
    } else $summary.trigger('focus');
  };
  global.appuShowRequestError = function (form, xhr, fallback) {
    var response = xhr && xhr.responseJSON || {};
    if (response.message || Array.isArray(response.errors)) {
      global.appuShowValidation(form, response);
      return;
    }
    var message = typeof response.error === 'string' ? response.error : fallback;
    if (!xhr || xhr.status === 0) message = 'No hubo conexión con el servidor. Tus datos siguen en el formulario; vuelve a intentarlo.';
    global.toastr.error(message);
  };

  global.appuBindSalesCreation = function (form, getLines, validate) {
    if (!form) return;
    const requested = new URLSearchParams(global.location.search).get('record_id');
    form.dataset.documentMode = /^[1-9]\d*$/.test(requested) ? 'VIEW' : 'CREATE';
    let saving = false;
    let requestKey = '';

    $('#btn-select-factura').on('click.salesCreation', function () {
      if ($('select[name="facturas_listado"]').val()) form.dataset.documentMode = 'VIEW';
    });

    function lock(locked) {
      saving = locked;
      form.inert = locked;
      form.setAttribute('aria-busy', String(locked));
      document.querySelectorAll('.appu-document-toolbar, #btn-nuevafac, #btn-select-factura, select[name="facturas_listado"]')
        .forEach(control => { control.inert = locked; });
    }

    $(form).on('submit.salesCreation', function (event) {
      event.preventDefault();
      if (saving || form.dataset.documentMode !== 'CREATE' || form.elements.id_factura.value || !global.appuCan('sales.manage')) return;
      if (validate && !validate()) return;

      const data = new FormData(form);
      requestKey ||= global.appuRequestKey();
      data.set('request_key', requestKey);
      data.set('response_format', 'json');
      data.set('facturacion_prods', JSON.stringify(getLines()));
      global.appuClearValidation(form);
      lock(true);
      Swal.fire({ title: 'Guardando comprobante', allowOutsideClick: false, allowEscapeKey: false, onBeforeOpen: () => Swal.showLoading() });

      $.ajax({ type: 'POST', url: form.action, data, dataType: 'json', contentType: false, processData: false })
        .done(function (response) {
          if (response?.error !== false || !/^[1-9]\d*$/.test(String(response.document_id))) {
            lock(false);
            Swal.close();
            global.toastr.error('No se pudo confirmar el guardado. Reintenta con los mismos datos.');
            return;
          }
          form.dataset.documentMode = 'VIEW';
          form.elements.id_factura.value = response.document_id;
          global.appuMarkSaved();
          const url = new URL(global.appuNewDocumentUrl());
          url.searchParams.delete('mode');
          url.searchParams.set('record_id', String(response.document_id));
          global.location.assign(url.href);
        }).fail(function (xhr) {
          lock(false);
          Swal.close();
          global.appuShowRequestError(form, xhr, 'No se pudo confirmar el guardado. Reintenta con los mismos datos.');
        });
    });
  };
})(window);
