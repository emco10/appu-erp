$(document).ready(function () {
  $("#m_ordenes_venta").attr("class", "nav-link active");
  $(document).prop("title", "Ordenes de venta - APPU");
});

var tbl_sales_orders = $("#table-sales-orders").DataTable({
  dom: "Bfrtip",
  order: [[0, "DESC"]],
  paging: false, info: false, ordering: false, searching: false,
  buttons: [
    { extend: "pdf", text: '<i class="fa fa-file-pdf"></i>&nbsp;&nbsp;Descargar PDF' },
    { extend: "csv", text: '<i class="fa fa-file-csv"></i>&nbsp;&nbsp;Descargar CSV' },
    { extend: "excel", text: '<i class="fa fa-file-excel"></i>&nbsp;&nbsp;Descargar Excel' },
    { extend: "print", text: '<i class="fa fa-print"></i>&nbsp;&nbsp;Imprimir' }
  ],
  language: { url: "../../plugins/datatables/Spanish.json" }
});

$.fn.dataTable.ext.search.push(function (settings, data) {
  if (settings.nTable !== document.getElementById("table-sales-orders")) return true;
  var selected = String($("#sales-order-status-filter").val() || "").toLowerCase();
  if (!selected) return true;
  return String($(data[7]).text() || data[7] || "").toLowerCase().indexOf(selected.toLowerCase()) !== -1;
});

tbl_sales_orders.columns([0]).visible(false);

