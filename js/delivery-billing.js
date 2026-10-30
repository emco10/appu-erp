$(function () {
  var $form = $('#delivery-billing-form'), $modal = $('#delivery-billing-modal');
  if (!$form.length) return;
  var epoch = 0, saving = false, preview = null;
  function localDate() { var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); }
  function setBusy(busy) { saving = busy; $form.find('button,input,select').prop('disabled', busy); if (!busy) $form.find('.billing-line-quantity').each(function () { $(this).prop('disabled', Number(this.max) <= 0); }); $modal.find('[data-bs-dismiss]').prop('disabled', busy); }
  function recalculate() {
    if (!preview) return;
    var subtotal = 0, context = preview.billing_context, quantities = Object.assign({}, context && context.billed_quantities || {});
    $form.find('.billing-line-quantity').each(function () { var line = $(this).data('line'), quantity = Math.max(0, Number(this.value) || 0); subtotal += Math.round(quantity * Number(line.unit_price) * 100) / 100; quantities[line.order_line_id] = Number(quantities[line.order_line_id] || 0) + quantity; });
    var tax = Math.round(subtotal * 18) / 100, discount = Math.round((subtotal + tax) * Number(preview.order.discount_rate || 0)) / 100;
    var rounding = context && Object.keys(context.ordered_quantities).every(function (line) { return Number(quantities[line] || 0) + .000001 >= Number(context.ordered_quantities[line]); }) ? Math.round((Number(preview.order.total_net) - context.previous_total - (subtotal + tax - discount)) * 100) / 100 : 0;
    $('#delivery-billing-total').text('Subtotal: ' + subtotal.toFixed(2) + ' · IGV: ' + tax.toFixed(2) + ' · Descuento: ' + discount.toFixed(2) + (rounding ? ' · Ajuste de redondeo: ' + rounding.toFixed(2) : '') + ' · Total: ' + (subtotal + tax - discount + rounding).toFixed(2) + ' ' + preview.order.currency);
  }
  function openBilling(guideId) {
    appuConfirmDiscard(function () {
      var request = ++epoch; preview = null; $form[0].reset(); appuClearValidation($form);
      $form.find('[name=request_key]').val(appuRequestKey());
      $('#delivery-billing-lines,#delivery-billing-custom-fields,#delivery-billing-result,#delivery-billing-total').empty();
      $('#delivery-billing-customer').text(''); $form.find('[type=submit]').show();
      $form.attr('data-document-mode', 'CREATE'); $form.find('[name=document_date]').val(localDate());
      setBusy(false); $form.find('[type=submit]').prop('disabled', true); $modal.modal('show');
      $.getJSON('../../modules/ventas/documentos.php', { action: 'delivery_billing_preview', guide_ids: String(guideId) }).done(function (data) {
        if (request !== epoch) return;
        preview = data; $('#delivery-billing-customer').text(data.order.customer_name + ' · ' + data.order.number + ' · ' + data.order.currency);
        var $series = $form.find('[name=series]').empty(); (data.series || []).forEach(function (series) { $series.append($('<option>').val(series).text(series)); });
        (data.lines || []).forEach(function (line, index) {
          var $row = $('<tr>').appendTo($('#delivery-billing-lines'));
          $('<td>').text(line.guide_number).appendTo($row); $('<td>').text(line.item_code + ' · ' + line.item_name).appendTo($row);
          $('<td>').text(line.open_quantity).appendTo($row); $('<td>').text(line.unit_price.toFixed(2)).appendTo($row);
          $('<input type="number" min="0" step="1" class="form-control billing-line-quantity" aria-label="Cantidad a facturar">').attr({ max: line.open_quantity, 'data-field-path': 'lines[' + index + '].quantity' }).val(line.open_quantity).prop('disabled', line.open_quantity <= 0).data('line', line).appendTo($('<td>').appendTo($row));
        });
        appuRenderCustomFields($('#delivery-billing-custom-fields'), data.custom_fields, data.custom_values, 'delivery-billing-custom-');
        recalculate();
        $form.find('[type=submit]').prop('disabled', !(data.series || []).length || !(data.lines || []).some(function (line) { return line.open_quantity > 0; }));
        appuMarkSaved();
      }).fail(function (xhr) { if (request === epoch) appuShowValidation($form, xhr.responseJSON || { message: 'No se pudo recuperar la entrega.' }); });
    });
  }
  $(document).on('click', '.js-guide-invoice', function () { openBilling(Number($(this).data('id'))); });
  $form.on('input change', 'input,select', function () { if (!saving && $form.attr('data-document-mode') === 'CREATE') { appuMarkDirty(); recalculate(); } });
  $modal.on('hide.bs.modal', function (event) {
    if (saving) { event.preventDefault(); return; }
    if (window.appuFormDirty) { event.preventDefault(); appuConfirmDiscard(function () { $modal.modal('hide'); }); return; }
    epoch++;
  });
  $form.on('submit', function (event) {
    event.preventDefault(); if (saving || !preview || $form.attr('data-document-mode') !== 'CREATE') return;
    var lines = [];
    $form.find('.billing-line-quantity:not(:disabled)').each(function () {
      var line = $(this).data('line'), quantity = Number($(this).val());
      if (quantity !== 0 || String(this.value).trim() === '') {
        $(this).attr('data-field-path', 'lines[' + lines.length + '].quantity');
        lines.push({ guide_id: line.guide_id, guide_line_id: line.guide_line_id, quantity: this.value });
      } else $(this).removeAttr('data-field-path');
    });
    var data = $form.serializeArray(); data.push({ name: 'action', value: 'invoice_from_deliveries' }, { name: 'lines', value: JSON.stringify(lines) });
    appuClearValidation($form); setBusy(true);
    $.ajax({ url: '../../modules/ventas/documentos.php', method: 'POST', dataType: 'json', data: data }).done(function (result) {
      appuMarkSaved(); $form.attr('data-document-mode', 'VIEW');
      $('#delivery-billing-result').empty().append($('<div class="alert alert-success">').text(result.message + ' ' + result.number + ' · ' + Number(result.total).toFixed(2) + ' ' + result.currency))
        .append($('<a class="btn btn-primary">').attr('href', '../../views/facturacion/registro-factura?record_id=' + result.invoice_id).text('Ver factura'));
      $form.find('[type=submit]').hide(); $(document).trigger('appu:delivery-billed');
    }).fail(function (xhr) { setBusy(false); appuShowValidation($form, xhr.responseJSON || { message: 'No se pudo crear la factura. Los datos permanecen para corregirlos.' }); })
      .always(function () { if ($form.attr('data-document-mode') === 'VIEW') { saving = false; $modal.find('[data-bs-dismiss]').prop('disabled', false); } });
  });
});
