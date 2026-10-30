const { test } = require('node:test');
const assert = require('node:assert/strict');
const { setup } = require('./ui-harness.cjs');

const sources = { sources: [{ source_type: 'sales_order', id: 10, document_number: 'OV-10', customer_name: 'A' }, { source_type: 'sales_order', id: 20, document_number: 'OV-20', customer_name: 'B' }], custom_fields: [{ id: 7, label: 'Motivo', field_type: 'text', required_status: 1 }] };
const source = id => ({ source: { customer_name: 'Cliente ' + id, ruc: 'QA', shipping_address: 'Destino ' + id, warehouse_id: 1 }, details: [{ source_detail_id: id, item_code: 'ART', description: 'Artículo', ordered_quantity: 10, delivered_quantity: 6, pending_quantity: 4 }] });

async function guideSetup() {
  const t = await setup('views/logistica/guias.php', ['ajax/guias.js']);
  t.pending(r => !r.data)[0].deferred.resolve({ guides: [] });
  t.$('#btn-new-remission-guide').trigger('click');
  t.pending(r => r.data?.action === 'sources').at(-1).deferred.resolve(structuredClone(sources));
  t.pending(r => r.url.includes('warehouses')).at(-1).deferred.resolve({ warehouses: [{ id: 1, code: 'A', name: 'Principal', active_status: 1 }] });
  return t;
}

test('Entrega ignora el origen anterior y Nuevo limpia cabecera, líneas y UDF', async () => {
  const t = await guideSetup(); const { $ } = t;
  try {
    $('#guide-source').val('sales_order:10').trigger('change'); const old = t.pending(r => r.data?.action === 'source')[0];
    $('#guide-source').val('sales_order:20').trigger('change'); t.pending(r => r.data?.action === 'source').at(-1).deferred.resolve(source(20)); old.deferred.resolve(source(10));
    assert.equal($('[name=recipient_name]').val(), 'Cliente 20');
    assert.equal($('.js-guide-quantity').attr('data-source-detail-id'), '20');
    $('#guide-custom-7').val('Anterior'); const key = $('#remission-guide-form [name=request_key]').val();
    $('#btn-new-remission-guide').trigger('click');
    assert.equal($('[name=recipient_name]').val(), ''); assert.equal($('.js-guide-quantity').length, 0); assert.equal($('#guide-custom-fields').text(), '');
    assert.notEqual($('#remission-guide-form [name=request_key]').val(), key);
    assert.equal($('#remission-guide-form').attr('data-document-mode'), 'CREATE');
  } finally { t.dom.window.close(); }
});

test('Entrega muestra todos los errores y conserva datos/identificador para reintentar', async () => {
  const t = await guideSetup(); const { $ } = t;
  try {
    $('#guide-source').val('sales_order:10').trigger('change'); t.pending(r => r.data?.action === 'source')[0].deferred.resolve(source(10));
    $('.js-guide-quantity').val('5'); $('#guide-custom-7').val('Conservar');
    const key = $('#remission-guide-form [name=request_key]').val();
    $('#remission-guide-form').trigger('submit'); $('#remission-guide-form').trigger('submit');
    assert.equal(t.requests.filter(r => r.method === 'POST').length, 1);
    t.pending(r => r.method === 'POST')[0].deferred.reject({ responseJSON: { message: 'Corrige los campos', errors: [{ path: 'lines[10].quantity', message: 'Solo quedan 4 unidades pendientes.' }, { path: 'sender_name', message: 'Falta remitente' }] } });
    assert.equal($('.appu-validation-summary li').length, 2); assert.equal($('.is-invalid').length, 2);
    assert.equal($('.js-guide-quantity').val(), '5'); assert.equal($('#guide-custom-7').val(), 'Conservar'); assert.equal($('#remission-guide-form [name=request_key]').val(), key);
    $('.js-guide-quantity').val('4'); $('#remission-guide-form').trigger('submit');
    t.pending(r => r.method === 'POST')[0].deferred.resolve({ guide_id: 99, message: 'Entrega registrada' });
    assert.equal($('#remission-guide-form').attr('data-document-mode'), 'LIST'); assert.equal(t.window.appuFormDirty, false);
  } finally { t.dom.window.close(); }
});

const preview = { order: { number: 'OV-1', customer_name: 'Cliente QA', currency: 'PEN', discount_rate: 0 }, series: ['F001'], lines: [{ guide_id: 1, guide_number: 'EN-1', guide_line_id: 10, item_code: 'ART', item_name: 'Artículo QA', unit_price: 10, open_quantity: 6 }], custom_fields: [{ id: 8, label: 'Canal', field_type: 'select', required_status: 1, options: [{ value_code: 'CORP', label: 'Corporativo' }] }], custom_values: { 8: 'CORP' } };
async function billingSetup() {
  const t = await setup('views/logistica/guias.php', ['js/delivery-billing.js']);
  t.$('body').append('<button class="js-guide-invoice" data-id="1">Copiar a factura</button>');
  t.$('.js-guide-invoice').trigger('click'); t.pending(r => r.data?.action === 'delivery_billing_preview')[0].deferred.resolve(structuredClone(preview));
  return t;
}

test('Factura de entrega conserva origen y UDF, recalcula total y queda en VIEW después de guardar', async () => {
  const t = await billingSetup(); const { $ } = t;
  try {
    assert.equal($('#delivery-billing-custom-8').val(), 'CORP'); assert.match($('#delivery-billing-total').text(), /70\.80 PEN/);
    $('.billing-line-quantity').val('4').trigger('input'); assert.match($('#delivery-billing-total').text(), /47\.20 PEN/);
    $('#delivery-billing-form').trigger('submit'); $('#delivery-billing-form').trigger('submit');
    const post = t.pending(r => r.method === 'POST')[0];
    const payload = JSON.parse(post.data.find(field => field.name === 'lines').value);
    assert.deepEqual(payload, [{ guide_id: 1, guide_line_id: 10, quantity: '4' }]);
    post.deferred.resolve({ invoice_id: 5, number: 'F001-5', total: 47.2, currency: 'PEN', message: 'Factura creada' });
    assert.equal($('#delivery-billing-form').attr('data-document-mode'), 'VIEW');
    assert.match($('#delivery-billing-result a').attr('href'), /record_id=5/);
    $('#delivery-billing-form').trigger('submit'); assert.equal(t.requests.filter(r => r.method === 'POST').length, 1);
    $('.js-guide-invoice').trigger('click'); assert.notEqual($('#delivery-billing-form [type=submit]').css('display'), 'none');
  } finally { t.dom.window.close(); }
});

test('Factura inválida conserva cantidades/UDF y usa el mismo request_key en el reintento', async () => {
  const t = await billingSetup(); const { $ } = t;
  try {
    const key = $('#delivery-billing-form [name=request_key]').val();
    $('#delivery-billing-form').trigger('submit');
    t.pending(r => r.method === 'POST')[0].deferred.reject({ responseJSON: { message: 'Corrige los campos', errors: [{ path: 'custom_field[8]', message: 'Canal obligatorio' }] } });
    assert.equal($('#delivery-billing-custom-8').val(), 'CORP'); assert.equal($('.billing-line-quantity').val(), '6'); assert.equal($('.is-invalid').length, 1);
    $('#delivery-billing-form').trigger('submit'); const post = t.pending(r => r.method === 'POST')[0]; assert.equal(post.data.find(field => field.name === 'request_key').value, key);
  } finally { t.dom.window.close(); }
});
