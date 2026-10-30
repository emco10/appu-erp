const { test } = require('node:test');
const assert = require('node:assert/strict');
const { setup } = require('./ui-harness.cjs');

test('El mapa muestra nodos, relaciones y líneas, y permite navegar a la factura', async () => {
  const t = await setup('views/ventas/flujo-comercial.php', ['ajax/flujo-comercial.js'], '?type=sales_order&id=1');
  const { $ } = t;
  try {
    t.pending(r => r.data?.action === 'flow')[0].deferred.resolve({ selected: 'sales_order:1', nodes: [
      { key: 'sales_order:1', type: 'sales_order', id: 1, label: 'Orden de venta', number: 'OV-1', date: '2026-10-27', status: 'CLOSED', customer: '<img src=x>', amount: 118, currency: 'PEN', url: '../ventas/resumen-ordenes-venta?record_id=1' },
      { key: 'remission_guide:2', type: 'remission_guide', id: 2, label: 'Entrega', number: 'EN-2', date: '2026-10-27', status: 'Registrada', customer: 'Cliente QA', amount: null, url: '../logistica/guias?record_id=2' },
      { key: 'invoice:3', type: 'invoice', id: 3, label: 'Factura', number: 'FV-3', date: '2026-10-27', status: 'Vigente', amount: 118, currency: 'PEN', url: '../facturacion/registro-factura?record_id=3' }
    ], edges: [{ source: 'sales_order:1', target: 'remission_guide:2', quantity: 10 }, { source: 'remission_guide:2', target: 'invoice:3', quantity: 10 }], line_applications: [{ source_number: 'OV-1', source_line_number: 1, target_number: 'EN-2', target_line_number: 1, stage: 'FULFILLMENT', quantity: 10, status: 'ACTIVE' }] });
    assert.equal($('.commercial-flow-node').length, 3); assert.equal($('.commercial-flow-svg path').length, 2);
    assert.equal($('.commercial-flow-node img').length, 0); assert.match($('.is-selected').text(), /Cerrada/);
    assert.match($('#commercial-flow-lines').text(), /OV-1/); assert.match($('#commercial-flow-lines').text(), /Activa/);
    $('.flow-select').filter(function () { return $(this).data('type') === 'invoice'; }).trigger('click');
    assert.equal(t.pending(r => r.data?.action === 'flow').at(-1).data.type, 'invoice'); assert.match(t.window.location.search, /type=invoice&id=3/);
  } finally { t.dom.window.close(); }
});

test('Abrir una entrega por record_id muestra consulta sin generar otra salida ni impresión automática', async () => {
  const t = await setup('views/logistica/guias.php', ['ajax/guias.js'], '?record_id=9'); const { $ } = t;
  try {
    t.pending(r => !r.data)[0].deferred.resolve({ guides: [] });
    const request = t.pending(r => r.data?.action === 'detail')[0]; assert.equal(request.data.guide_id, 9);
    request.deferred.resolve({ guide: { id: 9, number: 'EN-9', source_number: 'OV-9', status: 'Registrada', transfer_date: '2026-10-27', recipient_name: 'Cliente', destination_address: 'Destino', inventory_posted: 1 }, details: [{ item_code: 'ART', description: 'Artículo QA', unit_code: 'UND', quantity: 6 }] });
    assert.match($('#guide-preview-header').text(), /OV-9/); assert.match($('#guide-preview-header').text(), /Salida física registrada/);
    assert.equal($('#guide-preview-lines tr').length, 1); assert.equal($('#guide-preview-modal').attr('data-document-mode'), 'VIEW');
    assert.equal(t.requests.filter(r => r.method === 'POST').length, 0);
  } finally { t.dom.window.close(); }
});

test('El mapa de compras distribuye recepción y pago, muestra cantidades y usa el endpoint común',async()=>{
 const t=await setup('views/ventas/flujo-comercial.php',['ajax/flujo-comercial.js'],'?type=goods_receipt&id=9');try{
  const request=t.pending(r=>r.data?.action==='flow')[0];assert.match(request.url,/modules\/trazabilidad\/flujo.php/);
  request.deferred.resolve({selected:'goods_receipt:9',nodes:[{key:'order:1',type:'order',id:1,label:'Orden de compra',number:'OC-1',date:'2026-10-28',status:'Parcial',amount:118,currency:'PEN',url:'../ordenes/orden-de-compra?record_id=1'},{key:'goods_receipt:9',type:'goods_receipt',id:9,label:'Entrada de mercancías',number:'EM-9',date:'2026-10-28',status:'Registrado',amount:null,url:'../compras/recepciones?receipt_id=9'},{key:'outgoing_payment:3',type:'outgoing_payment',id:3,label:'Pago efectuado',number:'PAG-3',date:'2026-10-28',status:'Registrado',amount:50,currency:'PEN',url:'../finanzas/cuentas-por-pagar?payment_id=3'}],edges:[{source:'order:1',target:'goods_receipt:9',quantity:4},{source:'order:1',target:'outgoing_payment:3',amount:50}],line_applications:[{source_number:'OC-1',source_line_number:1,target_number:'EM-9',target_line_number:1,stage:'RECEPTION',quantity:4,status:'ACTIVE'}]});
  assert.equal(t.$('.commercial-flow-node').length,3);assert.match(t.$('#commercial-flow-lines').text(),/Recepción/);assert.match(t.$('.commercial-flow-svg').text(),/50.00/);assert.ok(parseInt(t.$('.commercial-flow-node').last().css('left'))>parseInt(t.$('.commercial-flow-node').first().css('left')));assert.match(t.$('.flow-links').last().attr('href'),/payment_id=3/);
 }finally{t.dom.window.close();}
});
