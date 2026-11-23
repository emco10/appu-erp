const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');

function source(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

test('la ruta heredada de mantenimiento usa el maestro canónico de artículos', () => {
  const view = source('views/productos/mantenimiento-producto.php');
  assert.match(view, /\$page_script\s*=\s*'registro-producto'/);
  assert.match(view, /require __DIR__ \. '\/registro-producto\.php'/);
  assert.doesNotMatch(view, /producto_proveedor|producto_cantidad|producto_fecvenc/);
});

test('la ruta heredada de ingreso usa una entrada de inventario', () => {
  const view = source('views/productos/ingreso-almacen.php');
  assert.match(view, /\$inventoryDocumentType\s*=\s*'ENTRY'/);
  assert.match(view, /\$page_script\s*=\s*'actualizar-stock'/);
  assert.match(view, /require __DIR__ \. '\/actualizar-stock\.php'/);
  assert.doesNotMatch(view, /producto_proveedor|producto_marca/);
});

test('el maestro canónico solo exige nombre y afectación SUNAT', () => {
  const view = source('views/productos/registro-producto.php');
  const requiredNames = [...view.matchAll(/<[^>]+name="([^"]+)"[^>]*\brequired\b[^>]*>/g)].map((match) => match[1]);
  assert.deepEqual(requiredNames.sort(), ['producto_nombre', 'producto_tax_code']);
});
