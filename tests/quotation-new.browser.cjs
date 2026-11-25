const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');

async function run() {
  const baseURL = new URL(process.env.APPU_QA_URL || 'http://127.0.0.1:8765');
  assert.equal(baseURL.hostname, '127.0.0.1', 'La prueba solo admite el servidor QA local');
  const browser = await chromium.launch({ headless: true });
  const route = '/views/cotizacion/registro-cotizacion';

  try {
    const page = await browser.newPage({ baseURL: baseURL.href, viewport: { width: 1280, height: 800 } });
    const catalog = page.waitForResponse(response => response.url().endsWith('/cotizaciones/listar-cotizaciones.php'));
    const response = await page.goto(route);
    assert.equal(response.status(), 200);
    assert.match(response.headers()['x-appu-qa-database'] || '', /_qa$/);
    const records = await (await catalog).json();
    const recordId = process.env.APPU_QA_QUOTATION_ID || '';
    assert.match(recordId, /^[1-9]\d*$/, 'Ejecuta la prueba mediante quotation-lifecycle.php con APPU_QUOTATION_BROWSER_QA=1');
    assert.ok(records.some(record => String(record.id) === recordId), 'La cotización QA debe aparecer en el catálogo');

    const record = page.locator('[name=id_cotizacion]');
    const customer = page.locator('[name=cotizacion_valcliente]');
    const newButton = page.getByRole('button', { name: 'Nuevo', exact: true });
    await page.goto(route + '?record_id=' + recordId);
    await expect(record).toHaveValue(recordId);
    await expect(newButton).toHaveCount(1);

    const reference = page.locator('[name=cotizacion_clirefer]');
    await reference.fill('Cambio temporal QA');
    await newButton.click();
    await page.getByRole('button', { name: 'Seguir editando' }).click();
    assert.equal(new URL(page.url()).searchParams.get('record_id'), recordId);
    await expect(reference).toHaveValue('Cambio temporal QA');

    const legacyCookie = { name: 'COOKIE_ID_COTIZ', value: recordId, url: baseURL.origin };
    await page.context().addCookies([legacyCookie]);
    await newButton.click();
    await page.getByRole('button', { name: 'Descartar y continuar' }).click();
    await page.waitForURL(url => url.pathname === route && url.searchParams.get('mode') === 'create');
    await expect(newButton).toHaveCount(1);
    await expect(customer).toBeFocused();
    assert.equal(new URL(page.url()).searchParams.has('record_id'), false);
    await expect(record).toHaveValue('');
    await expect(customer).toHaveValue('');
    await expect(reference).toHaveValue('');
    assert.equal((await page.context().cookies()).some(cookie => cookie.name === legacyCookie.name), false);

    await page.goto(route + '?mode=create&record_id=' + recordId);
    await expect(customer).toBeFocused();
    await expect(record).toHaveValue('');

    await page.context().addCookies([legacyCookie]);
    await page.goto(route);
    await expect(record).toHaveValue(recordId);
    console.log('PASS Oferta de ventas: Nuevo, descarte, creación limpia y reapertura compatible');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
