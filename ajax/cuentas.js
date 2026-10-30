$(function () {
  var $financeItem = $("#m_finanzas").addClass("active").closest(".has-treeview");
  $financeItem.removeClass("menu-close").addClass("menu-open").children(".nav-treeview").stop(true, true).show();
  var $financeLink = window.financeType === "receivable" ? $("#m_cuentas_cobrar") : (window.financeType === "payable" ? $("#m_cuentas_pagar") : $("#m_cuentas_general"));
  $financeLink.addClass("active");
  // Al entrar desde otro módulo se muestra el inicio del menú lateral para
  // conservar acceso inmediato a Maestros y Ventas; Finanzas queda expandido
  // y el usuario puede desplazarse dentro del lateral si necesita sus hijos.
  window.setTimeout(function () { var sidebar = $(".main-sidebar .sidebar")[0]; if (sidebar) sidebar.scrollTop = 0; }, 50);
  // El enlace activo ya se ha marcado arriba; se conserva aquí la lógica de
  // carga de datos independiente del estado visual del menú.

  var endpoint = "../../modules/finanzas/cuentas.php" + (window.financeType ? "?type=" + encodeURIComponent(window.financeType) : "");
  var paymentRows = [], financialAccounts = [], requestedPaymentHandled = false;
  function money(value, currency) { return (currency || "PEN") + " " + Number(value || 0).toFixed(2); }
  function updateSelectionState() {
    var selected = $(".finance-select:checked"), labels = {};
    selected.each(function () { labels[$(this).data("account").account_label] = true; });
    if (Object.keys(labels).length > 1) { selected.last().prop("checked", false); toastr.warning("Selecciona cuentas del mismo tipo: cobrar o pagar."); selected = $(".finance-select:checked"); }
    $("#finance-bulk-payment").prop("disabled", selected.length < 2);
  }
  function load() {
    $.getJSON(endpoint).done(function (response) {
      paymentRows = response.payments || [];
      financialAccounts = response.financial_accounts || [];
      window.financeFinancialAccounts = financialAccounts;
      var $financialSelect = $("#finance-financial-account").empty().append("<option value=''>Seleccione una cuenta financiera</option>");
      financialAccounts.forEach(function (account) { $("<option>").val(account.id).text(account.code + " · " + account.name + " · " + account.currency).appendTo($financialSelect); });
      var $body = $("#finance-table tbody").empty();
      var $header = $("#finance-table thead tr");
      if (!$header.data("finance-initialized")) {
        var $selection = $header.find("th").filter(function () { return $(this).find("#finance-select-all").length; }).detach();
        $header.empty().append($selection);
        ["Tipo", "Documento", "Referencia externa", "Cliente / proveedor", "Vencimiento", "Antigüedad", "Total", "Pagado", "Saldo", "Estado", "Acciones"].forEach(function (title) { $("<th>").text(title).appendTo($header); });
        $header.data("finance-initialized", true);
      }
      (response.accounts || []).forEach(function (account) {
        var $row = $("<tr>");
        if ($("#finance-select-all").length) $("<td>").append($("<input type='checkbox' class='finance-select'>").data("account", account).prop("disabled", Number(account.balance_amount) <= 0)).appendTo($row);
        $("<td>").html(account.account_label === "Cobrar" ? '<span class="badge badge-info">Cobrar</span>' : '<span class="badge badge-warning">Pagar</span>').appendTo($row);
        $("<td>").text((account.document_type || "") + " " + (account.document_number || "-")).appendTo($row);
        $("<td>").text(account.supplier_reference || "-").appendTo($row);
        $("<td>").text(account.party_name || "Sin tercero").appendTo($row);
        $("<td>").text(account.due_date || "-").appendTo($row);
        $("<td>").text((account.aging_bucket || "Vigente") + (Number(account.days_overdue) > 0 ? " (" + Number(account.days_overdue) + " d)" : "")).appendTo($row);
        $("<td class='text-right'>").text(money(account.total_amount, account.currency)).appendTo($row);
        $("<td class='text-right'>").text(money(account.paid_amount, account.currency)).appendTo($row);
        $("<td class='text-right font-weight-bold'>").text(money(account.balance_amount, account.currency)).appendTo($row);
        $("<td>").html(appuStatusBadge(account.status || "Pendiente")).appendTo($row);
        var $actions = $("<td>");
        if ($("#finance-history-modal").length) $("<button class='btn btn-sm btn-outline-secondary mr-1 finance-history'>Historial</button>").data("account", account).appendTo($actions);
        if (appuCan("finance.manage") && Number(account.balance_amount) > 0 && ["anulada", "cancelada", "pagada"].indexOf(String(account.status || "").toLowerCase()) === -1) $("<button type='button' class='btn btn-sm btn-primary finance-pay'>").text(account.account_label === "Cobrar" ? "Cobrar" : "Pagar").data("account", account).appendTo($actions);
        $actions.appendTo($row); $body.append($row);
      });
      if (!(response.accounts || []).length) $("<tr>").append($("<td class='text-center text-muted'>").attr("colspan", $header.children().length).text("No hay documentos para mostrar.")).appendTo($body);
      $("#finance-select-all").prop("checked", false); updateSelectionState();
      var requestedPayment = new URLSearchParams(window.location.search).get("payment_id");
      if (requestedPayment && !requestedPaymentHandled) {
        requestedPaymentHandled = true;
        var payment = paymentRows.find(function (item) { return String(item.payment_id) === String(requestedPayment); });
        var account = payment && (response.accounts || []).find(function (item) { return Number(item.id) === Number(payment.account_id) && (item.account_label === "Pagar" ? "payable" : "receivable") === payment.account_type; });
        if (account) openHistory(account, requestedPayment); else toastr.warning("El cobro o pago solicitado no existe en esta cartera.");
      }
    }).fail(function (xhr) { toastr.error((xhr.responseJSON || {}).error || "No se pudieron cargar los saldos. Usa Actualizar para reintentar."); });
  }
  function openPayment(accounts) {
    var form = $("#finance-payment-form")[0]; form.reset(); form.account_id.value = accounts[0].id; form.account_label.value = accounts[0].account_label;
    var caption = accounts[0].account_label === "Cobrar" ? "Registrar cobro" : "Registrar pago";
    $("#finance-payment-modal .modal-title,#finance-payment-form button[type=submit]").text(caption);
    $("#finance-payment-document").text(accounts.length > 1 ? "Aplicación múltiple a " + accounts.length + " documentos" : accounts[0].account_label + " · " + (accounts[0].document_number || "-") + " · saldo " + money(accounts[0].balance_amount, accounts[0].currency));
    $("#finance-payment-form").data("accounts", accounts); $("#finance-payment-form input[name='amount']").val(accounts.length > 1 ? "" : accounts[0].balance_amount).prop("disabled", accounts.length > 1); $("#finance-payment-modal").modal("show");
  }
  $("#finance-refresh").on("click", load);
  $("#finance-select-all").on("change", function () { $(".finance-select:not(:disabled)").prop("checked", this.checked); updateSelectionState(); });
  $("#finance-table").on("change", ".finance-select", updateSelectionState);
  $("#finance-table").on("click", ".finance-pay", function () { openPayment([$(this).data("account")]); });
  $("#finance-bulk-payment").on("click", function () { var accounts = $(".finance-select:checked").map(function () { return $(this).data("account"); }).get(); if (accounts.length > 1) openPayment(accounts); });
  $("#finance-payment-form").on("submit", function (event) {
    event.preventDefault(); var form = this, accounts = $(form).data("accounts") || [];
    var allocations = accounts.length > 1 ? accounts.map(function (account) { return { account_id: account.id, amount: Number(account.balance_amount), notes: "Aplicación múltiple" }; }) : [{ account_id: form.account_id.value, amount: form.amount.value, notes: form.notes.value }];
    if ($(form).data("submitting")) return;
    var data = $(form).serializeArray(); data.push({ name: "allocations", value: JSON.stringify(allocations) });
    $(form).data("submitting", true).find("button[type=submit]").prop("disabled", true);
    $.post(endpoint, data).done(function (response) { $("#finance-payment-modal").modal("hide"); toastr.success(response.message); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo registrar el pago."); }).always(function () { $(form).data("submitting", false).find("button[type=submit]").prop("disabled", false); });
  });
  function openHistory(account, selectedPaymentId) {
    var type = account.account_label === "Pagar" ? "payable" : "receivable";
    var rows = paymentRows.filter(function (payment) { return payment.account_type === type && Number(payment.account_id) === Number(account.id); });
    $("#finance-history-document").text(account.account_label + " · " + (account.document_number || "-"));
    var $body = $("#finance-history-body").empty();
    if (!rows.length) $("<tr><td colspan='7' class='text-muted'>No hay pagos registrados.</td></tr>").appendTo($body);
    rows.forEach(function (payment) {
      var $row = $("<tr>").append($("<td>").text(payment.payment_date)).append($("<td>").text(payment.payment_number || "-")).append($("<td>").text(payment.payment_method)).append($("<td>").text(payment.financial_account_name || "-")).append($("<td>").text(payment.reference || "-")).append($("<td class='text-right'>").text(money(payment.amount, account.currency))).append($("<td>").text(payment.status || "Registrado"));
      if (String(payment.payment_id) === String(selectedPaymentId)) $row.addClass("table-active");
      if (payment.status !== "Anulado" && appuCan("finance.manage")) $("<button type='button' class='btn btn-sm btn-outline-danger ml-1 finance-reverse-payment'>Anular</button>").data("payment-id", payment.payment_id).data("payment-type", payment.account_type).appendTo($row.find("td:last"));
      if (typeof mostrarHistorialEstados === "function") $("<button type='button' class='btn btn-sm btn-outline-secondary ml-1 finance-audit-payment'>Trazabilidad</button>").data("payment", payment).appendTo($row.find("td:last"));
      $("<a class='btn btn-sm btn-outline-secondary ml-1'>Mapa</a>").attr("href", "../trazabilidad/flujo?type=" + (payment.account_type === "receivable" ? "incoming_payment" : "outgoing_payment") + "&id=" + encodeURIComponent(payment.payment_id)).appendTo($row.find("td:last"));
      $row.appendTo($body);
    });
    $("#finance-history-modal").modal("show");
  }
  $("#finance-table").on("click", ".finance-history", function () { openHistory($(this).data("account")); });
  $("#finance-history-body").on("click", ".legacy-finance-reverse-payment", function () {
    var paymentId = $(this).data("payment-id"); if (!window.confirm("¿Anular este pago y revertir sus saldos?")) return;
    $.post("../../modules/finanzas/cuentas.php", { action: "reverse_payment", payment_id: paymentId }).done(function (response) { toastr.success(response.message); $("#finance-history-modal").modal("hide"); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo anular el pago."); });
  });
  $("#finance-history-body").on("click", ".finance-audit-payment", function () {
    var payment = $(this).data("payment");
    mostrarHistorialEstados(payment.account_type === "receivable" ? "incoming_payment" : "outgoing_payment", payment.payment_id);
  });
  load();
});

$(document).on("show.bs.modal", "#finance-payment-modal", function () {
  var accounts = $("#finance-payment-form").data("accounts") || [], master = window.financeFinancialAccounts || [], current = accounts[0];
  var matching = current ? master.filter(function (account) { return account.currency === current.currency; }) : [];
  $("#finance-financial-account option").each(function () { var id = Number(this.value); $(this).prop("disabled", !!id && !matching.some(function (account) { return Number(account.id) === id; })); });
  $("#finance-financial-account").val(matching.length ? matching[0].id : "");
  var entity = String($("#finance-payment-form [name='account_label']").val() || "").toLowerCase() === "pagar" ? "outgoing_payment" : "incoming_payment";
  $("#finance-payment-form .appu-payment-custom-fields").each(function () { var active = $(this).data("payment-entity") === entity; $(this).toggle(active).find(":input").prop("disabled", !active); });
});

// La misma numeracion puede existir en cobros y pagos; siempre enviamos el tipo.
$("#finance-history-body").off("click", ".finance-reverse-payment").on("click", ".finance-reverse-payment", function () {
  var $button = $(this), paymentId = $button.data("payment-id"), paymentType = $button.data("payment-type");
  if (!window.confirm("Anular este pago y revertir sus saldos?")) return;
  var reason = window.prompt("Motivo de anulacion del pago (obligatorio):", "");
  if (reason === null || reason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); return; }
  $.post("../../modules/finanzas/cuentas.php", { action: "reverse_payment", payment_id: paymentId, payment_type: paymentType, reason: reason.trim() }).done(function (response) {
    toastr.success(response.message); $("#finance-history-modal").modal("hide"); $("#finance-refresh").trigger("click");
  }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo anular el pago."); });
});
