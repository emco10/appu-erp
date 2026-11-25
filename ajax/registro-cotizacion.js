$("#col-btn-anular-cotizprod").hide();
$("#btn-save-cotizprod").prop("disabled", true);
$("#btn-add-prodtocotiz").prop("disabled", true);
$("#btn-select-cotizacion").prop("disabled", true);
$('input[name="cotizacion_prodcant"]').prop("disabled", true);
$('#div_diaspago').hide();
$('input[name="cotizacion_formpago"]').prop("required",false);

$(document).ready(function(){
  $("#m_registro_cotizacion").attr("class","nav-link active");
  $("#m_cotizacion").attr("class","nav-link active");
  $("#m_cotizacion").parent().attr("class","nav-item has-treeview menu-open");
  $(document).prop('title', 'Registro de Cotización - APPU');
});

$.getJSON("../../modules/inventory/warehouses.php").done(function (data) { var $select = $('select[name="cotizacion_almacen_id"]'); var selectedWarehouse = $select.data("pending-warehouse") || $select.val() || '1'; $select.empty(); (data.warehouses || []).filter(function (warehouse) { return Number(warehouse.active_status) === 1; }).forEach(function (warehouse) { $select.append($('<option>').val(warehouse.id).text(warehouse.code + ' - ' + warehouse.name)); }); $select.val(String(selectedWarehouse)); });

$('select[name="cotizacion_formpagotext"]').on("change", function() {
  valtipo = $(this).val();
  cotiztipopago = $('input[name="cotizacion_formpago"]');
  div_tipopago = $('#div_diaspago');
  if(valtipo != ""){    
    if(valtipo == "Otro"){
      cotiztipopago.val("");
      cotiztipopago.prop("required",true);
      div_tipopago.show();
    } else {
      cotiztipopago.val(valtipo);
      cotiztipopago.prop("required",false);
      div_tipopago.hide();
    }
  }
});

$('input[name="cotizacion_valcliente"]').autocomplete({
  source: function(request, response) {
    $.getJSON("../../modules/clientes/obtener-clientes.php", { cotiz_nomcliente: $('input[name="cotizacion_valcliente"]').val() }, response);
  },
  select: function (event, ui) {
    $(this).val(ui.item.value || ui.item.label);
    $('input[name="cotizacion_cliruc"]').val("");
    $('input[name="cotizacion_clidirecc"]').val("");
    $('input[name="cotizacion_clirefer"]').val("");
    if (ui.item.id != "" && ui.item.id != null) {
      $.getJSON('../../modules/clientes/obtener-clientes.php', { client_id: ui.item.id }).done(function (response) {
        if (response.found) applyCustomer(response.customer);
      });
    }
  }
});
$('input[name="cotizacion_valcliente"]').on('input', function () {
  $('input[name="cotizacion_cliente"]').val('');
});

var customerRucTimer;
$('input[name="cotizacion_cliruc"]').autocomplete({
  minLength: 3,
  source: function (request, response) {
    $.getJSON('../../modules/clientes/obtener-clientes.php', { q: request.term }).done(function (rows) {
      response((rows || []).filter(function (row) { return row.id; }).map(function (row) { return { label: (row.ruc || '') + ' - ' + row.label, value: row.ruc || '', id: row.id }; }));
    });
  },
  select: function (event, ui) {
    $(this).val(ui.item.value).trigger('input');
  }
});
$('input[name="cotizacion_cliruc"]').on('input', function () {
  var field = $(this);
  var ruc = field.val().replace(/\D/g, '').slice(0, 11);
  field.val(ruc);
  $('input[name="cotizacion_cliente"]').val('');
  clearTimeout(customerRucTimer);
  if (ruc.length !== 11) return;
  customerRucTimer = setTimeout(function () {
    $.getJSON('../../modules/clientes/obtener-clientes.php', { ruc: ruc }).done(function (response) {
      if (!response.found) {
        $.Notification.notify('warning', 'bottom-right', 'Cliente no encontrado', 'No existe un socio de negocio con ese RUC.');
        return;
      }
      var c = response.customer;
      applyCustomer(c);
      $('input[name="cotizacion_clirefer"]').val('');
    });
  }, 250);
});

