$("#col-btn-delete-orden").hide();
var serviceForm = document.getElementById('FRM_INSERT_DETA_ORDSERV');
var serviceLoading=false,serviceProductEpoch=0,serviceCatalogEpoch=0,serviceProviderEpoch=0,serviceOpenEpoch=0,serviceStatus='Pendiente';
function serviceError(xhr,fallback){if(typeof window.appuShowRequestError==='function')window.appuShowRequestError($('#FRM_INSERT_DETA_ORDSERV'),xhr,fallback);else{var response=xhr.responseJSON||{};toastr.error(response.message||response.error||fallback);}}
function serviceCanEdit(){return !serviceForm.inert && appuCan('purchases.manage') && ['CREATE','EDIT'].includes(serviceForm.dataset.documentMode);}
$("#btn-save-orden").prop("disabled", true);
$("#btn-add-prodtporden").prop("disabled", true);
$("#btn-select-orden").prop("disabled", true);
$('#div_diaspago').hide();
$('input[name="orden_tipopago"]').prop("required",false);

$(document).ready(function(){
  $("#m_ordenes").attr("class","nav-link active");
  $("#m_ordenes").parent().attr("class","nav-item has-treeview menu-open");
  $("#m_orden_servicio").attr("class","nav-link active");
  $(document).prop('title', 'Orden de Servicio - APPU');
  if(!appuCan('purchases.manage')){
    $('#FRM_INSERT_DETA_ORDSERV :input').prop('disabled',true);
    $('#btn-save-orden,#btn-add-prodtporden,#col-btn-delete-orden').hide();
  }
});

var porc_igv = 0.18;

$('select[name="orden_tipopagotext"]').on("change", function() {
  valtipo = $(this).val();
  ordentipopago = $('input[name="orden_tipopago"]');
  div_tipopago = $('#div_diaspago');
  if(valtipo != ""){    
    if(valtipo == "Otro"){
      ordentipopago.val("");
      ordentipopago.prop("required",true);
      div_tipopago.show();
    } else {
      ordentipopago.val(valtipo);
      ordentipopago.prop("required",false);
      div_tipopago.hide();
    }
  }
});

$.post("../../modules/ordenes/listar-ordenes.php", {orden_tipo:"SERVICIO"}, function(data) {
  $('select[name="orden_listado"]').empty();
  $('select[name="orden_listado"]').select2({
    data: (typeof data === "string" ? JSON.parse(data) : data)
  });
  if (new URLSearchParams(window.location.search).has("record_id")) {
    appuOpenRequestedRecord('select[name="orden_listado"]', '#btn-select-orden');
    return;
  }
  var legacyOrder = leer_cookie('COOKIE_ID_ORDEN');
  if (legacyOrder) {
    eliminar_cookie('COOKIE_ID_ORDEN');
    $('select[name="orden_listado"]').val(legacyOrder).trigger("change");
    loadServiceOrder($('select[name="orden_listado"]').val());
  }
}).fail(function (xhr) {
  serviceError(xhr, "No se pudo cargar la lista de órdenes de servicio.");
});

$.post("../../modules/proveedores/listar-proveedores.php", function(data) {
  var selected=$('select[name="orden_proveedor"]').val(),label=$('select[name="orden_proveedor"] option:selected').text();
  $('select[name="orden_proveedor"]').empty();
  $('select[name="orden_proveedor"]').select2({
    data: (typeof data === "string" ? JSON.parse(data) : data)
  });
  if(selected){var $select=$('select[name="orden_proveedor"]');if(!$select.find('option').filter(function(){return String(this.value)===String(selected);}).length)$select.append($('<option>').val(selected).text(label||'Proveedor '+selected));$select.val(selected).trigger('change.select2');}
});

