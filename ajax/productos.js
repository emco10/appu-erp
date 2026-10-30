$(function () {
  $("#m_reportes").addClass("active").parent().addClass("menu-open");
  $("#m_rpt_productos").addClass("active");
  $(document).prop("title", "Inventario y productos - APPU");

  function renderTable(selector, headers, rows) {
    var $table = $("<table>").addClass("table table-hover table-striped mb-0");
    var $header = $("<tr>").appendTo($("<thead>").appendTo($table));
    headers.forEach(function (header) {
      $("<th>").text(header).appendTo($header);
    });

    var $body = $("<tbody>").appendTo($table);
    if (!rows.length) {
      $("<tr>").append($("<td>").attr("colspan", headers.length).addClass("text-center text-muted py-3").text("Sin registros para mostrar.")).appendTo($body);
    } else {
      rows.forEach(function (values) {
        var $row = $("<tr>").appendTo($body);
        values.forEach(function (value) {
          $("<td>").text(value == null || value === "" ? "—" : value).appendTo($row);
        });
      });
    }
    $(selector).empty().append($table);
  }

  function renderInventory(data) {
    var selectedWarehouse = String(data.warehouse_id || "");
    var $warehouse = $("#inventory-warehouse-select").empty().append("<option value=''>Todos los almacenes</option>");
    (data.warehouses || []).forEach(function (warehouse) {
      $("<option>").val(warehouse.id).text(warehouse.code + " - " + warehouse.name).appendTo($warehouse);
    });
    $warehouse.val(selectedWarehouse);
    var movementLabels = {
      1: "Ingreso",
      2: "Ajuste",
      3: "Salida por venta",
      4: "Devolución de cliente",
      5: "Reversión de documento",
      6: "Transferencia - salida",
      7: "Transferencia - entrada",
      8: "Ajuste de costo",
      9: "Salida de inventario"
    };
    var summary = data.summary || {};
    var metrics = [
      ["Productos activos", summary.product_count || 0, "fa-boxes", "bg-info"],
      ["Con existencias", summary.in_stock_count || 0, "fa-check-circle", "bg-success"],
      ["Sin existencias", summary.zero_stock_count || 0, "fa-exclamation-triangle", "bg-warning"],
      ["Valor del inventario", summary.total_inventory_value || 0, "fa-coins", "bg-primary"]
    ];
    var $kpis = $("#inventory-kpis").empty();
    metrics.forEach(function (metric) {
      var $box = $("<div>").addClass("col-xl-3 col-sm-6");
      var $small = $("<div>").addClass("small-box " + metric[3]).appendTo($box);
      var $inner = $("<div>").addClass("inner").appendTo($small);
      $("<h3>").text(new Intl.NumberFormat("es-PE").format(Number(metric[1]) || 0)).appendTo($inner);
      $("<p>").text(metric[0]).appendTo($inner);
      $("<div>").addClass("icon").append($("<i>").addClass("fas " + metric[2])).appendTo($small);
      $kpis.append($box);
    });

    renderTable("#inventory-stock", ["Código", "Producto", "Marca", "Existencias", "Costo promedio", "Valor inventario", "Unidad"], (data.stock || []).map(function (row) {
      return [row.code, row.name, row.brand, row.stock_quantity, Number(row.average_unit_cost || 0).toFixed(2), Number(row.inventory_value || 0).toFixed(2), row.unit_value];
    }));
    renderTable("#inventory-expirations", ["Vence", "Producto", "Lote", "Almacén", "Cantidad"], (data.expirations || []).map(function (row) {
      return [row.expiration_date, row.product_name, row.lot_number, row.warehouse_code + " - " + row.warehouse_name, row.quantity];
    }));
    var issues = data.integrity_issues || [];
    $("#inventory-integrity-card").toggleClass("d-none", issues.length === 0);
    renderTable("#inventory-integrity", ["Código", "Artículo", "Saldo maestro", "Suma almacenes", "Reservado"], issues.map(function(row) {
      return [row.code, row.name, row.master_quantity, row.warehouse_quantity, row.reserved_quantity];
    }));
    renderTable("#inventory-movements", ["Fecha", "Tipo", "Producto", "Cantidad", "Variación de valor", "Documento", "Observación"], (data.movements || []).map(function (row) {
      return [
        row.registration_date,
        movementLabels[Number(row.type)] || "Movimiento",
        row.product_name || row.product_code || "Producto eliminado",
        row.quantity,
        Number(row.inventory_value_delta || 0).toFixed(2),
        row.doc_reference,
        row.observation
      ];
    }));
    $("#inventory-report-loading").addClass("d-none");
  }

  function loadInventory() {
    $("#inventory-report-error").addClass("d-none").text("");
    $("#inventory-report-loading").removeClass("d-none");
    $.ajax({
      url: "../../modules/reportes/inventario.php",
      dataType: "json",
      data: { warehouse_id: $("#inventory-warehouse-select").val() || "" }
    }).done(renderInventory).fail(function (xhr) {
    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "No se pudo cargar el reporte de inventario.";
    $("#inventory-report-error").text(message).removeClass("d-none");
    $("#inventory-report-loading").addClass("d-none");
    });
  }

  $("#inventory-warehouse-select").on("change", loadInventory);
  loadInventory();

  $.post(
    "../../modules/productos/listar-productos-xprov.php",
    { ESTADO: "ALL", REPORT: 1 },
    function (data) {
      $("#inventory-product-select").select2({
        data: JSON.parse(data)
      });
    }
  );

  $("#btn-rpt-unidades-vendidas-cliente").on("click", function (event) {
    event.preventDefault();
    var productId = $("#inventory-product-select").val() || 0;
    var dateFrom = $("#inventory-date-from").val();
    var dateTo = $("#inventory-date-to").val();
    if (dateFrom && dateTo && dateFrom > dateTo) {
      toastr.error("La fecha inicial no puede ser posterior a la fecha final.");
      return;
    }
    var url = "../../modules/reportes/unidades-vendidas-por-cliente.php?productid=" + encodeURIComponent(productId)
      + "&datefrom=" + encodeURIComponent(dateFrom) + "&dateto=" + encodeURIComponent(dateTo);
    window.open(url);
  });

  $("#btn-rpt-top-mas-vendido").on("click", function (event) {
    event.preventDefault();
    window.open("../../modules/reportes/top-productos.php?mode=1", "Top 20 Productos Más Vendidos", "");
  });

  $("#btn-rpt-top-menos-vendido").on("click", function (event) {
    event.preventDefault();
    window.open("../../modules/reportes/top-productos.php?mode=2", "Top 20 Productos Menos Vendidos", "");
  });

  $("#btn-product-list").on("click", function () {
    window.location.assign("../../views/productos/listado-producto");
  });
});