$("#btn-new-customer").on("click", function () {
  window.location.href = "../../views/clientes/registro-cliente";
});
$("#btn-new-product").on("click", function () {
  window.location.href = "../../views/productos/registro-producto";
});

var quotationCustomerAddresses = [];
function loadQuotationAddresses(customerId, selectedBilling, selectedShipping) {
  var billing=$('select[name="cotizacion_direccion_fiscal_id"]'), shipping=$('select[name="cotizacion_direccion_entrega_id"]');
  billing.html('<option value="">Dirección principal</option>'); shipping.html('<option value="">Dirección principal</option>');
  if(!customerId)return;
  $.getJSON('../../modules/clientes/listar-direcciones.php',{customer_id:customerId}).done(function(response){
    quotationCustomerAddresses=response.addresses||[];
    quotationCustomerAddresses.forEach(function(a){var label=(a.address_code||'')+' - '+(a.address_name||'')+' · '+(a.address_line||'');var option=$('<option>').val(a.id).text(label);billing.append(option.clone());shipping.append(option.clone());});
    var defaultBilling=quotationCustomerAddresses.find(function(a){return a.address_type==='billing'&&Number(a.is_default)===1;});
    var defaultShipping=quotationCustomerAddresses.find(function(a){return a.address_type==='shipping'&&Number(a.is_default)===1;})||defaultBilling;
    billing.val(String(selectedBilling||defaultBilling&&defaultBilling.id||'')); shipping.val(String(selectedShipping||defaultShipping&&defaultShipping.id||'')); billing.trigger('change');
  });
}
$('select[name="cotizacion_direccion_fiscal_id"]').on('change',function(){var id=Number($(this).val()),a=quotationCustomerAddresses.find(function(item){return Number(item.id)===id;});if(a)$('input[name="cotizacion_clidirecc"]').val(a.address_line||'');});

function applyCustomer(customer) {
  if (!customer) return;
  $('input[name="cotizacion_cliente"]').val(customer.client_id);
  $('input[name="cotizacion_cliruc"]').val(customer.ruc || '');
  $('input[name="cotizacion_valcliente"]').val(customer.business_name || customer.trade_name || '');
  $('input[name="cotizacion_clidirecc"]').val(customer.address || '');
  $('input[name="cotizacion_clirefer"]').val('');
  $('input[name="cotizacion_cliemail"], input[name="cotizacion_email"]').val(customer.email || '');
  $('input[name="cotizacion_clitelefono"], input[name="cotizacion_telefono"]').val(customer.phone || customer.cellphone || '');
  $('input[name="cotizacion_clicelular"], input[name="cotizacion_celular"]').val(customer.cellphone || '');
  loadQuotationAddresses(customer.client_id);
  window.appuCustomerPriceFactor = Math.max(0.0001, Number(customer.price_list_factor || 1));
  var paymentDays = Number(customer.payment_days || 0);
  var paymentSelect = $('select[name="cotizacion_formpagotext"]');
  var standardPayment = paymentSelect.find('option[value="' + paymentDays + '"]').length > 0;
  paymentSelect.val(standardPayment ? String(paymentDays) : 'Otro').trigger('change');
  $('input[name="cotizacion_formpago"]').val(paymentDays);
  var currency = String(customer.default_currency || 'PEN').toUpperCase();
  $('select[name="cotizacion_tipmon"]').val(currency === 'PEN' ? 'MN' : 'ME').trigger('change');
  if (customer.sales_user_id) {
    $('select[name="cotizacion_usuario"]').val(String(customer.sales_user_id)).trigger('change');
    $('input[name="cotizacion_usuarioid"]').val(customer.sales_user_id);
  }
}