function cargarProductosOrdenServicio(proveedorId) {
  var current=++serviceCatalogEpoch;
  var payload = { ESTADO: 1 }; if (proveedorId) payload.PROV_ID = proveedorId;
  $.post("../../modules/productos/listar-productos-xprov.php", payload, function(data) {
    if(current!==serviceCatalogEpoch)return;
    $('select[name="orden_producto_select"]').empty().select2({ data: (typeof data === "string" ? JSON.parse(data) : data), language: "es", placeholder: "Buscar por código o descripción" });
  }).fail(function(xhr){if(current===serviceCatalogEpoch)serviceError(xhr,'No se pudo cargar el catálogo de artículos.');});
}
cargarProductosOrdenServicio("");
$('select[name="orden_producto_select"]').on("change", function() {
  var current=++serviceProductEpoch,productId = $(this).val();
  $('input[name="orden_nombre_serv"],input[name="orden_glosa"],input[name="orden_codprod"]').val('');
  $("#btn-add-prodtporden").prop('disabled',true);
  if (!productId) return;
  $.post("../../modules/productos/consultar-productos.php", { FILTER: productId, ESTADO: "1" }, function(data) {
    if(current!==serviceProductEpoch)return;
    var product = (typeof data === "string" ? JSON.parse(data) : data)[0]; if (!product) return;
    $('input[name="orden_codprod"]').val(product.CODPROD);
    $('input[name="orden_nombre_serv"]').val(product.NOMBRE);
    $('input[name="orden_glosa"]').val(product.DESCRIPTION || product.NOMBRE);
    $('input[name="orden_precunit"]').val(product.PRECIO);
    $('input[name="orden_stockprod"]').val(product.CANTIDAD);
    $('select[name="orden_valunit"]').val(String(product.UNITVALUE || "").toLowerCase()).trigger("change");
    if(Number($('input[name="orden_cantidad"]').val())<=0)$('input[name="orden_cantidad"]').val(1);
    $('input[name="orden_porcdscto"]').trigger('change');
    $("#btn-add-prodtporden").prop("disabled", !serviceCanEdit());
  }).fail(function(xhr){if(current===serviceProductEpoch)serviceError(xhr,'No se pudo recuperar el artículo.');});
});

/*
$('input[name="orden_cotizacion"]').autocomplete({
  source: function(request, response) {
    $.getJSON("../../modules/cotizaciones/obtener-cotizaciones.php", { orden_cotizacion: $('input[name="orden_cotizacion"]').val() }, response);
  },
  select: function (event, ui) {
    $(this).val(ui.item.label);
  }
});
*/

$('select[name="orden_listado"]').on("change", function() {
  val_lstordens = $(this).val();
  if (val_lstordens != "" && val_lstordens != null) {
    $("#btn-select-orden").prop("disabled", false);
  } else {
    $("#btn-select-orden").prop("disabled", true);
  }
});

