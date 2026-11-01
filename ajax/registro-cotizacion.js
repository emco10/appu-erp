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
  mydata = JSON.parse(data);
  data_users = mydata[0];
  user_id = mydata[1];
  user_job = mydata[2];

  $('select[name="cotizacion_usuario"]').empty();
  $('select[name="cotizacion_usuario"]').select2({
    data: data_users
  });

  $('select[name="cotizacion_usuario"]').val(user_id);
  $('select[name="cotizacion_usuario"]').trigger("change");
  $('input[name="cotizacion_usuarioid"]').val(user_id);
  
  if(user_job != "Secretaria" && user_job != "Secretario"){
    $('select[name="cotizacion_usuario"]').prop("disabled",true);
  }

});

$('select[name="cotizacion_usuario"]').on("change", function(){
  $('input[name="cotizacion_usuarioid"]').val($(this).val());
});

$.post("../../modules/cotizaciones/listar-cotizaciones.php", function(data) {
  $('select[name="cotizacion_listado"]').empty();
  $('select[name="cotizacion_listado"]').select2({
    data: JSON.parse(data)
  });
  appuOpenRequestedRecord('select[name="cotizacion_listado"]', '#btn-select-cotizacion');
});

$.post(
  "../../modules/productos/listar-productos-xprov.php",
  { ESTADO: 1 },
  function(data) {
    $('select[name="cotizacion_producto"]').empty();
    $('select[name="cotizacion_producto"]').select2({
      data: JSON.parse(data)
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
        var mydata = JSON.parse(data);
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
  importe = precio * cantidad;
  var importe_actual = importe;

  if (idprod != "" && cantidad != "" && cantidad > 0) {
    $("#btn-add-prodtocotiz").prop("disabled", true);
    tbl_prodcotiz
      .rows(function(idx, data, node) {
        old_importe = data[5];
        old_cantidad = parseInt(data[4]);
        if (data[1] === producto) {
          importe += old_importe;
          cantidad += old_cantidad;
        }
        return data[1] === producto;
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
          5: importe.toFixed(2)
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

$("#btn-select-cotizacion").click(function() {
  DATA_ID = $('select[name="cotizacion_listado"]').val();
  if (DATA_ID != "" && DATA_ID != null) {
    Swal.fire({
      html: "<h4>Cargando datos de cotización</h4>",
      allowOutsideClick: false,
      onBeforeOpen: () => {
        Swal.showLoading();
      }
    });
    $.post(
      "../../modules/cotizaciones/consultar-cotizacion.php",
      { FILTER: DATA_ID, ESTADO: "ALL" },
      function(data) {
        var data_json = JSON.parse(data);

        est_cotiz = data_json[0]["ESTADO"];

        $('input[name="cotizacion_valcliente"]').focus();

        $("#btn-anular-cotizprod").attr("js-id", data_json[0]["CODIGOID"]);
        $('input[name="id_cotizacion"]').val(data_json[0]["CODIGOID"]);
        $('input[name="cotizacion_fecha"]').val(data_json[0]["FECREG"]);
        $('input[name="cotizacion_nro"]').val(data_json[0]["CODIGO"]);

        $('select[name="cotizacion_estado"]').val(data_json[0]["ESTADO"]);

        $('select[name="cotizacion_usuario"]').val(data_json[0]["USER_ID"]);
        $('select[name="cotizacion_usuario"]').trigger("change");
        $('input[name="cotizacion_usuarioid"]').val(data_json[0]["USER_ID"]);

        $('input[name="cotizacion_cliente"]').val(data_json[0]["CLIENTID"]);
        $('input[name="cotizacion_valcliente"]').val(data_json[0]["CLIENTNAME"]);
        
        $('input[name="cotizacion_cliruc"]').val(data_json[0]["CLIENTRUC"]);
        $('input[name="cotizacion_clidirecc"]').val(data_json[0]["CLIENTADDR"]);
        loadQuotationAddresses(data_json[0]["CLIENTID"], data_json[0]["BILLING_ADDRESS_ID"], data_json[0]["SHIPPING_ADDRESS_ID"]);
        $('input[name="cotizacion_clirefer"]').val(data_json[0]["CLIENTREFER"]);

        if(est_cotiz == 2){
          $("#btn-anular-cotizprod").prop("disabled",true);
        } else {
          $("#btn-anular-cotizprod").prop("disabled",false);
        }

        $('select[name="cotizacion_formpagotext"]').val(data_json[0]["PAY_DAYS"]);
        $('select[name="cotizacion_formpagotext"]').trigger("change");

        if ($('select[name="cotizacion_formpagotext"]').val() == null) {
          $('select[name="cotizacion_formpagotext"]').val("Otro");
          $('select[name="cotizacion_formpagotext"]').trigger("change");
          $('#div_diaspago').show();
          $('input[name="cotizacion_formpago"]').prop("required",true);
          $('input[name="cotizacion_formpago"]').val(data_json[0]["PAY_DAYS"] );
        }

        $('input[name="cotizacion_fecentrega"]').val(data_json[0]["DELIV_DATE"]);
        $('select[name="cotizacion_almacen_id"]').data("pending-warehouse", data_json[0]["WAREHOUSE_ID"] || 1).val(data_json[0]["WAREHOUSE_ID"] || 1);

        $('select[name="cotizacion_tipmon"]').val(data_json[0]["CURRENCY"]);
        $('select[name="cotizacion_tipmon"]').trigger("change");

        $('input[name="cotizacion_porcdesc"]').val(data_json[0]["DESC_RATE"]);
        $('input[name="cotizacion_cantdesc"]').val(data_json[0]["DESC_VAL"]);
        $('input[name="cotizacion_opergrab"]').val(parseFloat(data_json[0]["TOTAL_SUB"]).toFixed(2));
        $('input[name="cotizacion_igv"]').val(parseFloat(data_json[0]["TOTAL_TAX"]).toFixed(2));
        $('input[name="cotizacion_total"]').val(parseFloat(data_json[0]["TOTAL_NET"]).toFixed(2));

        total_temporal = data_json[0]["TOTAL_NET"];
        codigo_idcotiz = data_json[0]["CODIGOID"];

        $.post("../../modules/cotizaciones/consultar-detalle-cotizacion.php",
          { IDCOTIZ: codigo_idcotiz }, function(data) {
            $('select[name="cotizacion_producto"]').val("");
            $('select[name="cotizacion_producto"]').trigger("change");
            $('input[name="cotizacion_proddesc"]').val("");
            $('input[name="cotizacion_prodprecio"]').val("");
            $('input[name="cotizacion_prodcant"]').val(0);
            $("#btn-add-prodtocotiz").prop("disabled", true);
            $("#btn-save-cotizprod").prop("disabled", false);

            tbl_prodcotiz.clear().draw();
            detacotiz_json = JSON.parse(data);
            for (i = 0; i < detacotiz_json.length; i++) {
              var precio = parseFloat(detacotiz_json[i]["PRECIOUNIT"]).toFixed(2);
              var importe = parseFloat(detacotiz_json[i]["IMPORTE"]).toFixed(2);

              tbl_prodcotiz.rows
                .add([
                  {
                    0: detacotiz_json[i]["IDPROD"],
                    1: detacotiz_json[i]["NOMBRE"],
                    2: detacotiz_json[i]["DESCRIP"],
                    3: precio,
                    4: detacotiz_json[i]["CANTIDAD"],
                    5: importe
                  }
                ])
                .draw();
            }
          }
        );

        $("#col-btn-save-cotizprod").hide();
        $("#col-btn-anular-cotizprod").show("fast");
        $("#col-btn-anular-cotizprod").attr("class", "col-md-12");
      }
    ).then(function() {
      Swal.close();
    });
  }
});

$("#table-productscotiz").on("dblclick", "tr", function() {
  var data_row = tbl_prodcotiz.row(this).data();
  var row_id = data_row[0];
  var importe_prod = data_row[5];

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

$("#FRM_INSERT_COTIZACION").submit(function(e) {
  e.preventDefault();
  if (!$('input[name="cotizacion_cliente"]').val()) {
    $.Notification.notify("warning", "bottom-right", "Cliente requerido", "Selecciona un cliente registrado desde la búsqueda.");
    $('input[name="cotizacion_valcliente"]').focus();
    return;
  }
  var form = $(this);
  var idform = form.attr("id");
  var url = form.attr("action");
  tbl_data = tbl_prodcotiz.rows().data().toArray();
  var formElement = document.getElementById(idform);
  var formData_rec = new FormData(formElement);
  formData_rec.append("cotiz_prods", JSON.stringify(tbl_data));
  var id_cotizacion = $('input[name="id_cotizacion"]').val();
  $.ajax({
    type: "POST",
    url: url,
    data: formData_rec,
    contentType: false,
    cache: false,
    processData: false,
    error: function(xhr) { Swal.close(); if (typeof window.appuShowRequestError === 'function') window.appuShowRequestError(form,xhr,'No se pudo guardar la cotización. Revisa los datos e inténtalo nuevamente.'); else toastr.error(xhr.responseJSON && xhr.responseJSON.message || 'No se pudo guardar la cotización. Revisa los datos e inténtalo nuevamente.'); },
    beforeSend: function() {
      Swal.fire({
        html: "<h4>Guardando cotización</h4>",
        allowOutsideClick: false,
        onBeforeOpen: () => {
          Swal.showLoading();
        }
      });
    },
    success: function(data) {
      //console.log(data);
      if (data == "ERROR") {
        $.Notification.notify(
          "error",
          "bottom-right",
          "Error de guardado",
          "No se pudo guardar la cotización"
        );
        Swal.close();
      } else if (data == "OK_INSERT") {
        $('input[name="cotizacion_valcliente"]').val("");
        $('input[name="cotizacion_fecha"]').focus();
        $.Notification.notify(
          "success",
          "bottom-right",
          "Cotización guardada",
          "Datos almacenados"
        );
        form.find("input, textarea, select").val("");
        $('select[name="cotizacion_producto"]').trigger("change");
        $("#btn-save-cotizprod").prop("disabled", true);
        $("#btn-add-prodtocotiz").prop("disabled", true);
        $('select[name="cotizacion_formpagotext"]').prop("disabled",false);
        $('#div_diaspago').hide();
        $('input[name="cotizacion_formpago"]').prop("required",false);
        tbl_prodcotiz.clear().draw();
        Swal.close();

        $.post("../../modules/cotizaciones/listar-cotizaciones.php", function(
          data
        ) {
          $('select[name="cotizacion_listado"]').empty();
          $('select[name="cotizacion_listado"]').select2({
            data: JSON.parse(data)
          });
        });
      } else if (data == "OK_UPDATE") {
        if (id_cotizacion != "" && id_cotizacion != null) {
          $('input[name="cotizacion_fecha"]').focus();
          $('input[name="cotizacion_valcliente"]').val("");
          $('input[name="id_cotizacion"]').val("");
          $("#btn-save-cotizprod font").html("Guardar cotización");
          $("#col-btn-save-cotizprod").attr("class", "col-md-12");
          $("#col-btn-anular-cotizprod").hide();
          form.find("input, textarea, select").val("");
          $('select[name="cotizacion_producto"]').trigger("change");
          $("#btn-save-cotizprod").prop("disabled", true);
          $("#btn-add-prodtocotiz").prop("disabled", true);
          $('select[name="cotizacion_formpagotext"]').prop("disabled",false);
          $('#div_diaspago').hide();
          $('input[name="cotizacion_formpago"]').prop("required",false);
        }

        $.Notification.notify(
          "success",
          "bottom-right",
          "Cotización actualizada",
          "Datos actualizados"
        );
        tbl_prodcotiz.clear().draw();
        
        $("#btn-save-cotizprod").prop("disabled", true);

        $.post("../../modules/cotizaciones/listar-cotizaciones.php", function(
          data
        ) {
          $('select[name="cotizacion_listado"]').empty();
          $('select[name="cotizacion_listado"]').select2({
            data: JSON.parse(data)
          });
        });

        Swal.close();
      }
    }
  });
});

$("#btn-anular-cotizprod").click(function() {
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
                    data: JSON.parse(data)
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

$("#btn-nuevo").click(function (e) {
    e.preventDefault();
    location.reload();
});

$("#btn-search-cotizacion").on("click", function () {
  $('select[name="cotizacion_listado"]').select2("open");
});
function navigateQuotation(direction) {
  var $select = $('select[name="cotizacion_listado"]');
  var options = $select.find("option").toArray();
  var current = $select.prop("selectedIndex");
  var next = current + direction;
  if (next < 1 || next >= options.length) return;
  $select.prop("selectedIndex", next).trigger("change");
  $("#btn-select-cotizacion").trigger("click");
}
$("#btn-prev-cotizacion").on("click", function () { navigateQuotation(-1); });
$("#btn-next-cotizacion").on("click", function () { navigateQuotation(1); });

$( document ).ready(function() {
  var cookie_idcotiz = leer_cookie('COOKIE_ID_COTIZ');
  if (cookie_idcotiz != "") {
    setTimeout(function(){
      $('select[name="cotizacion_listado"]').val(cookie_idcotiz);
      $('select[name="cotizacion_listado"]').trigger("change");
      $('#btn-select-cotizacion').trigger("click");
      eliminar_cookie("COOKIE_ID_COTIZ");
    },500);
  }
});