$("#btn-choose-customer").on("click", function () {
  $.getJSON("../../modules/clientes/obtener-clientes.php", { q: "" }).done(function (rows) {
    $("#customer-list-select").empty().select2({
      data: (rows || []).filter(function (row) { return row.id; }).map(function (row) { return { id: row.id, text: row.label, ruc: row.ruc }; }),
      dropdownParent: $("#customer-list-modal"),
      placeholder: "Seleccione un cliente"
    });
    $("#customer-list-modal").modal("show");
  });
});

$("#btn-use-customer").on("click", function () {
  var customerId = $("#customer-list-select").val();
  if (!customerId) return;
  $.getJSON('../../modules/clientes/obtener-clientes.php', { client_id: customerId }).done(function (response) {
    if (response.found) {
      applyCustomer(response.customer);
      if (typeof window.appuMarkDirty === "function") window.appuMarkDirty();
    }
    $("#customer-list-modal").modal("hide");
  }).fail(function (xhr) {
    var message = xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error);
    toastr.error(message || "No se pudo recuperar el cliente seleccionado.");
  });
});

$.post("../../modules/usuarios/listar-usuarios-xtipo.php", function(data) {
  mydata = (typeof data === "string" ? JSON.parse(data) : data);
  data_users = mydata[0];
  user_id = mydata[1];
  user_job = mydata[2];
  const form = document.getElementById('FRM_INSERT_COTIZACION');
  const sellerId = form.elements.id_cotizacion.value ? form.elements.cotizacion_usuarioid.value : user_id;

  $('select[name="cotizacion_usuario"]').empty();
  $('select[name="cotizacion_usuario"]').select2({
    data: data_users
  });

  $('select[name="cotizacion_usuario"]').val(sellerId);
  $('select[name="cotizacion_usuario"]').trigger("change");
  form.elements.cotizacion_usuarioid.value = sellerId;
  
  if(user_job != "Secretaria" && user_job != "Secretario"){
    $('select[name="cotizacion_usuario"]').prop("disabled",true);
  }

});

$('select[name="cotizacion_usuario"]').on("change", function(){
  $('input[name="cotizacion_usuarioid"]').val($(this).val());
});

const quotationEntry = new URLSearchParams(window.location.search);
const previousQuotationId = leer_cookie('COOKIE_ID_COTIZ');
if (previousQuotationId) eliminar_cookie('COOKIE_ID_COTIZ');

$.post("../../modules/cotizaciones/listar-cotizaciones.php", function(data) {
  const list = $('select[name="cotizacion_listado"]');
  list.empty().select2({
    data: (typeof data === "string" ? JSON.parse(data) : data)
  });

  if (quotationEntry.get('mode') === 'create') {
    if (document.activeElement === document.body) $('input[name="cotizacion_valcliente"]').trigger('focus');
    return;
  }
  if (quotationEntry.has('record_id')) {
    appuOpenRequestedRecord('select[name="cotizacion_listado"]', '#btn-select-cotizacion');
    return;
  }
  if (/^[1-9]\d*$/.test(previousQuotationId)) {
    list.val(previousQuotationId).trigger('change');
    if (list.val() === previousQuotationId) $('#btn-select-cotizacion').trigger('click');
  }
});

$.post(
  "../../modules/productos/listar-productos-xprov.php",
  { ESTADO: 1 },
  function(data) {
    $('select[name="cotizacion_producto"]').empty();
    $('select[name="cotizacion_producto"]').select2({
      data: (typeof data === "string" ? JSON.parse(data) : data)
    });
  }
);