$('select[name="orden_proveedor"]').on('change', function(){
  var provid = $(this).val(),currentProvider=++serviceProviderEpoch;
  cargarProductosOrdenServicio(provid);
  if(provid != ""){
    //$('input[name="orden_producto_val"]').val("");
    $('input[name="orden_porcdscto"]').val("0");
    $('input[name="orden_valordscto"]').val("0");
    $.post(
      "../../modules/proveedores/consultar-proveedor.php",
      { FILTER:provid },
      function(data) {
        if(currentProvider!==serviceProviderEpoch)return;
        var mydata = (typeof data === "string" ? JSON.parse(data) : data);
        if(!mydata.length)return;
        $('input[name="orden_provruc"]').val(mydata[0]["NUMERO"]);
        $('input[name="orden_nomprov"]').val(mydata[0]["RAZ_SOC"]);
        $('select[name="orden_proveedor"] option:selected').text(mydata[0].RAZ_SOC);
      }
    );

    /*$('input[name="orden_producto_val"]').autocomplete({  
      source: function(request, response) {
        $.getJSON("../../modules/productos/obtener-productos.php", { 
          orden_nomprod: $('input[name="orden_producto_val"]').val(), prov_id: provid 
        }, response);
      },
      select: function (event, ui) {
        $(this).val(ui.item.label);
        $('input[name="orden_cantidad"]').val(0);
        $('input[name="orden_codprod"]').val("");
        $('input[name="orden_descprod"]').val("");
        $('input[name="orden_precunit"]').val("");
        $('input[name="orden_stockprod"]').val("");
        $('input[name="orden_nameprod"]').val("");
        if (ui.item.id != "" && ui.item.id != null) {
          $('input[name="orden_cantidad"]').prop("disabled", false);
          $.post(
            "../../modules/productos/consultar-productos.php",
            { FILTER: ui.item.id, ESTADO:'1' },
            function(data) {
              $('input[name="orden_porcdscto"]').prop("disabled", false);
              $('input[name="orden_cantidad"]').prop("disabled", false);
              var mydata = (typeof data === "string" ? JSON.parse(data) : data);
              precioprod_temp = parseFloat(mydata[0]["PRECIO"]);
              stock_producto = parseInt(mydata[0]["CANTIDAD"]);
              $('input[name="orden_producto"]').val(mydata[0]["CODIGO"]);
              $('input[name="orden_nameprod"]').val(mydata[0]["NOMBRE"]);
              $('input[name="orden_codprod"]').val(mydata[0]["CODPROD"]);
              $('input[name="orden_descprod"]').val(mydata[0]["DESCRIPTION"]);
              $('input[name="orden_precunit"]').val(mydata[0]["PRECIO"]);
              $('input[name="orden_stockprod"]').val(stock_producto);
              $('input[name="orden_valunit"]').val(mydata[0]["UNITVALUE"]);
            }
          );
        } else {
          $('input[name="orden_cantidad"]').prop("disabled", true);
        }
      }
    });*/
  }
});

var precioprod_temp = 0;

/*$('select[name="orden_producto"]').on("change", function() {
  DATA_ID = $(this).val();  
  $('input[name="orden_cantidad"]').val(0);
  $('input[name="orden_codprod"]').val("");
  $('input[name="orden_descprod"]').val("");
  $('input[name="orden_precunit"]').val("");
  $('input[name="orden_stockprod"]').val("");
  $('input[name="orden_nameprod"]').val("");
  if (DATA_ID != "" && DATA_ID != null) {
    $('input[name="orden_cantidad"]').prop("disabled", false);
    $.post(
      "../../modules/productos/consultar-productos.php",
      { FILTER: DATA_ID, ESTADO:'1' },
      function(data) {
        $('input[name="orden_porcdscto"]').prop("disabled", false);
        $('input[name="orden_cantidad"]').prop("disabled", false);
        var mydata = (typeof data === "string" ? JSON.parse(data) : data);
        precioprod_temp = parseFloat(mydata[0]["PRECIO"]);
        stock_producto = parseInt(mydata[0]["CANTIDAD"]);
        $('input[name="orden_nameprod"]').val(mydata[0]["NOMBRE"]);
        $('input[name="orden_codprod"]').val(mydata[0]["CODPROD"]);
        $('input[name="orden_descprod"]').val(mydata[0]["DESCRIPTION"]);
        $('input[name="orden_precunit"]').val(mydata[0]["PRECIO"]);
        $('input[name="orden_stockprod"]').val(stock_producto);
        $('input[name="orden_valunit"]').val(mydata[0]["UNITVALUE"]);
      }
    );
  } else {
    $('input[name="orden_cantidad"]').prop("disabled", true);
  }
});*/

/*
$('input[name="orden_cantidad"]').on("change", function() {
  cant_prod = parseInt($(this).val());
  stock_prod = parseInt($('input[name="orden_stockprod"]').val());
  select_prod = $('input[name="orden_producto"]').val();

  //if (cant_prod <= stock_prod) {
    tbl_data = tbl_prodordcompra
      .rows()
      .data()
      .toArray();

    var cantidad_final = 0;
    cantidad_final += cant_prod;

    if (tbl_data.length > 0) {
      for (i = 0; i < tbl_data.length; i++) {
        id_prod = tbl_data[i][0];
        nom_prod = tbl_data[i][3];
        cant_agreg = parseInt(tbl_data[i][4]);
        if (select_prod == id_prod || select_prod == nom_prod) {
          cantidad_final += cant_agreg;
        }
      }

      $("#btn-add-prodtporden").prop("disabled", false);
      if (cantidad_final > stock_prod) {
        $("#btn-add-prodtporden").prop("disabled", true);
        $.Notification.notify(
          "error",
          "bottom-right",
          "Stock insuficiente",
          "Producto no cuenta con stock suficiente"
        );
      } else if (cantidad_final <= stock_prod) {
        $("#btn-add-prodtporden").prop("disabled", false);
      }
    } else {
      $("#btn-add-prodtporden").prop("disabled", false);
    }
});
*/


