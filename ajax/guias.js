$(function () {
  $("#m_logistica_guias").addClass("active");
  $(document).prop("title", "Guia de Remision - APPU");
  var guideEpoch = 0, guideSourceEpoch = 0, guideListEpoch = 0, guideViewEpoch = 0, guidePreview = null, guideSaving = false, guideLoading = false;
  var $guideForm = $('#remission-guide-form'), $guideModal = $('#remission-guide-modal');
  var guideState = appuDocumentState('LIST', function (mode) { $guideForm.attr('data-document-mode', mode); });
  function guideError(xhr, fallback) { var response = xhr.responseJSON || {}; return response.message || (typeof response.error === 'string' ? response.error : fallback); }
  function guideBusy(busy) {
    guideSaving = busy; $guideForm.find('button,input,select').prop('disabled', busy);
    if (!busy) $guideForm.find('.js-guide-quantity').each(function () { $(this).prop('disabled', Number(this.max) <= 0); });
  }

  function loadGuideWarehouses() {
    var epoch = guideEpoch;
    var $select = $("#guide-warehouse");
    var selected = $select.data("pending-warehouse") || $select.val() || "1";
    $.getJSON("../../modules/inventory/warehouses.php").done(function (response) {
      if (epoch !== guideEpoch) return;
      $select.empty();
      var warehouses = (response.warehouses || []).filter(function (warehouse) { return Number(warehouse.active_status) === 1; });
      warehouses.forEach(function (warehouse) {
        $select.append($('<option>').val(warehouse.id).text(warehouse.code + " - " + warehouse.name));
      });
      $select.val(String($select.data('pending-warehouse') || selected));
      if (!warehouses.length) {
        $select.append($("<option>").val("").text("No hay almacenes activos disponibles.").prop("selected", true));
        toastr.warning("Activa un almacén antes de registrar una guía de remisión.");
      }
    }).fail(function (xhr) {
      if (epoch !== guideEpoch) return;
      $select.empty().append($("<option>").val("").text("No se pudieron cargar los almacenes."));
      toastr.error(guideError(xhr, "No se pudieron cargar los almacenes. Verifica tu conexión y permisos."));
    });
  }

  var table = $("#table-remission-guides").DataTable({
    dom: "Bfrtip",
    order: [[0, "DESC"]],
    buttons: [
      { extend: "pdf", text: '<i class="fa fa-file-pdf"></i>&nbsp;&nbsp;PDF' },
      { extend: "csv", text: '<i class="fa fa-file-csv"></i>&nbsp;&nbsp;CSV' },
      { extend: "excel", text: '<i class="fa fa-file-excel"></i>&nbsp;&nbsp;Excel' },
      { extend: "print", text: '<i class="fa fa-print"></i>&nbsp;&nbsp;Imprimir' }
    ],
    language: { url: "../../plugins/datatables/Spanish.json" },
    columnDefs: [{ targets: 7, render: function (data, type) { return type === "display" ? appuStatusBadge(data) : data; } }]
  });
  table.columns([0]).visible(false);

  function loadGuides() {
    var request = ++guideListEpoch;
    $.getJSON("../../modules/logistica/guias.php").done(function (response) {
      if (request !== guideListEpoch) return;
      table.clear();
      (response.guides || []).forEach(function (guide) {
        table.rows.add([{
          0: guide.id,
          1: guide.number,
          2: guide.source_type + " #" + guide.source_id,
          3: guide.destination_address,
          4: guide.transfer_date,
          5: guide.transport_mode + (guide.vehicle_plate ? " / " + guide.vehicle_plate : ""),
          6: guide.warehouse_code || guide.warehouse_name || "-",
          7: guide.status,
          8: '<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-primary js-guide-view" data-id="' + guide.id + '" title="Ver / imprimir">Ver</button>' +
            (appuCan('sales.view') ? '<a class="btn btn-outline-secondary" href="../../views/ventas/flujo-comercial?type=remission_guide&id=' + guide.id + '">Relaciones</a>' : '') +
            (appuCan('sales.manage') && guide.source_type === 'sales_order' && Number(guide.inventory_posted) === 1 && guide.status !== 'Anulada' && Number(guide.unbilled_quantity) > 0 ? '<button type="button" class="btn btn-outline-success js-guide-invoice" data-id="' + guide.id + '">Copiar a factura</button>' : '') + '</div>'
        }]);
      });
      table.draw();
      var requested = new URLSearchParams(window.location.search).get('record_id');
      if (!window.appuGuideRecordHandled && requested && /^[1-9][0-9]*$/.test(requested)) { window.appuGuideRecordHandled = true; readGuide(Number(requested)); }
    }).fail(function (xhr) {
      if (request !== guideListEpoch) return;
      table.clear().draw();
      toastr.error(guideError(xhr, "No se pudieron cargar las guías. Verifica tu conexión y permisos."));
    });
  }

  function loadSources() {
    var epoch = guideEpoch;
    $.getJSON("../../modules/logistica/guias.php", { action: "sources" }).done(function (response) {
      if (epoch !== guideEpoch) return;
      var sources = response.sources || [];
      appuRenderCustomFields($('#guide-custom-fields'), response.custom_fields, response.custom_values, 'guide-custom-');
      var options = [{ id: "", text: sources.length ? "Seleccione factura, boleta u orden confirmada" : "No hay documentos confirmados disponibles para generar una guía." }];
      sources.forEach(function (source) {
        options.push({ id: source.source_type + ":" + source.id, text: source.source_type + " " + source.document_number + " - " + source.customer_name });
      });
      $("#guide-source").empty().select2({ data: options, dropdownParent: $("#remission-guide-modal") });
      var params = new URLSearchParams(window.location.search), type = params.get('source_type'), id = Number(params.get('source_id'));
      if (!window.appuGuideSourceHandled && ['sales_order','invoice','receipt'].includes(type) && id > 0) {
        window.appuGuideSourceHandled = true; var key = type + ':' + id;
        if (!$('#guide-source option').filter(function () { return this.value === key; }).length) $('#guide-source').append($('<option>').val(key).text(type + ' #' + id));
        $('#guide-source').val(key).trigger('change');
      }
    }).fail(function (xhr) {
      if (epoch !== guideEpoch) return;
      $("#guide-source").empty().select2({ data: [{ id: "", text: "No se pudieron cargar los documentos de origen." }], dropdownParent: $("#remission-guide-modal") });
      toastr.error(guideError(xhr, "No se pudieron cargar los documentos de origen. Verifica tu conexión y permisos."));
    });
  }

  function resetGuideForm() {
    guideEpoch++; guideSourceEpoch++; guideLoading = false; guideBusy(false);
    var form = $("#remission-guide-form")[0];
    form.reset();
    $guideForm.find('[name=request_key]').val(appuRequestKey());
    $('#guide-warehouse').removeData('pending-warehouse');
    $('#guide-custom-fields').empty();
    appuClearValidation($guideForm); guideState.set('CREATE');
    $("#guide-source").val("").trigger("change");
    $("#remission-guide-form [name='source_type'], #remission-guide-form [name='source_id']").val("");
    $("#guide-items tbody").html('<tr><td colspan="6" class="text-center text-muted">Selecciona un documento de origen.</td></tr>');
    $("#remission-guide-modal").modal("show");
    appuMarkSaved();
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[character];
    });
  }

  function printGuide(guide, details) {
    var popup = window.open("", "_blank", "width=950,height=750");
    if (!popup) { toastr.error("Permite ventanas emergentes para imprimir la guia."); return; }
    var rows = (details || []).map(function (detail) {
      return "<tr><td>" + escapeHtml(detail.item_code) + "</td><td>" + escapeHtml(detail.description) + "</td><td>" + escapeHtml(detail.unit_code) + "</td><td>" + escapeHtml(detail.quantity) + "</td></tr>";
    }).join("");
    var html = "<!doctype html><html lang='es'><head><meta charset='utf-8'><title>" + escapeHtml(guide.number) + "</title><style>body{font:13px Arial;margin:35px;color:#222}h1{font-size:22px}h2{font-size:15px;border-bottom:1px solid #777;padding-bottom:5px;margin-top:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.box{border:1px solid #aaa;padding:10px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #aaa;padding:7px;text-align:left}th{background:#eee}.notice{background:#fff3cd;border:1px solid #e0b84c;padding:10px;font-weight:bold}</style></head><body>";
    html += "<h1>Guia de Remision " + escapeHtml(guide.number) + "</h1><div class='notice'>Documento operativo. No es una GRE electronica ni se envia a SUNAT.</div>";
    html += "<div class='grid'><div class='box'><b>Origen</b><br>" + escapeHtml(guide.origin_address) + "<br>Ubigeo: " + escapeHtml(guide.origin_ubigeo) + "</div><div class='box'><b>Destino</b><br>" + escapeHtml(guide.destination_address) + "<br>Ubigeo: " + escapeHtml(guide.destination_ubigeo) + "</div></div>";
    html += "<h2>Datos del traslado</h2><div class='grid'><div>Motivo: " + escapeHtml(guide.transfer_reason) + "</div><div>Modalidad: " + escapeHtml(guide.transport_mode) + "</div><div>Almacén: " + escapeHtml(guide.warehouse_code || guide.warehouse_name || "-") + "</div><div>Fecha: " + escapeHtml(guide.transfer_date) + "</div><div>Estado: " + escapeHtml(guide.status) + "</div><div>Remitente: " + escapeHtml(guide.sender_name) + "</div><div>Destinatario: " + escapeHtml(guide.recipient_name) + "</div><div>Vehiculo: " + escapeHtml(guide.vehicle_plate || "-") + "</div><div>Conductor: " + escapeHtml(guide.driver_name || "-") + "</div></div>";
    html += "<h2>Bienes trasladados</h2><table><thead><tr><th>Codigo</th><th>Descripcion</th><th>Unidad</th><th>Cantidad</th></tr></thead><tbody>" + rows + "</tbody></table><p>Origen del documento: " + escapeHtml(guide.source_type) + " #" + escapeHtml(guide.source_id) + "</p><script>window.onload=function(){window.print()}<\/script></body></html>";
    popup.document.open(); popup.document.write(html); popup.document.close();
  }

  function readGuide(id) {
    var request = ++guideViewEpoch; guidePreview = null;
    $('#guide-preview-header').text('Cargando entrega…'); $('#guide-preview-lines').empty(); $('#guide-preview-print').prop('disabled', true); $('#guide-preview-modal').modal('show');
    $.getJSON('../../modules/logistica/guias.php', { action: 'detail', guide_id: id }).done(function (data) {
      if (request !== guideViewEpoch) return;
      guidePreview = data; var guide = data.guide, $header = $('#guide-preview-header').empty();
      [['Número', guide.number], ['Documento origen', guide.source_number], ['Estado', guide.status], ['Fecha de traslado', guide.transfer_date], ['Destinatario', guide.recipient_name], ['Destino', guide.destination_address], ['Inventario', Number(guide.inventory_posted) ? (guide.status === 'Anulada' ? 'Salida física revertida' : 'Salida física registrada') : 'Sin otra salida física']].forEach(function (entry) { $('<p class="mb-1">').append($('<strong>').text(entry[0] + ': ')).append(document.createTextNode(entry[1] || '')).appendTo($header); });
      (data.details || []).forEach(function (line) { var $row = $('<tr>').appendTo($('#guide-preview-lines')); [line.item_code,line.description,line.unit_code,line.quantity].forEach(function (value) { $('<td>').text(value).appendTo($row); }); });
      $('#guide-preview-print').prop('disabled', false);
    }).fail(function (xhr) { if (request === guideViewEpoch) $('#guide-preview-header').text(guideError(xhr, 'No se pudo consultar la entrega.')); });
  }

  $("#btn-new-remission-guide").on("click", function () {
    appuConfirmDiscard(function () { resetGuideForm(); loadSources(); loadGuideWarehouses(); });
  });

  $("#guide-source").on("change", function () {
    var sourceEpoch = ++guideSourceEpoch, epoch = guideEpoch;
    var value = $(this).val() || "";
    var parts = value.split(":");
    $guideForm.find('[name=source_type],[name=source_id],[name=recipient_name],[name=recipient_ruc],[name=recipient_address],[name=destination_address]').val('');
    $('#guide-items tbody').empty(); guideLoading = false;
    if (parts.length !== 2) return;
    $("#remission-guide-form [name='source_type']").val(parts[0]);
    $("#remission-guide-form [name='source_id']").val(parts[1]);
    guideLoading = true; $guideForm.find('[type=submit]').prop('disabled', true);
    $.getJSON("../../modules/logistica/guias.php", { action: "source", source_type: parts[0], source_id: parts[1] }).done(function (response) {
      if (epoch !== guideEpoch || sourceEpoch !== guideSourceEpoch) return;
      var source = response.source;
      $("#remission-guide-form [name='recipient_name']").val(source.customer_name || "");
      $("#remission-guide-form [name='recipient_ruc']").val(source.ruc || "");
      $("#remission-guide-form [name='recipient_address']").val(source.shipping_address || source.address || "");
      $("#remission-guide-form [name='destination_address']").val(source.shipping_address || source.address || "");
      $("#guide-warehouse").data("pending-warehouse", source.warehouse_id || 1).val(source.warehouse_id || 1);
      var $body = $("#guide-items tbody").empty();
      (response.details || []).forEach(function (detail) {
        var pending = Number(detail.pending_quantity || 0);
        var $row = $("<tr>").appendTo($body);
        $("<td>").text(detail.item_code || "").appendTo($row);
        $("<td>").text(detail.description || "").appendTo($row);
        $("<td class='text-right'>").text(detail.ordered_quantity || detail.quantity || 0).appendTo($row);
        $("<td class='text-right'>").text(detail.delivered_quantity || 0).appendTo($row);
        $("<td class='text-right font-weight-bold'>").text(pending).appendTo($row);
        var $cell = $("<td>").appendTo($row);
        $("<input>").attr({ type: "number", min: 0, max: pending, step: parts[0] === 'sales_order' ? '1' : '0.001', "data-source-detail-id": detail.source_detail_id, 'data-field-path': 'lines[' + detail.source_detail_id + '].quantity', 'aria-label': 'Cantidad a entregar', class: "form-control form-control-sm js-guide-quantity" }).val(0).prop("disabled", pending <= 0).appendTo($cell);
      });
      if (!(response.details || []).length) $body.html('<tr><td colspan="6" class="text-center text-muted">No hay productos disponibles.</td></tr>');
    }).fail(function (xhr) {
      if (epoch !== guideEpoch || sourceEpoch !== guideSourceEpoch) return;
      $("#guide-items tbody").html('<tr><td colspan="6" class="text-center text-danger">No se pudo cargar el detalle del documento.</td></tr>');
      appuShowValidation($guideForm, { message: guideError(xhr, 'No se pudo cargar el origen. Selecciona otro documento o vuelve a intentarlo.') });
    }).always(function () { if (epoch === guideEpoch && sourceEpoch === guideSourceEpoch) { guideLoading = false; $guideForm.find('[type=submit]').prop('disabled', false); } });
  });

  $("#remission-guide-form").on("submit", function (event) {
    event.preventDefault();
    if (guideSaving || guideLoading) return;
    var items = [];
    $(".js-guide-quantity:not(:disabled)").each(function () {
      var quantity = Number($(this).val() || 0);
      if (quantity !== 0 || String(this.value).trim() === '') items.push({ source_detail_id: Number($(this).data("source-detail-id")), quantity: this.value });
    });
    var data = $(this).serializeArray();
    data.push({ name: "items", value: JSON.stringify(items) });
    appuClearValidation($guideForm); guideBusy(true);
    $.ajax({ url: "../../modules/logistica/guias.php", method: "POST", dataType: "json", data: data })
      .done(function (response) {
        toastr.success(response.message || "Guia registrada.");
        guideBusy(false); appuMarkSaved();
        $("#remission-guide-modal").modal("hide");
        loadGuides();
      }).fail(function (xhr) {
        guideBusy(false); appuShowValidation($guideForm, xhr.responseJSON || { message: 'No se pudo registrar la entrega. Los datos permanecen para corregirlos.' });
      });
  });

  $("#table-remission-guides").contextMenu({
    selector: "tbody tr",
    callback: function (key) {
      var row = table.row(this).data();
      if (!row) return;
      if (key === "history") {
        mostrarHistorialEstados("remission_guide", row[0]);
        return;
      }
      if (key === "view") {
        $.getJSON("../../modules/logistica/guias.php", { action: "detail", guide_id: row[0] }).done(function (response) { printGuide(response.guide, response.details); });
        return;
      }
      if (key === "cancel") {
        if (!window.confirm("¿Anular esta guia? Sus cantidades volveran a estar pendientes.")) return;
        var cancellationReason = window.prompt("Motivo de anulacion de la guia (obligatorio):", "");
        if (cancellationReason === null || cancellationReason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); return; }
        $.ajax({ url: "../../modules/logistica/guias.php", method: "POST", dataType: "json", data: { action: "cancel", guide_id: row[0], cancellation_reason: cancellationReason.trim() } })
          .done(function (response) { toastr.success(response.message); loadGuides(); })
          .fail(function (xhr) { toastr.error(guideError(xhr, "No se pudo anular la guia.")); });
        return;
      }
      if (key !== "advance") return;
      var nextStatus = row[7] === "Registrada" ? "En transito" : "Entregada";
      $.ajax({ url: "../../modules/logistica/guias.php", method: "POST", dataType: "json", data: { action: "advance", guide_id: row[0], status: nextStatus } })
        .done(function (response) { toastr.success(response.message); loadGuides(); })
        .fail(function (xhr) { toastr.error(guideError(xhr, "No se pudo actualizar la guia.")); });
    },
    items: {
      history: { name: "Ver historial", icon: "paste" },
      view: { name: "Ver / imprimir guia", icon: "edit" },
      advance: { name: "Avanzar estado", icon: "paste", visible: function () { var row = table.row(this).data(); return appuCan("logistics.manage") && row && row[7] !== "Entregada" && row[7] !== "Anulada"; } },
      cancel: { name: "Anular guia", icon: "delete", visible: function () { var row = table.row(this).data(); return appuCan("logistics.manage") && row && (row[7] === "Registrada" || row[7] === "En transito"); } },
      quit: { name: "Cerrar", icon: "quit" }
    }
  });

  loadGuides();
  $(document).on('appu:delivery-billed', loadGuides);
  $(document).on('click', '.js-guide-view', function () { readGuide(Number($(this).data('id'))); });
  $('#guide-preview-print').on('click', function () { if (guidePreview) printGuide(guidePreview.guide, guidePreview.details); });
  $('#guide-preview-modal').on('hide.bs.modal', function () { guideViewEpoch++; });
  $guideForm.on('input change', 'input,select', function (event) { if (!event.isTrigger && guideState.get() === 'CREATE') appuMarkDirty(); });
  $guideModal.on('hide.bs.modal', function (event) {
    if (guideSaving) { event.preventDefault(); return; }
    if (window.appuFormDirty) { event.preventDefault(); appuConfirmDiscard(function () { $guideModal.modal('hide'); }); return; }
    guideEpoch++; guideSourceEpoch++; guideState.set('LIST');
  });
  if (new URLSearchParams(window.location.search).get('source_id')) $('#btn-new-remission-guide').trigger('click');
});