$('select[name="cotizacion_producto"]').on("change", function() {
  DATA_ID = $(this).val();
  $('input[name="cotizacion_prodcant"]').val(0);
  $('input[name="cotizacion_nameprod"]').val("");
  $('input[name="cotizacion_proddesc"]').val("");
  $('input[name="cotizacion_prodprecio"]').val("");
  $('input[name="cotizacion_stockprod"]').val("");
  if (DATA_ID != "" && DATA_ID != null) {
    $('input[name="cotizacion_prodcant"]').prop("disabled", false);
    $.post(
      "../../modules/productos/consultar-productos.php",
      { FILTER: DATA_ID, ESTADO: "1", warehouse_id: $('select[name="cotizacion_almacen_id"]').val() || 1 },
      function(data) {
        var mydata = (typeof data === "string" ? JSON.parse(data) : data);
        stock_producto = parseInt(mydata[0]["CANTIDAD"]);
        $('input[name="cotizacion_nameprod"]').val(mydata[0]["NOMBRE"]);
        $('input[name="cotizacion_proddesc"]').val(mydata[0]["DESCRIPTION"]);
        $('input[name="cotizacion_prodprecio"]').val((Number(mydata[0]["PRECIO"] || 0) * Number(window.appuCustomerPriceFactor || 1)).toFixed(2));
        $('input[name="cotizacion_stockprod"]').val(mydata[0]["CANTIDAD"]);
        if (stock_producto <= 0) {
          $.Notification.notify(
            "error",
            "bottom-right",
            "Stock agotado",
            "Producto seleccionado no cuenta con existencias"
          );
          $("#btn-add-prodtocotiz").prop("disabled", true);
        }
      }
    );
  } else {
    $('input[name="cotizacion_prodcant"]').prop("disabled", true);
  }
});

 $('select[name="cotizacion_almacen_id"]').on("change", function () { if ($('select[name="cotizacion_producto"]').val()) $('select[name="cotizacion_producto"]').trigger("change"); });
 $('input[name="cotizacion_prodcant"]').on("change", function() {
  cant_prod = parseInt($(this).val());
  stock_prod = parseInt($('input[name="cotizacion_stockprod"]').val());
  select_prod = $('select[name="cotizacion_producto"]').val();

  if (cant_prod <= stock_prod) {
    tbl_data = tbl_prodcotiz
      .rows()
      .data()
      .toArray();

    var cantidad_final = 0;
    cantidad_final += cant_prod;

    if (tbl_data.length > 0) {
      for (i = 0; i < tbl_data.length; i++) {
        id_prod = tbl_data[i][0];
        cant_agreg = parseInt(tbl_data[i][4]);
        if (select_prod == id_prod) {
          cantidad_final += cant_agreg;
        }
      }
      //console.log(cantidad_final);
      if (cantidad_final > stock_prod) {
        $("#btn-add-prodtocotiz").prop("disabled", true);
        $.Notification.notify(
          "error",
          "bottom-right",
          "Stock insuficiente",
          "Producto no cuenta con stock suficiente"
        );
      } else if (cantidad_final <= stock_prod) {
        $("#btn-add-prodtocotiz").prop("disabled", false);
      }
    } else {
      $("#btn-add-prodtocotiz").prop("disabled", false);
    }
  } else if (cant_prod > stock_prod) {
    $("#btn-add-prodtocotiz").prop("disabled", true);
    $.Notification.notify(
      "error",
      "bottom-right",
      "Stock insuficiente",
      "Producto no cuenta con stock suficiente"
    );
  }
});

var tbl_prodcotiz = $("#table-productscotiz").DataTable({
  columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }],
  "language": {"url": "../../plugins/datatables/Spanish.json"}
});

var total_temporal = 0;
tbl_prodcotiz.columns([0]).visible(false);

var tbl_data = "";