var tbl_prodordcompra = $("#table-ord-servicios").DataTable({
  columnDefs: [{ targets: '_all', render: $.fn.dataTable.render.text() }],
  "language": {"url": "../../plugins/datatables/Spanish.json"}
});

var total_temporal = 0;

//tbl_prodordcompra.columns([0,10,11,12,13,14]).visible(false);

var tbl_data = "";

$("#btn-add-prodtporden").click(function(e) {

  e.preventDefault();
  if(!serviceCanEdit())return;
  
  //code = $('input[name="orden_codprod"]').val();
  name = $('input[name="orden_nombre_serv"]').val();
  gloss = $('input[name="orden_glosa"]').val();
  //unit_value = $('select[name="orden_valunit"]').val();
  
  //if (unit_value == "") unit_value = "-";

  unit_price = parseFloat($('input[name="orden_precunit"]').val());
  quantity = Number($('input[name="orden_cantidad"]').val());

  discount_rate = parseFloat($('input[name="orden_porcdscto"]').val());
  discounted_total = parseFloat($('input[name="orden_valordscto"]').val());

  if (name != "" && gloss != "" && Number.isFinite(unit_price) && unit_price >= 0 && Number.isInteger(quantity) && quantity > 0 && Number.isFinite(discount_rate) && discount_rate>=0 && discount_rate<=100) {
    discounted_total=Math.round(unit_price*quantity*(1-discount_rate/100)*100)/100;
    $("#btn-add-prodtporden").prop("disabled", true);

    tbl_prodordcompra
      .rows(function(idx, data, node) {
      })
      .remove()
      .draw();
      
      detail_index = tbl_prodordcompra.rows().count() + 1;

      tbl_prodordcompra.rows
      .add([
        {
          0: detail_index,
          //1: code,
          1: name,
          2: gloss,
          //3: unit_value,
          3: unit_price,
          4: quantity,
          5: discount_rate,
          6: discounted_total
        }
      ])
      .draw();

    tbl_data = tbl_prodordcompra
      .rows()
      .data()
      .toArray();

    total_compra =
      $('input[name="orden_totcompra"]').val() != ""
        ? $('input[name="orden_totcompra"]').val()
        : 0;

    total_compra_actual = parseFloat(total_compra);
    total_compra_actual += discounted_total 
    
    igv = total_compra_actual * porc_igv;    
    total_neto = total_compra_actual + igv;

    total_temporal = total_neto;
    
    //$('input[name="orden_codprod"]').val("");
    $('input[name="orden_nombre_serv"]').val("");
    $('input[name="orden_glosa"]').val(""); 
    //$('input[name="orden_valunit"]').val("");
    $('input[name="orden_precunit"]').val(0);
    $('input[name="orden_cantidad"]').val(0);
    $('input[name="orden_porcdscto"]').val(0);
    $('input[name="orden_valordscto"]').val(0);
    $('input[name="orden_valorigv"]').val(0);

    $('input[name="orden_totcompra"]').val(total_compra_actual.toFixed(2));
    $('input[name="orden_igv"]').val(igv.toFixed(2));
    $('input[name="orden_totneto"]').val(total_neto.toFixed(2));

    $.Notification.notify(
      "success",
      "bottom-right",
      "Servicio añadido",
      "El servicio fue agregado a la OS."
    );

    if (tbl_data.length > 0) {
      $("#btn-save-orden").prop("disabled", false);
      //porc_descuento = parseFloat(porc_desc) / 100;
      //val_desc = new_total * porc_descuento;
      //$('input[name="orden_valordscto"]').val(val_desc.toFixed(2));
    } else {
      $('input[name="orden_valordscto"]').val(0);
      $('input[name="orden_porcdscto"]').val(0);
      $('input[name="orden_igv"]').val(0);
      $('input[name="orden_porcigv"]').val(0);
      $("#btn-save-orden").prop("disabled", true);
      total_temporal = 0;
    }

  } else {
    $('input[name="orden_codprod"]').focus();
    $.Notification.notify(
      "error",
      "bottom-right",
      "Datos Faltantes de Servicio",
      "Complete nombre, glosa, precio válido, cantidad entera positiva y descuento de 0 a 100."
    );
  }
});

