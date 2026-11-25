const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');

async function run() {
  const origin = new URL(process.env.APPU_QA_URL);
  assert.equal(origin.hostname, '127.0.0.1');
  const documents = JSON.parse(process.env.APPU_QA_SALES_DOCUMENTS);
  const browser = await chromium.launch({ headless: true });
  try {
    for (const { screen, id, series } of documents) {
      const page = await browser.newPage({ baseURL: origin.href, viewport: { width: 1280, height: 800 } });
      const path = '/views/facturacion/registro-' + screen;
      const form = page.locator('#FRM_INSERT_FACTURA');
      const keys = [];
      await page.route('**/modules/facturacion/insert-' + screen + '.php', async route => {
        const key = route.request().postData().match(/name="request_key"\r\n\r\n([A-Za-z0-9_-]{16,64})\r\n/);
        assert.ok(key, 'El navegador debe enviar la clave de operación.');
        keys.push(key[1]);
        if (keys.length === 1) await route.abort('connectionreset');
        else await route.fulfill({ json: { error: false, document_id: id, number: series + '-QA' } });
      });
      const response = await page.goto(path + '?mode=create');
      assert.equal(response.headers()['x-appu-qa-database'], 'db_appu_qa');
      await expect(form).toHaveAttribute('data-document-mode', 'CREATE');

      if (screen === 'factura') {
        await expect(page.locator('[name=facturacion_series] option[value="' + series + '"]')).toHaveCount(1);
        await form.evaluate((element, seriesCode) => {
          element.elements.facturacion_series.value = seriesCode;
          element.elements.facturacion_cliruc.value = '20000000000';
          element.elements.facturacion_valcliente.value = 'Cliente QA';
          element.elements.facturacion_fecha.value = new Date().toISOString().slice(0, 10);
          element.elements.facturacion_formpagotext.value = '0';
          element.elements.facturacion_tipmon.value = 'MN';
          window.tbl_prodfactura.row.add([1, 'QA', 'Artículo QA', 'Descripción QA', 10, 1, 10, '10', 0, '', '', '', '', '']).draw();
        }, series);
      }

      const reference = page.locator('[name=facturacion_clirefer]');
      await reference.fill('Conservar referencia QA');
      await form.evaluate(element => {
        element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      });
      await expect(page.getByText('No hubo conexión con el servidor.', { exact: false })).toBeVisible();
      await expect(reference).toHaveValue('Conservar referencia QA');
      assert.equal(keys.length, 1);
      await form.dispatchEvent('submit');
      await page.waitForURL(url => url.pathname === path && url.searchParams.get('record_id') === String(id));
      await expect(form.locator('[name=id_factura]')).toHaveValue(String(id));
      await expect(form).toHaveAttribute('data-document-mode', 'VIEW');
      await form.dispatchEvent('submit');
      assert.deepEqual(keys, [keys[0], keys[0]]);
      await page.close();
    }
    console.log('PASS Chromium sales creation retry and reopening of existing QA invoice, receipt and credit note');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