$("#btn-add-prodtocotiz").click(function() {
  idprod = $('select[name="cotizacion_producto"]').val();
  producto = $('input[name="cotizacion_nameprod"]').val();
  descripcion = $('input[name="cotizacion_proddesc"]').val();
  precio = parseFloat($('input[name="cotizacion_prodprecio"]').val());
  cantidad = parseInt($('input[name="cotizacion_prodcant"]').val());
  var descuentoLinea = Math.min(100, Math.max(0, Number($('input[name="cotizacion_proddescuento"]').val() || 0)));
  var proyecto = String($('input[name="cotizacion_prodproyecto"]').val() || '').trim();
  var centroCosto = String($('input[name="cotizacion_prodcentrocosto"]').val() || '').trim();
  var lote = String($('input[name="cotizacion_prodlote"]').val() || '').trim();
  var serie = String($('input[name="cotizacion_prodserie"]').val() || '').trim();
  var vencimiento = String($('input[name="cotizacion_prodvencimiento"]').val() || '');
  importe = Math.round(precio * cantidad * (1 - descuentoLinea / 100) * 100) / 100;
  var importe_actual = importe;

  if (idprod != "" && cantidad != "" && cantidad > 0) {
    $("#btn-add-prodtocotiz").prop("disabled", true);
    tbl_prodcotiz
      .rows(function(idx, data, node) {
        old_importe = data[6];
        old_cantidad = parseInt(data[4]);
        var sameLine = data[1] === producto && Number(data[5] || 0) === descuentoLinea && String(data[7] || '') === proyecto && String(data[8] || '') === centroCosto && String(data[9] || '') === lote && String(data[10] || '') === serie && String(data[11] || '') === vencimiento;
        if (sameLine) {
          importe += old_importe;
          cantidad += old_cantidad;
        }
        return sameLine;
      })
      .remove()
      .draw();

    tbl_prodcotiz.rows
      .add([
        {
          0: idprod,
          1: producto,
          2: descripcion,
          3: precio.toFixed(2),
          4: cantidad,
          5: descuentoLinea.toFixed(2),
          6: importe.toFixed(2),
          7: proyecto,
          8: centroCosto,
          9: lote,
          10: serie,
          11: vencimiento
        }
      ])
      .draw();

    tbl_data = tbl_prodcotiz
      .rows()
      .data()
      .toArray();

    opergrab =
      $('input[name="cotizacion_opergrab"]').val() != ""
        ? $('input[name="cotizacion_opergrab"]').val()
        : 0;
    importe_totactual = parseFloat(opergrab);
    importe_totactual += importe_actual;
    new_igv = importe_totactual * 0.18;
    new_total = importe_totactual + new_igv;

    total_temporal = new_total;

    $('input[name="cotizacion_opergrab"]').val(importe_totactual.toFixed(2));
    $('input[name="cotizacion_igv"]').val(new_igv.toFixed(2));
    $('input[name="cotizacion_total"]').val(new_total.toFixed(2));

    $('input[name="cotizacion_prodcant"]').val(0);
    $('input[name="cotizacion_prodprecio"]').val(0.00);

    $.Notification.notify(
      "success",
      "bottom-right",
      "Producto añadido",
      "El producto ha sido agregado a la cotización correctamente"
    );

    if (tbl_data.length > 0) {
      $("#btn-save-cotizprod").prop("disabled", false);
      porc_desc = parseFloat($('input[name="cotizacion_porcdesc"]').val()) / 100;
      val_desc = new_total * porc_desc;
      $('input[name="cotizacion_cantdesc"]').val(val_desc.toFixed(3));
    } else {
      $('input[name="cotizacion_cantdesc"]').val(0);
      $('input[name="cotizacion_porcdesc"]').val(0);
      $("#btn-save-cotizprod").prop("disabled", true);
      total_temporal = 0;
    }
  } else {
    $('select[name="cotizacion_producto"]').focus();
    $.Notification.notify(
      "error",
      "bottom-right",
      "Error al añadir",
      "Seleccione un producto de la lista"
    );
  }
});

$('select[name="cotizacion_listado"]').on("change", function() {
  val_lstcotiz = $(this).val();
  if (val_lstcotiz != "" && val_lstcotiz != null) {
    $("#btn-select-cotizacion").prop("disabled", false);
  } else {
    $("#btn-select-cotizacion").prop("disabled", true);
  }
});

let quotationLoadRevision = 0;
let quotationLoading = false;
let quotationSaving = false;
let quotationRequestKey = '';