var salesOrderCatalog = { products: [] }, salesOrderPriceFactor = 1;
var salesOrderPage = 1, salesOrderPages = 1, salesOrderListEpoch = 0;
var salesOrderCurrent = null, salesOrderEpoch = 0, salesOrderSaving = false;
var $salesOrderForm = $('#sales-order-create-form'), $salesOrderModal = $('#sales-order-create-modal');
var salesOrderState = appuDocumentState('LIST', function (mode) {
  $salesOrderForm.attr('data-document-mode', mode);
  $('#sales-order-mode').text({ CREATE: 'Crear', VIEW: 'Consulta', EDIT: 'Editar' }[mode] || mode);
  var editable = mode === 'CREATE' || mode === 'EDIT';
  $salesOrderForm.find('input,select').prop('disabled', !editable);
  $('#sales-order-add-line,.sales-order-remove').toggle(editable);
  $salesOrderForm.find('[type=submit]').toggle(editable);
  $('#sales-order-edit').toggle(mode === 'VIEW' && appuCan('sales.manage') && salesOrderCurrent && salesOrderCurrent.order.status === 'Pendiente' && !salesOrderCurrent.order.quotation_id);
  $('#sales-order-cancel-edit').toggle(mode === 'EDIT');
  $('#sales-order-new').toggle(appuCan('sales.manage'));
  $('#sales-order-relations').toggle(mode === 'VIEW' && !!salesOrderCurrent);
  $('#sales-order-copy-delivery').toggle(mode === 'VIEW' && appuCan('logistics.manage') && !!salesOrderCurrent && salesOrderCurrent.order.status === 'Confirmada' && salesOrderCurrent.order.domain_status !== 'CLOSED');
});
function salesOrderBusy(busy, submitting) {
  salesOrderSaving = busy && !!submitting;
  $salesOrderForm.find('input,select').prop('disabled', busy || salesOrderState.get() === 'VIEW');
  $salesOrderForm.find('button').prop('disabled', busy);
  $salesOrderModal.find('[data-bs-dismiss]').prop('disabled', salesOrderSaving);
}
function salesOrderPathFields() {
  $('.sales-order-line').each(function (index) {
    $(this).find('.sales-order-product').attr('data-field-path', 'details[' + index + '].product_id');
    $(this).find('.sales-order-quantity').attr('data-field-path', 'details[' + index + '].quantity');
    $(this).find('.sales-order-price').attr('data-field-path', 'details[' + index + '].unit_price');
  });
}
function addSalesOrderLine(detail) {
  var $line = $('<div class="row sales-order-line mb-2"><div class="col-md-6"><select class="form-control sales-order-product" aria-label="Artículo"></select></div><div class="col-md-2"><input class="form-control sales-order-quantity" aria-label="Cantidad" type="number" min="1" step="1" value="1"></div><div class="col-md-3"><input class="form-control sales-order-price" aria-label="Precio sin IGV" type="number" min="0" step="0.01" value="0"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger sales-order-remove" title="Quitar línea"><i class="fas fa-times"></i></button></div></div>');
  var $select = $line.find('select').append($('<option>').val('').text('Seleccione artículo'));
  (salesOrderCatalog.products || []).forEach(function (p) { $select.append($('<option>').val(p.id).text(p.code + ' - ' + p.name).attr('data-price', p.unit_price)); });
  if (detail && !$select.find('option').filter(function () { return String(this.value) === String(detail.item_id); }).length) $select.append($('<option>').val(detail.item_id).text(detail.item_code + ' - ' + detail.item_name + ' (inactivo)'));
  $('#sales-order-lines').append($line);
  if (detail && detail.open_quantity != null) $('<small class="text-muted col-12">').text('Cantidad atendida: ' + (detail.processed_quantity || 0) + ' · Pendiente de entrega: ' + detail.open_quantity).appendTo($line);
  $select.select2({ dropdownParent: $salesOrderModal, width: '100%' });
  if (detail) { $select.val(String(detail.item_id)).trigger('change.select2'); $line.find('.sales-order-quantity').val(detail.item_quantity); $line.find('.sales-order-price').val(detail.item_unit_price); }
  $select.on('change', function () { $line.find('.sales-order-price').val((Number($select.find('option:selected').attr('data-price') || 0) * salesOrderPriceFactor).toFixed(2)); recalcSalesOrderTotal(); });
  $line.find('input').on('input', recalcSalesOrderTotal);
  $line.find('.sales-order-remove').on('click', function () { $line.remove(); salesOrderPathFields(); recalcSalesOrderTotal(); appuMarkDirty(); });
  salesOrderPathFields(); recalcSalesOrderTotal();
}
function recalcSalesOrderTotal() {
  var subtotal = 0;
  $('.sales-order-line').each(function () { subtotal += Math.round(Number($(this).find('.sales-order-quantity').val() || 0) * Number($(this).find('.sales-order-price').val() || 0) * 100) / 100; });
  $('#sales-order-total').text((subtotal + Math.round(subtotal * 18) / 100).toFixed(2));
}
function renderSalesOrderCustomFields(values) {
  appuRenderCustomFields($('#sales-order-custom-fields'), salesOrderCatalog.custom_fields, values, 'sales-order-custom-');
}
function salesOrderOption($select, value, label) {
  if (value && !$select.find('option').filter(function () { return String(this.value) === String(value); }).length) $select.append($('<option>').val(value).text(label + ' (inactivo)'));
  $select.val(String(value || '')).trigger('change.select2');
}
function salesOrderFill(snapshot) {
  salesOrderCurrent = snapshot;
  var order = snapshot.order;
  salesOrderOption($('#sales-order-customer'), order.customer_id, order.customer_name);
  salesOrderOption($('#sales-order-warehouse'), order.warehouse_id, 'Almacén ' + order.warehouse_id);
  $salesOrderForm.find('[name=delivery_date]').val(order.delivery_date);
  $salesOrderForm.find('[name=currency]').val(order.currency);
  $salesOrderForm.find('[name=reference]').val(order.reference);
  $('#sales-order-shipping-address').empty().append($('<option>').val(order.shipping_address_id || '').text(order.shipping_address || 'Dirección principal del cliente'));
  $('#sales-order-lines').empty(); snapshot.details.forEach(addSalesOrderLine);
  renderSalesOrderCustomFields(snapshot.custom_fields);
  $('#sales-order-identity').text(order.number + ' · ' + order.status);
  $('#sales-order-relations').attr('href', '../../views/ventas/flujo-comercial?type=sales_order&id=' + order.id);
  $('#sales-order-copy-delivery').attr('href', '../../views/logistica/guias?source_type=sales_order&source_id=' + order.id);
  appuClearValidation($salesOrderForm); appuMarkSaved();
}
function openSalesOrder(id) {
  appuConfirmDiscard(function () {
    var epoch = ++salesOrderEpoch;
    salesOrderBusy(true);
    $.getJSON('../../modules/ventas/ordenes.php', { catalog: 1 }).then(function (catalog) {
      if (epoch !== salesOrderEpoch) return;
      setSalesOrderCatalog(catalog);
      return $.getJSON('../../modules/ventas/ordenes.php', { view_id: id });
    }).done(function (snapshot) {
      if (epoch !== salesOrderEpoch || !snapshot) return;
      salesOrderFill(snapshot); salesOrderState.set('VIEW'); $salesOrderModal.modal('show');
    }).fail(function (xhr) { if (epoch === salesOrderEpoch) toastr.error(xhr.responseJSON && xhr.responseJSON.message || 'No se pudo consultar la orden.'); })
      .always(function () { if (epoch === salesOrderEpoch) salesOrderBusy(false); });
  });
}
function setSalesOrderCatalog(catalog) {
  salesOrderCatalog = catalog;
  [['#sales-order-customer', catalog.customers, function (c) { return (c.ruc || '') + ' - ' + c.business_name; }], ['#sales-order-warehouse', catalog.warehouses, function (w) { return w.code + ' - ' + w.name; }]].forEach(function (entry) {
    var $select = $(entry[0]).empty().append($('<option>').val('').text('Seleccione'));
    (entry[1] || []).forEach(function (row) { $select.append($('<option>').val(row.id).text(entry[2](row))); });
    if (!$select.hasClass('select2-hidden-accessible')) $select.select2({ dropdownParent: $salesOrderModal, width: '100%' });
    $select.val('').trigger('change.select2');
  });
}
function newSalesOrder() {
  appuConfirmDiscard(function () {
    var epoch = ++salesOrderEpoch;
    salesOrderCurrent = null; salesOrderPriceFactor = 1;
    $salesOrderForm[0].reset(); appuClearValidation($salesOrderForm);
    $('#sales-order-lines,#sales-order-custom-fields').empty();
    $('#sales-order-customer,#sales-order-warehouse').val('').trigger('change.select2');
    $('#sales-order-shipping-address').html('<option value="">Dirección principal del cliente</option>');
    $('#sales-order-total').text('0.00'); $('#sales-order-identity').text('');
    var today = new Date(); today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    $salesOrderForm.find('[name=delivery_date]').val(today.toISOString().slice(0, 10));
    salesOrderState.set('CREATE'); salesOrderBusy(true); $salesOrderModal.modal('show'); appuMarkSaved();
    $.getJSON('../../modules/ventas/ordenes.php', { catalog: 1 }).done(function (catalog) {
      if (epoch !== salesOrderEpoch) return;
      setSalesOrderCatalog(catalog); renderSalesOrderCustomFields({}); addSalesOrderLine();
    }).fail(function () { if (epoch === salesOrderEpoch) appuShowValidation($salesOrderForm, { message: 'No se pudo cargar el catálogo. Cierra y vuelve a abrir Nueva orden.' }); })
      .always(function () { if (epoch === salesOrderEpoch) salesOrderBusy(false); });
  });
}
$('#btn-new-sales-order').on('click', newSalesOrder);
$('#sales-order-new').on('click', newSalesOrder);
$('#sales-order-add-line').on('click', function () { addSalesOrderLine(); appuMarkDirty(); });
$('#sales-order-edit').on('click', function () { salesOrderEpoch++; salesOrderState.set('EDIT'); });
$('#sales-order-cancel-edit').on('click', function () { appuConfirmDiscard(function () { salesOrderEpoch++; salesOrderFill(salesOrderCurrent); salesOrderState.set('VIEW'); }); });
$salesOrderForm.on('input change', 'input,select', function () { if (['CREATE', 'EDIT'].includes(salesOrderState.get())) appuMarkDirty(); });
$salesOrderModal.on('hide.bs.modal', function (event) {
  if (salesOrderSaving) { event.preventDefault(); return; }
  if (window.appuFormDirty) { event.preventDefault(); appuConfirmDiscard(function () { $salesOrderModal.modal('hide'); }); return; }
  salesOrderEpoch++; salesOrderState.set('LIST');
});
$('#sales-order-customer').on('change', function () {
  var customerId = Number($(this).val()), customer = (salesOrderCatalog.customers || []).find(function (item) { return Number(item.id) === customerId; });
  var epoch = salesOrderEpoch;
  salesOrderPriceFactor = Math.max(.0001, Number(customer && customer.price_factor || 1));
  if (customer) $salesOrderForm.find('[name=currency]').val(customer.default_currency || 'PEN');
  var $select = $('#sales-order-shipping-address').html('<option value="">Dirección principal del cliente</option>');
  if (customer) $.getJSON('../../modules/clientes/listar-direcciones.php', { customer_id: customerId }).done(function (response) {
    if (epoch !== salesOrderEpoch || Number($('#sales-order-customer').val()) !== customerId || !['CREATE', 'EDIT'].includes(salesOrderState.get())) return;
    var preferred = '';
    (response.addresses || []).forEach(function (address) { $select.append($('<option>').val(address.id).text(address.address_name + ' · ' + address.address_line)); if (address.address_type === 'shipping' && Number(address.is_default) === 1) preferred = address.id; });
    $select.val(String(preferred));
  }).fail(function () { if (epoch === salesOrderEpoch) toastr.error('No se pudieron cargar las direcciones del cliente.'); });
  $('.sales-order-product').trigger('change');
});
$salesOrderForm.on('submit', function (event) {
  event.preventDefault();
  if (salesOrderSaving || !['CREATE', 'EDIT'].includes(salesOrderState.get())) return;
  var mode = salesOrderState.get(), behavior = event.originalEvent && event.originalEvent.submitter ? event.originalEvent.submitter.value : 'view';
  var details = [];
  $('.sales-order-line').each(function () { details.push({ product_id: $(this).find('.sales-order-product').val(), quantity: $(this).find('.sales-order-quantity').val(), unit_price: $(this).find('.sales-order-price').val() }); });
  var data = $salesOrderForm.serializeArray();
  data.push({ name: 'action', value: mode === 'EDIT' ? 'update' : 'create_direct' }, { name: 'details', value: JSON.stringify(details) });
  if (mode === 'EDIT') data.push({ name: 'order_id', value: salesOrderCurrent.order.id }, { name: 'version', value: salesOrderCurrent.version });
  salesOrderBusy(true, true); appuClearValidation($salesOrderForm);
  $.ajax({ url: '../../modules/ventas/ordenes.php', method: 'POST', dataType: 'json', data: data }).done(function (response) {
    appuMarkSaved(); toastr.success(response.message);
    salesOrderState.set('VIEW');
    $('#sales-order-identity').text(response.number + ' · Guardada');
    salesOrderBusy(false); listarOrdenesVenta();
    if (behavior === 'new') newSalesOrder(); else openSalesOrder(response.order_id);
  }).fail(function (xhr) { salesOrderBusy(false); appuShowValidation($salesOrderForm, xhr.responseJSON || { message: 'No se pudo guardar la orden. Intenta nuevamente.' }); });
});
$(document).on('click', '.js-order-view', function () { openSalesOrder(Number($(this).data('id'))); });

