const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const root = path.resolve(__dirname, '..');
const payload = '<img src=x onerror="window.appuXss=true"><svg onload="window.appuXss=true"></svg> " & cliente';
const cases = [
  { file: 'ajax/registro-factura.js', count: 8 },
  { file: 'ajax/registro-boleta.js', count: 8 },
  { file: 'ajax/registro-nota-credito.js', count: 8 },
  { file: 'ajax/registro-cotizacion.js', count: 6 },
  { file: 'ajax/orden-de-compra.js', count: 10 },
  { file: 'ajax/orden-de-servicio.js', count: 8 },
  { file: 'ajax/compra-interna.js', count: 10 },
  { file: 'ajax/historial-movimiento.js', count: 12 },
  { file: 'ajax/mantenimiento-producto.js', count: 11 },
  { file: 'ajax/guias.js', count: 9, text: [0, 1, 2, 3, 4, 5, 6], html: { 7: 'Registrada', 8: '<button data-safe-action>Ver</button>' }, badge: 7 },
  { file: 'ajax/resumen-ordenes.js', count: 8, text: [0, 1, 2, 3, 4, 6], html: { 5: '<span class="badge">Pendiente</span>', 7: '<button data-safe-action>Aprobar</button>' }, badge: 5 },
  { file: 'ajax/resumen-compras.js', count: 9, text: [0, 2, 3, 4, 5, 7], html: { 1: 'COMPRA', 6: '<span class="badge">Pendiente</span>', 8: '<button data-safe-action>Ver</button>' }, badge: 6 },
  { file: 'ajax/resumen-cotizaciones.js', count: 7, text: [0, 1, 2, 3, 4, 5], html: { 6: '<button data-safe-action>Copiar</button>' } }
];

// Read the actual initialization object; DataTables itself performs all drawing,
// search and row retrieval. Network and export plugins are outside these tests.
function environment(html) {
  const dom = new JSDOM(html, { url: 'http://localhost/views/qa', runScripts: 'outside-only' });
  const w = dom.window, $ = jquery(w);
  w.$ = w.jQuery = $;
  w.alert = message => { throw new Error(message); };
  w.appuStatusBadge = value => $('<span class="badge">').text(value).prop('outerHTML');
  w.eval(fs.readFileSync(path.join(root, 'plugins/datatables/jquery.dataTables.js'), 'utf8'));
  return { dom, w, $ };
}

test('DataTables vendor: el renderer de texto escapa arrays y bloquea setters de prototipo', () => {
  const t=environment('<table id="qa"><thead><tr><th>Nombre</th></tr></thead></table>');
  try {
    const {w,$}=t, value=[payload];
    const table=$('#qa').DataTable({data:[{name:value}],columns:[{data:'name',render:$.fn.dataTable.render.text()}],dom:'t'});
    assert.equal($('#qa img,#qa svg').length,0,'CVE-2021-23445: arrays no pueden omitir el escape');
    assert.equal($('#qa tbody td').text(),payload);
    assert.equal(table.cell(0,0).data(),value,'El array original sigue disponible para la aplicación');
    const setter=$.fn.dataTable.ext.internal._fnSetObjectDataFn('__proto__.appuSecurityProbe');
    assert.throws(()=>setter(w.eval('({})'),true),/proto/i,'CVE-2020-28458: bloquear claves de prototipo');
    assert.equal(w.eval('({}).appuSecurityProbe'),undefined);
  } finally { t.dom.window.close(); }
});

test('DataTables actualizado conserva Bootstrap y exportaciones Buttons con los plugins locales reales', () => {
  const t=environment('<table id="qa"><thead><tr><th>Nombre</th></tr></thead></table>');
  try {
    const {w,$}=t;
    w.URL.createObjectURL=()=> 'blob:qa';w.URL.revokeObjectURL=()=>{};
    for(const file of ['plugins/datatables-bs4/dataTables.bootstrap4.js','plugins/datatables/dataTables.buttons.min.js','plugins/datatables/jszip.min.js','plugins/datatables/pdfmake.min.js','plugins/datatables/vfs_fonts.js','plugins/datatables/buttons.html5.min.js','plugins/datatables/buttons.print.min.js'])w.eval(fs.readFileSync(path.join(root,file),'utf8'));
    const table=$('#qa').DataTable({data:[{name:payload}],columns:[{data:'name',render:$.fn.dataTable.render.text()}],dom:'Bfrtip',buttons:['csvHtml5','excelHtml5','pdfHtml5','print']});
    assert.equal(table.buttons().count(),4,'Falta una exportación CSV/Excel/PDF/impresión');
    assert.equal($('.dataTables_wrapper.dt-bootstrap4').length,1);
    const exported=table.buttons.exportData({columns:[0]});
    assert.equal(exported.header[0],'Nombre');assert.equal(exported.body[0][0],payload);
    assert.equal($('#qa img,#qa svg').length,0);
  } finally { t.dom.window.close(); }
});

