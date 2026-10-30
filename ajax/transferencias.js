$(function () {
  $("#m_transferencias").addClass("active");

  if (!$("#transfer-lot").length) {
    $("<div class='form-group' id='transfer-lot-group'><label>Lote de origen</label><select id='transfer-lot' name='lot_id' class='form-control'><option value=''>Sin lote / no aplica</option></select><small class='form-text text-muted'>Si el artículo tiene lotes en el almacén, selecciona uno.</small></div>").insertBefore($("#transfer-to").closest(".form-group"));
  }

  var lots = [];
  function fill(selector, rows, label) {
    var $select = $(selector).empty().append("<option value=''>Seleccione</option>");
    (rows || []).forEach(function (row) { $("<option>").val(row.id).text(label(row)).appendTo($select); });
  }
  function refreshLots() {
    var productId = Number($("#transfer-product").val() || 0);
    var warehouseId = Number($("#transfer-from").val() || 0);
    var $lot = $("#transfer-lot").empty().append("<option value=''>Sin lote / no aplica</option>");
    lots.filter(function (lot) { return Number(lot.product_id) === productId && Number(lot.warehouse_id) === warehouseId && Number(lot.quantity) - Number(lot.reserved_quantity) > 0; }).forEach(function (lot) {
      var expiry = lot.expiration_date ? " · vence " + lot.expiration_date : "";
      $("<option>").val(lot.id).text(lot.lot_number + " · disponible " + (Number(lot.quantity) - Number(lot.reserved_quantity)) + expiry).appendTo($lot);
    });
  }
  function load() {
    $.getJSON("../../modules/inventory/transfers.php").done(function (response) {
      lots = response.lots || [];
      fill("#transfer-product", response.products, function (row) { return row.code + " - " + row.name; });
      fill("#transfer-from, #transfer-to", response.warehouses, function (row) { return row.code + " - " + row.name; });
      refreshLots();
      var $body = $("#transfer-table tbody").empty();
      (response.transfers || []).filter(function (row) { return Number(row.quantity) < 0 || row.type == 7; }).forEach(function (row) {
        var $actions = $("<td class='text-right'>");
        if (appuCan("inventory.manage") && Number(row.type) === 6 && Number(row.transfer_id) > 0 && row.transfer_status !== "Anulada") $("<button type='button' class='btn btn-sm btn-outline-danger js-cancel-transfer ml-2'>Anular</button>").data("transfer-id", row.transfer_id).appendTo($actions);
        var $quantity = $("<td class='text-right'>").text(Math.abs(Number(row.quantity)));
        $quantity.append($actions.children());
        $("<tr>").append($("<td>").text(row.doc_reference)).append($("<td>").text(row.registration_date)).append($("<td>").text(row.product_code + " - " + row.product_name)).append($("<td>").text(row.warehouse_name)).append($("<td>").text(row.type == 6 ? "Salida" : "Ingreso")).append($quantity).appendTo($body);
      });
    }).fail(function (xhr) { toastr.error((xhr.responseJSON || {}).error || "No se pudieron cargar las transferencias. Reintenta la consulta."); });
  }
  $("#transfer-product, #transfer-from").on("change", refreshLots);
  $("#transfer-form").on("submit", function (event) {
    event.preventDefault();
    var $form = $(this); if ($form.data("submitting")) return;
    var data = $form.serialize(); $form.data("submitting", true).find("button[type=submit]").prop("disabled", true);
    $.post("../../modules/inventory/transfers.php", data).done(function (response) {
      toastr.success(response.message); event.target.reset(); refreshLots(); load();
    }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo registrar la transferencia."); }).always(function () { $form.data("submitting", false).find("button[type=submit]").prop("disabled", false); });
  });
  $("#transfer-table").on("click", ".js-cancel-transfer", function () {
    var transferId = $(this).data("transfer-id");
    if (!window.confirm("¿Anular esta transferencia y revertir el stock?")) return;
    var cancellationReason = window.prompt("Motivo de anulacion de la transferencia (obligatorio):", "");
    if (cancellationReason === null || cancellationReason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); return; }
    $.post("../../modules/inventory/transfers.php", { action: "cancel", transfer_id: transferId, cancellation_reason: cancellationReason.trim() }).done(function (response) { toastr.success(response.message); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo anular la transferencia."); });
  });
  load();
});
