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
    const textId = process.env.APPU_QA_QUOTATION_TEXT;
    const flagId = process.env.APPU_QA_QUOTATION_FLAG;
    const hiddenId = process.env.APPU_QA_QUOTATION_HIDDEN;
    assert.match(recordId, /^[1-9]\d*$/, 'Ejecuta la prueba mediante quotation-lifecycle.php con APPU_QUOTATION_BROWSER_QA=1');
    assert.ok(records.some(record => String(record.id) === recordId), 'La cotización QA debe aparecer en el catálogo');

    const record = page.locator('[name=id_cotizacion]');
    const customer = page.locator('[name=cotizacion_valcliente]');
    const newButton = page.getByRole('button', { name: 'Nuevo', exact: true });
    const edit = page.getByRole('button', { name: 'Editar', exact: true });
    const discard = page.getByRole('button', { name: 'Descartar cambios' });
    const form = page.locator('#FRM_INSERT_COTIZACION');
    const saveButton = page.locator('.appu-doc-save');
    await page.goto(route + '?record_id=' + recordId);
    await expect(record).toHaveValue(recordId);
    await expect(newButton).toHaveCount(1);
    const version = page.locator('[name=version]');
    const customText = page.locator(`[name="custom_field[${textId}]"]`);
    const customFlag = page.locator(`[name="custom_field[${flagId}]"]`);
    await expect(version).toHaveValue(/^[a-f0-9]{64}$/);
    await expect(customText).toHaveValue('Detalle QA');
    await expect(customFlag).toHaveValue('1');

    const reference = page.locator('[name=cotizacion_clirefer]');
    const rows = page.locator('#table-productscotiz tbody tr');
    await expect(form).toHaveAttribute('data-document-mode', 'VIEW');
    await expect(reference).toBeDisabled();
    await expect(saveButton).toBeDisabled();
    await rows.first().dblclick();
    await expect(rows.first()).toContainText('ARTICULO COT QA');

    const readOnlyPage = await browser.newPage({ baseURL: baseURL.href });
    await readOnlyPage.addInitScript(() => Object.defineProperty(window, 'APPU_AUTH', {
      get: () => ({ role: 'qa-sales-view', permissions: ['sales.view'] }),
      set: () => {}
    }));
    await readOnlyPage.goto(route + '?record_id=' + recordId);
    await expect(readOnlyPage.locator('[name=id_cotizacion]')).toHaveValue(recordId);
    await expect(readOnlyPage.getByRole('button', { name: 'Editar', exact: true })).toBeHidden();
    await expect(readOnlyPage.locator('[name=cotizacion_clirefer]')).toBeDisabled();
    await expect(readOnlyPage.locator('.appu-doc-save')).toBeDisabled();
    await expect(readOnlyPage.locator('.appu-doc-void')).toBeDisabled();
    await readOnlyPage.close();
    await expect(page.locator('[name=cotizacion_clidirecc]')).toHaveValue('Dirección guardada en oferta QA');

    await edit.click();
    await expect(form).toHaveAttribute('data-document-mode', 'EDIT');
    await expect(reference).toBeFocused();
    await reference.fill('Edición que se descartará QA');
    await customText.fill('Campo que se descartará QA');
    await rows.first().dblclick();
    await expect(saveButton).toBeDisabled();
    await page.route('**/cotizaciones/consultar-documento.php?*', async request => {
      await request.fulfill({ status: 500, json: { message: 'Falló la consulta QA' } });
    }, { times: 1 });
    await discard.click();
    await page.getByRole('button', { name: 'Descartar y continuar' }).click();
    await expect(page.getByText('Falló la consulta QA')).toBeVisible();
    await expect(form).toHaveAttribute('data-document-mode', 'EDIT');
    await expect(reference).toHaveValue('Edición que se descartará QA');
    await discard.click();
    await page.getByRole('button', { name: 'Descartar y continuar' }).click();
    await expect(form).toHaveAttribute('data-document-mode', 'VIEW');
    await expect(reference).toHaveValue('QA');
    await expect(customText).toHaveValue('Detalle QA');
    await expect(rows.first()).toContainText('ARTICULO COT QA');

    await edit.click();
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
    await expect(form).toHaveAttribute('data-document-mode', 'CREATE');
    await expect(newButton).toHaveCount(1);
    await expect(customer).toBeFocused();
    assert.equal(new URL(page.url()).searchParams.has('record_id'), false);
    await expect(record).toHaveValue('');
    await expect(customer).toHaveValue('');
    await expect(reference).toHaveValue('');
    await expect(version).toHaveValue('');
    await expect(customText).toHaveValue('');
    assert.equal((await page.context().cookies()).some(cookie => cookie.name === legacyCookie.name), false);

    await page.goto(route + '?mode=create&record_id=' + recordId);
    await expect(customer).toBeFocused();
    await expect(record).toHaveValue('');

    await page.context().addCookies([legacyCookie]);
    await page.goto(route);
    await expect(record).toHaveValue(recordId);
    await expect(page.locator('[name=cotizacion_fecha]')).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);
    await expect(page.locator('[name=cotizacion_fecentrega]')).toHaveValue(/^\d{4}-\d{2}-\d{2}$/);

    const stalePage = await browser.newPage({ baseURL: baseURL.href });
    await stalePage.goto(route + '?record_id=' + recordId);
    const originalVersion = await version.inputValue();
    await expect(stalePage.locator('[name=version]')).toHaveValue(originalVersion);

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
    await edit.click();
    await reference.fill('Guardado y reintento QA');
    await customText.fill('');
    await customFlag.selectOption('0');
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
    await expect(saveButton).toHaveText(/Guardar/);
    await saveButton.click({ timeout: 10000 });
    await expect.poll(() => submissions.length).toBe(2);
    assert.deepEqual(submissions, [firstResponse, firstResponse], 'El reintento debe devolver la primera confirmación');
    await page.waitForURL(url => url.pathname === route && url.searchParams.get('record_id') === recordId, { timeout: 10000 });
    await expect(record).toHaveValue(recordId);
    await expect(reference).toHaveValue('Guardado y reintento QA');
    await expect(version).not.toHaveValue(originalVersion);
    await expect(customText).toHaveValue('');
    await expect(customFlag).toHaveValue('0');
    await expect(form).toHaveAttribute('data-document-mode', 'VIEW');

    const staleReference = stalePage.locator('[name=cotizacion_clirefer]');
    await stalePage.getByRole('button', { name: 'Editar', exact: true }).click();
    await staleReference.fill('Edición simultánea QA');
    const staleSave = stalePage.waitForResponse(response => response.url().endsWith('/cotizaciones/insert-update-cotizacion.php'));
    await stalePage.locator('.appu-doc-save').click();
    assert.equal((await staleSave).status(), 409);
    await expect(stalePage.locator('.appu-validation-summary')).toContainText('La cotización cambió');
    await expect(staleReference).toHaveValue('Edición simultánea QA');
    const current = await (await page.request.get('/modules/cotizaciones/consultar-documento.php?id=' + recordId)).json();
    assert.equal(current.quotation.reference, 'Guardado y reintento QA');
    assert.equal(current.custom_values[textId], '');
    assert.equal(current.custom_values[flagId], '0');
    assert.equal(Object.hasOwn(current.custom_values, hiddenId), false);

    await stalePage.getByRole('button', { name: 'Descartar cambios' }).click();
    await stalePage.getByRole('button', { name: 'Descartar y continuar' }).click();
    await expect(stalePage.locator('[name=version]')).toHaveValue(current.version);
    await expect(staleReference).toHaveValue('Guardado y reintento QA');
    await stalePage.close();

    const voidButton = page.locator('.appu-doc-void');
    await voidButton.click();
    await page.getByRole('textbox', { name: 'Motivo de la anulación' }).fill('Anulación QA en navegador');
    await page.getByRole('button', { name: 'Anular', exact: true }).last().click();
    await expect(page.locator('[name=cotizacion_estado]')).toHaveValue('2');
    await expect(form).toHaveAttribute('data-document-mode', 'VIEW');
    await expect(record).toHaveValue(recordId);
    await expect(reference).toHaveValue('Guardado y reintento QA');
    await expect(edit).toBeHidden();
    await expect(voidButton).toBeDisabled();
    console.log('PASS Oferta de ventas: Nuevo, consulta, editar, descarte, UDF, conflicto, reintento y anulación');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
