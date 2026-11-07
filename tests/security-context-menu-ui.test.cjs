const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const root = path.resolve(__dirname, '..');
const callers = [
  ['ajax/guias.js', '#table-remission-guides', 'table', 'remission_guide'],
  ['ajax/resumen-ordenes.js', '#table-ordenes', 'tbl_ordenes', 'order'],
  ['ajax/resumen-ordenes-venta.js', '#table-sales-orders', 'tbl_sales_orders', 'sales_order'],
  ['ajax/resumen-cotizaciones.js', '#table-cotizaciones', 'tbl_cotizaciones', null],
  ['ajax/resumen-compras.js', '#table-ordenes', 'tbl_ordenes', 'purchase']
];

function environment(plugin = 'jquery.contextMenu.min.js') {
  const dom = new JSDOM('<div id="target"></div><div id="positioned"></div>', {
    url: 'http://localhost/views/qa', runScripts: 'outside-only'
  });
  const w = dom.window, $ = jquery(w);
  w.$ = w.jQuery = $;
  w.eval(fs.readFileSync(path.join(root, 'plugins/context-menu', plugin), 'utf8'));
  w.eval(fs.readFileSync(path.join(root, 'plugins/context-menu/jquery.ui.position.js'), 'utf8'));
  $.fx.off = true;
  return { dom, w, $ };
}

// Compile the real options object, including its callbacks, without executing
// the rest of the page (which would start unrelated catalog HTTP requests).
function optionsFromSource(w, file) {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  const start = source.indexOf('.contextMenu(') + '.contextMenu('.length;
  assert.ok(start >= '.contextMenu('.length, 'No context-menu call: ' + file);
  for (let end = source.indexOf(');', start); end !== -1; end = source.indexOf(');', end + 2)) {
    try { return w.eval('(' + source.slice(start, end) + ')'); } catch (error) {
      if (!(error instanceof w.SyntaxError)) throw error;
    }
  }
  throw new Error('Cannot compile real context-menu options: ' + file);
}

test('Position CVE-2021-41184: of nunca interpreta una cadena como HTML', () => {
  const t = environment();
  try {
    const { $, w } = t, parsed = [], original = $.parseHTML;
    $.parseHTML = function (value, ...args) {
      if (/<(?:img|svg|script)\b/i.test(String(value))) parsed.push(value);
      return original.call(this, value, ...args);
    };
    for (const of of ['<img onerror="window.appuPositionXss=true" src="/404">', '<svg onload="window.appuPositionXss=true"></svg>']) {
      try { $('#positioned').position({ of, my: 'left top', at: 'right bottom', collision: 'none' }); } catch (error) {
        assert.match(error.message, /syntax|expression|selector/i);
      }
    }
    assert.deepEqual(parsed, [], 'Una opción of creó HTML en la versión vulnerable');
    assert.equal(w.appuPositionXss, undefined);
    assert.equal($('img,svg,script').length, 0);
  } finally { t.dom.window.close(); }
});

test('Position conserva objetivos DOM, jQuery y selectores CSS existentes', () => {
  const t = environment();
  try {
    for (const of of [t.$('#target')[0], t.$('#target'), '#target']) {
      const positioned = t.$('#positioned');
      assert.equal(positioned.position({ of, my: 'left top', at: 'right bottom', collision: 'none' })[0], positioned[0]);
      assert.match(positioned[0].style.left, /^-?\d+(?:\.\d+)?px$/);
      assert.match(positioned[0].style.top, /^-?\d+(?:\.\d+)?px$/);
    }
  } finally { t.dom.window.close(); }
});