function listarOrdenesVenta() {
  var epoch = ++salesOrderListEpoch;
  var filters = $('#sales-order-search-form').serializeArray();
  filters.push({ name: 'search', value: '1' }, { name: 'page', value: salesOrderPage }, { name: 'status', value: $('#sales-order-status-filter').val() || '' });
  $.getJSON("../../modules/ventas/ordenes.php", filters)
    .done(function (response) {
      if (epoch !== salesOrderListEpoch) return;
      var pagination = response.pagination || { page: 1, pages: 1, total: (response.orders || []).length };
      salesOrderPages = pagination.pages;
      $('#sales-order-page-info').text('Página ' + pagination.page + ' de ' + Math.max(1, pagination.pages) + ' · ' + pagination.total + ' registros');
      $('#sales-order-page-prev').prop('disabled', pagination.page <= 1);
      $('#sales-order-page-next').prop('disabled', pagination.page >= pagination.pages);
      appuClearValidation($('#sales-order-search-form'));
      tbl_sales_orders.clear();
      (response.orders || []).forEach(function (order) {
        tbl_sales_orders.rows.add([{
          0: order.id,
          1: appuEscapeHtml(order.number),
          2: appuEscapeHtml(order.quotation_number || "-"),
          3: appuEscapeHtml(order.customer_name),
          4: appuEscapeHtml(order.issue_date),
          5: appuEscapeHtml(order.delivery_date),
          6: Number(order.total_net || 0).toFixed(2) + " " + appuEscapeHtml(order.currency || ""),
          7: appuStatusBadge(order.status),
          8: appuStatusBadge(order.delivery_status || "Pendiente"),
          9: '<div class="btn-group btn-group-sm" role="group" aria-label="Acciones de la orden">' +
            '<button type="button" class="btn btn-outline-primary js-order-view" data-id="' + order.id + '" title="Ver orden"><i class="fas fa-eye"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary js-order-history" data-id="' + order.id + '" title="Historial"><i class="fas fa-history"></i></button>' +
            (appuCan("sales.manage") && order.status === "Pendiente" ? '<button type="button" class="btn btn-outline-success js-order-confirm" data-id="' + order.id + '" title="Confirmar"><i class="fas fa-check"></i></button>' : '') +
            (appuCan("sales.manage") && order.status !== "Anulada" && order.status !== "Atendida" ? '<button type="button" class="btn btn-outline-danger js-order-cancel" data-id="' + order.id + '" data-number="' + appuEscapeHtml(order.number) + '" title="Anular"><i class="fas fa-ban"></i></button>' : '') +
            '</div>'
        }]);
      });
      tbl_sales_orders.draw();
      var requestedId = !window.appuRequestedRecordHandled && new URLSearchParams(window.location.search).get("record_id");
      if (requestedId) {
        var target = tbl_sales_orders.rows().indexes().filter(function (index) { return String(tbl_sales_orders.row(index).data()[0]) === String(requestedId); });
        window.appuRequestedRecordHandled = true;
        openSalesOrder(Number(requestedId));
        if (target.length) {
          var node = tbl_sales_orders.row(target[0]).node();
          $(node).addClass("table-primary");
          node.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }
    }).fail(function (xhr) { if (epoch !== salesOrderListEpoch) return; appuShowValidation($('#sales-order-search-form'), xhr.responseJSON || { message: 'No se pudo cargar el listado de órdenes. Usa Actualizar para reintentar.' }); });
}

listarOrdenesVenta();
$("#btn-refresh-sales-orders").on("click", listarOrdenesVenta);
$("#sales-order-status-filter").on("change", function () { salesOrderPage = 1; listarOrdenesVenta(); });
$('#sales-order-search-form').on('submit', function (event) { event.preventDefault(); salesOrderPage = 1; $('#table-sales-orders').attr('data-document-mode', 'SEARCH'); listarOrdenesVenta(); });
$('#sales-order-search-clear').on('click', function () { $('#sales-order-search-form')[0].reset(); $('#sales-order-status-filter').val(''); $('#sales-order-search-field').trigger('change'); salesOrderPage = 1; $('#table-sales-orders').attr('data-document-mode', 'LIST'); listarOrdenesVenta(); });
$('#sales-order-page-prev').on('click', function () { if (salesOrderPage > 1) { salesOrderPage--; listarOrdenesVenta(); } });
$('#sales-order-page-next').on('click', function () { if (salesOrderPage < salesOrderPages) { salesOrderPage++; listarOrdenesVenta(); } });
var salesOrderSearchFields = [];
$.getJSON('../../modules/ventas/ordenes.php', { catalog: 1 }).done(function (data) {
  salesOrderSearchFields = (data.custom_fields || []).filter(function (field) { return Number(field.searchable) === 1; });
  salesOrderSearchFields.forEach(function (field) { $('#sales-order-search-field').append($('<option>').val(field.id).text(field.label)); });
}).fail(function () { toastr.error('No se pudieron cargar los campos de búsqueda. Actualiza la página para reintentar.'); });
$('#sales-order-search-field').on('change', function () {
  var field = salesOrderSearchFields.find(function (item) { return String(item.id) === String($('#sales-order-search-field').val()); });
  var $old = $('#sales-order-search-value'), $new;
  if (field && (field.field_type === 'select' || field.field_type === 'checkbox')) {
    $new = $('<select>');
    var options = field.field_type === 'checkbox' ? [{ value_code: '1', label: 'Sí' }, { value_code: '0', label: 'No' }] : field.options;
    (options || []).forEach(function (option) { $new.append($('<option>').val(option.value_code).text(option.label)); });
  } else $new = $('<input>').attr('type', field && field.field_type === 'date' ? 'date' : 'text');
  $new.attr({ id: 'sales-order-search-value', name: 'custom_field_value', class: 'form-control' }).prop('disabled', !field); $old.replaceWith($new);
});
$('#sales-order-search-field').trigger('change');

$("#table-sales-orders").contextMenu({
  selector: "tbody tr",
  callback: function (key) {
    var row = tbl_sales_orders.row(this).data();
    if (!row) return;

    if (key === "history") {
      mostrarHistorialEstados("sales_order", row[0]);
      return;
    }

    if (key !== "confirm" && key !== "cancel") return;
    var requestData = { action: key, order_id: row[0] };
    if (key === "cancel") {
      var askReason = window.Swal ? Swal.fire({
        title: "Anular orden " + row[1],
        input: "textarea",
        inputLabel: "Motivo de anulacion",
        inputPlaceholder: "Ej.: cliente cancelo el pedido...",
        inputAttributes: { maxlength: 500, "aria-label": "Motivo de anulacion" },
        showCancelButton: true,
        confirmButtonText: "Anular orden",
        cancelButtonText: "Volver",
        confirmButtonColor: "#b42318",
        inputValidator: function (value) {
          return !value || value.trim().length < 5 ? "Indica un motivo de al menos 5 caracteres." : undefined;
        }
      }) : Promise.resolve({ isConfirmed: window.confirm("¿Anular la orden " + row[1] + "? Indica el motivo en el siguiente campo.") });
      askReason.then(function (result) {
        if (!result || !result.isConfirmed) return;
        var reason = result.value || window.prompt("Motivo de anulacion:", "");
        if (!reason || reason.trim().length < 5) { toastr.warning("La anulacion requiere un motivo de al menos 5 caracteres."); return; }
        requestData.cancel_reason = reason.trim();
        submitSalesOrderStatus(requestData);
      });
      return;
    }
    submitSalesOrderStatus(requestData);
  },
  items: {
    history: { name: "Ver historial", icon: "paste" },
    confirm: { name: "Confirmar", icon: "paste", visible: function () { var row = tbl_sales_orders.row(this).data(); return appuCan("sales.manage") && row && String(row[7]).toLowerCase().indexOf("pendiente") !== -1; } },
    cancel: { name: "Anular", icon: "delete", visible: function () { var row = tbl_sales_orders.row(this).data(); return appuCan("sales.manage") && row && String(row[7]).toLowerCase().indexOf("anulada") === -1 && String(row[7]).toLowerCase().indexOf("atendida") === -1; } },
    quit: { name: "Cerrar", icon: "quit" }
  }
});

function submitSalesOrderStatus(requestData) {
    $.ajax({
      url: "../../modules/ventas/ordenes.php",
      method: "POST",
      dataType: "json",
      data: requestData
    }).done(function (response) {
      toastr.success(response.message || "Orden actualizada.");
      listarOrdenesVenta();
    }).fail(function (xhr) {
      toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "No se pudo actualizar la orden.");
    });
}

$(document).on("click", ".js-order-history", function () { mostrarHistorialEstados("sales_order", Number($(this).data("id"))); });
$(document).on("click", ".js-order-confirm", function () { submitSalesOrderStatus({ action: "confirm", order_id: Number($(this).data("id")) }); });
$(document).on("click", ".js-order-cancel", function () {
  var id = Number($(this).data("id"));
  var number = String($(this).data("number") || "");
  var reason = window.prompt("Motivo de anulacion de la orden " + number + " (minimo 5 caracteres):", "");
  if (reason === null) return;
  if (reason.trim().length < 5) return toastr.warning("Indica un motivo de al menos 5 caracteres.");
  submitSalesOrderStatus({ action: "cancel", order_id: id, cancel_reason: reason.trim() });
});
