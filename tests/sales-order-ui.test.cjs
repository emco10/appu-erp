const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const root = path.resolve(__dirname, '..');

const catalog = {
  customers: [{ id: 1, business_name: 'Cliente A', ruc: 'QA1', default_currency: 'PEN', price_factor: 1 }, { id: 2, business_name: 'Cliente B', ruc: 'QA2', default_currency: 'USD', price_factor: 2 }],
  warehouses: [{ id: 1, code: 'A', name: 'Principal' }],
  products: [{ id: 1, code: 'ART1', name: 'Artículo QA', unit_price: 10 }],
  custom_fields: [{ id: 7, label: 'Canal', field_type: 'select', required_status: 1, options: [{ value_code: 'MARKETPLACE', label: 'Marketplace' }] }]
};
const snapshot = { order: { id: 55, number: 'OV-55', status: 'Pendiente', customer_id: 1, warehouse_id: 1, delivery_date: '2026-10-30', currency: 'PEN', reference: 'Original', shipping_address: 'Entrega original', quotation_id: null }, details: [{ item_id: 1, item_quantity: 3, item_unit_price: 10 }], custom_fields: { 7: 'MARKETPLACE' }, version: 'version-1' };

async function setup() {
  const html = fs.readFileSync(path.join(root, 'views/ventas/resumen-ordenes-venta.php'), 'utf8').replace(/<\?php[\s\S]*?\?>/g, '');
  const dom = new JSDOM(html, { url: 'http://localhost/views/ventas/resumen-ordenes-venta', runScripts: 'outside-only', pretendToBeVisual: true });
  const window = dom.window, $ = jquery(window), requests = [], notifications = [];
  window.$ = window.jQuery = $;
  window.HTMLElement.prototype.scrollIntoView = function () {};
  $.fn.select2 = function (action) {
    if (typeof action !== 'string') this.each(function () { $(this).addClass('select2-hidden-accessible'); if (!$(this).next('.select2').length) $(this).after('<span class="select2"><span class="select2-selection" tabindex="0"></span></span>'); });
    return this;
  };
  $.fn.modal = function (action) {
    if (action === 'hide') { const event = $.Event('hide.bs.modal'); this.trigger(event); if (!event.isDefaultPrevented()) this.removeClass('show'); }
    else if (action === 'show') this.addClass('show');
    return this;
  };
  const tableRows = [];
  const table = { columns: () => ({ visible() {} }), rows: Object.assign(() => ({ indexes: () => [], nodes: () => [] }), { add: rows => tableRows.push(...rows) }), clear: () => { tableRows.length = 0; }, draw() {}, row: () => ({ data: () => null }) };
  $.fn.DataTable = () => table;
  $.fn.dataTable = { ext: { search: [] } };
  $.fn.contextMenu = function () { return this; };
  function request(url, data, method) { const deferred = $.Deferred(); requests.push({ url, data, method, deferred }); return deferred.promise(); }
  $.getJSON = (url, data) => request(url, data, 'GET');
  $.ajax = options => request(options.url, options.data, options.method);
  window.toastr = Object.fromEntries(['error', 'success', 'warning', 'info'].map(name => [name, message => notifications.push({ name, message })]));
  window.appuCan = () => true;
  window.appuEscapeHtml = value => $('<div>').text(value).html();
  window.appuStatusBadge = value => value;
  window.appuMarkSaved = () => { window.appuFormDirty = false; };
  window.appuMarkDirty = () => { window.appuFormDirty = true; };
  window.appuConfirmDiscard = callback => { window.appuMarkSaved(); callback(); };
  window.mostrarHistorialEstados = () => {};
  window.eval(fs.readFileSync(path.join(root, 'js/document-ui.js'), 'utf8'));
  window.eval(fs.readFileSync(path.join(root, 'ajax/resumen-ordenes-venta.js'), 'utf8'));
  requests.filter(r => r.method === 'GET').forEach(r => r.deferred.resolve(r.data?.catalog ? structuredClone(catalog) : { orders: [], pagination: { page: 1, pages: 0, total: 0 } }));
  await new Promise(resolve => setTimeout(resolve, 5));
  const pending = predicate => requests.filter(predicate).filter(r => r.deferred.state() === 'pending');
  const resolveCatalog = () => pending(r => r.data?.catalog).at(-1).deferred.resolve(structuredClone(catalog));
  return { dom, window, $, requests, notifications, pending, resolveCatalog, flush: () => new Promise(resolve => setTimeout(resolve, 10)) };
}