for (const plugin of ['jquery.contextMenu.min.js', 'jquery.contextMenu.js']) for (const [file, selector, tableName, historyType] of callers) test(plugin + ' / ' + file + ': contexto DOM, acción y registro de destrucción', () => {
  const t = environment(plugin);
  try {
    const { $, w } = t, history = [], row = [123, 'QA-123', '', '', '', 'Pendiente', 'Pendiente', 'Registrada'];
    const id = selector.slice(1);
    $('body').append($('<table>').attr('id', id).append('<tbody><tr><td>Documento QA</td></tr></tbody>'));
    w.appuCan = () => true;
    w.mostrarHistorialEstados = (...args) => history.push(args);
    w[tableName] = { row: () => ({ data: () => row }), rows: () => ({ data: () => ({ toArray: () => [row] }) }) };
    const options = optionsFromSource(w, file);
    assert.equal(options.selector, 'tbody tr');
    assert.equal(options.context, undefined, 'La página no debe proporcionar un contexto externo');
    assert.equal(options.build, undefined, 'Los menús de APPU son configuración local');
    const element = $(selector), trigger = element.find('tr');
    element.contextMenu(options);
    assert.equal(options.context[0], element[0], 'El wrapper debe fijar el contexto a la tabla DOM');
    trigger.trigger($.Event('contextmenu', { pageX: 20, pageY: 20 }));
    const menu = $('.context-menu-root');
    assert.equal(menu.length, 1);
    const saved = menu.data('contextMenuRoot');
    assert.equal(saved.context, element[0]);
    assert.equal(saved.$trigger[0], trigger[0]);
    assert.equal($.contextMenu.menus[saved.ns], saved, 'El menú debe estar registrado en el plugin real');
    assert.equal(menu.find('img,svg,script').length, 0);
    for (const item of Object.values(options.items)) if (item && typeof item === 'object' && typeof item.name === 'string') assert.ok(menu.text().includes(item.name));
    if (historyType) {
      const historyItem = menu.find('.context-menu-item').filter(function () { return $(this).text() === 'Ver historial'; });
      historyItem.trigger('mouseup');
      assert.deepEqual(history, [[historyType, 123]], 'La acción debe usar el registro seleccionado');
    } else {
      // The quotation page has no History action; its cancel item closes the
      // menu through the same plugin callback without making an HTTP request.
      menu.find('.context-menu-item').filter(function () { return $(this).text() === 'Cancelar'; }).trigger('mouseup');
    }
    assert.equal($.contextMenu.menus[saved.ns], saved, 'El callback cierra el menú sin destruir su registro');
    assert.equal($('.context-menu-root').length, 1, 'El menú debe existir antes de probar Destroy');
    // Destroy's context is a matching trigger row; it stays a jQuery/DOM node
    // when the library evaluates its selector and removes this menu.
    trigger.contextMenu('destroy');
    assert.equal($('.context-menu-root').length, 0, 'Destroy debe retirar el menú del contexto DOM');
    assert.equal(Object.hasOwn($.contextMenu.menus, saved.ns), false, 'Destroy debe eliminar el registro, no solamente ocultar HTML');
  } finally { t.dom.window.close(); }
});

test('El mapa del plugin minificado conserva la fuente auditada, normalizando solo finales de línea', () => {
  const directory = path.join(root, 'plugins/context-menu');
  const minified = fs.readFileSync(path.join(directory, 'jquery.contextMenu.min.js'), 'utf8');
  const source = fs.readFileSync(path.join(directory, 'jquery.contextMenu.js'), 'utf8');
  const map = JSON.parse(fs.readFileSync(path.join(directory, 'jquery.contextMenu.min.js.map'), 'utf8'));
  assert.match(minified, /sourceMappingURL=jquery\.contextMenu\.min\.js\.map/);
  assert.equal(map.sourcesContent.length, 1);
  assert.equal(map.sourcesContent[0].replace(/\r\n/g, '\n'), source.replace(/\r\n/g, '\n'));
});

test('Revisión de contexto cubre los cinco consumidores y exige revisar nuevas opciones', () => {
  const discovered = [];
  function inspect(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const file = path.join(directory, entry.name);
      if (entry.isDirectory()) inspect(file);
      else if (/\.(?:js|php)$/.test(entry.name)) {
        const source = fs.readFileSync(file, 'utf8');
        const calls = [...source.matchAll(/(?:\.contextMenu\s*\(|\.position\s*\()/g)];
        for (const call of calls) discovered.push([path.relative(root, file).replace(/\\/g, '/'), call[0]]);
      }
    }
  }
  for (const directory of ['global', 'modules', 'ajax', 'js', 'views']) inspect(path.join(root, directory));
  assert.deepEqual(discovered.map(item => item[0]).sort(), callers.map(item => item[0]).sort());
  assert.ok(discovered.every(item => item[1].includes('contextMenu')), 'Nueva llamada Position requiere revisión');
  const t = environment();
  try {
    for (const [file, selector] of callers) {
      const source = fs.readFileSync(path.join(root, file), 'utf8');
      assert.ok(source.includes('$("' + selector + '").contextMenu({'), 'El consumidor debe seleccionar una tabla literal');
      const options = optionsFromSource(t.w, file);
      for (const key of ['context', 'build', 'position', 'determinePosition', 'positionSubmenu', 'appendTo']) assert.equal(options[key], undefined, 'Nueva opción ' + key + ' requiere revisar: ' + file);
    }
  } finally { t.dom.window.close(); }
});
