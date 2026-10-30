$(function () {
  $("#m_logistica_documentos").addClass("active");
  $(document).prop("title", "Mapa de documentos - APPU");

  var statusLabels = {
    1: "Pendiente",
    2: "En preparación",
    3: "En ruta",
    4: "Entregada"
  };
  var documentStatusLabels = {
    1: "Vigente",
    2: "Anulada",
    3: "Pendiente de pago",
    4: "Cancelada"
  };
  var typeLabels = {
    invoice: "Factura",
    receipt: "Boleta",
    quotation: "Cotización",
    sales_order: "Orden de venta",
    purchase: "Compra",
    purchase_request: "Solicitud de compra",
    purchase_order: "Orden de compra",
    credit_note: "Nota de crédito"
  };
  var currencyLabels = { MN: "PEN", ME: "ME" };
  var documentRoutes = {
    quotation: "../cotizacion/registro-cotizacion",
    sales_order: "../ventas/resumen-ordenes-venta",
    invoice: "../facturacion/registro-factura",
    receipt: "../facturacion/registro-boleta",
    credit_note: "../facturacion/registro-nota-credito",
    purchase_request: "../compras/solicitudes",
    purchase_order: "../ordenes/orden-de-compra",
    purchase: "../compras/resumen-compras"
  };
  var currentMapDocument = null;

  function formatAmount(amount, currency) {
    if (currency === "MN") {
      return new Intl.NumberFormat("es-PE", { style: "currency", currency: "PEN" }).format(Number(amount) || 0);
    }
    return (currencyLabels[currency] || currency || "") + " " + new Intl.NumberFormat("es-PE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(Number(amount) || 0);
  }

  function renderDocumentNode(title, number, date, customer, amount, currency, status, relation, type, id) {
    var $item = $("<div>").addClass("list-group-item");
    var $row = $("<div>").addClass("d-flex flex-wrap justify-content-between align-items-center").appendTo($item);
    var $details = $("<div>").appendTo($row);
    $("<strong>").text(title + " " + number).appendTo($details);
    $("<div>").addClass("small text-muted").text([date, customer, relation].filter(Boolean).join(" · ")).appendTo($details);
    $("<span>").addClass("badge badge-light ml-2").text(documentStatusLabels[status] || ("Estado " + status)).appendTo($details);
    var $right = $("<div>").addClass("ml-auto pl-2 text-right").appendTo($row);
    $("<strong>").addClass("d-block").text(formatAmount(amount, currency)).appendTo($right);
    if (type && Number(id) > 0) {
      $("<button>").attr({ type: "button", class: "btn btn-xs btn-outline-primary mt-1 mr-1" })
        .text("Ver relaciones")
        .on("click", function () { loadMap(type, Number(id)); })
        .appendTo($right);
      if (documentRoutes[type]) {
        $("<a>").attr({ href: documentRoutes[type] + "?record_id=" + encodeURIComponent(id), class: "btn btn-xs btn-outline-secondary mt-1" })
          .text("Abrir módulo")
          .appendTo($right);
      }
    }
    return $item;
  }

  function renderDeliveryNode(sale, delivery) {
    var $item = $("<div>").addClass("list-group-item list-group-item-light");
    var $row = $("<div>").addClass("d-flex flex-wrap justify-content-between align-items-center").appendTo($item);
    var $details = $("<div>").appendTo($row);

    if (!delivery) {
      $("<strong>").text("Sin despacho registrado").appendTo($details);
      $("<div>").addClass("small text-muted").text("Se registra un despacho interno completo para este documento.").appendTo($details);
      if (Number(sale.status) !== 2) {
        $("<button>").attr({
          type: "button",
          class: "btn btn-sm btn-outline-primary mt-2 js-create-delivery",
          "data-source-type": sale.type,
          "data-source-id": sale.id,
          "data-recipient": sale.customer || "",
          "data-address": sale.address || "",
          "data-planned-date": sale.planned_date || ""
        }).text("Registrar despacho").appendTo($row);
      }
      return $item;
    }

    $("<strong>").text("Entrega interna: " + (statusLabels[delivery.status] || "Pendiente")).appendTo($details);
    $("<div>").addClass("small text-muted").text(
      "Programada: " + delivery.planned_date
      + (delivery.carrier ? " · Transportista: " + delivery.carrier : "")
      + (delivery.tracking_number ? " · Ref.: " + delivery.tracking_number : "")
    ).appendTo($details);
    var $actions = $("<div>").addClass("mt-2 mt-sm-0").appendTo($row);
    $("<button>").attr({
      type: "button",
      class: "btn btn-sm btn-outline-secondary mr-1 js-print-delivery"
    }).data("delivery", delivery).data("sale", sale).text("Imprimir constancia").appendTo($actions);

    if (delivery.status < 4) {
      var nextStatus = delivery.status + 1;
      $("<button>").attr({
        type: "button",
        class: "btn btn-sm btn-primary js-advance-delivery",
        "data-delivery-id": delivery.id,
        "data-status": nextStatus
      }).text("Marcar: " + statusLabels[nextStatus]).appendTo($actions);
    }
    return $item;
  }

  function renderMap(data) {
    var $map = $("#document-map").empty();
    $("<h5>").addClass("mb-3").text("Relaciones de " + typeLabels[data.selected.type] + " " + data.selected.number).appendTo($map);
    $('<a class="btn btn-sm btn-outline-primary mb-3">').attr('href','../trazabilidad/flujo?type='+encodeURIComponent(data.selected.type)+'&id='+encodeURIComponent(data.selected.id)).text('Ver mapa completo con aplicaciones por línea').appendTo($map);
    var $timeline = $("<div>").addClass("list-group document-relationship-map").appendTo($map);
    var quotation = data.quotation;

    if (quotation) {
      $timeline.append(renderDocumentNode(
        "Cotización",
        quotation.number,
        quotation.date,
        quotation.customer,
        quotation.amount,
        quotation.currency,
        Number(quotation.status),
        "Documento de origen", "quotation", quotation.id
      ));
    }

    if (data.sales_order) {
      $timeline.append(renderDocumentNode(
        "Orden de venta",
        data.sales_order.number,
        data.sales_order.date,
        data.sales_order.customer,
        data.sales_order.amount,
        data.sales_order.currency,
        Number(data.sales_order.status),
        quotation ? "Confirmada desde la cotización" : "Documento de origen", "sales_order", data.sales_order.id
      ));
    }

    if (!data.purchase && data.purchase_request) {
      $timeline.append(renderDocumentNode(
        "Solicitud de compra",
        data.purchase_request.number,
        data.purchase_request.date,
        "",
        data.purchase_request.amount,
        data.purchase_request.currency,
        Number(data.purchase_request.status),
        "Documento de origen", "purchase_request", data.purchase_request.id
      ));
    }
    if (!data.purchase && data.purchase_order) {
      $timeline.append(renderDocumentNode(
        "Orden de compra",
        data.purchase_order.number,
        data.purchase_order.date,
        data.purchase_order.customer,
        data.purchase_order.amount,
        data.purchase_order.currency,
        Number(data.purchase_order.status),
        data.purchase_request ? "Generada desde la solicitud" : "Documento de origen", "purchase_order", data.purchase_order.id
      ));
    }
    if (data.purchase) {
      if (data.purchase_request) {
        $timeline.append(renderDocumentNode(
          "Solicitud de compra",
          data.purchase_request.number,
          data.purchase_request.date,
          "",
          data.purchase_request.amount,
          data.purchase_request.currency,
          1,
          "Documento de origen", "purchase_request", data.purchase_request.id
        ));
      }
      if (data.purchase_order) {
        $timeline.append(renderDocumentNode(
          "Orden de compra",
          data.purchase_order.number,
          data.purchase_order.date,
          data.purchase_order.customer,
          data.purchase_order.amount,
          data.purchase_order.currency,
          Number(data.purchase_order.status),
          data.purchase_request ? "Generada desde la solicitud" : "Documento de origen", "purchase_order", data.purchase_order.id
        ));
      }
      $timeline.append(renderDocumentNode(
        "Compra",
        data.purchase.number,
        data.purchase.date,
        data.purchase.customer,
        data.purchase.amount,
        data.purchase.currency,
        Number(data.purchase.status),
        "Documento de compra", "purchase", data.purchase.id
      ));
      (data.goods_receipts || []).forEach(function (receipt) {
        var $receipt = $("<div>").addClass("list-group-item list-group-item-light");
        $("<strong>").text("Recepción de mercancía " + receipt.number).appendTo($receipt);
        $("<div>").addClass("small text-muted").text([
          receipt.date,
          receipt.warehouse_name ? "Almacén: " + receipt.warehouse_name : "",
          receipt.status
        ].filter(Boolean).join(" · ")).appendTo($receipt);
        $timeline.append($receipt);
      });
      (data.landed_costs || []).forEach(function (cost) {
        var $cost = $("<div>").addClass("list-group-item list-group-item-info");
        $("<strong>").text("Costo de entrega " + cost.number).appendTo($cost);
        $("<div>").addClass("small").text([
          cost.date,
          cost.receipt_number ? "Recepción: " + cost.receipt_number : "",
          cost.concept,
          String(cost.allocation_method).toUpperCase() === "QUANTITY" ? "Distribución por cantidad" : "Distribución por valor",
          formatAmount(cost.total_cost, cost.currency),
          cost.status
        ].filter(Boolean).join(" · ")).appendTo($cost);
        $("<a>").addClass("btn btn-xs btn-outline-info mt-2").attr("href", "../compras/costos-entrega?record_id=" + encodeURIComponent(cost.id)).text("Ver costo").appendTo($cost);
        $timeline.append($cost);
      });
      if (data.payable) {
        var $payable = $("<div>").addClass("list-group-item list-group-item-warning");
        $("<strong>").text("Cuenta por pagar " + (data.payable.document_number || "")).appendTo($payable);
        $("<div>").addClass("small").text([
          "Estado: " + (data.payable.status || "Pendiente"),
          "Saldo: " + formatAmount(data.payable.balance_amount, data.payable.currency),
          "Vence: " + (data.payable.due_date || "")
        ].filter(Boolean).join(" · ")).appendTo($payable);
        $timeline.append($payable);
      }
      (data.payments || []).forEach(function (payment) {
        var $payment = $("<div>").addClass("list-group-item list-group-item-success");
        $("<strong>").text("Pago efectuado " + (payment.payment_number || "")).appendTo($payment);
        $("<div>").addClass("small").text([
          payment.date,
          payment.payment_method,
          formatAmount(payment.amount, payment.currency),
          payment.status || "Registrado",
          payment.reference ? "Ref.: " + payment.reference : ""
        ].filter(Boolean).join(" · ")).appendTo($payment);
        $timeline.append($payment);
      });
    }

    (data.sales_documents || []).forEach(function (sale) {
      sale.address = data.selected.type === sale.type && Number(data.selected.id) === Number(sale.id)
        ? (data.selected.address || "")
        : "";
      $timeline.append(renderDocumentNode(
        typeLabels[sale.type],
        sale.number,
        sale.date,
        sale.customer,
        sale.amount,
        sale.currency,
        Number(sale.status),
        quotation ? "Emitida desde la cotización" : "Documento de venta", sale.type, sale.id
      ));
      var delivery = (data.deliveries || []).find(function (item) {
        return item.source_type === sale.type && Number(item.source_id) === Number(sale.id);
      });
      $timeline.append(renderDeliveryNode(sale, delivery));
      (data.payments || []).filter(function (payment) {
        return payment.source_type === "receivable" && Number(payment.source_id) === Number(sale.id);
      }).forEach(function (payment) {
        var $payment = $("<div>").addClass("list-group-item list-group-item-success");
        $("<strong>").text("Cobro recibido " + (payment.payment_number || "")).appendTo($payment);
        $("<div>").addClass("small").text([
          payment.date,
          payment.payment_method,
          formatAmount(payment.amount, payment.currency),
          payment.status || "Registrado",
          payment.reference ? "Ref.: " + payment.reference : ""
        ].filter(Boolean).join(" · ")).appendTo($payment);
        $timeline.append($payment);
      });

      (data.credit_notes || []).filter(function (note) {
        return note.source_type === sale.type && Number(note.source_id) === Number(sale.id);
      }).forEach(function (note) {
        $timeline.append(renderDocumentNode(
          "Nota de crédito",
          note.number,
          note.date,
          note.customer,
          -Math.abs(Number(note.amount)),
          note.currency,
          Number(note.status),
          "Aplicada a " + typeLabels[sale.type] + " " + sale.number, "credit_note", note.id
        ));
      });
    });

    if (data.selected.type === "credit_note" && data.sales_documents.length === 0) {
      $timeline.append(renderDocumentNode(
        "Nota de crédito",
        data.selected.display_number || data.selected.number,
        data.selected.date,
        data.selected.customer,
        -Math.abs(Number(data.selected.amount)),
        data.selected.currency,
        Number(data.selected.status),
        "No se encontró el documento de origen", "credit_note", data.selected.id
      ));
    } else if (data.selected.type === "quotation" && !data.sales_documents.length) {
      $("<div>").addClass("list-group-item text-muted").text("Todavía no hay factura ni boleta vinculada a esta cotización.").appendTo($timeline);
    }
  }

  function loadMap(type, id) {
    currentMapDocument = { type: type, id: id };
    $("#document-search-error").addClass("d-none").text("");
    $.getJSON("../../modules/logistica/documentos.php", { action: "map", type: type, id: id })
      .done(renderMap)
      .fail(function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo cargar el mapa.";
        $("#document-search-error").text(message).removeClass("d-none");
      });
  }

  function loadDeliveries() {
    $.getJSON("../../modules/logistica/documentos.php", { action: "deliveries" })
      .done(function (response) {
        var rows = response.deliveries || [];
        var $table = $("<table>").addClass("table table-hover table-striped mb-0");
        var $head = $("<tr>").appendTo($("<thead>").appendTo($table));
        ["Documento", "Cliente", "Entrega programada", "Estado", "Acciones"].forEach(function (label) {
          $("<th>").text(label).appendTo($head);
        });
        var $body = $("<tbody>").appendTo($table);
        if (!rows.length) {
          $("<tr>").append($("<td>").attr("colspan", 5).addClass("text-center text-muted py-3").text("No hay despachos registrados. Busca una factura o boleta para crear uno.")).appendTo($body);
        }
        rows.forEach(function (delivery) {
          var $row = $("<tr>").appendTo($body);
          $("<td>").text((typeLabels[delivery.source_type] || "Documento") + " " + (delivery.document_series || "") + "-" + (delivery.document_number || "")).appendTo($row);
          $("<td>").text(delivery.customer || "").appendTo($row);
          $("<td>").text(delivery.planned_date).appendTo($row);
          $("<td>").append($("<span>").addClass("badge badge-info").text(statusLabels[delivery.status] || "Pendiente")).appendTo($row);
          var $actions = $("<td>").appendTo($row);
          $("<button>").attr({
            type: "button",
            class: "btn btn-sm btn-outline-primary js-open-delivery-map",
            "data-source-type": delivery.source_type,
            "data-source-id": delivery.source_id
          }).text("Ver mapa").appendTo($actions);
        });
        $("#delivery-list").empty().append($table);
      })
      .fail(function () {
        $("#delivery-list").text("No se pudieron cargar los despachos.");
      });
  }

  function searchDocuments(term) {
    $("#document-search-error").addClass("d-none").text("");
    $("#document-search-results").empty();
    $("#document-map").empty();
    $.getJSON("../../modules/logistica/documentos.php", { action: "search", q: term })
      .done(function (response) {
        var documents = response.documents || [];
        if (!documents.length) {
          $("#document-search-results").append($("<div>").addClass("list-group-item text-muted").text("No se encontraron documentos."));
          return;
        }
        documents.forEach(function (document) {
          var $button = $("<button>").attr({
            type: "button",
            class: "list-group-item list-group-item-action js-open-document-map"
          }).data("type", document.type).data("id", document.id);
          var $row = $("<div>").addClass("d-flex flex-wrap justify-content-between").appendTo($button);
          $("<strong>").text(document.type_label + " " + document.number).appendTo($row);
          $("<span>").text(formatAmount(document.amount, document.currency)).appendTo($row);
          $("<div>").addClass("small text-muted w-100").text(document.date + " · " + document.customer + " · " + document.status_label).appendTo($button);
          $("#document-search-results").append($button);
        });
      })
      .fail(function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo buscar documentos.";
        $("#document-search-error").text(message).removeClass("d-none");
      });
  }

  function openDeliveryForm(button) {
    var $form = $("#delivery-form");
    $form[0].reset();
    $form.find('[name="source_type"]').val(button.getAttribute("data-source-type"));
    $form.find('[name="source_id"]').val(button.getAttribute("data-source-id"));
    $("#delivery-recipient").val(button.getAttribute("data-recipient") || "");
    $("#delivery-address").val(button.getAttribute("data-address") || "");
    $("#delivery-planned-date").val(button.getAttribute("data-planned-date") || $("#delivery-planned-date").val());
    $("#delivery-modal").modal("show");
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[character];
    });
  }

  function printDelivery(delivery, sale) {
    var popup = window.open("", "_blank", "width=850,height=700");
    if (!popup) {
      toastr.error("Permite las ventanas emergentes para imprimir la constancia.");
      return;
    }
    var html = "<!doctype html><html lang='es'><head><meta charset='utf-8'><title>Constancia interna de entrega</title>"
      + "<style>body{font:14px Arial,sans-serif;margin:40px;color:#222}h1{font-size:22px}table{width:100%;border-collapse:collapse;margin:24px 0}th,td{padding:10px;border:1px solid #aaa;text-align:left}th{width:30%;background:#f2f2f2}.notice{padding:12px;border:1px solid #b77;background:#fff5f5;font-weight:bold}.signature{margin-top:80px;display:flex;justify-content:space-around}.signature span{width:35%;text-align:center;border-top:1px solid #555;padding-top:8px}@media print{button{display:none}}</style></head><body>"
      + "<h1>Constancia interna de despacho y entrega</h1>"
      + "<div class='notice'>Documento interno de control. No es una guía de remisión electrónica ni tiene validez como GRE ante SUNAT.</div>"
      + "<table><tr><th>Documento de venta</th><td>" + escapeHtml(typeLabels[sale.type]) + " " + escapeHtml(sale.number) + "</td></tr>"
      + "<tr><th>Cliente</th><td>" + escapeHtml(delivery.recipient_name) + "</td></tr>"
      + "<tr><th>Dirección</th><td>" + escapeHtml(delivery.delivery_address) + "</td></tr>"
      + "<tr><th>Fecha programada</th><td>" + escapeHtml(delivery.planned_date) + "</td></tr>"
      + "<tr><th>Estado</th><td>" + escapeHtml(statusLabels[delivery.status] || "Pendiente") + "</td></tr>"
      + "<tr><th>Transportista</th><td>" + escapeHtml(delivery.carrier || "—") + "</td></tr>"
      + "<tr><th>Referencia</th><td>" + escapeHtml(delivery.tracking_number || "—") + "</td></tr>"
      + "<tr><th>Observaciones</th><td>" + escapeHtml(delivery.notes || "—") + "</td></tr></table>"
      + "<div class='signature'><span>Entrega / transportista</span><span>Recepción / cliente</span></div>"
      + "<script>window.onload=function(){window.print()}<\/script></body></html>";
    popup.document.open();
    popup.document.write(html);
    popup.document.close();
  }

  $("#document-search-form").on("submit", function (event) {
    event.preventDefault();
    var term = $("#document-search").val().trim();
    if (term.length < 2) {
      $("#document-search-error").text("Escribe al menos dos caracteres para buscar.").removeClass("d-none");
      return;
    }
    searchDocuments(term);
  });

  $(document).on("click", ".js-open-document-map", function () {
    loadMap($(this).data("type"), $(this).data("id"));
  });

  $(document).on("click", ".js-create-delivery", function () {
    openDeliveryForm(this);
  });

  $("#delivery-form").on("submit", function (event) {
    event.preventDefault();
    $.ajax({
      url: "../../modules/logistica/documentos.php",
      method: "POST",
      dataType: "json",
      data: $(this).serialize()
    }).done(function (response) {
      toastr.success(response.message);
      $("#delivery-modal").modal("hide");
      loadDeliveries();
      loadMap($("#delivery-form [name='source_type']").val(), $("#delivery-form [name='source_id']").val());
    }).fail(function (xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo registrar el despacho.";
      toastr.error(message);
    });
  });

  $(document).on("click", ".js-advance-delivery", function () {
    var button = this;
    $.ajax({
      url: "../../modules/logistica/documentos.php",
      method: "POST",
      dataType: "json",
      data: {
        action: "advance_delivery",
        delivery_id: button.getAttribute("data-delivery-id"),
        status: button.getAttribute("data-status")
      }
    }).done(function () {
      toastr.success("Estado del despacho actualizado.");
      loadDeliveries();
      if (currentMapDocument) {
        loadMap(currentMapDocument.type, currentMapDocument.id);
      }
    }).fail(function (xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo actualizar el despacho.";
      toastr.error(message);
    });
  });

  $(document).on("click", ".js-print-delivery", function () {
    printDelivery($(this).data("delivery"), $(this).data("sale"));
  });

  $(document).on("click", ".js-open-delivery-map", function () {
    var type = $(this).data("source-type");
    var id = $(this).data("source-id");
    $("#document-search").val(type + " " + id);
    loadMap(type, id);
  });

  loadDeliveries();
  var initialParams = new URLSearchParams(window.location.search);
  var initialType = initialParams.get("type");
  var initialId = Number(initialParams.get("id"));
  if (typeLabels[initialType] && initialId > 0) {
    loadMap(initialType, initialId);
  }
});