for (const spec of cases) test(spec.file + ': datos externos se dibujan como texto y conservan su valor original', () => {
  const t = environment('<table id="qa"><thead><tr>' + '<th>Campo</th>'.repeat(spec.count) + '</tr></thead><tbody></tbody></table>');
  try {
    const source = fs.readFileSync(path.join(root, spec.file), 'utf8');
    const match = source.match(/\.[Dd]ataTable\((\{[\s\S]*?\n\s*\})\);/);
    assert.ok(match, 'No se encontró la configuración real de ' + spec.file);
    const config = t.w.eval('(' + match[1] + ')');
    Object.assign(config, { ajax: undefined, language: {}, dom: 't', buttons: [], ordering: false });
    const textColumns = spec.text || Array.from({ length: spec.count }, (_, index) => index);
    const row = {};
    for (let index = 0; index < spec.count; index++) row[config.columns?.[index]?.data || index] = spec.html?.[index] || payload;
    config.data = [row];
    const table = t.$('#qa').DataTable(config);
    assert.equal(t.$('#qa tbody img,#qa tbody svg,#qa tbody script').length, 0, 'Se interpretaron datos como HTML ejecutable');
    for (const index of textColumns) {
      assert.equal(t.$(table.cell(0, index).node()).text(), payload);
      assert.equal(table.cell(0, index).data(), payload, 'El renderer alteró el dato enviado/consultado');
    }
    if (spec.badge !== undefined) assert.equal(t.$(table.cell(0, spec.badge).node()).find('.badge').length, 1);
    if (spec.html && Object.values(spec.html).some(value => value.includes('data-safe-action'))) assert.equal(t.$('#qa tbody [data-safe-action]').length, 1, 'Se perdió la acción del documento');
    table.search('cliente').draw();
    assert.equal(table.rows({ search: 'applied' }).count(), 1, 'La búsqueda dejó de encontrar el texto');
  } finally { t.dom.window.close(); }
});

test('Maestros legacy: lista real no interpreta nombre/dirección como HTML y Consultar conserva la ficha', () => {
  const t = environment('<form id="master"><input name="id"><input name="name"></form><div id="toolbar"></div><table id="records"><thead><tr><th>ID</th><th>Nombre</th></tr></thead></table>');
  try {
    const { w, $ } = t;
    w.appuCan = () => true; w.appuConfirmDiscard = callback => callback(); w.appuMarkSaved = () => {};
    $.fn.modal = function () { return this; };
    const requests = [];
    $.ajax = options => { const d = $.Deferred(); if(options.success)d.done(options.success); requests.push({ ...options, deferred: d }); return d.promise(); };
    $.post = (url, data, done) => { const d = $.Deferred(); if (done) d.done(done); requests.push({ url, data, deferred: d }); return d.promise(); };
    w.eval(fs.readFileSync(path.join(root, 'js/legacy-master-workspace.js'), 'utf8'));
    w.HTMLElement.prototype.scrollIntoView=function(){};
    const workspace = w.appuLegacyMasterWorkspace({ form: '#master', toolbar: '#toolbar', table: '#records', permission: 'customers.manage', endpoint: '/qa-list', prefix: 'CLI', idField: 'id', codeColumn: 'ID', fields: { id: 'CODIGO', name: 'NAME' }, columns: ['ID', 'NAME'], searchColumns: [{ key: 'ID', label: 'ID' }, { key: 'NAME', label: 'Nombre' }] });
    requests.find(request => String(request.url).includes('Spanish.json')).deferred.resolve({});
    requests.find(request => request.data?.FILTER === 'ALL').deferred.resolve({ data: [{ ID: 'CLI-1', NAME: payload }] });
    assert.equal($('#records tbody img,#records tbody svg').length, 0);
    assert.equal($('#records tbody td').eq(1).text(), payload);
    workspace.open('CLI-1');
    const detail = requests.find(request => request.data?.FILTER === 'CLI-1');
    assert.ok(detail, 'Consultar no pidió la ficha seleccionada');
    detail.deferred.resolve([{ CODIGO: 1, NAME: payload }]);
    assert.equal($('#master [name=name]').val(), payload);
    assert.equal($('#master').attr('data-mode'), 'VIEW');
    assert.equal($('#master').find('img,svg').length, 0);
  } finally { t.dom.window.close(); }
});
