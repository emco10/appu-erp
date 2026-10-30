$(function () {
  $("#m_reportes").addClass("active").parent().addClass("menu-open");
  $("#m_finanzas_reporte").addClass("active");
  $(document).prop("title", "Antigüedad de saldos - APPU");
  var currentAccounts = [];

  function money(value, currency) {
    return (currency || "PEN") + " " + new Intl.NumberFormat("es-PE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0);
  }
  function isoLocal(date) {
    var offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
  }
  var today = new Date(), from = new Date(); from.setDate(from.getDate() - 29);
  $("#finance-report-from").val(isoLocal(from)); $("#finance-report-to").val(isoLocal(today));

  function renderTable(selector, headers, rows) {
    var $table = $(selector).empty(), $head = $("<thead><tr></tr></thead>").appendTo($table), $body = $("<tbody>").appendTo($table);
    headers.forEach(function (header) { $("<th>").text(header).appendTo($head.find("tr")); });
    if (!rows.length) $("<tr>").append($("<td>").attr("colspan", headers.length).addClass("text-center text-muted py-3").text("Sin datos para los filtros aplicados.")).appendTo($body);
    rows.forEach(function (cells) { var $row = $("<tr>").appendTo($body); cells.forEach(function (cell) { var $cell = $("<td>"); if (cell && cell.jquery) $cell.append(cell); else $cell.text(cell == null || cell === "" ? "—" : cell); $row.append($cell); }); });
  }
  function documentLink(account) {
    var routes = { invoice: "../facturacion/registro-factura", receipt: "../facturacion/registro-boleta", purchase: "../compras/resumen-compras" };
    var route = routes[account.document_type];
    if (!route) return $("<span>").text(account.document_number);
    return $("<a>").attr("href", route + "?record_id=" + encodeURIComponent(account.document_id)).text(account.document_number).attr("title", "Abrir documento");
  }
  function csvCell(value) {
    value = String(value == null ? "" : value);
    if (/^[=+\-@]/.test(value)) value = "'" + value;
    return '"' + value.replace(/"/g, '""') + '"';
  }
  function exportCsv() {
    if (!currentAccounts.length) { toastr.info("No hay documentos para exportar."); return; }
    var headers = ["Tipo", "Documento", "Referencia externa", "RUC", "Socio de negocio", "Emisión", "Vencimiento", "Moneda", "Total", "Pagado", "Saldo", "Días vencidos", "Rango", "Estado"];
    var rows = currentAccounts.map(function (x) { return [x.account_label, x.document_number, x.supplier_reference, x.party_tax_id, x.party_name, x.issue_date, x.due_date, x.currency, x.total_amount, x.paid_amount, x.balance_amount, x.days_overdue, x.aging_bucket, x.status]; });
    var csv = "\ufeff" + [headers].concat(rows).map(function (row) { return row.map(csvCell).join(";"); }).join("\r\n");
    var link = document.createElement("a"); link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" })); link.download = "antiguedad-saldos-" + isoLocal(new Date()) + ".csv"; document.body.appendChild(link); link.click(); URL.revokeObjectURL(link.href); link.remove();
  }

  function render(report) {
    currentAccounts = report.accounts || [];
    var selectedCurrency = $("#finance-report-currency").val(), $currency = $("#finance-report-currency").empty().append('<option value="">Todas</option>');
    (report.currencies || []).forEach(function (value) { $("<option>").val(value).text(value).appendTo($currency); }); $currency.val(selectedCurrency);
    var $summary = $("#finance-report-summary").empty();
    (report.summary || []).forEach(function (item) {
      var tone = item.account_type === "receivable" ? "primary" : "secondary";
      var $card = $("<div class='col-xl-3 col-md-6'>").append($("<div class='card card-outline card-" + tone + "'>").append($("<div class='card-body'>").append($("<div class='text-uppercase small text-muted'>").text("Por " + item.account_label.toLowerCase() + " · " + item.currency)).append($("<h4 class='mb-1'>").text(money(item.balance_amount, item.currency))).append($("<div class='small'>").text(item.account_count + " documentos · " + money(item.overdue_amount, item.currency) + " vencido"))));
      $summary.append($card);
    });
    if (!(report.summary || []).length) $summary.html('<div class="col-12"><div class="alert alert-light border">No existen saldos abiertos para los filtros aplicados.</div></div>');
    renderTable("#finance-report-aging", ["Tipo", "Moneda", "Rango", "Documentos", "Saldo"], (report.aging || []).map(function (x) { return [x.account_label, x.currency, x.aging_bucket, x.document_count, money(x.balance_amount, x.currency)]; }));
    renderTable("#finance-report-partners", ["Tipo", "RUC", "Socio", "Moneda", "Docs.", "Saldo", "Vencido"], (report.partners || []).map(function (x) { return [x.account_label, x.party_tax_id, x.party_name, x.currency, x.document_count, money(x.balance_amount, x.currency), money(x.overdue_amount, x.currency)]; }));
    renderTable("#finance-report-accounts", ["Tipo", "Documento", "Referencia", "Socio", "Emisión", "Vencimiento", "Saldo", "Días", "Rango", "Estado"], currentAccounts.map(function (x) { return [x.account_label, documentLink(x), x.supplier_reference || "—", (x.party_tax_id ? x.party_tax_id + " · " : "") + (x.party_name || "Sin socio"), x.issue_date, x.due_date, money(x.balance_amount, x.currency), x.days_overdue, x.aging_bucket, x.status]; }));
    $("#finance-report-count").text(currentAccounts.length);
    renderTable("#finance-report-payments", ["Tipo", "Medio", "Moneda", "Operaciones", "Importe"], (report.payments || []).map(function (x) { return [x.account_type === "receivable" ? "Cobro" : "Pago", x.payment_method, x.currency, x.payment_count, money(x.amount, x.currency)]; }));
  }
  function loadReport() {
    var params = { date_from: $("#finance-report-from").val(), date_to: $("#finance-report-to").val(), account_type: $("#finance-report-type").val(), currency: $("#finance-report-currency").val(), party: $("#finance-report-party").val().trim() };
    if (!params.date_from || !params.date_to || params.date_from > params.date_to) { toastr.warning("El rango de fechas no es válido."); return; }
    $("#finance-report-loading").removeClass("d-none"); $("#finance-report-filter :input").prop("disabled", true);
    $.getJSON("../../modules/reportes/finanzas.php", params).done(render).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo cargar el reporte financiero."); }).always(function () { $("#finance-report-loading").addClass("d-none"); $("#finance-report-filter :input").prop("disabled", false); });
  }
  $("#finance-report-filter").on("submit", function (event) { event.preventDefault(); loadReport(); });
  $("#finance-report-export").on("click", exportCsv);
  loadReport();
});