function renderQuotationCustomFields(definitions, values) {
  appuRenderCustomFields($('#quotation-custom-fields'), definitions, values, 'quotation-custom-');
  $('#quotation-custom-fields-section').prop('hidden', !definitions.length);
}

renderQuotationCustomFields($('#quotation-custom-fields').data('definitions') || [], {});

$("#btn-select-cotizacion").on('click', function () {
  if (quotationSaving) return;
  const id = String($('select[name="cotizacion_listado"]').val() || '');
  if (!/^[1-9]\d*$/.test(id)) return;

  const revision = ++quotationLoadRevision;
  quotationLoading = true;
  Swal.fire({
    title: 'Cargando cotización',
    allowOutsideClick: false,
    allowEscapeKey: false,
    onBeforeOpen: () => Swal.showLoading()
  });

  $.getJSON('../../modules/cotizaciones/consultar-documento.php', { id }).done(function (snapshot) {
    if (revision !== quotationLoadRevision) return;
    const { quotation: quote, details, custom_fields, custom_values, version } = snapshot || {};
    if (!quote || String(quote.id) !== id || !Array.isArray(details) || !/^[a-f0-9]{64}$/.test(version)) {
      toastr.warning('La cotización seleccionada no está disponible.');
      return;
    }

    const form = document.getElementById('FRM_INSERT_COTIZACION');
    const fields = form.elements;
    quotationRequestKey = '';
    appuClearValidation(form);
    fields.id_cotizacion.value = id;
    fields.version.value = version;
    fields.cotizacion_fecha.value = String(quote.date || '').slice(0, 10);
    fields.cotizacion_nro.value = quote.number;
    fields.cotizacion_estado.value = quote.status;
    $('select[name="cotizacion_usuario"]').val(quote.seller_id).trigger('change');
    fields.cotizacion_usuarioid.value = quote.seller_id;
    fields.cotizacion_cliente.value = quote.customer_id;
    fields.cotizacion_valcliente.value = quote.name;
    fields.cotizacion_cliruc.value = quote.ruc;
    fields.cotizacion_clidirecc.value = quote.address;
    fields.cotizacion_clirefer.value = quote.reference;
    loadQuotationAddresses(quote.customer_id, quote.billing_address_id, quote.shipping_address_id);

    const payment = $('select[name="cotizacion_formpagotext"]').val(quote.payment_days);
    if (payment.val() === null) payment.val('Otro');
    payment.trigger('change');
    fields.cotizacion_formpago.value = quote.payment_days;

    fields.cotizacion_fecentrega.value = String(quote.delivery_date || '').slice(0, 10);
    $('select[name="cotizacion_almacen_id"]').data('pending-warehouse', quote.warehouse_id || 1).val(quote.warehouse_id || 1);
    $('select[name="cotizacion_tipmon"]').val(quote.currency).trigger('change');
    fields.cotizacion_porcdesc.value = quote.discount_rate;
    fields.cotizacion_cantdesc.value = quote.discount_value;
    fields.cotizacion_opergrab.value = Number(quote.total_sub).toFixed(2);
    fields.cotizacion_igv.value = Number(quote.total_tax).toFixed(2);
    fields.cotizacion_total.value = Number(quote.total_net).toFixed(2);
    total_temporal = Number(quote.total_net) + Number(quote.discount_value);
    renderQuotationCustomFields(custom_fields || [], custom_values);

    $('select[name="cotizacion_producto"]').val('').trigger('change');
    const rows = details.map(function (line) {
      return [line.item_id, line.item_name, line.item_description, Number(line.item_unit_price).toFixed(2),
        line.item_quantity, Number(line.item_discount_rate || 0).toFixed(2), Number(line.item_discounted_total).toFixed(2),
        line.project_code || '', line.cost_center_code || '', line.lot_number || '', line.serial_number || '', line.expiration_date || ''];
    });
    tbl_prodcotiz.clear().rows.add(rows).draw();

    const canManage = appuCan('sales.manage') && Number(quote.status) !== 2;
    $('#btn-add-prodtocotiz').prop('disabled', true);
    $('#btn-save-cotizprod').prop('disabled', !canManage || !rows.length);
    $('#col-btn-save-cotizprod').hide();
    $('#btn-anular-cotizprod').attr('js-id', id).prop('disabled', !canManage);
    $('#col-btn-anular-cotizprod').show().attr('class', 'col-md-12');
    appuMarkSaved();
  }).fail(function (xhr) {
    if (revision === quotationLoadRevision) {
      toastr.error(xhr.responseJSON?.message || 'No se pudo consultar la cotización. Vuelve a seleccionarla.');
    }
  }).always(function () {
    if (revision !== quotationLoadRevision) return;
    quotationLoading = false;
    Swal.close();
  });
});