$("#table-ord-servicios").on("dblclick", "tr", function() {
  if(!serviceCanEdit())return;
  var data_row = tbl_prodordcompra.row(this).data();

  if (data_row == null) return;

  var row_id = data_row[0];
  var importe_prod = parseFloat(data_row[6]);
  
  //var porc_igv = $('input[name="orden_porcigv"]').val();
  //porc_igv = porc_igv==""?0:parseFloat(porc_igv);

  opergrab =
    $('input[name="orden_totcompra"]').val() != ""
      ? $('input[name="orden_totcompra"]').val()
      : 0;
  importe_totactual = parseFloat(opergrab);
  importe_totactual -= importe_prod;

  new_igv = importe_totactual * porc_igv;
  new_total = importe_totactual + new_igv;

  //total_temporal = new_total;
  total_temporal = importe_totactual;

  $('input[name="orden_totcompra"]').val(importe_totactual.toFixed(2));
  $('input[name="orden_igv"]').val(new_igv.toFixed(2));
  $('input[name="orden_totneto"]').val(new_total.toFixed(2));

  tbl_prodordcompra
    .rows(tbl_prodordcompra.row(this))
    .remove()
    .draw();

  tbl_data = tbl_prodordcompra
    .rows()
    .data()
    .toArray();

  $('input[name="orden_cantidad"]').val(0);
  $("#btn-add-prodtporden").prop("disabled", true);

  $.Notification.notify(
    "success",
    "bottom-right",
    "Servicio eliminado",
    "El servicio ha sido eliminado de la OS."
  );

  if (tbl_data.length > 0) {
    $("#btn-save-orden").prop("disabled", false);
    //porc_desc = parseFloat($('input[name="orden_porcdscto"]').val()) / 100;
    //val_desc = new_total * porc_desc;
    //$('input[name="orden_valordscto"]').val(val_desc.toFixed(2));
  } else {
    $("#btn-save-orden").prop("disabled", true);
    $('input[name="orden_valordscto"]').val(0);
    $('input[name="orden_porcdscto"]').val(0);
    total_temporal = 0;
  }
});

$('input[name="orden_tipomoneda"]').on("change", function() {
  $('input[name="orden_porcdscto"]').trigger("change");
});

$('input[name="orden_precunit"]').on("change", function() {
  $('input[name="orden_porcdscto"]').trigger("change");
});

$('input[name="orden_cantidad"]').on("change", function() {
  $('input[name="orden_porcdscto"]').trigger("change");
  $("#btn-add-prodtporden").prop("disabled", false);
});

$('input[name="orden_porcdscto"]').on("change", function() {
  precioprod_temp = $('input[name="orden_precunit"]').val();
  cantidad_articulo = $('input[name="orden_cantidad"]').val();
  total_articulo = precioprod_temp * cantidad_articulo;
  porc_desc = parseFloat($(this).val()==""?0:$(this).val()) / 100;
  val_desc = (total_articulo - (total_articulo * porc_desc)).toFixed(2);
  val_igv = (val_desc * porc_igv).toFixed(2);

  $('input[name="orden_valordscto"]').val(val_desc);
  $('input[name="orden_valorigv"]').val(val_igv);
});

