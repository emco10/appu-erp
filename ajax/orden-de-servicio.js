$("#col-btn-delete-orden").hide();
var serviceSaving=false,serviceProductEpoch=0,serviceCatalogEpoch=0,serviceProviderEpoch=0,serviceOpenEpoch=0,serviceStatus='Pendiente';
function serviceError(xhr,fallback){var response=xhr.responseJSON||{};toastr.error(response.message||response.error||fallback);Swal.close();}
function serviceCanEdit(){return appuCan('purchases.manage')&&['Pendiente','Rechazado'].indexOf(serviceStatus)!==-1;}
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
    data: JSON.parse(data)
  });
});

$.post("../../modules/proveedores/listar-proveedores.php", function(data) {
  var selected=$('select[name="orden_proveedor"]').val(),label=$('select[name="orden_proveedor"] option:selected').text();
  $('select[name="orden_proveedor"]').empty();
  $('select[name="orden_proveedor"]').select2({
    data: JSON.parse(data)
  });
  if(selected){var $select=$('select[name="orden_proveedor"]');if(!$select.find('option').filter(function(){return String(this.value)===String(selected);}).length)$select.append($('<option>').val(selected).text(label||'Proveedor '+selected));$select.val(selected).trigger('change.select2');}
});

function cargarProductosOrdenServicio(proveedorId) {
  var current=++serviceCatalogEpoch;
  var payload = { ESTADO: 1 }; if (proveedorId) payload.PROV_ID = proveedorId;
  $.post("../../modules/productos/listar-productos-xprov.php", payload, function(data) {
    if(current!==serviceCatalogEpoch)return;
    $('select[name="orden_producto_select"]').empty().select2({ data: JSON.parse(data), language: "es", placeholder: "Buscar por código o descripción" });
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
    var product = JSON.parse(data)[0]; if (!product) return;
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
        var mydata = JSON.parse(data);
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
              var mydata = JSON.parse(data);
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
        var mydata = JSON.parse(data);
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
  "language": {"url": "../../plugins/datatables/Spanish.json"}
});

var total_temporal = 0;

//tbl_prodordcompra.columns([0,10,11,12,13,14]).visible(false);

var tbl_data = "";

$("#btn-add-prodtporden").click(function(e) {

  e.preventDefault();
  if(serviceSaving||!serviceCanEdit())return;
  
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
  if(serviceSaving||!serviceCanEdit())return;
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

$("#btn-select-orden").click(function() {
  if(serviceSaving)return;
  var current=++serviceOpenEpoch;
  DATA_ID = $('select[name="orden_listado"]').val();
  if (DATA_ID != "" && DATA_ID != null) {
    Swal.fire({
      html: "<h4>Cargando datos de orden</h4>",
      allowOutsideClick: false,
      onBeforeOpen: () => {
        Swal.showLoading();
      }
    });
    $.post(
      "../../modules/ordenes/consultar-orden.php",
      { TIPO_ORDEN:"SERVICIO", FILTER: DATA_ID, ESTADO: "ALL" },
      function(data) {
        if(current!==serviceOpenEpoch)return;
        var data_json = JSON.parse(data);
        if(!data_json.length){Swal.close();return toastr.warning('No se encontró la orden seleccionada.');}
        serviceStatus=data_json[0].ORDESTADO;
        $('#FRM_INSERT_DETA_ORDSERV [name]').filter(function(){return this.name.indexOf('custom_field[')===0;}).each(function(){var match=this.name.match(/\[(\d+)\]/),value=match?(data_json[0].CUSTOM_FIELDS||{})[match[1]]:'';if(this.type==='checkbox')$(this).prop('checked',String(value)==='1');else $(this).val(value==null?'':value).trigger('change.select2');});
        $('input[name="orden_nro"]').focus();
        $('input[name="orden_nro"]').val(data_json[0]["ORDNUMBER"]);
        $("#btn-delete-orden").attr("js-id", data_json[0]["IDORDEN"]);
        $('input[name="orden_id"]').val(data_json[0]["IDORDEN"]);
        $('select[name="orden_estado"]').val(data_json[0]["ORDESTADO"]);
        $('input[name="orden_tcventa"]').val(data_json[0]["EXCVENTA"]);
        $('input[name="orden_tccompra"]').val(data_json[0]["EXCCOMP"]);

        var providerId=data_json[0]["PROVID"],$provider=$('select[name="orden_proveedor"]');
        if(!$provider.find('option').filter(function(){return String(this.value)===String(providerId);}).length)$provider.append($('<option>').val(providerId).text('Proveedor '+providerId));
        $provider.val(providerId);
        $('select[name="orden_proveedor"]').trigger("change");

        $('input[name="orden_provruc"]').val();

        $('select[name="orden_tipomoneda"]').val(data_json[0]["TIPOMON"]);

        $('input[name="orden_fecemision"]').val(data_json[0]["FECEMIS"]);
        $('input[name="orden_fecentrega"]').val(data_json[0]["FECDEL"]);
        $('input[name="orden_nrocuenta"]').val(data_json[0]["ACCNUM"]);

        $('select[name="orden_tipopagotext"]').val(data_json[0]["PAYDAYS"] );
        $('select[name="orden_tipopagotext"]').trigger("change");

        if ($('select[name="orden_tipopagotext"]').val() == null){
          $('select[name="orden_tipopagotext"]').val("Otro");
          $('select[name="orden_tipopagotext"]').trigger("change");
          $('#div_diaspago').show();
          $('input[name="orden_tipopago"]').prop("required",true);
          $('input[name="orden_tipopago"]').val(data_json[0]["PAYDAYS"] );
        }
        
        $('input[name="orden_cotizacion"]').val(data_json[0]["COTIZ"]);
        $('input[name="orden_observ"]').val(data_json[0]["OBSERV"]);
        $('input[name="orden_solicitante"]').val(data_json[0]["REQUES"]);
        $('input[name="orden_autorizador"]').val(data_json[0]["APROV"]);

        $('input[name="orden_igv"]').val((data_json[0]["TOTIGV"] * 1).toFixed(2));
        $('input[name="orden_totcompra"]').val((data_json[0]["TOTCOMP"] * 1).toFixed(2));
        $('input[name="orden_totneto"]').val((data_json[0]["TOTNET"] * 1).toFixed(2));

        total_temporal = parseFloat(data_json[0]["TOTCOMP"]);
        codigo_idorden = data_json[0]["IDORDEN"];
        
        $.post(
          "../../modules/ordenes/consultar-detalle-orden.php",
          { IDORDEN: codigo_idorden },
          function(data) {
            if(current!==serviceOpenEpoch)return;

            //$('input[name="orden_codprod"]').val("");
            //$('input[name="orden_valunit"]').val("");
            $('input[name="orden_nombre_serv"]').val("");
            $('input[name="orden_glosa"]').val("");
            $('input[name="orden_porcdscto"]').val(0);
            $('input[name="orden_valordscto"]').val("");
            $('input[name="orden_precunit"]').val("");
            $('input[name="orden_cantidad"]').val(0);

            $("#btn-add-prodtporden").prop("disabled", true);
            $("#btn-save-orden").prop("disabled", !serviceCanEdit());

            tbl_prodordcompra.clear().draw();
            detaorden_json = JSON.parse(data);
            for (i = 0; i < detaorden_json.length; i++) {
              tbl_prodordcompra.rows
                .add([
                  {
                    0: detaorden_json[i]["ROW_ID"],
                    //1: detaorden_json[i]["CODE"],
                    1: detaorden_json[i]["DESCRIPTION"],
                    2: detaorden_json[i]["GLOSS"],
                    //4: detaorden_json[i]["UNIT_VALUE"],
                    3: (detaorden_json[i]["UNIT_PRICE"] * 1).toFixed(2),
                    4: detaorden_json[i]["QUANTITY"],
                    5: (detaorden_json[i]["DISCOUNT_RATE"] * 1).toFixed(2),
                    6: (detaorden_json[i]["DISCOUNTED_TOTAL"] * 1).toFixed(2)
                  }
                ])
                .draw();
            }
            $('#FRM_INSERT_DETA_ORDSERV :input').not('[type=hidden]').prop('disabled',!serviceCanEdit());
            $('[name="orden_estado"]').prop('disabled',true);
            $('#btn-delete-orden').prop('disabled',!appuCan('purchases.manage')||['Anulado','Anulada','Cancelada'].indexOf(serviceStatus)!==-1);
            if(window.appuMarkSaved)window.appuMarkSaved();
          }
        ).fail(function(xhr){if(current===serviceOpenEpoch)serviceError(xhr,'No se pudo recuperar el detalle de la orden.');});

        $("#btn-save-orden font").html("Actualizar Orden de Servicio");
        $("#col-btn-save-orden").attr("class", "col-md-6");
        $("#col-btn-delete-orden").toggle(appuCan('purchases.manage'));
      }
    ).fail(function(xhr){if(current===serviceOpenEpoch)serviceError(xhr,'No se pudo recuperar la orden.');}).always(function() {
      if(current===serviceOpenEpoch)Swal.close();
    });
  }
});

$("#FRM_INSERT_DETA_ORDSERV").submit(function (e) {
  e.preventDefault();
  if(serviceSaving||!serviceCanEdit())return;

  var form = $(this);
  var idform = form.attr("id");
  var url = form.attr('action');
  var formElement = document.getElementById(idform);

  tbl_data = tbl_prodordcompra.rows().data().toArray();
  if(!tbl_data.length)return toastr.warning('Agrega al menos un servicio.');

  var formData_rec = new FormData(formElement);
  formData_rec.append("orden_tipo","SERVICIO");
  formData_rec.append("orden_lstservicios", JSON.stringify(tbl_data));
  var id_ordcompra = $('input[name="orden_id"]').val();
  serviceSaving=true;$('#btn-save-orden,#btn-add-prodtporden,#btn-select-orden').prop('disabled',true);

  $.ajax({
      type: "POST",
      url: url,
      data: formData_rec,
      contentType: false,
      cache: false,
      processData: false,
      beforeSend: function () {
          Swal.fire({
              html: '<h4>Guardando información</h4>',
              allowOutsideClick: false,
              onBeforeOpen: () => {
                  Swal.showLoading();
              }
          })
      },
      success: function (data) {
          if (data == "ERROR") {
            $.Notification.notify("error", "bottom-right", "Error de guardado", 
              "No se pudo guardar datos de orden de servicio");
            Swal.close();
          } else if (data == "OK_INSERT") {
            $('input[name="orden_nro"]').focus();
            form.find("input, textarea, select").val("");

            $('select[name="orden_estado"]').val("Pendiente");
            $('select[name="orden_estado"]').trigger("change");

            $('select[name="orden_proveedor"]').trigger("change");

            $('select[name="orden_tipopagotext"]').trigger("change");
            $('select[name="orden_tipopagotext"]').prop("disabled",false);

            $('#div_diaspago').hide();
            $('input[name="orden_tipopago"]').prop("required",false);

            $.Notification.notify("success", "bottom-right", "Orden de Servicio guardada", "Datos almacenados");
            tbl_prodordcompra.clear().draw();

            Swal.close();
            $("#btn-save-orden").prop("disabled", true);
            $("#btn-add-prodtporden").prop("disabled", true);

            $.post("../../modules/ordenes/listar-ordenes.php", {orden_tipo:"SERVICIO"}, function(data) {
              $('select[name="orden_listado"]').empty();
              $('select[name="orden_listado"]').select2({
                data: JSON.parse(data)
              });
            });
            
          } else if (data == "OK_UPDATE") {
              if (id_ordcompra != "" && id_ordcompra != null) {
                $('input[name="orden_nro"]').focus();
                $('input[name="orden_id"]').val("");
                $("#btn-save-orden font").html("Guardar orden de servicio");
                $("#btn-save-orden").prop("disabled", true);
                $("#btn-add-prodtporden").prop("disabled", true);
                $("#col-btn-save-orden").attr("class", "col-md-12");
                $("#col-btn-delete-orden").hide();
                form.find("input, textarea, select").val("");

                $('select[name="orden_estado"]').val("Pendiente");
                $('select[name="orden_estado"]').trigger("change");

                $('select[name="orden_proveedor"]').trigger("change");

                $('select[name="orden_tipopagotext"]').trigger("change");
                $('select[name="orden_tipopagotext"]').prop("disabled",false);

                $('#div_diaspago').hide();
                $('input[name="orden_tipopago"]').prop("required",false);
                tbl_prodordcompra.clear().draw();
              }
              $.Notification.notify("success", "bottom-right", "Orden de servicio actualizada", 
                "Datos actualizados");
              Swal.close();
          }
      },
      error:function(xhr){serviceError(xhr,'No se pudo guardar la orden. Los datos se conservan.');},
      complete:function(){serviceSaving=false;Swal.close();$('#btn-save-orden').prop('disabled',!serviceCanEdit()||!tbl_prodordcompra.rows().count());$('#btn-select-orden').prop('disabled',!$('[name="orden_listado"]').val());}
  });
});

$("#btn-delete-orden").click(function() {
  if(serviceSaving||!appuCan('purchases.manage'))return;
  element = $(this);
  id_val = element.attr("js-id");
  if (id_val != "" && id_val != null) {
    Swal.fire({
      title: "Anular orden de servicio",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Anular",
      cancelButtonText: "Cancelar"
    }).then(result => {
      if (result.value) {
        var cancellationReason = window.prompt("Motivo de anulacion de la orden (obligatorio):", "");
        if (cancellationReason === null || cancellationReason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); return; }
        $.post(
          "../../modules/ordenes/eliminar-orden.php",
          { ID_ORDEN: id_val, reason: cancellationReason.trim() },
          function(data) {
            if (data == true) {
              $('input[name="orden_nro"]').focus();
              tbl_prodordcompra.clear().draw();
              $("#FRM_INSERT_DETA_ORDSERV")
                .find("input, textarea, select")
                .val("");

              $('select[name="orden_proveedor"]').trigger("change");     
              
              $.post("../../modules/ordenes/listar-ordenes.php", {orden_tipo:"SERVICIO"}, function(data) {
                $('select[name="orden_listado"]').empty();
                $('select[name="orden_listado"]').select2({
                  data: JSON.parse(data)
                });
              });

              $('input[name="orden_id"]').val("");
              $("#btn-add-prodtporden").prop("disabled", false);
              $("#btn-save-orden").prop("disabled", true);
              $("#btn-save-orden font").html("Guardar Orden de Servicio");
              $("#col-btn-save-orden").attr("class", "col-md-12");
              $("#col-btn-delete-orden").hide();

              $('select[name="orden_tipopagotext"]').prop("disabled",false);
              $('#div_diaspago').hide();
              $('input[name="orden_tipopago"]').prop("required",false);

              $.Notification.notify(
                "success",
                "bottom-right",
                "Orden de Servicio Eliminada",
                "Información eliminada correctamente."
              );
            }
          }
        );
      }
    });
  }
});

$(document).ready(function() {
  var cookie_idorden = leer_cookie('COOKIE_ID_ORDEN');
  if (cookie_idorden != "") {
    setTimeout(function(){
      $('select[name="orden_listado"]').val(cookie_idorden);
      $('select[name="orden_listado"]').trigger("change");
      $('#btn-select-orden').trigger("click");
      eliminar_cookie("COOKIE_ID_ORDEN");
    },500);
  }
});
