$(function () {
  $("#m_reportes").addClass("active").parent().addClass("menu-open");
  $("#m_ventas_periodo").addClass("active");
  $(document).prop("title", "Análisis de ventas - APPU");

  var latestReport = null;
  var currencyLabels = { MN: "PEN", ME: "USD" };
  var currencyFormatters = {};

  function formatAmount(amount, currency) {
    var label = currencyLabels[currency] || currency;
    if (currency === "MN" || currency === "PEN" || currency === "USD" || currency === "EUR") {
      currencyFormatters[currency] = currencyFormatters[currency] || new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: label
      });
      return currencyFormatters[currency].format(Number(amount) || 0);
    }
    return label + " " + new Intl.NumberFormat("es-PE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(Number(amount) || 0);
  }

  function renderTable(selector, headers, rows) {
    var $table = $("<table>").addClass("table table-hover table-striped mb-0");
    var $thead = $("<thead>").appendTo($table);
    var $headerRow = $("<tr>").appendTo($thead);
    headers.forEach(function (header) {
      $("<th>").text(header).appendTo($headerRow);
    });

    var $tbody = $("<tbody>").appendTo($table);
    if (!rows.length) {
      $("<tr>").append($("<td>").attr("colspan", headers.length).addClass("text-center text-muted py-3").text("Sin datos para este período.")).appendTo($tbody);
    } else {
      rows.forEach(function (row) {
        var $row = $("<tr>").appendTo($tbody);
        row.forEach(function (value) {
          $("<td>").text(value == null ? "" : value).appendTo($row);
        });
      });
    }
    $(selector).empty().append($table);
  }

  function renderKpis(report) {
    var $container = $("#sales-kpis").empty();
    var currencies = Object.keys(report.summary);
    if (!currencies.length) {
      currencies = ["MN"];
      report.summary.MN = { net_sales: 0, sales_documents: 0, credit_notes: 0 };
    }

    currencies.forEach(function (currency) {
      var totals = report.summary[currency];
      var previous = report.comparison.summary[currency];
      var ticket = totals.sales_documents ? totals.net_sales / totals.sales_documents : 0;
      var changeLabel = "Sin ventas en el período anterior";
      if (previous) {
        if (Number(previous.net_sales) === 0) {
          changeLabel = Number(totals.net_sales) === 0 ? "Sin cambio vs. período anterior" : "Ventas nuevas vs. período anterior";
        } else {
          var percentage = (Number(totals.net_sales) - Number(previous.net_sales)) / Math.abs(Number(previous.net_sales)) * 100;
          changeLabel = (percentage > 0 ? "+" : "") + percentage.toFixed(1) + "% vs. período anterior";
        }
      }
      var metrics = [
        ["Ventas netas", formatAmount(totals.net_sales, currency), "fa-chart-line", "bg-info", changeLabel],
        ["Facturas y boletas", new Intl.NumberFormat("es-PE").format(totals.sales_documents), "fa-file-invoice", "bg-success"],
        ["Ticket promedio", formatAmount(ticket, currency), "fa-receipt", "bg-primary"],
        ["Notas de crédito", new Intl.NumberFormat("es-PE").format(totals.credit_notes), "fa-undo-alt", "bg-warning"]
      ];

      metrics.forEach(function (metric) {
        var $box = $("<div>").addClass("col-xl-3 col-sm-6");
        var $smallBox = $("<div>").addClass("small-box " + metric[3]).appendTo($box);
        var $inner = $("<div>").addClass("inner").appendTo($smallBox);
        $("<h3>").addClass("dashboard-kpi-value").text(metric[1]).appendTo($inner);
        $("<p>").text(metric[0] + " · " + (currencyLabels[currency] || currency)).appendTo($inner);
        if (metric[4]) {
          $("<span>").addClass("small").text(metric[4]).appendTo($inner);
        }
        $("<div>").addClass("icon").append($("<i>").addClass("fas " + metric[2])).appendTo($smallBox);
        $container.append($box);
      });
    });
  }

  function renderMonthly(report) {
    var rows = report.monthly;
    var maxAmount = rows.reduce(function (max, row) {
      return Math.max(max, Math.abs(Number(row.net_amount) || 0));
    }, 0);
    var $list = $("<div>").addClass("sales-monthly-list");

    if (!rows.length) {
      $list.append($("<p>").addClass("text-muted mb-0").text("No hay ventas registradas en el período seleccionado."));
    }

    rows.forEach(function (row) {
      var width = maxAmount ? Math.max(2, Math.abs(Number(row.net_amount)) / maxAmount * 100) : 0;
      var $line = $("<div>").addClass("sales-monthly-row");
      $("<span>").addClass("sales-month-label").text(row.month + " · " + (currencyLabels[row.currency] || row.currency)).appendTo($line);
      var $progress = $("<div>").addClass("progress sales-month-progress").appendTo($line);
      $("<div>").addClass("progress-bar " + (Number(row.net_amount) < 0 ? "bg-danger" : "bg-info")).css("width", width + "%").appendTo($progress);
      $("<strong>").addClass("sales-month-amount").text(formatAmount(row.net_amount, row.currency)).appendTo($line);
      $list.append($line);
    });
    $("#sales-monthly").empty().append($list);
  }

  function renderReport(report) {
    renderKpis(report);
    renderMonthly(report);

    renderTable("#sales-document-types", ["Documento", "Cantidad", "Venta neta"], report.by_type.map(function (row) {
      return [row.document_type + " · " + (currencyLabels[row.currency] || row.currency), row.document_count, formatAmount(row.net_amount, row.currency)];
    }));
    renderTable("#sales-sellers", ["Vendedor · moneda", "Documentos", "Venta neta"], report.sellers.map(function (row) {
      return [row.seller + " · " + (currencyLabels[row.currency] || row.currency), row.document_count, formatAmount(row.net_amount, row.currency)];
    }));
    renderTable("#sales-products", ["Producto · moneda", "Unidades netas", "Importe de líneas"], report.products.map(function (row) {
      return [(row.product_code ? row.product_code + " · " : "") + row.product + " · " + (currencyLabels[row.currency] || row.currency), row.net_units, formatAmount(row.line_amount, row.currency)];
    }));
    renderTable("#sales-customers", ["Cliente · moneda", "Documentos", "Venta neta"], report.customers.map(function (row) {
      return [row.customer + " · " + (currencyLabels[row.currency] || row.currency), row.document_count, formatAmount(row.net_amount, row.currency)];
    }));
  }

  function loadReport() {
    $("#sales-report-error").addClass("d-none").text("");
    $("#sales-report-loading").removeClass("d-none");

    $.ajax({
      url: "../../modules/reportes/cargar-reporte-ventas-x-periodo.php",
      method: "POST",
      dataType: "json",
      data: {
        DATE_FROM: $("#sales-date-from").val(),
        DATE_TO: $("#sales-date-to").val()
      }
    }).done(function (report) {
      latestReport = report;
      renderReport(report);
    }).fail(function (xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo generar el reporte.";
      $("#sales-report-error").text(message).removeClass("d-none");
    }).always(function () {
      $("#sales-report-loading").addClass("d-none");
    });
  }

  $("#sales-report-filter").on("submit", function (event) {
    event.preventDefault();
    if ($("#sales-date-from").val() > $("#sales-date-to").val()) {
      $("#sales-report-error").text("La fecha inicial no puede ser posterior a la fecha final.").removeClass("d-none");
      return;
    }
    loadReport();
  });

  $("#btn-export-sales-report").on("click", function () {
    if (!latestReport) {
      return;
    }
    var csvRows = [["Indicador", "Moneda", "Valor"]];
    Object.keys(latestReport.summary).forEach(function (currency) {
      var totals = latestReport.summary[currency];
      csvRows.push(["Ventas netas", currency, totals.net_sales]);
      csvRows.push(["Facturas y boletas", currency, totals.sales_documents]);
      csvRows.push(["Notas de crédito", currency, totals.credit_notes]);
    });
    csvRows.push([]);
    csvRows.push(["Mes", "Moneda", "Venta neta"]);
    latestReport.monthly.forEach(function (row) {
      csvRows.push([row.month, row.currency, row.net_amount]);
    });
    csvRows.push([]);
    csvRows.push(["Tipo de documento", "Moneda", "Documentos", "Venta neta"]);
    latestReport.by_type.forEach(function (row) {
      csvRows.push([row.document_type, row.currency, row.document_count, row.net_amount]);
    });
    csvRows.push([]);
    csvRows.push(["Vendedor", "Moneda", "Documentos", "Venta neta"]);
    latestReport.sellers.forEach(function (row) {
      csvRows.push([row.seller, row.currency, row.document_count, row.net_amount]);
    });
    csvRows.push([]);
    csvRows.push(["Producto", "Código", "Moneda", "Unidades netas", "Importe de líneas"]);
    latestReport.products.forEach(function (row) {
      csvRows.push([row.product, row.product_code, row.currency, row.net_units, row.line_amount]);
    });
    csvRows.push([]);
    csvRows.push(["Cliente", "RUC", "Moneda", "Documentos", "Venta neta"]);
    latestReport.customers.forEach(function (row) {
      csvRows.push([row.customer, row.ruc, row.currency, row.document_count, row.net_amount]);
    });

    var csv = "\uFEFF" + csvRows.map(function (row) {
      return row.map(function (value) {
        var text=String(value == null ? "" : value);if(typeof value==='string'&&/^[=+\-@]/.test(text))text="'"+text;
        return '"' + text.replace(/"/g, '""') + '"';
      }).join(",");
    }).join("\r\n");
    var blobUrl = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    var link = document.createElement("a");
    link.href = blobUrl;
    link.download = "analisis-ventas-" + latestReport.date_from + "-" + latestReport.date_to + ".csv";
    link.click();
    URL.revokeObjectURL(blobUrl);
  });

  loadReport();
});