$("#table-productscotiz").on("dblclick", "tr", function() {
  var data_row = tbl_prodcotiz.row(this).data();
  var row_id = data_row[0];
  var importe_prod = data_row[6];

  opergrab =
    $('input[name="cotizacion_opergrab"]').val() != ""
      ? $('input[name="cotizacion_opergrab"]').val()
      : 0;
  importe_totactual = parseFloat(opergrab);
  importe_totactual -= importe_prod;
  new_igv = importe_totactual * 0.18;
  new_total = importe_totactual + new_igv;

  total_temporal = new_total;

  $('input[name="cotizacion_opergrab"]').val(importe_totactual.toFixed(2));
  $('input[name="cotizacion_igv"]').val(new_igv.toFixed(2));
  $('input[name="cotizacion_total"]').val(new_total.toFixed(2));

  tbl_prodcotiz
    .rows(tbl_prodcotiz.row(this))
    .remove()
    .draw();

  tbl_data = tbl_prodcotiz
    .rows()
    .data()
    .toArray();

  $('input[name="cotizacion_prodcant"]').val(0);
  $("#btn-add-prodtocotiz").prop("disabled", true);

  $.Notification.notify(
    "success",
    "bottom-right",
    "Producto eliminado",
    "El producto ha sido eliminado correctamente"
  );

  if (tbl_data.length > 0) {
    $("#btn-save-cotizprod").prop("disabled", false);
    porc_desc = parseFloat($('input[name="cotizacion_porcdesc"]').val()) / 100;
    val_desc = new_total * porc_desc;
    $('input[name="cotizacion_cantdesc"]').val(val_desc.toFixed(3));
  } else {
    $("#btn-save-cotizprod").prop("disabled", true);
    $('input[name="cotizacion_cantdesc"]').val(0);
    $('input[name="cotizacion_porcdesc"]').val(0);
    total_temporal = 0;
  }
});

$('input[name="cotizacion_porcdesc"]').on("change", function() {
  num_desc = parseFloat($(this).val());
  if (isNaN(num_desc)) num_desc=0;

  porc_desc = num_desc / 100;
  total_actual = parseFloat($('input[name="cotizacion_total"]').val());
  val_desc = total_actual * porc_desc;
  $('input[name="cotizacion_cantdesc"]').val(val_desc.toFixed(3));

  total_desc = total_temporal - val_desc;
  $('input[name="cotizacion_total"]').val(total_desc.toFixed(2));
});

