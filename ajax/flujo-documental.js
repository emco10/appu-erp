$(function () {
  $("#m_reportes").addClass("active").parent().addClass("menu-open");
  $.getJSON("../../modules/reportes/flujo-documental.php").done(function (data) {
    var labels = {
      quotation: "Cotización", sales_order: "Orden de venta", invoice: "Factura", receipt: "Boleta",
      credit_note: "Nota de crédito", purchase: "Compra", purchase_request: "Solicitud de compra",
      purchase_order: "Orden de compra", order: "Orden de compra", goods_receipt: "Entrada de mercancías",
      remission_guide: "Guía de remisión", incoming_payment: "Cobro recibido",
      outgoing_payment: "Pago efectuado", landed_cost: "Costo de entrega"
    };
    var mapTypes = { quotation: true, sales_order: true, invoice: true, receipt: true, credit_note: true, purchase: true, purchase_request: true, purchase_order: true, order: true, goods_receipt: true, remission_guide: true, incoming_payment: true, outgoing_payment: true, landed_cost: true };
    $("#flow-summary").html((data.summary || []).map(function (summary) {
      return "<div class='col-md-3 mb-2'><div class='border rounded p-2'><strong>" +
        (labels[summary.source_type] || summary.source_type) + " → " + (labels[summary.target_type] || summary.target_type) +
        "</strong><br><span class='text-muted'>" + summary.relationship_type + ": " + summary.flow_count + "</span></div></div>";
    }).join("") || "<div class='col-12 text-muted'>Aún no hay relaciones registradas.</div>");
    var $body = $("#flow-table tbody").empty();
    (data.flows || []).forEach(function (flow) {
      var $row = $("<tr>").append($("<td>").text((labels[flow.source_type] || flow.source_type) + " " + (flow.source_number || ("#" + flow.source_id))))
        .append($("<td>").text((labels[flow.target_type] || flow.target_type) + " " + (flow.target_number || ("#" + flow.target_id))))
        .append($("<td>").text(flow.relationship_type)).append($("<td>").text(flow.quantity_applied || "-"))
        .append($("<td>").text(flow.registration_date));
      var $actions = $("<td>");
      var mapType = mapTypes[flow.source_type] ? flow.source_type : (mapTypes[flow.target_type] ? flow.target_type : null);
      var mapId = mapType === flow.source_type ? flow.source_id : flow.target_id;
      if (mapType) {
        $("<a>").attr({
          href: "../trazabilidad/flujo?type=" + encodeURIComponent(mapType) + "&id=" + encodeURIComponent(mapId),
          class: "btn btn-xs btn-outline-primary"
        }).text("Ver mapa").appendTo($actions);
      } else {
        $actions.text("—");
      }
      $row.append($actions).appendTo($body);
    });
  }).fail(function () { toastr.error("No se pudo cargar el flujo documental."); });
});
