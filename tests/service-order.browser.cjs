const assert = require('node:assert/strict');
const { chromium, expect } = require('@playwright/test');

async function run() {
  const baseURL = new URL(process.env.APPU_QA_URL);
  assert.equal(baseURL.hostname, '127.0.0.1');
  const id = process.env.APPU_QA_SERVICE_ORDER_ID;
  assert.match(id, /^[1-9]\d*$/);

  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ baseURL: baseURL.href, viewport: { width: 1280, height: 820 } });
    const path = '/views/ordenes/orden-de-servicio?record_id=' + id;
    const form = page.locator('#FRM_INSERT_DETA_ORDSERV');
    const observation = form.locator('[name=orden_observ]');
    const requests = [];
    const response = await page.goto(path);
    assert.equal(response.headers()['x-appu-qa-database'], 'db_appu_qa');
    await expect(form).toHaveAttribute('data-document-mode', 'EDIT');
    await expect(form.locator('[name=orden_id]')).toHaveValue(id);
    await expect(form.locator('[name=orden_solicitante]')).toHaveValue('');
    await expect(page.locator('#table-ord-servicios tbody')).toContainText('Consultoría QA');

    await page.route('**/modules/ordenes/insert-update-orden.php', async route => {
      const key = /name="request_key"\r\n\r\n([A-Za-z0-9_-]{16,64})\r\n/.exec(route.request().postData())?.[1];
      const server = await route.fetch();
      const result = await server.json();
      requests.push({ key, status: server.status(), result });
      if (requests.length === 1 && server.ok()) await route.abort('connectionreset');
      else await route.fulfill({ response: server });
    });

    await observation.fill('Reintento Chromium QA');
    await page.locator('.appu-doc-save').click();
    await expect(page.getByText('No hubo conexión con el servidor.', { exact: false })).toBeVisible();
    await expect(observation).toHaveValue('Reintento Chromium QA');
    assert.equal(requests.length, 1);
    assert.ok(requests[0].key);

    await Promise.all([page.waitForNavigation(), page.locator('.appu-doc-save').click()]);
    await expect(form).toHaveAttribute('data-document-mode', 'EDIT');
    await expect(form.locator('[name=orden_id]')).toHaveValue(id);
    await expect(observation).toHaveValue('Reintento Chromium QA');
    assert.equal(requests.length, 2);
    assert.equal(requests[0].status, 200);
    assert.equal(requests[0].result.result, 'OK_UPDATE');
    assert.deepEqual(requests[1], requests[0]);
    console.log('PASS Chromium service order real save, lost response, retry, and reopen');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