test('Nueva orden descarta datos y respuestas de una apertura anterior', async () => {
  const t = await setup(); const { $ } = t;
  try {
    $('#btn-new-sales-order').trigger('click');
    const old = t.pending(r => r.data?.catalog)[0];
    $('#btn-new-sales-order').trigger('click'); t.resolveCatalog();
    old.deferred.resolve({ customers: [], warehouses: [], products: [], custom_fields: [] });
    assert.equal($('.sales-order-line').length, 1);
    assert.equal($('#sales-order-customer option').length, 3);
    $('#sales-order-customer').val('1').trigger('change');
    const addressRequest = t.pending(r => r.url.includes('listar-direcciones'))[0];
    $('#btn-new-sales-order').trigger('click'); t.resolveCatalog();
    addressRequest.deferred.resolve({ addresses: [{ id: 99, address_name: 'Obsoleta', address_line: 'Anterior', address_type: 'shipping', is_default: 1 }] });
    assert.equal($('#sales-order-customer').val(), '');
    assert.equal($('#sales-order-shipping-address option').length, 1);
    assert.equal($('#sales-order-custom-7').val(), '');
    assert.equal(t.window.salesOrderState.get(), 'CREATE');
    assert.equal($('#sales-order-total').text(), '0.00');
  } finally { t.dom.window.close(); }
});

test('Buscar declara SEARCH y descarta resultados de filtros anteriores', async () => {
  const t = await setup(); const { $ } = t;
  try {
    $('#sales-order-search-text').val('Anterior'); $('#sales-order-search-form').trigger('submit');
    const old = t.pending(r => r.url.includes('ordenes.php'))[0];
    $('#sales-order-search-text').val('Actual'); $('#sales-order-search-form').trigger('submit');
    const current = t.pending(r => r.url.includes('ordenes.php')).at(-1);
    current.deferred.resolve({ orders: [], pagination: { page: 1, pages: 1, total: 2 } });
    old.deferred.resolve({ orders: [], pagination: { page: 1, pages: 50, total: 1000 } });
    assert.equal($('#table-sales-orders').attr('data-document-mode'), 'SEARCH');
    assert.match($('#sales-order-page-info').text(), /2 registros/);
    $('#sales-order-search-clear').trigger('click');
    assert.equal($('#sales-order-search-text').val(), '');
    assert.equal($('#table-sales-orders').attr('data-document-mode'), 'LIST');
  } finally { t.dom.window.close(); }
});

test('Consultar, editar y cancelar restaura cabecera, líneas y UDF', async () => {
  const t = await setup(); const { $ } = t;
  try {
    t.window.openSalesOrder(55); t.resolveCatalog(); await t.flush();
    t.pending(r => r.data?.view_id)[0].deferred.resolve(structuredClone(snapshot)); await t.flush();
    assert.equal(t.window.salesOrderState.get(), 'VIEW');
    assert.equal($('.sales-order-quantity').prop('disabled'), true);
    assert.equal($('#sales-order-custom-7').prop('disabled'), true);
    assert.equal($('#sales-order-total').text(), '35.40');
    $('#sales-order-edit').trigger('click');
    assert.equal(t.window.salesOrderState.get(), 'EDIT');
    assert.equal($('.sales-order-quantity').prop('disabled'), false);
    $('.sales-order-quantity').val('9').trigger('input');
    $('[name=reference]').val('Modificada').trigger('input');
    $('#sales-order-custom-7').val('');
    $('#sales-order-cancel-edit').trigger('click');
    assert.equal(t.window.salesOrderState.get(), 'VIEW');
    assert.equal($('.sales-order-quantity').val(), '3');
    assert.equal($('[name=reference]').val(), 'Original');
    assert.equal($('#sales-order-custom-7').val(), 'MARKETPLACE');
    assert.equal(t.window.appuFormDirty, false);
  } finally { t.dom.window.close(); }
});

test('Errores múltiples mantienen datos, marcan campos y enfocan el primer error', async () => {
  const t = await setup(); const { $ } = t;
  try {
    $('#btn-new-sales-order').trigger('click'); t.resolveCatalog();
    $('[name=reference]').val('No perder');
    $('#sales-order-create-form').trigger('submit');
    const post = t.pending(r => r.method === 'POST')[0];
    assert.equal($('[name=reference]').prop('disabled'), true);
    post.deferred.reject({ responseJSON: { message: 'Corrige los campos', errors: [{ path: 'customer_id', message: 'Falta cliente' }, { path: 'details[0].quantity', message: 'Cantidad inválida' }, { path: 'custom_field[7]', message: 'Canal obligatorio' }] } });
    assert.equal($('.appu-validation-summary li').length, 3);
    assert.equal($('.is-invalid').length, 3);
    assert.equal($('[name=reference]').val(), 'No perder');
    assert.equal(t.window.document.activeElement.className, 'select2-selection');
    assert.equal($('[name=reference]').prop('disabled'), false);
  } finally { t.dom.window.close(); }
});

test('Guardar exitoso y fallo de recarga no permite repetir el alta', async () => {
  const t = await setup(); const { $ } = t;
  try {
    $('#btn-new-sales-order').trigger('click'); t.resolveCatalog();
    $('#sales-order-create-form').trigger('submit');
    t.pending(r => r.method === 'POST')[0].deferred.resolve({ order_id: 55, number: 'OV-55', message: 'Guardada' });
    t.pending(r => r.data?.catalog).at(-1).deferred.reject({}); await t.flush();
    assert.equal(t.window.salesOrderState.get(), 'VIEW');
    $('#sales-order-create-form').trigger('submit');
    assert.equal(t.requests.filter(r => r.method === 'POST').length, 1);
    assert.match($('#sales-order-identity').text(), /OV-55/);
  } finally { t.dom.window.close(); }
});
