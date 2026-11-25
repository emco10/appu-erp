const test = require('node:test');
const assert = require('node:assert/strict');
const { setup } = require('./ui-harness.cjs');
const ruc = '20000000001';
const result = (extra = {}) => ({ success: true, data: { document_type: 'RUC', document_number: ruc, legal_name: 'EMPRESA SIMULACION', address: 'DIRECCION DE PRUEBA', state: 'ACTIVO', condition: 'HABIDO', source: 'decolecta', consulted_at: '2026-11-06T12:00:00Z', cache_hit: true, tax_profile: { retention_agent: false, perception_agent: null, good_taxpayer: true }, ...extra } });
async function start(provider = false, canManage = true) {
  const prefix = provider ? 'proveedor' : 'cliente', doc = provider ? 'numero' : 'ruc';
  const t = await setup(`views/${provider ? 'proveedores/registro-proveedor' : 'clientes/registro-cliente'}.php`, ['js/identity-lookup.js'], '', { can: () => canManage });
  const $form = t.$(`form#FRM_INSERT_${provider ? 'PROVEEDOR' : 'CLIENTE'}`).attr({ 'data-mode': 'CREATE', 'aria-busy': 'false' });
  t.document = t.$(`[name=${prefix}_${doc}]`).val(ruc);
  t.name = t.$(`[name=${prefix}_razsoc]`);
  t.form = $form;
  t.api = t.window.appuIdentityLookup({ form: '#' + $form.attr('id'), documentField: `${prefix}_${doc}`, idField: `${prefix}_id`, partnerType: provider ? 'provider' : 'customer', permission: provider ? 'providers.manage' : 'customers.manage', fields: { legal_name: { name: `${prefix}_razsoc`, label: 'Razón social' }, address: { name: `${prefix}_direccion`, label: 'Dirección' } } });
  t.click = () => t.$('[data-identity-consult]').trigger('click');
  t.resolve = async (data = result()) => { t.requests.at(-1).deferred.resolve(data); await t.flush(); };
  return t;
}
test('Solo Consultar inicia red y autocompleta RUC sin guardar la ficha', async () => {
  const t = await start();
  try {
    t.document.trigger('input').trigger('change'); assert.equal(t.requests.length, 0);
    t.name.val('NOMBRE EXISTENTE'); t.click(); t.click(); assert.equal(t.requests.length, 1);
    assert.deepEqual(JSON.parse(JSON.stringify(t.requests[0].data)), { partner_type: 'customer', document_type: 'RUC', document_number: ruc });
    await t.resolve(); assert.equal(t.name.val(), 'EMPRESA SIMULACION');
    assert.equal(t.$('[name=cliente_direccion]').val(), 'DIRECCION DE PRUEBA');
    assert.equal(t.$('[data-identity-select]').length, 0);
    assert.equal(t.$('[data-identity-apply]').length, 0);
    assert.match(t.$('[data-identity-preview]').text(), /caché/);
    assert.match(t.$('[data-identity-status]').text(), /automáticamente/);
    const profile = JSON.parse(t.$('[name=partner_tax_profile_json]').val());
    assert.equal(profile.source, 'decolecta'); assert.equal(profile.tax_profile.good_taxpayer, true);
    assert.equal(t.requests.length, 1); assert.equal(t.window.appuFormDirty, true);
  } finally { t.dom.window.close(); }
});
test('Perfil tributario se aplica automáticamente, conserva desconocido y caché; edición manual elimina atribución al proveedor', async () => {
  const t = await start(true);
  try {
    t.click(); await t.resolve();
    let saved = JSON.parse(t.$('[name=partner_tax_profile_json]').val());
    assert.equal(saved.tax_profile.retention_agent, false); assert.equal(saved.tax_profile.perception_agent, null); assert.equal(saved.source, 'decolecta'); assert.equal(saved.cache_hit, true);
    t.$('[data-identity-tax=retention_agent]').val('yes').trigger('change');
    saved = JSON.parse(t.$('[name=partner_tax_profile_json]').val()); assert.equal(saved.source, 'manual'); assert.equal(saved.consulted_at, null); assert.equal(saved.tax_profile.retention_agent, true); assert.equal(saved.tax_profile.perception_agent, null);
    t.document.val('20000000010').trigger('input'); assert.equal(t.$('[name=partner_tax_profile_json]').val(), ''); assert.equal(t.$('[data-identity-tax=retention_agent]').val(), ''); assert.equal(t.requests.length, 1);
  } finally { t.dom.window.close(); }
});
test('Una respuesta tardía no cambia ficha, documento ni modo de solo lectura', async () => {
  const t = await start();
  try {
    t.click(); t.document.val('20000000010').trigger('input'); await t.resolve(); assert.equal(t.$('[data-identity-apply]').length, 0); assert.equal(t.name.val(), '');
    t.document.val(ruc).trigger('input'); t.click(); t.form.attr('data-mode', 'VIEW'); t.document.prop('disabled', true); t.api.render(); await t.resolve();
    assert.equal(t.$('[data-identity-apply]').length, 0); assert.equal(t.$('[data-identity-consult]').prop('disabled'), true); assert.equal(t.$('[data-identity-tax=retention_agent]').prop('disabled'), true);
    t.click(); assert.equal(t.requests.length, 2);
  } finally { t.dom.window.close(); }
});
test('DNI autocompleta nombres y nunca dirección ni condiciones tributarias', async () => {
  const t = await start();
  try {
    t.document.val('12345678').trigger('input'); t.click();
    await t.resolve(result({ document_type: 'DNI', document_number: '12345678', legal_name: 'PERSONA SIMULACION', address: 'DATO PERSONAL QUE NO SE DEBE USAR' }));
    assert.equal(t.name.val(), 'PERSONA SIMULACION'); assert.equal(t.$('[name=cliente_direccion]').val(), '');
    assert.equal(t.$('[data-identity-select]').length, 0); assert.equal(t.$('[name=partner_tax_profile_json]').val(), '');
    assert.equal(t.$('[data-identity-tax=retention_agent]').prop('disabled'), true);
    assert.doesNotMatch(t.$('[data-identity-preview]').text(), /DATO PERSONAL/);
  } finally { t.dom.window.close(); }
});
test('Permisos, documento inválido y fallo del proveedor conservan registro manual', async () => {
  const denied = await start(false, false);
  try { denied.click(); assert.equal(denied.requests.length, 0); assert.equal(denied.$('[data-identity-consult]').prop('disabled'), true); } finally { denied.dom.window.close(); }
  const t = await start(true);
  try {
    t.name.val('MANUAL'); t.document.val('CODIGO-EXTRANJERO').trigger('input'); t.click(); assert.equal(t.requests.length, 0);
    t.document.val(ruc).trigger('input'); t.click(); t.requests[0].deferred.reject({ status: 503, responseJSON: { message: 'Proveedor no configurado; completa manualmente.' } }); await t.flush();
    assert.equal(t.name.val(), 'MANUAL'); assert.match(t.$('[data-identity-status]').text(), /manualmente/); assert.equal(t.$('[data-identity-consult]').prop('disabled'), false);
  } finally { t.dom.window.close(); }
});
test('Resultados externos se asignan como valor inerte y se rechaza otro documento', async () => {
  const t = await start();
  try {
    t.click(); await t.resolve(result({ legal_name: '<img src=x onerror=alert(1)>' })); assert.equal(t.$('[data-identity-preview] img').length, 0); assert.equal(t.name.val(), '<img src=x onerror=alert(1)>');
    t.click(); await t.resolve(result({ document_number: '20000000010' })); assert.equal(t.$('[data-identity-apply]').length, 0); assert.equal(t.name.val(), '<img src=x onerror=alert(1)>');
  } finally { t.dom.window.close(); }
});
