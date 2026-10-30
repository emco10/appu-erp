$(function () {
  "use strict";
  $("#m_almacenes").addClass("active");

  function resetForm() {
    $("#warehouse-form")[0].reset();
    $("#warehouse-form [name='id']").val("");
    $("#warehouse-form [name='action']").val("save");
    $("#warehouse-form-title").text("Nuevo almacén");
    $("#warehouse-form button[type='submit'] span").text("Crear almacén");
    $("#warehouse-form-cancel").addClass("d-none");
  }

  function load() {
    $.getJSON("../../modules/inventory/warehouses.php").done(function (response) {
      var $body = $("#warehouse-table tbody").empty();
      (response.warehouses || []).forEach(function (warehouse) {
        var usage = warehouse.usage || {}, active = Number(warehouse.active_status) === 1;
        var openDocuments = Number(usage.open_sales_orders || 0) + Number(usage.open_quotations || 0);
        var $actions = $("<div class='btn-group btn-group-sm'></div>");
        $("<button type='button' class='btn btn-outline-primary'><i class='fas fa-edit'></i></button>").attr("title", "Editar").on("click", function () {
          $("#warehouse-form [name='id']").val(warehouse.id);
          $("#warehouse-form [name='code']").val(warehouse.code);
          $("#warehouse-form [name='name']").val(warehouse.name);
          $("#warehouse-form [name='address']").val(warehouse.address || "");
          $("#warehouse-form-title").text("Editar almacén");
          $("#warehouse-form button[type='submit'] span").text("Guardar cambios");
          $("#warehouse-form-cancel").removeClass("d-none");
          $("#warehouse-form [name='code']").focus();
        }).appendTo($actions);
        $("<button type='button' class='btn'></button>").addClass(active ? "btn-outline-danger" : "btn-outline-success").attr("title", active ? "Desactivar" : "Activar").html(active ? "<i class='fas fa-ban'></i>" : "<i class='fas fa-check'></i>").on("click", function () {
          $.post("../../modules/inventory/warehouses.php", { action: "toggle", id: warehouse.id, active_status: active ? 0 : 1 }).done(function (result) { toastr.success(result.message); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo cambiar el estado del almacén."); });
        }).appendTo($actions);
        $("<tr>").append($("<td>").text(warehouse.code)).append($("<td>").text(warehouse.name)).append($("<td>").text(warehouse.address || "-"))
          .append($("<td>").text(Number(usage.stock_quantity || 0).toFixed(3) + " / " + Number(usage.reserved_quantity || 0).toFixed(3)))
          .append($("<td>").text(openDocuments ? openDocuments + " abierto(s)" : "Ninguno"))
          .append($("<td>").html(active ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'))
          .append($("<td>").append($actions)).appendTo($body);
      });
    }).fail(function () { toastr.error("No se pudieron cargar los almacenes."); });
  }

  $("#warehouse-form").on("submit", function (event) {
    event.preventDefault();
    $.post("../../modules/inventory/warehouses.php", $(this).serialize()).done(function (response) { toastr.success(response.message); resetForm(); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo guardar el almacén."); });
  });
  $("#warehouse-form-cancel").on("click", resetForm);
  load();
});
