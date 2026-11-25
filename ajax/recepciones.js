$(function () {
  $("#m_entrada_mercancias").addClass("active");
  $(document).prop("title", "Entrada de mercancías - Compras - APPU");
  var endpoint = "../../modules/compras/goods-receipts.php",
    current = null,
    mode = "CREATE",
    documentRead = 0,
    loading = false,
    saving = false,
    requestKey = "";
  var form = document.getElementById("goods-receipt-form");
  function updateSaveButton() {
    form.setAttribute("aria-busy", String(saving || loading));
    $("#btn-save-goods-receipt").prop("disabled", mode === "VIEW" || saving || loading || !appuCan("purchases.manage"));
  }
  function key(type, id) {
    return type + ":" + id;
  }
  function splitKey(value) {
    var p = String(value || "").split(":");
    return { type: p[0], id: p[1] };
  }
  function money(value) {
    return Number(value || 0).toLocaleString("es-PE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }
  function inputCell($input) {
    var cell = document.createElement("td");
    cell.appendChild($input[0]);
    return cell;
  }
  $(
    '<br><strong>Valor de inventario: </strong><span id="goods-receipt-base-total">0.00</span>',
  ).appendTo($("#goods-receipt-total").parent());
  $("#goods-receipt-lines thead th").eq(6).text("Costo unitario (documento)");
  function total() {
    if (!current || !current.base) {
      $("#goods-receipt-valuation-currency,#goods-receipt-valuation-rate")
        .val("")
        .removeData("source");
      $("#goods-receipt-total,#goods-receipt-base-total").text("0.00");
      return;
    }
    var base = current.base,
      source = key(base.base_type, base.id),
      $rate = $("#goods-receipt-valuation-rate");
    if ($rate.data("source") !== source) {
      $rate
        .data("source", source)
        .val(
          base.suggested_valuation_exchange_rate == null
            ? ""
            : base.suggested_valuation_exchange_rate,
        )
        .prop("readonly", base.currency === base.valuation_currency);
      $("#goods-receipt-valuation-currency").val(base.valuation_currency);
      $("#goods-receipt-valuation-help").text(
        base.valuation_currency +
          " por cada 1 " +
          base.currency +
          ". La tasa se conservará en la entrada para su consulta y reversión.",
      );
    }
    var value = 0;
    $("#goods-receipt-lines tr[data-line-id]").each(function () {
      value +=
        (Number($(this).find(".receipt-quantity").val()) || 0) *
        (Number($(this).data("unit-cost")) || 0);
    });
    $("#goods-receipt-total").text(base.currency + " " + money(value));
    var rate = Number($rate.val());
    $("#goods-receipt-base-total").text(
      rate > 0
        ? base.valuation_currency + " " + money(value * rate)
        : "Indica el tipo de cambio",
    );
  }
  function savedValuation() {
    var header = current.receipt,
      rows = current.details || [];
    $("#goods-receipt-valuation-currency").val(
      header.valuation_currency || "No registrada",
    );
    $("#goods-receipt-valuation-rate").val(
      header.valuation_exchange_rate || "",
    );
    $("#goods-receipt-valuation-help").text(
      header.valuation_currency
        ? header.valuation_currency +
            " por cada 1 " +
            header.currency +
            ". Tipo de cambio histórico del documento."
        : "Entrada histórica sin identificación de moneda de valorización.",
    );
    var original = 0,
      base = 0;
    rows.forEach(function (line, index) {
      original += Number(
        line.document_inventory_value == null
          ? line.base_inventory_value
          : line.document_inventory_value,
      );
      base += Number(line.base_inventory_value || 0);
      $("#goods-receipt-lines tbody tr")
        .eq(index)
        .children()
        .eq(6)
        .text(
          money(
            line.document_unit_cost == null
              ? line.base_unit_cost
              : line.document_unit_cost,
          ),
        );
    });
    $("#goods-receipt-total").text(header.currency + " " + money(original));
    $("#goods-receipt-base-total").text(
      (header.valuation_currency || "Valor histórico") + " " + money(base),
    );
  }
  function setMode(next) {
    mode = next;
    var view = next === "VIEW";
    $("#goods-receipt-form")
      .find("input[name],select[name],#goods-receipt-base,#goods-receipt-lines input")
      .prop("disabled", view);
    $("#btn-save-goods-receipt").toggle(!view);
    updateSaveButton();
    $("#btn-anular-goods-receipt")
      .toggleClass("d-none", !view)
      .prop(
        "disabled",
        !view || $("#goods-receipt-status").val() !== "Registrado",
      );
    $(".appu-document-toolbar__eyebrow").text(view ? "CONSULTA" : "NUEVO");
    if (view && current && current.receipt) savedValuation();
    if (window.appuMarkSaved) window.appuMarkSaved();
  }
  function clearDocument() {
    current = null;
    requestKey = "";
    $("#goods-receipt-id,#goods-receipt-number").val("");
    $("#goods-receipt-status").val("Nuevo");
    $(
      "#goods-receipt-base-type-label,#goods-receipt-base-status,#goods-receipt-provider-code,#goods-receipt-provider-name,#goods-receipt-provider-address,#goods-receipt-currency",
    ).val("");
    $("#goods-receipt-lines tbody").html(
      '<tr><td colspan="13" class="text-center text-muted py-4">Seleccione un documento base.</td></tr>',
    );
    $("#goods-receipt-history tbody").html(
      '<tr><td colspan="6" class="text-center text-muted">Seleccione un documento base.</td></tr>',
    );
    setMode("CREATE");
    total();
  }
  function renderHistory(rows) {
    var $b = $("#goods-receipt-history tbody").empty();
    (rows || []).forEach(function (x) {
      var $actionCell = $('<td class="text-right">');
      if (x.status === "Registrado") {
        var $button = $('<button type="button" class="btn btn-xs btn-outline-danger js-cancel-receipt" title="Anular">').data("id", Number(x.id));
        var icon = document.createElement("i");
        icon.className = "fas fa-ban";
        $button[0].appendChild(icon);
        $button.appendTo($actionCell);
      }
      $("<tr>")
        .append(
          $("<td>").text(x.number),
          $("<td>").text(x.receipt_date),
          $("<td>").text(x.supplier_delivery_reference || "-"),
          $("<td>").text(x.warehouse_code + " · " + x.warehouse_name),
          $("<td>").html(
            window.appuStatusBadge ? appuStatusBadge(x.status) : x.status,
          ),
          $actionCell,
        )
        .appendTo($b);
    });
    if (!(rows || []).length)
      $b.html(
        '<tr><td colspan="6" class="text-center text-muted">Aún no existen entradas para este documento.</td></tr>',
      );
  }
  function loadBase(type, id) {
    if (saving) return;
    var read = ++documentRead;
    if (!type || !id) {
      loading = false;
      clearDocument();
      return;
    }
    loading = true;
    updateSaveButton();
    $.getJSON(endpoint, { base_type: type, base_id: id })
      .done(function (r) {
        if (read !== documentRead) return;
        current = r;
        requestKey = "";
        var b = r.base;
        $("#goods-receipt-base-type-label").val(b.base_label);
        $("#goods-receipt-base-status").val(b.status);
        $("#goods-receipt-provider-code").val(b.provider_code);
        $("#goods-receipt-provider-name").val(b.provider_name);
        $("#goods-receipt-provider-address").val(
          b.provider_address || b.master_address || "",
        );
        $("#goods-receipt-currency").val(b.currency);
        var $w = $("#goods-receipt-warehouse")
          .empty()
          .append('<option value="">Seleccione un almacén</option>');
        (r.warehouses || []).forEach(function (w) {
          $("<option>")
            .val(w.id)
            .text(w.code + " · " + w.name)
            .appendTo($w);
        });
        var $body = $("#goods-receipt-lines tbody").empty();
        (r.details || []).forEach(function (x) {
          var pending = Number(x.pending_quantity) || 0;
          $("<tr>")
            .attr("data-line-id", x.id)
            .attr("data-unit-cost", x.item_unit_price)
            .append(
              $("<td>").text(x.item_code),
              $("<td>").text(x.item_description),
              $("<td>").text(x.unit_of_measure || "-"),
              $('<td class="text-right">').text(
                Number(x.ordered_quantity).toFixed(3),
              ),
              $('<td class="text-right">').text(
                Number(x.received_quantity).toFixed(3),
              ),
              $('<td class="text-right">').text(pending.toFixed(3)),
              $('<td class="text-right">').text(money(x.item_unit_price)),
              inputCell(
                $(
                  '<input type="number" min="0" step="1" class="form-control form-control-sm receipt-quantity">',
                )
                  .attr("max", pending)
                  .val(pending),
              ),
              inputCell(
                $('<input maxlength="50" class="form-control form-control-sm receipt-project">').val(x.project_code || ""),
              ),
              inputCell(
                $('<input maxlength="50" class="form-control form-control-sm receipt-cost-center">').val(x.cost_center_code || ""),
              ),
              inputCell(
                $('<input maxlength="80" class="form-control form-control-sm receipt-lot">').val(x.lot_number || ""),
              ),
              inputCell(
                $('<input maxlength="100" class="form-control form-control-sm receipt-serial">').val(x.serial_number || ""),
              ),
              inputCell(
                $('<input type="date" class="form-control form-control-sm receipt-expiration">').val(x.expiration_date || ""),
              ),
            )
            .appendTo($body);
        });
        if (!(r.details || []).length)
          $body.html(
            '<tr><td colspan="13" class="text-center text-muted">El documento no tiene líneas.</td></tr>',
          );
        renderHistory(r.receipts);
        total();
      })
      .fail(function (x) {
        if (read !== documentRead) return;
        var base = current && current.base;
        $("#goods-receipt-base").val(base ? key(base.base_type, base.id) : "").trigger("change.select2");
        toastr.error(
          (x.responseJSON || {}).message ||
            "No se pudo cargar el documento base.",
        );
      })
      .always(function () {
        if (read !== documentRead) return;
        loading = false;
        updateSaveButton();
      });
  }
  function loadRecords(selected) {
    $.getJSON(endpoint, { action: "list" }).done(function (r) {
      var $list = $("#goods-receipt-list")
        .empty()
        .append('<option value="">Seleccione un documento registrado</option>');
      (r.receipts || []).forEach(function (x) {
        $("<option>")
          .val(x.id)
          .text(
            x.number +
              " · " +
              x.receipt_date +
              " · " +
              x.provider_name +
              " · " +
              x.status,
          )
          .appendTo($list);
      });
      if (selected) $list.val(String(selected)).trigger("change.select2");
    });
  }
  function loadReceipt(id) {
    if (saving) return;
    if (!id) return toastr.info("Selecciona una entrada de mercancías.");
    var read = ++documentRead;
    loading = true;
    updateSaveButton();
    $.getJSON(endpoint, { action: "receipt", receipt_id: id })
      .done(function (r) {
        if (read !== documentRead) return;
        var h = r.receipt;
        if (!h || String(h.id) !== String(id)) return toastr.error("La entrada solicitada no está disponible.");
        current = r;
        requestKey = "";
        $("#goods-receipt-id").val(h.id);
        $("#goods-receipt-number").val(h.number);
        $("#goods-receipt-status").val(h.status);
        $("#goods-receipt-base")
          .empty()
          .append(
            $("<option>")
              .val(key(h.base_document_type, h.base_document_id))
              .text(h.base_label + " " + h.base_number),
          )
          .val(key(h.base_document_type, h.base_document_id));
        $("#goods-receipt-base-type-label").val(h.base_label);
        $("#goods-receipt-base-status").val("Documento base " + h.base_number);
        $("#goods-receipt-provider-code").val(h.provider_code);
        $("#goods-receipt-provider-name").val(h.provider_name);
        $("#goods-receipt-provider-address").val(h.provider_address);
        $("#goods-receipt-currency").val(h.currency);
        $("#goods-receipt-warehouse")
          .empty()
          .append(
            $("<option>")
              .val(h.warehouse_id)
              .text(h.warehouse_code + " · " + h.warehouse_name),
          )
          .val(h.warehouse_id);
        $("[name=receipt_date]").val(h.receipt_date);
        $("[name=supplier_delivery_reference]").val(
          h.supplier_delivery_reference,
        );
        $("[name=notes]").val(h.notes);
        var $body = $("#goods-receipt-lines tbody").empty();
        (r.details || []).forEach(function (x) {
          $("<tr>")
            .attr("data-unit-cost", x.base_unit_cost)
            .append(
              $("<td>").text(x.item_code),
              $("<td>").text(x.item_description),
              $("<td>").text(x.unit_of_measure || "-"),
              $('<td class="text-right">').text(
                Number(x.ordered_quantity).toFixed(3),
              ),
              $('<td class="text-right">').text(
                Number(x.received_quantity).toFixed(3),
              ),
              $('<td class="text-right">').text(
                Number(x.pending_quantity).toFixed(3),
              ),
              $('<td class="text-right">').text(money(x.base_unit_cost)),
              $('<td class="text-right font-weight-bold">').text(
                Number(x.quantity).toFixed(3),
              ),
              $("<td>").text(x.project_code || "-"),
              $("<td>").text(x.cost_center_code || "-"),
              $("<td>").text(x.lot_number || "-"),
              $("<td>").text(x.serial_number || "-"),
              $("<td>").text(x.expiration_date || "-"),
            )
            .appendTo($body);
        });
        $("#goods-receipt-total").text(
          h.currency +
            " " +
            money(
              (r.details || []).reduce(function (sum, x) {
                return sum + Number(x.base_inventory_value || 0);
              }, 0),
            ),
        );
        renderHistory([h]);
        setMode("VIEW");
      })
      .fail(function (x) {
        if (read !== documentRead) return;
        toastr.error(
          (x.responseJSON || {}).message || "No se pudo abrir la entrada.",
        );
      })
      .always(function () {
        if (read !== documentRead) return;
        loading = false;
        updateSaveButton();
      });
  }
  function loadEligible(selected) {
    var read = ++documentRead;
    $.getJSON(endpoint, { action: "eligible" })
      .done(function (r) {
        if (read !== documentRead) return;
        var $s = $("#goods-receipt-base")
          .empty()
          .append(
            '<option value="">Seleccione una orden o factura con saldo pendiente</option>',
          );
        (r.bases || []).forEach(function (x) {
          $("<option>")
            .val(key(x.base_type, x.id))
            .text(
              x.base_label +
                " " +
                x.number +
                " · " +
                x.provider_name +
                " · pendiente " +
                Number(x.pending_quantity).toFixed(3),
            )
            .appendTo($s);
        });
        if (selected && Array.from($s[0].options).some(option => option.value === selected)) {
          $s.val(selected);
          var p = splitKey(selected);
          loadBase(p.type, p.id);
        } else clearDocument();
      })
      .fail(function () {
        if (read !== documentRead) return;
        toastr.error("No se pudieron consultar los documentos pendientes.");
      });
  }
  $("#goods-receipt-base").on("change", function () {
    if (mode === "VIEW") return;
    var p = splitKey(this.value);
    loadBase(p.type, p.id);
  });
  $("#btn-select-goods-receipt").on("click", function () {
    loadReceipt($("#goods-receipt-list").val());
  });
  $(document).on("input", ".receipt-quantity", total);
  $("#goods-receipt-valuation-rate").on("input change", total);
  $("#goods-receipt-form").on("submit", function (e) {
    e.preventDefault();
    if (mode === "VIEW" || saving || loading || !appuCan("purchases.manage")) return;
    if (!current || !current.base) return toastr.warning("Selecciona un documento base.");
    var selected = splitKey($("#goods-receipt-base").val()),
      items = [];
    $("#goods-receipt-lines tr[data-line-id]").each(function () {
      var qty = Number($(this).find(".receipt-quantity").val()) || 0;
      if (qty > 0)
        items.push({
          base_line_id: $(this).data("line-id"),
          quantity: qty,
          project_code: $(this).find(".receipt-project").val(),
          cost_center_code: $(this).find(".receipt-cost-center").val(),
          lot_number: $(this).find(".receipt-lot").val(),
          serial_number: $(this).find(".receipt-serial").val(),
          expiration_date: $(this).find(".receipt-expiration").val(),
        });
    });
    if (!items.length)
      return toastr.warning("Indica al menos una cantidad a recibir.");
    var data = $(this).serializeArray();
    requestKey ||= appuRequestKey();
    data.push(
      { name: "base_type", value: selected.type },
      { name: "base_id", value: selected.id },
      { name: "items", value: JSON.stringify(items) },
      { name: "request_key", value: requestKey },
    );
    saving = true;
    form.inert = true;
    $(".appu-document-toolbar,.appu-document-selector").prop("inert", true);
    updateSaveButton();
    appuClearValidation(form);
    $.post(endpoint, $.param(data), null, "json")
      .done(function (r) {
        if (!r || !/^[1-9]\d*$/.test(String(r.receipt_id))) {
          return toastr.error("El servidor no confirmó la entrada. Reintenta con los mismos datos.");
        }
        $("#goods-receipt-id").val(r.receipt_id);
        $("#goods-receipt-number").val(r.receipt_number);
        $("#goods-receipt-status").val("Registrado");
        setMode("VIEW");
        toastr.success(r.message);
        window.history.replaceState(null, "", appuDocumentRecordUrl(r.receipt_id));
      })
      .fail(function (x) {
        appuShowRequestError(form, x, "No se pudo guardar la entrada. Reintenta con los mismos datos.");
      })
      .always(function () {
        saving = false;
        form.inert = false;
        $(".appu-document-toolbar,.appu-document-selector").prop("inert", false);
        updateSaveButton();
        if (mode === "VIEW") {
          var id = $("#goods-receipt-id").val();
          loadRecords(id);
          loadReceipt(id);
        }
      });
  });
  function cancelReceipt(id) {
    if (saving || loading) return;
    var reason = window.prompt(
      "Motivo de anulación (mínimo 5 caracteres):",
      "",
    );
    if (reason === null) return;
    $.post(endpoint, { action: "cancel", receipt_id: id, reason: reason })
      .done(function (r) {
        toastr.success(r.message);
        loadRecords(id);
        loadReceipt(id);
      })
      .fail(function (x) {
        toastr.error(
          (x.responseJSON || {}).message || "No se pudo anular la entrada.",
        );
      });
  }
  $(document).on("click", ".js-cancel-receipt", function () {
    cancelReceipt($(this).data("id"));
  });
  $("#btn-anular-goods-receipt").on("click", function () {
    cancelReceipt($("#goods-receipt-id").val());
  });
  var q = new URLSearchParams(location.search),
    receiptId = q.get("record_id") || q.get("receipt_id"),
    initial =
      q.get("base_type") && q.get("base_id")
        ? key(q.get("base_type").toUpperCase(), q.get("base_id"))
        : q.get("purchase_id")
          ? key("PURCHASE", q.get("purchase_id"))
          : "";
  loadRecords(receiptId);
  setMode(receiptId ? "VIEW" : "CREATE");
  if (receiptId) loadReceipt(receiptId);
  else loadEligible(initial);
});
