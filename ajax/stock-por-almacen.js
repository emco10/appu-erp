$(function () {
  $("#m_stock_almacen").addClass("active");
  var currentRows=[];
  $('<button type="button" id="stock-query-export" class="btn btn-sm btn-outline-primary float-right">Exportar CSV</button>').appendTo($('#stock-query-table').closest('.card').children('.card-header'));
  function load(filters) {
    $.getJSON("../../modules/inventory/stock-query.php", filters || {}).done(function (r) {
      currentRows=r.rows||[];
      function fill(selector, rows, label) { var selected=$(selector).val();var $s=$(selector).empty().append("<option value=''>Todos</option>"); (rows||[]).forEach(function(x){$s.append($('<option>').val(x.id).text(label(x)));});if(selected)$s.val(selected); }
      fill("#stock-warehouse",r.warehouses,function(x){return x.code+" - "+x.name;}); fill("#stock-product",r.products,function(x){return x.code+" - "+x.name;});
      var $body=$("#stock-query-table tbody").empty();
      (r.rows||[]).forEach(function(x){var expired=x.expiration_date&&x.expiration_date<new Date().toISOString().slice(0,10);$("<tr>").append($("<td>").text(x.warehouse_code+" - "+x.warehouse_name)).append($("<td>").text(x.product_code+" - "+x.product_name)).append($("<td>").text(x.lot_number)).append($("<td>").addClass(expired?"text-danger font-weight-bold":"").text(x.expiration_date||"Sin vencimiento")).append($("<td class='text-right'>").text(x.quantity)).append($("<td class='text-right'>").text(x.reserved_quantity)).append($("<td class='text-right font-weight-bold'>").text(x.available_quantity)).append($("<td class='text-right'>").text(Number(x.average_unit_cost||0).toFixed(4))).append($("<td class='text-right'>").text(Number(x.inventory_value||0).toFixed(2))).appendTo($body);});
      var $trace=$("#stock-lot-consumption-table tbody").empty();
      if(!currentRows.length)$('<tr><td colspan="9" class="text-muted text-center">No hay existencias para los filtros aplicados.</td></tr>').appendTo($body);
      (r.lot_consumptions||[]).forEach(function(x){$("<tr>").append($("<td>").text(x.registration_date)).append($("<td>").text(x.source_type+" #"+x.source_id)).append($("<td>").text(x.warehouse_code+" - "+x.warehouse_name)).append($("<td>").text(x.product_code+" - "+x.product_name)).append($("<td>").text(x.lot_number)).append($("<td>").text(x.expiration_date||"Sin vencimiento")).append($("<td class='text-right'>").text(x.quantity)).appendTo($trace);});
      if(!r.lot_consumptions||!r.lot_consumptions.length)$('<tr><td colspan="7" class="text-muted">Aún no hay salidas trazadas por lote.</td></tr>').appendTo($trace);
    }).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||'No se pudo consultar el inventario. Reintenta la búsqueda.');});
  }
  $("#stock-query-form").on("submit",function(e){e.preventDefault();load($(this).serialize());});load();
  $('#stock-query-export').on('click',function(){if(!currentRows.length){toastr.info('No hay existencias para exportar.');return;}var rows=[['Almacén','Artículo','Lote','Vencimiento','Stock','Reservado','Disponible','Costo promedio','Valor inventario']].concat(currentRows.map(function(row){return[row.warehouse_code,row.product_code,row.lot_number,row.expiration_date,row.quantity,row.reserved_quantity,row.available_quantity,row.average_unit_cost,row.inventory_value];}));var csv='\ufeff'+rows.map(function(row){return row.map(function(value){var text=String(value==null?'':value);if(/^[=+\-@]/.test(text))text="'"+text;return '"'+text.replace(/"/g,'""')+'"';}).join(';');}).join('\r\n');var link=document.createElement('a');link.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));link.download='existencias-almacen.csv';document.body.appendChild(link);link.click();URL.revokeObjectURL(link.href);link.remove();});
});
