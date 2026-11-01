const test = require('node:test');
const assert = require('node:assert/strict');
const { setup } = require('./ui-harness.cjs');
const definitions = [{ id: 9, label: 'Canal', field_type: 'select', visible: 1, searchable: 1, default_value: 'WEB', options: [{ value_code: 'WEB', label: 'Web' }, { value_code: 'TIENDA', label: 'Tienda' }] }];
const list = { records: [{ id: 1, code: 'UNO', name: 'Primero', brand: 'APPU', active_status: 1 }, { id: 2, code: 'DOS', name: 'Segundo', brand: 'APPU', active_status: 0 }], pagination: { page: 1, pages: 1, total: 2, per_page: 25 } };
function record(id = 1, custom = { 9: 'TIENDA' }) {
  return { id, version: 'version-' + id, definitions: structuredClone(definitions), custom_fields: custom, values: {
    producto_id: String(id), producto_codigo: 'PROD-' + id, producto_code: 'CODE-' + id, producto_nombre: 'Artículo ' + id,
    producto_marca: 'Marca', producto_precio: '15.50', producto_unitvalue: 'und', producto_tracking_type: 'SERIAL', producto_estado: false
  } };
}
async function start(query = '') {
  const t = await setup('views/productos/registro-producto.php', ['js/master-workspace.js', 'ajax/registro-producto.js'], query);
  t.resolveCatalog = () => t.pending(r => r.data?.action === 'catalog').at(-1).deferred.resolve({ custom_fields: structuredClone(definitions) });
  t.resolveSearch = (data = list) => t.pending(r => r.data?.action === 'search').at(-1).deferred.resolve(structuredClone(data));
  t.open = async (id = 1, custom) => { t.window.appuProductoWorkspace.open(id); t.pending(r => r.data?.action === 'get').at(-1).deferred.resolve(record(id, custom)); await t.flush(); };
  t.saveRequests = () => t.requests.filter(r => r.method === 'POST');
  if (!query) t.resolveCatalog(); t.resolveSearch(); await t.flush();
  return t;
}

test('Nuevo invalida fichas y catálogos anteriores y limpia todos los campos', async () => {
  const t = await start(); const { $, window } = t;
  try {
    await t.open();
    window.appuProductoWorkspace.open(2);
    const lateRecord = t.pending(r => r.data?.action === 'get')[0];
    $('#btn-new').trigger('click'); const lateCatalog = t.pending(r => r.data?.action === 'catalog')[0];
    $('[data-master-action=new]').trigger('click'); t.resolveCatalog();
    lateCatalog.deferred.resolve({ custom_fields: [] }); lateRecord.deferred.resolve(record(2)); await t.flush();
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'CREATE');
    assert.equal($('[name=producto_id]').val(), ''); assert.equal($('[name=producto_code]').val(), '');
    assert.equal($('[name="custom_field[9]"]').val(), 'WEB'); assert.equal($('#prod_estado').prop('checked'), false);
    assert.equal($('[name=producto_tracking_type]').val(), 'NONE');
    assert.equal($('[data-master-action=save]').prop('disabled'), false);
  } finally { t.dom.window.close(); }
});

test('El seguimiento por artículo ofrece Ninguno, Serie y Lote y conserva la selección', async () => {
  const t = await start(); const { $ } = t;
  try {
    const $tracking = $('[name=producto_tracking_type]');
    assert.deepEqual($tracking.find('option').map((_, option) => option.value).get(), ['NONE', 'SERIAL', 'LOT']);
    await t.open();
    assert.equal($tracking.val(), 'SERIAL');
    $('[data-master-action=edit]').trigger('click');
    $tracking.val('LOT').trigger('change');
    $('#FRM_INSERT_PRODUCTO').trigger('submit');
    assert.equal(t.saveRequests()[0].data.get('producto_tracking_type'), 'LOT');
  } finally { t.dom.window.close(); }
});

