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
    await expect(page.locator('[name=cotizacion_fecha]')).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);
    await expect(page.locator('[name=cotizacion_fecentrega]')).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);

    const submissions = [];
    const saveEndpoint = '**/modules/cotizaciones/insert-update-cotizacion.php';
    let firstResponse;
    await page.route(saveEndpoint, async requestRoute => {
      const httpResponse = await requestRoute.fetch();
      const result = await httpResponse.json();
      submissions.push(result);
      if (submissions.length === 1) {
        firstResponse = result;
        await requestRoute.abort('connectionreset');
      } else {
        await requestRoute.fulfill({ response: httpResponse });
      }
    });
    const form = page.locator('#FRM_INSERT_COTIZACION');
    await reference.fill('Guardado y reintento QA');
    await form.evaluate(element => {
      for (let count = 0; count < 2; count++) {
        element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }
    });
    await expect(page.getByText('No se pudo confirmar el guardado.', { exact: false })).toBeVisible();
    assert.equal(submissions.length, 1, 'Dos envíos inmediatos deben originar una solicitud');
    assert.equal(firstResponse.quotation_id, Number(recordId));
    assert.equal(firstResponse.result, 'OK_UPDATE');
    await expect(reference).toHaveValue('Guardado y reintento QA');
    await expect(form).toHaveAttribute('aria-busy', 'false');

    const invalidFields = await form.evaluate(element => [...element.elements]
      .filter(field => field.willValidate && !field.validity.valid)
      .map(field => ({ name: field.name, message: field.validationMessage })));
    assert.deepEqual(invalidFields, [], 'La cotización consultada debe conservar los campos requeridos');
    const saveButton = page.locator('.appu-doc-save');
    await expect(saveButton).toHaveText(/Guardar/);
    await saveButton.click({ timeout: 10000 });
    await expect.poll(() => submissions.length).toBe(2);
    assert.deepEqual(submissions, [firstResponse, firstResponse], 'El reintento debe devolver la primera confirmación');
    await page.waitForURL(url => url.pathname === route && url.searchParams.get('record_id') === recordId, { timeout: 10000 });
    await expect(record).toHaveValue(recordId);
    await expect(reference).toHaveValue('Guardado y reintento QA');
    console.log('PASS Oferta de ventas: Nuevo, consulta, doble envío, pérdida de respuesta y reapertura tras guardar');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
