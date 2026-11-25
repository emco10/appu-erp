const assert = require('node:assert/strict');
const { chromium } = require('@playwright/test');

async function run() {
  const baseURL = new URL(process.env.APPU_QA_URL || 'http://127.0.0.1:8765');
  assert.equal(baseURL.hostname, '127.0.0.1', 'La prueba solo admite el servidor QA local');
  const browser = await chromium.launch({ headless: true });

  try {
    const page = await browser.newPage({ baseURL: baseURL.href, viewport: { width: 1280, height: 800 } });
    const response = await page.goto('/views/home');
    assert.equal(response.status(), 200);
    assert.match(response.headers()['x-appu-qa-database'] || '', /_qa$/);
    const navigation = page.getByRole('navigation', { name: 'Módulos del ERP' });
    assert.deepEqual(await navigation.locator(':scope > ul > li > details > summary, :scope > ul > li > a').evaluateAll(
      links => links.map(link => link.textContent.trim())
    ), ['Inicio', 'Gestión', 'Socios de negocio', 'Ventas', 'Compras', 'Inventario', 'Finanzas', 'Logística', 'Reportes']);

    const search = page.getByRole('searchbox', { name: 'Buscar en el menú' });
    await search.pressSequentially('Proveedor');
    const searchResult = page.locator('#appu-sidebar-results a[href$="/views/proveedores/registro-proveedor"]');
    await searchResult.waitFor({ state: 'visible', timeout: 5000 });
    await search.press('Escape');

    const partners = navigation.locator('#m_socios_negocio');
    await partners.focus();
    await page.keyboard.press('Enter');
    assert.equal(await partners.evaluate(summary => summary.parentElement.open), true);
    await navigation.getByRole('link', { name: 'Proveedores', exact: true }).click();
    await page.waitForURL('**/views/proveedores/registro-proveedor');
    assert.equal(await navigation.locator('[aria-current=page]').getAttribute('id'), 'm_proveedores');

    await navigation.locator('#m_ventas').click();
    await navigation.locator('#m_cotizacion').click();
    await navigation.getByRole('link', { name: 'Registrar oferta', exact: true }).click();
    await page.waitForURL('**/views/cotizacion/registro-cotizacion');
    assert.equal(await navigation.locator('[aria-current=page]').getAttribute('id'), 'm_registro_cotizacion');
    await page.close();

    const mobile = await browser.newPage({ baseURL: baseURL.href, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const mobileResponse = await mobile.goto('/views/home');
    assert.equal(mobileResponse.status(), 200);
    await mobile.locator('[data-widget=pushmenu]').tap();
    const management = mobile.locator('#m_gestion');
    await management.tap();
    await mobile.getByRole('link', { name: 'Configuración general', exact: true }).tap();
    await mobile.waitForURL('**/views/gestion/index');
    await mobile.close();
    console.log('PASS menús reales en Chromium: búsqueda, teclado, grupos anidados, vista activa y acceso móvil');
  } finally {
    await browser.close();
  }
}

run().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