test('Consultar es solo lectura y Cancelar restaura la ficha sin heredar UDF', async () => {
  const t = await start(); const { $ } = t;
  try {
    await t.open(); assert.equal($('[name=producto_nombre]').prop('disabled'), true);
    $('#FRM_INSERT_PRODUCTO').trigger('submit'); assert.equal(t.saveRequests().length, 0);
    $('[data-master-action=edit]').trigger('click');
    $('[name=producto_nombre]').val('Cambio'); $('[name="custom_field[9]"]').val('WEB');
    $('[data-master-action=cancel]').trigger('click');
    assert.equal($('[name=producto_nombre]').val(), 'Artículo 1'); assert.equal($('[name="custom_field[9]"]').val(), 'TIENDA');
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'VIEW');
    await t.open(2, {}); assert.equal($('[name="custom_field[9]"]').val(), '');
    assert.equal($('[name=producto_id]').val(), '2');
  } finally { t.dom.window.close(); }
});

test('Errores y fallo de red conservan valores y la misma clave de reintento', async () => {
  const t = await start(); const { $ } = t;
  try {
    $('[name=producto_code]').val('NUEVO'); $('[name=producto_nombre]').val('Mi artículo');
    $('#FRM_INSERT_PRODUCTO').trigger('submit'); $('#FRM_INSERT_PRODUCTO').trigger('submit');
    assert.equal(t.saveRequests().length, 1);
    const request = t.saveRequests()[0]; assert.equal(request.data.get('producto_code'), 'NUEVO');
    request.deferred.reject({ status: 422, responseJSON: { message: 'Corrige estos campos', errors: [{ path: 'producto_marca', message: 'Falta marca' }, { path: 'producto_precio', message: 'Falta precio' }] } }); await t.flush();
    assert.equal($('.appu-field-error').length, 2); assert.equal($('[name=producto_nombre]').val(), 'Mi artículo');
    assert.equal(t.window.document.activeElement.name, 'producto_marca');
    $('#FRM_INSERT_PRODUCTO').trigger('submit'); const retry = t.saveRequests()[1];
    assert.equal(retry.data.get('request_key'), request.data.get('request_key'));
    retry.deferred.reject({ status: 0 }); await t.flush();
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'CREATE');
    assert.equal($('[name=producto_code]').val(), 'NUEVO'); assert.equal($('[data-master-action=save]').prop('disabled'), false);
  } finally { t.dom.window.close(); }
});

test('Guardar confirma una sola vez; Editar envía versión y Guardar y nuevo limpia la identidad', async () => {
  const t = await start(); const { $ } = t;
  try {
    $('#FRM_INSERT_PRODUCTO').trigger('submit'); t.saveRequests()[0].deferred.resolve({ success: true, snapshot: record(7) }); await t.flush();
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'VIEW'); assert.equal($('[name=producto_id]').val(), '7');
    $('#FRM_INSERT_PRODUCTO').trigger('submit'); assert.equal(t.saveRequests().length, 1);
    $('[data-master-action=edit]').trigger('click'); $('[data-master-action=save-new]').trigger('click');
    const second = t.saveRequests()[1]; assert.equal(second.data.get('version'), 'version-7'); assert.equal(second.data.get('producto_id'), '7');
    assert.notEqual(second.data.get('request_key'), t.saveRequests()[0].data.get('request_key'));
    second.deferred.resolve({ success: true, snapshot: record(7) }); await t.flush(); t.resolveCatalog(); await t.flush();
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'CREATE'); assert.equal($('[name=producto_id]').val(), '');
    assert.equal($('[name="custom_field[9]"]').val(), 'WEB');
  } finally { t.dom.window.close(); }
});

test('Búsqueda descarta resultados tardíos y una navegación pendiente no pisa Nuevo', async () => {
  const t = await start(); const { $, window } = t;
  try {
    $('[data-master-action=find]').trigger('click'); const old = t.pending(r => r.data?.action === 'search')[0];
    $('[data-master-status]').val('0').trigger('change');
    t.resolveSearch({ records: [{ id: 2, code: 'DOS', name: '<script>error</script>', brand: 'APPU', active_status: 0 }], pagination: { page: 1, pages: 2, total: 26, per_page: 25 } });
    old.deferred.resolve(structuredClone(list)); await t.flush();
    assert.equal($('#appu-buscar-producto-modal tbody tr').length, 1); assert.equal($('#appu-buscar-producto-modal tbody script').length, 0);
    $('[data-master-select=2]').trigger('click'); assert.equal(t.pending(r => r.data?.action === 'get').length, 1);
    t.pending(r => r.data?.action === 'get')[0].deferred.resolve(record(2)); await t.flush();
    $('[data-master-action=next]').trigger('click'); const page = t.pending(r => r.data?.action === 'search').at(-1);
    assert.equal(page.data.page, 2); window.appuProductoWorkspace.newRecord(); t.resolveCatalog();
    page.deferred.resolve({ records: [list.records[0]], pagination: { page: 2, pages: 2, total: 26, per_page: 25 } }); await t.flush();
    assert.equal(t.pending(r => r.data?.action === 'get').length, 0); assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'CREATE');
  } finally { t.dom.window.close(); }
});