$("#FRM_INSERT_COTIZACION").on('submit', function (event) {
  event.preventDefault();
  if (quotationSaving || quotationLoading || !appuCan('sales.manage')) return;

  const form = this;
  if (!form.elements.cotizacion_cliente.value) {
    appuShowValidation(form, {
      message: 'Selecciona un cliente registrado.',
      errors: [{ path: 'cotizacion_valcliente', message: 'Elige el cliente desde la búsqueda.' }]
    });
    return;
  }

  const rows = tbl_prodcotiz.rows().data().toArray();
  if (!rows.length) {
    toastr.warning('Agrega al menos un artículo a la cotización.');
    return;
  }

  const data = new FormData(form);
  quotationRequestKey ||= appuRequestKey();
  data.set('cotiz_prods', JSON.stringify(rows));
  data.set('request_key', quotationRequestKey);
  data.set('response_format', 'json');
  appuClearValidation(form);

  const toolbar = document.querySelector('.appu-document-toolbar');
  const saveButton = form.querySelector('#btn-save-cotizprod');
  function lock(locked) {
    quotationSaving = locked;
    form.inert = locked;
    if (toolbar) toolbar.inert = locked;
    form.setAttribute('aria-busy', String(locked));
    saveButton.disabled = locked;
  }

  lock(true);
  Swal.fire({
    title: 'Guardando cotización',
    allowOutsideClick: false,
    allowEscapeKey: false,
    onBeforeOpen: () => Swal.showLoading()
  });

  $.ajax({
    type: 'POST',
    url: form.action,
    data,
    dataType: 'json',
    contentType: false,
    processData: false
  }).done(function (response) {
    if (!response || response.error !== false || !/^[1-9]\d*$/.test(String(response.quotation_id))) {
      lock(false);
      Swal.close();
      appuShowRequestError(form, { responseJSON: response }, 'No se pudo confirmar el guardado. Reintenta con los mismos datos.');
      return;
    }

    appuMarkSaved();
    const savedUrl = new URL(appuNewDocumentUrl());
    savedUrl.searchParams.delete('mode');
    savedUrl.searchParams.set('record_id', String(response.quotation_id));
    window.location.assign(savedUrl.href);
  }).fail(function (xhr) {
    lock(false);
    Swal.close();
    if (!xhr.status || xhr.status >= 500) {
      toastr.error('No se pudo confirmar el guardado. Reintenta con los mismos datos para confirmar la operación.');
      return;
    }
    appuShowRequestError(form, xhr, 'No se pudo guardar la cotización. Revisa los datos e inténtalo nuevamente.');
  });
});

$("#btn-anular-cotizprod").click(function() {
  if (quotationSaving || quotationLoading || !appuCan('sales.manage')) return;
  element = $(this);
  id_val = element.attr("js-id");
  if (id_val != "" && id_val != null) {
    Swal.fire({
      title: "Se anulará esta cotización",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Anular",
      cancelButtonText: "Cancelar"
    }).then(result => {
      if (result.value) {
        var reason = window.prompt("Motivo de anulacion de la cotizacion (obligatorio):", "");
        if (reason === null || reason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); return; }
        $.post(
          "../../modules/cotizaciones/anular-cotizacion.php",
          { ID_COTIZ: id_val, reason: reason.trim() },
          function(data) {
            if (data == true) {
              $('input[name="cotizacion_fecha"]').focus();
              tbl_prodcotiz.clear().draw();
              $("#FRM_INSERT_COTIZACION")
                .find("input, textarea, select")
                .val("");
              $('select[name="cotizacion_producto"]').trigger("change");
              $("#btn-add-prodtocotiz").prop("disabled", false);
              $("#btn-save-cotizprod").prop("disabled", true);
              $.post(
                "../../modules/cotizaciones/listar-cotizaciones.php",
                function(data) {
                  $('select[name="cotizacion_listado"]').empty();
                  $('select[name="cotizacion_listado"]').select2({
                    data: (typeof data === "string" ? JSON.parse(data) : data)
                  });
                }
              );
              $('input[name="id_cotizacion"]').val("");
              $('input[name="cotizacion_valcliente"]').val("");
              $("#btn-save-cotizprod font").html("Guardar cotización");
              $("#col-btn-save-cotizprod").attr("class", "col-md-12");
              $("#col-btn-anular-cotizprod").hide();

              $('select[name="cotizacion_formpagotext"]').prop("disabled",false);
              $('#div_diaspago').hide();
              $('input[name="cotizacion_formpago"]').prop("required",false);
              $.Notification.notify(
                "success",
                "bottom-right",
                "Operación completada",
                "Cotización anulada correctamente"
              );
            }
          }
        );
      }
    });
  }
});