/*
$('input[name="orden_porcigv"]').on("change", function() {
  oper_grab = parseFloat($('input[name="orden_totcompra"]').val());
  porc_igv = parseFloat($(this).val()==""?0:$(this).val()) / 100;
  val_igv = (oper_grab * porc_igv).toFixed(2);  
  $('input[name="orden_igv"]').val(val_igv);
  total_igv = oper_grab + parseFloat(val_igv);
  $('input[name="orden_totneto"]').val(total_igv.toFixed(2));
});
*/

function renderServiceOrder(header, lines) {
  serviceProductEpoch++;
  serviceStatus = header.ORDESTADO;
  serviceForm.dataset.documentMode = appuCan("purchases.manage") && ["Pendiente", "Rechazado"].includes(serviceStatus) ? "EDIT" : "VIEW";
  appuLoadPurchaseFields(serviceForm, header);

  $('input[name="orden_nro"]').val(header.ORDNUMBER);
  $('input[name="orden_id"]').val(header.IDORDEN);
  $('select[name="orden_estado"]').val(serviceStatus);
  $('input[name="orden_tcventa"]').val(header.EXCVENTA);
  $('input[name="orden_tccompra"]').val(header.EXCCOMP);

  var $provider = $('select[name="orden_proveedor"]');
  if (!Array.from($provider[0].options).some(option => option.value === String(header.PROVID))) {
    $provider[0].add(new Option('Proveedor ' + header.PROVID, String(header.PROVID)));
  }
  $provider.val(header.PROVID).trigger("change");
  $('input[name="orden_fecemision"]').val(header.FECEMIS);
  $('input[name="orden_fecentrega"]').val(header.FECDEL);
  $('input[name="orden_nrocuenta"]').val(header.ACCNUM);

  var $payment = $('select[name="orden_tipopagotext"]');
  $payment.val(header.PAYDAYS);
  $payment.val($payment.val() ?? 'Otro').trigger("change");
  $('input[name="orden_tipopago"]').val(header.PAYDAYS);

  $('input[name="orden_cotizacion"]').val(header.COTIZ);
  $('input[name="orden_observ"]').val(header.OBSERV);
  $('input[name="orden_solicitante"]').val(header.REQUES);
  $('input[name="orden_autorizador"]').val(header.APROV);
  $('input[name="orden_igv"]').val(Number(header.TOTIGV).toFixed(2));
  $('input[name="orden_totcompra"]').val(Number(header.TOTCOMP).toFixed(2));
  $('input[name="orden_totneto"]').val(Number(header.TOTNET).toFixed(2));
  total_temporal = Number(header.TOTCOMP);

  $('input[name="orden_nombre_serv"],input[name="orden_glosa"],input[name="orden_valordscto"],input[name="orden_precunit"]').val("");
  $('input[name="orden_porcdscto"],input[name="orden_cantidad"]').val(0);
  tbl_prodordcompra.clear();
  tbl_prodordcompra.rows.add(lines.map(line => ({
    0: line.ROW_ID,
    1: line.DESCRIPTION,
    2: line.GLOSS,
    3: Number(line.UNIT_PRICE).toFixed(2),
    4: line.QUANTITY,
    5: Number(line.DISCOUNT_RATE).toFixed(2),
    6: Number(line.DISCOUNTED_TOTAL).toFixed(2)
  }))).draw();

  var editable = serviceCanEdit();
  $(serviceForm).find(":input").not("[type=hidden]").prop("disabled", !editable);
  $('[name="orden_estado"],#btn-add-prodtporden').prop("disabled", true);
  $("#btn-save-orden").prop("disabled", !editable).find("font").text("Actualizar Orden de Servicio");
  $("#col-btn-save-orden").attr("class", "col-md-6");
  $("#col-btn-delete-orden").toggle(appuCan("purchases.manage"));
  $("#btn-delete-orden").prop("disabled", !appuCan("purchases.manage") || serviceIsCancelled());
  serviceForm.dispatchEvent(new Event("appu:document-loaded"));
  appuMarkSaved();
}