test('Una consulta fallida conserva los cambios y la protección al salir', async () => {
  const t = await start(); const { $, window } = t;
  try {
    await t.open(); $('[data-master-action=edit]').trigger('click'); $('[name=producto_nombre]').val('Pendiente'); window.appuMarkDirty();
    window.appuProductoWorkspace.open(2); t.pending(r => r.data?.action === 'get')[0].deferred.reject({ status: 500 }); await t.flush();
    assert.equal($('[name=producto_nombre]').val(), 'Pendiente'); assert.equal(window.appuFormDirty, true);
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'EDIT');
  } finally { t.dom.window.close(); }
});

test('Navegación atraviesa páginas y respeta la decisión de seguir editando', async () => {
  const t = await start(); const { $, window } = t;
  try {
    t.window.appuProductoWorkspace.find();
    t.resolveSearch({ records: [list.records[1]], pagination: { page: 1, pages: 2, total: 26, per_page: 25 } }); await t.flush();
    await t.open(2); $('[data-master-action=next]').trigger('click');
    t.resolveSearch({ records: [list.records[0]], pagination: { page: 2, pages: 2, total: 26, per_page: 25 } }); await t.flush();
    assert.equal(t.pending(r => r.data?.action === 'get').at(-1).data.id, 1);
    t.pending(r => r.data?.action === 'get').at(-1).deferred.resolve(record()); await t.flush();
    assert.equal($('[data-master-position]').text(), '26 de 26');
    assert.equal($('[data-master-action=next]').prop('disabled'), true);
    $('[data-master-action=edit]').trigger('click'); $('[name=producto_nombre]').val('Pendiente').trigger('input');
    assert.equal(window.appuFormDirty, true);
    window.appuConfirmDiscard = () => {}; const before = t.requests.length;
    $('[data-master-action=new]').trigger('click'); $('[data-master-action=prev]').trigger('click'); $('[data-master-action=cancel]').trigger('click');
    assert.equal(t.requests.length, before); assert.equal($('[name=producto_nombre]').val(), 'Pendiente'); assert.equal(window.appuFormDirty, true);
  } finally { t.dom.window.close(); }
});

test('Un usuario de consulta puede buscar sin habilitar acciones de escritura', async () => {
  const t = await setup('views/productos/registro-producto.php', ['js/master-workspace.js', 'ajax/registro-producto.js'], '', { can: () => false });
  const { $ } = t;
  try {
    t.pending(r => r.data?.action === 'catalog')[0].deferred.resolve({ custom_fields: definitions });
    t.pending(r => r.data?.action === 'search')[0].deferred.resolve(list); await t.flush();
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'LIST');
    assert.equal($('[data-master-action=new]').css('display'), 'none');
    t.window.appuProductoWorkspace.open(1); t.pending(r => r.data?.action === 'get')[0].deferred.resolve(record()); await t.flush();
    $('[data-master-action=edit]').trigger('click'); $('#FRM_INSERT_PRODUCTO').trigger('submit');
    assert.equal($('#FRM_INSERT_PRODUCTO').attr('data-mode'), 'VIEW'); assert.equal($('[name=producto_nombre]').prop('disabled'), true);
    assert.equal(t.requests.filter(r => r.method === 'POST').length, 0);
    $('[data-master-action=find]').trigger('click'); assert.equal($('[data-master-field] option[value=9]').length, 1);
  } finally { t.dom.window.close(); }
});
