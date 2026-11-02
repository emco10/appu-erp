const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');

const css = fs.readFileSync(path.join(__dirname, '..', 'css', 'style-appu.css'), 'utf8');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1100, height: 700 } });
  await page.setContent(`
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; font: 16px Arial, sans-serif; }
      .row { display: flex; flex-wrap: wrap; margin: 0 -8px; }
      .col { padding: 0 8px; }
      .dashboard-kpis .col { width: 25%; }
      #sunat-summary .col { width: 16.666667%; }
      .small-box { min-height: 140px; }
      .small-box .icon { position: absolute; }
      ${css}
    </style>
    <main style="padding:16px">
      <div class="row dashboard-kpis">
        ${card('S/ 0.00', 'Solicitudes de compra abiertas')}
        ${card('0', 'Facturas y boletas emitidas')}
        ${card('S/ 0.00', 'Saldo por cobrar')}
        ${card('0', 'Lotes por vencer en 30 días')}
      </div>
      <div id="sunat-summary" class="row">
        ${card('120', 'Documentos aceptados')}
        ${card('12', 'Pendientes de envío')}
        ${card('3', 'Observados por SUNAT')}
        ${card('1', 'Documentos rechazados')}
        ${card('8', 'Series electrónicas')}
        ${card('S/ 0.00', 'Monto enviado')}
      </div>
    </main>`);

  const collisions = await page.locator('.small-box').evaluateAll(boxes => boxes.flatMap((box, index) => {
    const icon = box.querySelector('.icon').getBoundingClientRect();
    return [...box.querySelectorAll('h3, p')].filter(node => {
      const text = node.getBoundingClientRect();
      return text.left < icon.right && text.right > icon.left && text.top < icon.bottom && text.bottom > icon.top;
    }).map(node => ({ index, text: node.textContent.trim() }));
  }));
  await browser.close();
  if (collisions.length) throw new Error(`Text/icon overlap at 1100px: ${JSON.stringify(collisions)}`);
  console.log('PASS no text/icon overlap at 1100px for dashboard and SUNAT cards');
})().catch(error => { console.error(error); process.exit(1); });

function card(value, label) {
  return `<div class="col"><section class="small-box"><div class="inner"><h3>${value}</h3><p>${label}</p></div><div class="icon">&#9632;</div></section></div>`;
}