function serviceIsCancelled() {
  return ["Anulado", "Anulada", "Cancelado", "Cancelada"].includes(serviceStatus);
}

function loadServiceOrder(id) {
  if (!id || (serviceForm.inert && !serviceLoading)) return;
  var read = ++serviceOpenEpoch;
  serviceLoading = true;
  serviceForm.inert = true;
  serviceForm.setAttribute("aria-busy", "true");
  Swal.fire({
    title: "Cargando orden de servicio",
    allowOutsideClick: false,
    allowEscapeKey: false,
    onBeforeOpen: () => Swal.showLoading()
  });

  function finish() {
    serviceLoading = false;
    serviceForm.inert = false;
    serviceForm.setAttribute("aria-busy", "false");
    Swal.close();
  }
  function failed(xhr) {
    if (read !== serviceOpenEpoch) return;
    finish();
    serviceError(xhr || {}, "No se pudo abrir la orden de servicio. Intenta consultarla de nuevo.");
  }

  $.post("../../modules/ordenes/consultar-orden.php", { TIPO_ORDEN: "SERVICIO", FILTER: id, ESTADO: "ALL" }, function (headers) {
    if (read !== serviceOpenEpoch) return;
    var header = Array.isArray(headers) && headers[0];
    if (!header || String(header.IDORDEN) !== String(id)) return failed();
    $.post("../../modules/ordenes/consultar-detalle-orden.php", { IDORDEN: id }, function (lines) {
      if (read !== serviceOpenEpoch) return;
      if (!Array.isArray(lines)) return failed();
      finish();
      renderServiceOrder(header, lines);
    }, "json").fail(failed);
  }, "json").fail(failed);
}

$("#btn-select-orden").on("click", function () {
  loadServiceOrder($('select[name="orden_listado"]').val());
});

appuBindPurchaseSave(serviceForm, function (data) {
  data.set("orden_tipo", "SERVICIO");
  data.set("orden_lstservicios", JSON.stringify(tbl_prodordcompra.rows().data().toArray()));
}, function () {
  if (tbl_prodordcompra.rows().count()) return true;
  toastr.warning("Agrega al menos un servicio.");
  return false;
});

$("#btn-delete-orden").on("click", function () {
  var id = serviceForm.elements.orden_id.value;
  if (!id || serviceForm.inert || serviceIsCancelled() || !appuCan("purchases.manage")) return;
  serviceForm.inert = true;
  $(".appu-document-toolbar").prop("inert", true);

  function unlock() {
    serviceForm.inert = false;
    serviceForm.setAttribute("aria-busy", "false");
    $(".appu-document-toolbar").prop("inert", false);
  }

  Swal.fire({
    title: "Anular orden de servicio",
    input: "text",
    inputLabel: "Motivo de anulación",
    inputValidator: value => String(value || "").trim().length >= 5 ? undefined : "Indica un motivo de al menos 5 caracteres.",
    showCancelButton: true,
    confirmButtonText: "Anular",
    cancelButtonText: "Volver"
  }).then(function (result) {
    if (typeof result.value !== "string" || result.value.trim().length < 5) return unlock();
    serviceForm.setAttribute("aria-busy", "true");
    $.post("../../modules/ordenes/eliminar-orden.php", { ID_ORDEN: id, reason: result.value.trim() })
      .done(function (response) {
        if (String(response).trim() !== "1") return serviceError({}, "No se pudo confirmar la anulación.");
        serviceStatus = "Anulado";
        serviceForm.dataset.documentMode = "VIEW";
        $('[name="orden_estado"]').val(serviceStatus);
        $(serviceForm).find(":input").not("[type=hidden]").prop("disabled", true);
        appuMarkSaved();
        window.history.replaceState(null, "", appuDocumentRecordUrl(id));
        toastr.success("Orden de servicio anulada.");
      })
      .fail(function (xhr) { serviceError(xhr, "No se pudo anular la orden. Consulta su estado antes de reintentar."); })
      .always(unlock);
  });
});
