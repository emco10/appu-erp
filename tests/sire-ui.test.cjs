const { test } = require('node:test');
const assert = require('node:assert/strict');
const { setup } = require('./ui-harness.cjs');

async function create(settings = {}) {
  return setup('views/gestion/sire.php', ['ajax/sire.js'], '', settings);
}

test('SIRE conserva comillas de una respuesta como datos sin inyectar atributos en los botones',async()=>{
  const t=await create();try{
    const payload='123456789012" autofocus onfocus="window.__xss=1';
    t.$('#sire-tickets').trigger('click');
    t.requests.at(-1).deferred.resolve({data:{tickets:[{ticket:payload,period:payload,status:'completed',status_description:'Terminado',files:[{name:'file.zip'}]}],pagination:{total:1}}});
    const button=t.$('[data-sire-download]');
    assert.equal(button.attr('data-ticket'),payload);
    assert.equal(button.attr('data-period'),payload);
    assert.equal(t.$('#sire-ticket-table [onfocus],#sire-ticket-table [autofocus]').length,0);
    assert.equal(t.window.__xss,undefined);
  }finally{t.dom.window.close();}
});

test('SIRE no consulta al abrir ni al cambiar libro/período; consulta solo al pulsar un botón', async () => {
  const t = await create();
  try {
    assert.equal(t.requests.length, 0);
    t.$('#sire-book').val('140000').trigger('change');
    t.$('#sire-period').val('2026-10').trigger('change');
    assert.equal(t.requests.length, 0);
    t.$('#sire-periods').trigger('click');
    t.$('#sire-periods').trigger('click');
    assert.equal(t.requests.length, 1);
    assert.equal(t.requests[0].method, 'GET');
    assert.equal(t.requests[0].data.book, '140000');
    t.requests[0].deferred.resolve({ data: { periods: [{ period: '202610', status_description: '<img src=x onerror=alert(1)>' }] } });
    assert.equal(t.$('#sire-period-list img').length, 0);
    assert.match(t.$('#sire-period-list').text(), /<img/);
    await t.flush();
    assert.equal(t.requests.length, 1);
  } finally { t.dom.window.close(); }
});

test('SIRE exporta manualmente una sola vez por clic y conserva el ticket sin consultar automáticamente', async () => {
  const t = await create();
  try {
    t.$('#sire-period').val('2026-10');
    t.$('#sire-export').trigger('click');
    t.$('#sire-export').trigger('click');
    assert.equal(t.requests.length, 1);
    assert.equal(t.requests[0].method, 'POST');
    assert.equal(t.requests[0].data.period, '202610');
    t.requests[0].deferred.resolve({ data: { ticket: '20260300000131' } });
    assert.equal(t.$('#sire-ticket').val(), '20260300000131');
    assert.match(t.$('#sire-status').text(), /Consultar tickets/);
    await t.flush();
    assert.equal(t.requests.length, 1);
    assert.equal(t.$('#sire-export').prop('disabled'), false);
  } finally { t.dom.window.close(); }
});

test('SIRE de consulta oculta y bloquea exportación; conserva datos cuando el API está pendiente de configurar', async () => {
  const t = await create({ can: permission => permission === 'management.view' });
  try {
    assert.equal(t.$('#sire-export').css('display'), 'none');
    t.$('#sire-export').trigger('click');
    assert.equal(t.requests.length, 0);
    t.$('#sire-period').val('2026-10');
    t.$('#sire-ticket').val('20260300000131');
    t.$('#sire-proposal').trigger('click');
    t.requests[0].deferred.reject({ status: 503, responseJSON: { message: 'SIRE pendiente de configurar.' } });
    assert.equal(t.$('#sire-ticket').val(), '20260300000131');
    assert.equal(t.$('#sire-period').val(), '2026-10');
    assert.match(t.$('#sire-status').text(), /pendiente de configurar/);
    assert.equal(t.$('#sire-proposal').prop('disabled'), false);
    assert.equal(t.requests.length, 1);
  } finally { t.dom.window.close(); }
});

test('SIRE pagina la propuesta por acción manual y representa datos como texto', async () => {
  const t = await create();
  try {
    t.$('#sire-proposal').trigger('click');
    assert.equal(t.requests.length, 0);
    t.$('#sire-ticket').val('20260300000131');
    t.$('#sire-period').val('2026-10');
    t.$('#sire-proposal').trigger('click');
    t.requests[0].deferred.resolve({ data: { pagination: { total: 70 }, columns: ['RUC', '<script>x</script>'], records: [['20600000001', '<img src=x>']] } });
    assert.equal(t.$('#sire-proposal-table script,#sire-proposal-table img').length, 0);
    assert.match(t.$('#sire-proposal-table tbody').text(), /<img src=x>/);
    assert.equal(t.$('#sire-proposal-next').prop('disabled'), false);
    assert.equal(t.requests.length, 1);
    t.$('#sire-proposal-next').trigger('click');
    assert.equal(t.requests.length, 2);
    assert.equal(t.requests[1].data.page, 2);
    assert.equal(t.requests[1].data.per_page, 50);
  } finally { t.dom.window.close(); }
});

test('SIRE descarta respuestas tardías después de cambiar el libro', async () => {
  const t = await create();
  try {
    t.$('#sire-ticket').val('20260300000131');
    t.$('#sire-period').val('2026-10');
    t.$('#sire-proposal').trigger('click');
    t.$('#sire-book').val('140000').trigger('change');
    t.requests[0].deferred.resolve({ data: { pagination: { total: 1 }, columns: ['OLD BOOK'], records: [['old value']] } });
    assert.equal(t.$('#sire-proposal-table').text().trim(), '');
    assert.equal(t.$('#sire-ticket').val(), '');
    assert.equal(t.requests.length, 1);
  } finally { t.dom.window.close(); }
});

test('SIRE seleccionar un ticket no dispara descargas ni nuevas consultas', async () => {
  const t = await create();
  try {
    t.$('#sire-period').val('2026-10');
    t.$('#sire-filters').trigger('submit');
    t.requests[0].deferred.resolve({ data: { pagination: { total: 1 }, tickets: [{ ticket: '20260300000131', period: '202610', status: 'completed', status_description: 'Terminado', files: [{ name: 'proposal.zip' }] }] } });
    t.$('[data-sire-use]').trigger('click');
    assert.equal(t.$('#sire-ticket').val(), '20260300000131');
    assert.equal(t.requests.length, 1);
    assert.match(t.$('#sire-status').text(), /Ticket seleccionado/);
  } finally { t.dom.window.close(); }
});

test('SIRE muestra un fallo de descarga sin perder el formulario y sin reintentar automáticamente', async () => {
  let downloads = 0;
  const t = await create({ beforeScripts: window => {
    window.fetch = async () => { downloads++; return { ok: false, json: async () => ({ message: 'El ticket todavía está en proceso.' }) }; };
  } });
  try {
    t.$('#sire-period').val('2026-10');
    t.$('#sire-ticket').val('20260300000131');
    t.$('#sire-download').trigger('click');
    await t.flush();
    assert.equal(downloads, 1);
    assert.match(t.$('#sire-status').text(), /todavía está en proceso/);
    assert.equal(t.$('#sire-ticket').val(), '20260300000131');
    assert.equal(t.$('#sire-download').prop('disabled'), false);
    assert.equal(t.requests.length, 0);
  } finally { t.dom.window.close(); }
});
