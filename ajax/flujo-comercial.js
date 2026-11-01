$(function () {
  $('#m_flujo_documental').addClass('active').parents('.has-treeview').addClass('menu-open');
  var epoch = 0, current = null;
  var ranks = { quotation: 0, sales_order: 1, remission_guide: 2, invoice: 3, receipt: 3, credit_note: 4, purchase_request: 0, order: 1, purchase_order: 1, goods_receipt: 2, purchase: 3, landed_cost: 3, incoming_payment: 4, outgoing_payment: 4 };
  var statuses = { DRAFT: 'Borrador', PENDING_APPROVAL: 'Pendiente de aprobación', OPEN: 'Abierta', PARTIALLY_PROCESSED: 'Parcial', CLOSED: 'Cerrada', CANCELLED: 'Anulada' };
  var labels = { quotation: 'Cotización', sales_order: 'Orden de venta', invoice: 'Factura', receipt: 'Boleta', credit_note: 'Nota de crédito', purchase_request: 'Solicitud de compra', order: 'Orden de compra', purchase_order: 'Orden de compra', goods_receipt: 'Entrada de mercancías', remission_guide: 'Guía de remisión', purchase: 'Factura de proveedor', landed_cost: 'Costo de entrega', incoming_payment: 'Cobro recibido', outgoing_payment: 'Pago efectuado' };
  function canOpen(type) { return ($('#commercial-flow').data('types') || {})[type] === true; }
  function svg(name, attributes) { var element = document.createElementNS('http://www.w3.org/2000/svg', name); Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); }); return element; }
  function renderReport(data) {
    $('#flow-report-error').addClass('d-none');
    $('#flow-summary').empty();
    (data.summary || []).forEach(function (summary) {
      var $card = $('<div class="col-md-3 mb-2"><div class="border rounded p-2"></div></div>').appendTo('#flow-summary').children();
      $('<strong>').text((labels[summary.source_type] || summary.source_type) + ' → ' + (labels[summary.target_type] || summary.target_type)).appendTo($card);
      $('<br>').appendTo($card);
      $('<span class="text-muted">').text(summary.relationship_type + ': ' + summary.flow_count).appendTo($card);
    });
    if (!(data.summary || []).length) $('<div class="col-12 text-muted">').text('Aún no hay relaciones registradas.').appendTo('#flow-summary');
    var $body = $('#flow-table tbody').empty();
    (data.flows || []).forEach(function (flow) {
      var $row = $('<tr>').appendTo($body);
      $('<td>').text((labels[flow.source_type] || flow.source_type) + ' ' + (flow.source_number || ('#' + flow.source_id))).appendTo($row);
      $('<td>').text((labels[flow.target_type] || flow.target_type) + ' ' + (flow.target_number || ('#' + flow.target_id))).appendTo($row);
      $('<td>').text(flow.relationship_type).appendTo($row);
      $('<td>').text(flow.quantity_applied || '-').appendTo($row);
      $('<td>').text(flow.registration_date).appendTo($row);
      var mapType = canOpen(flow.source_type) ? flow.source_type : (canOpen(flow.target_type) ? flow.target_type : null);
      var mapId = mapType === flow.source_type ? flow.source_id : flow.target_id;
      var $action = $('<td>').appendTo($row);
      if (mapType) $('<button type="button" class="btn btn-xs btn-outline-primary js-open-flow">').text('Ver relaciones').data({ type: mapType, id: mapId }).appendTo($action);
      else $action.text('Sin permiso para abrir');
    });
    if (!(data.flows || []).length) $('<tr><td colspan="6" class="text-muted">Aún no hay relaciones registradas.</td></tr>').appendTo($body);
  }
  function loadReport() {
    if (!$('#flow-table').length) return;
    $('#flow-report-error').addClass('d-none');
    $.getJSON('../../modules/reportes/flujo-documental.php').done(renderReport).fail(function (xhr) {
      $('#flow-report-error').text(xhr.responseJSON && xhr.responseJSON.message || 'No se pudo cargar la lista de relaciones.').removeClass('d-none');
    });
  }
  function draw(data) {
    var nodes = {}, columns = {}, positions = {}, $canvas = $('#commercial-flow-canvas').empty().addClass('commercial-flow-canvas'), height = 170;
    var incoming={},targets={},levels={},visited={};
    data.nodes.forEach(function(node){incoming[node.key]=0;targets[node.key]=[];levels[node.key]=0;});
    data.edges.forEach(function(edge){if(incoming[edge.source]!==undefined&&incoming[edge.target]!==undefined&&edge.source!==edge.target){targets[edge.source].push(edge.target);incoming[edge.target]++;}});
    var queue=Object.keys(incoming).filter(function(key){return incoming[key]===0;});
    while(queue.length){var key=queue.shift();visited[key]=true;targets[key].forEach(function(target){levels[target]=Math.max(levels[target],levels[key]+1);if(--incoming[target]===0)queue.push(target);});}
    data.nodes.forEach(function (node) { nodes[node.key] = node; var rank = visited[node.key]?levels[node.key]:(ranks[node.type]||0), row = columns[rank] || 0; columns[rank] = row + 1; positions[node.key] = { x: rank * 285, y: row * 170 + 15 }; height = Math.max(height, (row + 1) * 170); });
    var width = Math.max(1080, Math.max.apply(null, data.nodes.map(function (node) { return positions[node.key].x + 240; })));
    $canvas.css({ width: width + 'px', height: height + 'px' });
    var canvas = svg('svg', { width: width, height: height, class: 'commercial-flow-svg', 'aria-hidden': 'true' }); $canvas.append(canvas);
    data.edges.forEach(function (edge) {
      var from = positions[edge.source], to = positions[edge.target]; if (!from || !to) return;
      var start = from.x + 220, finish = to.x, sy = from.y + 66, ty = to.y + 66, middle = (start + finish) / 2;
      canvas.appendChild(svg('path', { d: 'M' + start + ',' + sy + ' C' + middle + ',' + sy + ' ' + middle + ',' + ty + ' ' + finish + ',' + ty, 'stroke-dasharray': nodes[edge.target].cancelled ? '5,4' : 'none' }));
      var text = svg('text', { x: middle, y: (sy + ty) / 2 - 5, 'text-anchor': 'middle' }); text.textContent = edge.quantity ? String(edge.quantity) : (edge.reversed_quantity ? 'Revertida' : (edge.amount ? Number(edge.amount).toFixed(2) : '→')); canvas.appendChild(text);
    });
    data.nodes.forEach(function (node) {
      var position = positions[node.key], $node = $('<article class="commercial-flow-node">').toggleClass('is-selected', node.key === data.selected).toggleClass('is-cancelled', node.cancelled).css({ left: position.x, top: position.y }).appendTo($canvas);
      $('<button type="button" class="flow-select">').data({ type: node.type, id: node.id }).text(node.label + ' ' + node.number).attr('title', 'Ver relaciones de ' + node.number).appendTo($node);
      $('<small>').text(node.date + ' · ' + (statuses[node.status] || node.status)).appendTo($node); $('<small>').text(node.customer).attr('title', node.customer).appendTo($node);
      $('<strong>').text(node.amount == null ? 'Documento físico' : Number(node.amount).toFixed(2) + ' ' + node.currency).appendTo($node);
      $('<a>').attr({ href: node.url, class: 'flow-links' }).text('Abrir documento').appendTo($node);
    });
    var selected = nodes[data.selected]; $('#commercial-flow-title').text('Relaciones de ' + (selected ? selected.label + ' ' + selected.number : 'documento'));
    $('#commercial-flow-status').text((data.truncated ? 'Se muestran los primeros 100 documentos relacionados.' : data.nodes.length + ' documentos relacionados.')+(data.missing_related_documents?' Hay '+data.missing_related_documents+' referencias históricas cuyo registro no está disponible.':''));
    var $body = $('#commercial-flow-lines').empty();
    (data.line_applications || []).forEach(function (line) { var $row = $('<tr>').appendTo($body); [line.source_number,line.source_line_number,line.target_number,line.target_line_number,({ CONVERSION: 'Copia', FULFILLMENT: 'Entrega', RECEPTION: 'Recepción', BILLING: 'Facturación', CREDIT: 'Crédito' })[line.stage] || line.stage,line.quantity,line.status === 'ACTIVE' ? 'Activa' : 'Revertida'].forEach(function (value) { $('<td>').text(value).appendTo($row); }); });
    if (!(data.line_applications || []).length) $('<tr>').append($('<td colspan="7" class="text-muted">').text('Sin aplicaciones por línea registradas para este documento.')).appendTo($body);
  }
  function load(type, id) {
    current = { type: type, id: id }; var request = ++epoch; $('#commercial-flow-error').addClass('d-none');
    $.getJSON('../../modules/trazabilidad/flujo.php', { action: 'flow', type: type, id: id }).done(function (data) { if (request === epoch) draw(data); }).fail(function (xhr) { if (request === epoch) $('#commercial-flow-error').text(xhr.responseJSON && xhr.responseJSON.message || 'No se pudo cargar el flujo documental.').removeClass('d-none'); });
  }
  $(document).on('click', '.flow-select, .js-open-flow', function () { var type = $(this).data('type'), id = $(this).data('id'); history.replaceState(null, '', '?type=' + encodeURIComponent(type) + '&id=' + id); load(type, id); });
  $('#commercial-flow-refresh').on('click', function () { if (current) load(current.type, current.id); });
  $('#flow-report-refresh').on('click', loadReport);
  var params = new URLSearchParams(window.location.search), type = params.get('type'), id = Number(params.get('id'));
  if (Object.prototype.hasOwnProperty.call(ranks, type) && id > 0) load(type, id); else $('#commercial-flow-status').text('Selecciona una relación reciente o abre este mapa desde un documento.');
  loadReport();
});
