(function (global) {
  'use strict';
  /** One lifecycle for master forms; adapters provide fields and the existing save endpoint. */
  global.appuMasterWorkspace = function (options) {
    var $form = $(options.form), $bar = $(options.toolbar), $search = $(options.search);
    var canManage = appuCan(options.permission), epoch = 0, listEpoch = 0;
    var saving = false, loading = false, ready = false, snapshot = null, definitions = [];
    var records = [], cursor = -1, pagination = { page: 1, pages: 0, total: 0, per_page: 25 };
    var requestKey = appuRequestKey(), searchTimer, state = appuDocumentState('LIST', render);
    var labels = { LIST: 'Lista', SEARCH: 'Buscar', CREATE: 'Crear', VIEW: 'Consultar', EDIT: 'Editar' };
    $form.attr('novalidate', 'novalidate');
    $search.attr('data-mode', 'SEARCH');
    $bar.attr('data-page-nav', 'true');
    function action(name) { return $bar.find('[data-master-action="' + name + '"]'); }
    function markClean() { if (global.appuMarkSaved) global.appuMarkSaved(); }
    function guard(callback) { if (!saving) { var dirty = !!global.appuFormDirty; appuConfirmDiscard(function () { callback(dirty); }); } }
    function searchFields() {
      var $fields = $search.find('[data-master-field]'), selected = $fields.val();
      $fields.empty().append($('<option>').val('').text('Sin campo adicional'));
      definitions.filter(function (field) { return Number(field.searchable) === 1; }).forEach(function (field) {
        $fields.append($('<option>').val(field.id).text(field.label));
      });
      $fields.val(selected || '');
    }
    function render() {
      var editable = canManage && ready && !loading && !saving && ['CREATE', 'EDIT'].includes(state.get());
      $form.attr('data-mode', state.get()).attr('aria-busy', loading || saving ? 'true' : 'false');
      $form.find(':input').not(':button,[type=submit]').each(function () {
        $(this).prop('disabled', !editable || this.hasAttribute('data-master-readonly'));
      });
      $form.find('[type=submit]').prop('disabled', !editable).toggle(['CREATE', 'EDIT'].includes(state.get()) && canManage);
      action('save').add(action('save-new')).toggle(['CREATE', 'EDIT'].includes(state.get()) && canManage).prop('disabled', !editable);
      action('edit').toggle(state.get() === 'VIEW' && canManage && !!snapshot).prop('disabled', saving || loading);
      action('cancel').toggle(['CREATE', 'EDIT'].includes(state.get())).prop('disabled', saving || loading);
      action('new').toggle(canManage).prop('disabled', saving);
      action('find').add(action('list')).prop('disabled', saving);
      var position = cursor < 0 ? 0 : (pagination.page - 1) * pagination.per_page + cursor + 1;
      $bar.find('[data-master-position]').text(position + ' de ' + pagination.total);
      $bar.find('[data-master-mode]').text(saving ? 'Guardando…' : loading ? 'Cargando…' : labels[state.get()]);
      var atStart = cursor === 0 && pagination.page === 1, atEnd = cursor === records.length - 1 && pagination.page >= pagination.pages;
      action('first').add(action('prev')).prop('disabled', saving || loading || !records.length || atStart);
      action('next').add(action('last')).prop('disabled', saving || loading || !records.length || atEnd);
    }
    function failure(xhr, fallback) {
      var response = xhr.responseJSON;
      if (!response && xhr.responseText) { try { response = JSON.parse(xhr.responseText); } catch (_) {} }
      appuShowValidation($form, response || { message: fallback });
      if ($search.hasClass('show')) $search.find('[data-master-search-error]').text((response || {}).message || fallback);
    }
    function customFields(values, isNew) {
      var normalized = {};
      if (!isNew) definitions.forEach(function (field) { normalized[field.id] = ''; });
      Object.assign(normalized, values || {});
      appuRenderCustomFields(options.customFields, definitions, normalized, options.fieldPrefix);
    }
    function clear() {
      $form[0].reset();
      $form.find(':input[name]').not(':button,[type=submit]').each(function () {
        if (this.type === 'checkbox' || this.type === 'radio') $(this).prop('checked', false);
        else $(this).val('');
      });
      $form.find('select').trigger('change.select2');
      $(options.customFields).empty();
      appuClearValidation($form);
    }
    function apply(record) {
      clear(); snapshot = record;
      if (record.definitions) definitions = record.definitions;
      searchFields();
      Object.keys(record.values).forEach(function (name) {
        var $field = $form.find('[name]').filter(function () { return this.name === name; });
        if ($field.is(':checkbox')) $field.prop('checked', !!record.values[name]);
        else $field.val(record.values[name]).trigger('change.select2');
      });
      customFields(record.custom_fields, false);
      cursor = records.findIndex(function (row) { return Number(row.id) === Number(record.id); });
      ready = true; loading = false; requestKey = appuRequestKey(); state.set('VIEW'); markClean();
    }
    function fresh() {
      var current = ++epoch;
      clear(); snapshot = null; ready = false; loading = true; cursor = -1; requestKey = appuRequestKey();
      state.set('CREATE'); markClean();
      $.getJSON(options.endpoint, { action: 'catalog' }).done(function (data) {
        if (current !== epoch) return;
        definitions = data.custom_fields || []; searchFields(); customFields({}, true); ready = true; loading = false; render(); markClean();
        $form.find('[name="' + options.firstField + '"]').trigger('focus');
      }).fail(function (xhr) {
        if (current !== epoch) return;
        loading = false; render(); failure(xhr, 'No se pudieron cargar los campos. Pulsa Nuevo para reintentar.');
      });
    }
    function open(id, restoreDirty) {
      var current = ++epoch;
      loading = true; render(); appuClearValidation($form);
      $.getJSON(options.endpoint, { action: 'get', id: id }).done(function (record) {
        if (current !== epoch) return;
        apply(record); $search.modal('hide');
      }).fail(function (xhr) {
        if (current !== epoch) return;
        loading = false; render();
        if (restoreDirty && global.appuMarkDirty) global.appuMarkDirty();
        failure(xhr, 'No se pudo recuperar la ficha. Puedes reintentar sin perder los datos.');
      });
    }
    function filters() {
      return { action: 'search', q: $search.find('[data-master-query]').val() || '',
        status: $search.find('[data-master-status]').val() || '',
        custom_field_id: $search.find('[data-master-field]').val() || '',
        custom_field_value: $search.find('[data-master-value]').val() || '' };
    }
    function renderResults() {
      var $body = $search.find('tbody').empty();
      records.forEach(function (record) {
        var $row = $('<tr>');
        options.columns.forEach(function (column) { $('<td>').text(record[column] == null ? '' : record[column]).appendTo($row); });
        $('<td>').text(Number(record.active_status) === 1 ? 'Activo' : 'Inactivo').appendTo($row);
        $('<td>').append($('<button type="button" class="btn btn-sm btn-primary">').attr('data-master-select', record.id).text('Consultar')).appendTo($row);
        $body.append($row);
      });
      if (!records.length) $body.append($('<tr>').append($('<td class="text-center">').attr('colspan', options.columns.length + 2).text('No se encontraron registros.')));
      $search.find('[data-master-page]').text('Página ' + pagination.page + ' de ' + Math.max(1, pagination.pages) + ' · ' + pagination.total + ' registros');
      $search.find('[data-master-prev-page]').prop('disabled', pagination.page <= 1);
      $search.find('[data-master-next-page]').prop('disabled', pagination.page >= pagination.pages);
      render();
    }
    function search(page, callback, onFailure) {
      var current = ++listEpoch;
      $search.find('[data-master-search-error]').empty();
      $search.find('tbody button,[data-master-prev-page],[data-master-next-page]').prop('disabled', true);
      $search.attr('aria-busy', 'true');
      $.getJSON(options.endpoint, Object.assign(filters(), { page: page || 1 })).done(function (data) {
        if (current !== listEpoch) return;
        records = data.records; pagination = data.pagination;
        cursor = snapshot ? records.findIndex(function (row) { return Number(row.id) === Number(snapshot.id); }) : -1;
        renderResults(); if (callback) callback();
      }).fail(function (xhr) {
        if (current !== listEpoch) return;
        $search.find('[data-master-search-error]').text((xhr.responseJSON || {}).message || 'No se pudo buscar. Pulsa Buscar para reintentar.');
        $search.find('tbody').empty();
        $search.find('[data-master-page]').text('Búsqueda no completada');
        if (onFailure) onFailure();
      }).always(function () { if (current === listEpoch) $search.attr('aria-busy', 'false'); });
    }
    function find() {
      if (saving) return;
      $search.modal('show');
      searchFields(); search(1); $search.find('[data-master-query]').trigger('focus');
    }
    function navigate(direction) {
      guard(function (dirty) {
        var current = epoch;
        var index = direction === 'first' ? 0 : direction === 'last' ? records.length - 1 : cursor < 0 ? 0 : cursor + (direction === 'prev' ? -1 : 1);
        var page = direction === 'first' ? 1 : direction === 'last' ? pagination.pages : pagination.page + (index < 0 ? -1 : index >= records.length ? 1 : 0);
        if (page !== pagination.page) search(page, function () {
          if (current !== epoch) return;
          if (records.length) open(records[direction === 'prev' || direction === 'last' ? records.length - 1 : 0].id, dirty);
          else if (dirty && global.appuMarkDirty) global.appuMarkDirty();
        }, function () { if (current === epoch && dirty && global.appuMarkDirty) global.appuMarkDirty(); });
        else if (records.length) open(records[Math.max(0, Math.min(records.length - 1, index))].id, dirty);
      });
    }
    function save(after) {
      if (saving || loading || !ready || !canManage || !['CREATE', 'EDIT'].includes(state.get())) return;
      var current = epoch, payload = new FormData($form[0]);
      payload.set('response_format', 'json'); payload.set('request_key', requestKey);
      if (snapshot) payload.set('version', snapshot.version);
      saving = true; render(); appuClearValidation($form);
      $.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', data: payload, processData: false, contentType: false })
        .done(function (data) {
          if (current !== epoch) return;
          if (!data.success || !data.snapshot) { saving = false; render(); failure({ responseJSON: data }, 'No se confirmó el guardado.'); return; }
          saving = false; apply(data.snapshot); toastr.success('Registro guardado.');
          if (after === 'new') fresh(); search(pagination.page);
        }).fail(function (xhr) {
          if (current === epoch) { saving = false; render(); failure(xhr, 'No se pudo confirmar el guardado. Los datos se conservan; puedes reintentar.'); }
        }).always(function () { if (current === epoch) { saving = false; render(); } });
    }
    $bar.on('click', '[data-master-action]', function () {
      if (this.disabled) return;
      var name = $(this).attr('data-master-action');
      if (name === 'new') guard(fresh);
      else if (name === 'find') find();
      else if (['first', 'prev', 'next', 'last'].includes(name)) navigate(name);
      else if (name === 'edit' && snapshot && !saving && !loading && canManage) { requestKey = appuRequestKey(); state.set('EDIT'); }
      else if (name === 'cancel') guard(function () { ++epoch; if (snapshot) apply(snapshot); else { clear(); ready = false; loading = false; state.set('LIST'); markClean(); } });
      else if (name === 'save' || name === 'save-new') save(name === 'save-new' ? 'new' : 'view');
    });
    $form.on('submit.master', function (event) { event.preventDefault(); save('view'); });
    $form.on('input.master change.master', ':input:not([type=hidden])', function () {
      if (ready && !loading && !saving && ['CREATE', 'EDIT'].includes(state.get()) && global.appuMarkDirty) global.appuMarkDirty();
    });
    $search.on('submit.master', 'form', function (event) { event.preventDefault(); clearTimeout(searchTimer); search(1); });
    $search.on('input.master', '[data-master-query]', function () { clearTimeout(searchTimer); searchTimer = setTimeout(function () { search(1); }, 250); });
    $search.on('change.master', '[data-master-status],[data-master-field],[data-master-value]', function () { clearTimeout(searchTimer); search(1); });
    $search.on('click.master', '[data-master-select]', function () { if (!this.disabled) { var id = $(this).attr('data-master-select'); guard(function (dirty) { open(id, dirty); }); } });
    $search.on('click.master', '[data-master-prev-page]', function () { if (!this.disabled) search(pagination.page - 1); });
    $search.on('click.master', '[data-master-next-page]', function () { if (!this.disabled) search(pagination.page + 1); });
    var initialId = new URLSearchParams(global.location.search).get('record_id') || new URLSearchParams(global.location.search).get('id');
    if (initialId) open(initialId); else if (canManage) fresh(); else {
      render();
      var catalogEpoch = epoch;
      $.getJSON(options.endpoint, { action: 'catalog' }).done(function (data) { if (catalogEpoch === epoch) { definitions = data.custom_fields || []; searchFields(); } });
    }
    search(1);
    return { open: function (id) { guard(function (dirty) { open(id, dirty); }); }, newRecord: function () { guard(fresh); }, find: find, mode: state.get };
  };
})(window);
