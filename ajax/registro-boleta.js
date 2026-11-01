$("#col-btn-anular-factura").hide();
function appuAplicarClienteBoleta(c) { if (!c) return; $('input[name="facturacion_cliente"]').val(c.client_id || ''); $('input[name="facturacion_cliruc"]').val(c.ruc || ''); $('input[name="facturacion_valcliente"]').val(c.business_name || c.trade_name || ''); $('input[name="facturacion_clidirecc"]').val(c.address || ''); $('input[name="facturacion_cliemail"], input[name="facturacion_email"]').val(c.email || ''); $('input[name="facturacion_clitelefono"], input[name="facturacion_telefono"]').val(c.phone || c.cellphone || ''); $('input[name="facturacion_clicelular"], input[name="facturacion_celular"]').val(c.cellphone || ''); }
$.getJSON("../../modules/facturacion/series.php", { document_type: "RECEIPT" }).done(function (data) { var $select = $('select[name="facturacion_series"]'); $select.empty(); (data.series || []).forEach(function (series) { $select.append($('<option>').val(series.series).text(series.series)); }); $select.trigger("change"); });
$.getJSON("../../modules/inventory/warehouses.php").done(function (data) { var $select = $('select[name="facturacion_almacen_id"]'); var selectedWarehouse = $select.data("pending-warehouse") || $select.val() || '1'; $select.empty(); (data.warehouses || []).filter(function (warehouse) { return Number(warehouse.active_status) === 1; }).forEach(function (warehouse) { $select.append($('<option>').val(warehouse.id).text(warehouse.code + ' - ' + warehouse.name)); }); $select.val(String(selectedWarehouse)); });
function appuCargarClientePorRucBoleta() { var $ruc = $('input[name="facturacion_cliruc"]'); var ruc = ($ruc.val() || '').replace(/\D/g, ''); if (ruc.length !== 11) return; $.getJSON('../../modules/clientes/obtener-clientes.php', { ruc: ruc }).done(function (response) { if (!response.found || !response.customer) { toastr.warning('No se encontró un cliente con ese RUC.'); return; } var c = response.customer; $('input[name="facturacion_cliente"]').val(c.client_id || ''); $('input[name="facturacion_valcliente"]').val(c.business_name || ''); $('input[name="facturacion_clidirecc"]').val(c.address || ''); }); }
$('input[name="facturacion_cliruc"]').on('blur change', appuCargarClientePorRucBoleta).on('keyup', function () { if ($(this).val().replace(/\D/g, '').length === 11) appuCargarClientePorRucBoleta(); });
function appuCargarClientePorRucBoleta() { var ruc = ($('input[name="facturacion_cliruc"]').val() || '').replace(/\D/g, ''); if (ruc.length !== 11) return; $.getJSON('../../modules/clientes/obtener-clientes.php', { ruc: ruc }).done(function (response) { if (!response.found || !response.customer) { toastr.warning('No se encontro un cliente con ese RUC.'); return; } appuAplicarClienteBoleta(response.customer); }).fail(function () { toastr.error('No se pudo consultar el maestro de clientes.'); }); }
$('input[name="facturacion_cliruc"]').off('blur change keyup input').on('blur change', appuCargarClientePorRucBoleta).on('keyup input', function () { if ($(this).val().replace(/\D/g, '').length === 11) appuCargarClientePorRucBoleta(); });
$("#col-btn-pendiente-factura").hide();
$("#col-btn-cancelar-factura").hide();

$("#btn-save-facturaprod").prop("disabled", true);
$("#btn-add-prodtofactura").prop("disabled", true);
$("#btn-select-cotizacion").prop("disabled", true);
$("#btn-select-orden-venta").prop("disabled", true);
$("#btn-select-factura").prop("disabled", true);
$('input[name="facturacion_prodcant"]').prop("disabled", true);
$('#div_diaspago').hide();
$('input[name="facturacion_formpago"]').prop("required",false);

$(document).ready(function(){
  $("#m_registro_boleta").attr("class","nav-link active");
  $("#m_boleta").attr("class","nav-link active");
  $("#m_boleta").parent().attr("class","nav-item has-treeview menu-open");
  $(document).prop('title', 'Registro de Boleta - APPU');
});

$('select[name="facturacion_formpagotext"]').on("change", function() {
  valtipo = $(this).val();
  cotiztipopago = $('input[name="facturacion_formpago"]');
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

$.post("../../modules/facturacion/listar-boletas.php", function(data) {
  $('select[name="facturas_listado"]').empty();
  $('select[name="facturas_listado"]').select2({
    data: JSON.parse(data)
  });
  appuOpenRequestedRecord('select[name="facturas_listado"]', '#btn-select-factura');
});

$('select[name="facturas_listado"]').on("change", function() {
  val_lstfacs = $(this).val();
  if (val_lstfacs != "" && val_lstfacs != null) {
    $("#btn-select-factura").prop("disabled", false);
  } else {
    $("#btn-select-factura").prop("disabled", true);
  }
});

$('input[name="facturacion_valcliente"]').autocomplete({
  source: function(request, response) {
    $.getJSON("../../modules/clientes/obtener-clientes.php", { cotiz_nomcliente: $('input[name="facturacion_valcliente"]').val() }, response);
  },
  select: function (event, ui) {
    $(this).val(ui.item.label);
    $('input[name="facturacion_cliruc"]').val("");
    $('input[name="facturacion_clidirecc"]').val("");
    $('input[name="facturacion_clirefer"]').val("");
    if (ui.item.id != "" && ui.item.id != null) {
      $.post(
        "../../modules/clientes/consultar-cliente.php",
        { FILTER: ui.item.id },
        function(data) {
          var mydata = JSON.parse(data);
          appuAplicarClienteBoleta({client_id: mydata[0]["CODIGO"], ruc: mydata[0]["RUC"], business_name: mydata[0]["RAZ_SOC"], trade_name: mydata[0]["NOM_COM"], address: mydata[0]["DIRECC"], email: mydata[0]["EMAIL"], phone: mydata[0]["TELEF"], cellphone: mydata[0]["CELULAR"]});
          //$('input[name="facturacion_clirefer"]').val("No registrada");
        }
      );
    }
  }
});

buscarCorrelativo();

$.post("../../modules/usuarios/listar-usuarios-xtipo.php", function(data) {
  mydata = JSON.parse(data);
  data_users = mydata[0];
  user_id = mydata[1];
  user_job = mydata[2];

  $('select[name="facturacion_usuario"]').empty();
  $('select[name="facturacion_usuario"]').select2({
    data: data_users
  });

  $('select[name="facturacion_usuario"]').val(user_id);
  $('select[name="facturacion_usuario"]').trigger("change");
  $('input[name="facturacion_usuarioid"]').val(user_id);
  
  if(user_job != "Secretaria" && user_job != "Secretario"){
    $('select[name="facturacion_usuario"]').prop("disabled",true);
  }

});

$('select[name="facturacion_usuario"]').on("change", function(){
  $('input[name="facturacion_usuarioid"]').val($(this).val());
});

$.post("../../modules/cotizaciones/listar-cotizaciones.php", function(data) {
  $('select[name="facturacion_listadocotiz"]').empty();
  $('select[name="facturacion_listadocotiz"]').select2({
    data: JSON.parse(data)
  });
});

$.getJSON("../../modules/ventas/ordenes.php", function(response) {
  var orders = [{ id: "", text: "Seleccione una orden confirmada" }];
  (response.orders || []).forEach(function(order) {
    if (order.status === "Confirmada") {
      orders.push({ id: order.id, text: order.number + " - " + order.customer_name });
    }
  });
  $('select[name="facturacion_listado_orden_venta"]').empty().select2({ data: orders });
});

$('select[name="facturacion_listado_orden_venta"]').on("change", function() {
  $("#btn-select-orden-venta").prop("disabled", !$(this).val());
});

$.post(
  "../../modules/productos/listar-productos-xprov.php",
  { ESTADO: 1 },
  function(data) {
    $('select[name="facturacion_producto"]').empty();
    $('select[name="facturacion_producto"]').select2({
      data: JSON.parse(data)
    });
  }
);

$('select[name="facturacion_producto"]').on("change", function() {
  DATA_ID = $(this).val();
  $('input[name="facturacion_prodcant"]').val(0);
  $('input[name="facturacion_nameprod"]').val("");
  $('input[name="facturacion_proddesc"]').val("");
  $('input[name="facturacion_prodprecio"]').val("");
  $('input[name="facturacion_stockprod"]').val("");
  $('input[name="facturacion_codeprod"]').val("");
  if (DATA_ID != "" && DATA_ID != null) {
    $('input[name="facturacion_prodcant"]').prop("disabled", false);
    $.post(
      "../../modules/productos/consultar-productos.php",
      { FILTER: DATA_ID, ESTADO: "1", warehouse_id: $('select[name="facturacion_almacen_id"]').val() || 1 },
      function(data) {
        var mydata = JSON.parse(data);
        stock_producto = parseInt(mydata[0]["CANTIDAD"]);
        $('input[name="facturacion_codeprod"]').val(mydata[0]["CODPROD"]);
        $('input[name="facturacion_nameprod"]').val(mydata[0]["NOMBRE"]);
        $('input[name="facturacion_proddesc"]').val(mydata[0]["DESCRIPTION"]);
        $('input[name="facturacion_prodprecio"]').val(mydata[0]["PRECIO"]);
        $('input[name="facturacion_stockprod"]').val(mydata[0]["CANTIDAD"]);
        selected_tax_affectation = String(mydata[0]["TAX_CODE"] || "10");
        if (stock_producto <= 0) {
          $.Notification.notify(
            "error",
            "bottom-right",
            "Stock agotado",
            "Producto seleccionado no cuenta con existencias"
          );
          $("#btn-add-prodtofactura").prop("disabled", true);
        }
      }
    );
  } else {
    $('input[name="facturacion_prodcant"]').prop("disabled", true);
  }
});

 $('select[name="facturacion_almacen_id"]').on("change", function () { if ($('select[name="facturacion_producto"]').val()) $('select[name="facturacion_producto"]').trigger("change"); });
 $('input[name="facturacion_prodcant"]').on("change", function() {
  cant_prod = parseInt($(this).val());
  stock_prod = parseInt($('input[name="facturacion_stockprod"]').val());
  select_prod = $('select[name="facturacion_producto"]').val();

  if (cant_prod <= stock_prod) {
    tbl_data = tbl_prodfactura
      .rows()
      .data()
      .toArray();

    var cantidad_final = 0;
    cantidad_final += cant_prod;

    if (tbl_data.length > 0) {
      for (i = 0; i < tbl_data.length; i++) {
        id_prod = tbl_data[i][0];
        cant_agreg = parseInt(tbl_data[i][5]);
        if (select_prod == id_prod) {
          cantidad_final += cant_agreg;
        }
      }
      //console.log(cantidad_final);
      if (cantidad_final > stock_prod) {
        $("#btn-add-prodtofactura").prop("disabled", true);
        $.Notification.notify(
          "error",
          "bottom-right",
          "Stock insuficiente",
          "Producto no cuenta con stock suficiente"
        );
      } else if (cantidad_final <= stock_prod) {
        $("#btn-add-prodtofactura").prop("disabled", false);
      }
    } else {
      $("#btn-add-prodtofactura").prop("disabled", false);
    }
  } else if (cant_prod > stock_prod) {
    $("#btn-add-prodtofactura").prop("disabled", true);
    $.Notification.notify(
      "error",
      "bottom-right",
      "Stock insuficiente",
      "Producto no cuenta con stock suficiente"
    );
  }
});

var tbl_prodfactura = $("#table-productsfactura").DataTable({
  "language": {"url": "../../plugins/datatables/Spanish.json"}
});

var total_temporal = 0;
tbl_prodfactura.columns([0]).visible(false);

var tbl_data = "";
var selected_tax_affectation = "10";
function appuIgvFilas(rows) { return rows.reduce(function(total, row) { return total + (String(row[7] || "10") === "10" ? Number(row[6] || 0) * 0.18 : 0); }, 0); }

$("#btn-add-prodtofactura").click(function() {
  idprod = $('select[name="facturacion_producto"]').val();
  cod_prod = $('input[name="facturacion_codeprod"]').val();
  producto = $('input[name="facturacion_nameprod"]').val();
  descripcion = $('input[name="facturacion_proddesc"]').val();
  precio = parseFloat($('input[name="facturacion_prodprecio"]').val());
  cantidad = parseInt($('input[name="facturacion_prodcant"]').val());
  importe = precio * cantidad;
  var importe_actual = importe;

  if (idprod != "" && cantidad != "" && cantidad > 0) {
    $("#btn-add-prodtofactura").prop("disabled", true);
    tbl_prodfactura
      .rows(function(idx, data, node) {
        old_importe = data[6];
        old_cantidad = parseInt(data[5]);
        if (data[2] === producto) {
          importe += old_importe;
          cantidad += old_cantidad;
        }
        return data[2] === producto;
      })
      .remove()
      .draw();

    tbl_prodfactura.rows
      .add([
        {
          0: idprod,
          1: cod_prod,
          2: producto,
          3: descripcion,
          4: precio.toFixed(2),
          5: cantidad,
          6: importe.toFixed(2),
          7: selected_tax_affectation
        }
      ])
      .draw();

    tbl_data = tbl_prodfactura
      .rows()
      .data()
      .toArray();

    opergrab =
      $('input[name="facturacion_opergrab"]').val() != ""
        ? $('input[name="facturacion_opergrab"]').val()
        : 0;
    importe_totactual = parseFloat(opergrab);
    importe_totactual += importe_actual;
    new_igv = appuIgvFilas(tbl_data);
    new_total = importe_totactual + new_igv;

    total_temporal = new_total;

    $('input[name="facturacion_opergrab"]').val(importe_totactual.toFixed(2));
    $('input[name="facturacion_igv"]').val(new_igv.toFixed(2));
    $('input[name="facturacion_total"]').val(new_total.toFixed(2));

    $('input[name="facturacion_prodcant"]').val(0);
    $('input[name="facturacion_prodprecio"]').val(0.00);

    $.Notification.notify(
      "success",
      "bottom-right",
      "Producto añadido",
      "El producto ha sido agregado a la boleta correctamente"
    );

    if (tbl_data.length > 0) {
      $("#btn-save-facturaprod").prop("disabled", false);
      porc_desc = parseFloat($('input[name="facturacion_porcdesc"]').val()) / 100;
      val_desc = new_total * porc_desc;
      $('input[name="facturacion_cantdesc"]').val(val_desc.toFixed(3));
    } else {
      $('input[name="facturacion_cantdesc"]').val(0);
      $('input[name="facturacion_porcdesc"]').val(0);
      $("#btn-save-facturaprod").prop("disabled", true);
      total_temporal = 0;
    }
  } else {
    $('select[name="facturacion_producto"]').focus();
    $.Notification.notify(
      "error",
      "bottom-right",
      "Error al añadir",
      "Seleccione un producto de la lista"
    );
  }
});

$('select[name="facturacion_listadocotiz"]').on("change", function() {
  val_lstcotiz = $(this).val();
  if (val_lstcotiz != "" && val_lstcotiz != null) {
    $("#btn-select-cotizacion").prop("disabled", false);
  } else {
    $("#btn-select-cotizacion").prop("disabled", true);
  }
});

$("#btn-select-factura").click(function() {
  DATA_ID = $('select[name="facturas_listado"]').val();
  if (DATA_ID != "" && DATA_ID != null) {

    $('input[name="id_factura"]').val("");
    $('select[name="facturacion_estado"]').val("1");

    Swal.fire({
      html: "<h4>Cargando datos de boleta</h4>",
      allowOutsideClick: false,
      onBeforeOpen: () => {
        Swal.showLoading();
      }
    });

    $.post(
      "../../modules/facturacion/consultar-boleta.php",
      { FILTER: DATA_ID, ESTADO:"ALL" },
      function(data) {
        var data_json = JSON.parse(data);

        if(data_json.length > 0){

          id_factura = data_json[0]["CODIGOID"];
          est_factura = data_json[0]["ESTADO"];

          $("#col-btn-save-facturaprod").hide("fast");

          if(est_factura == 1) //VIGENTE
          {
            $("#btn-anular-factura").prop("disabled",false);
            $("#btn-pendiente-factura").prop("disabled",false);
            $("#btn-cancelar-factura").prop("disabled",false);
          }
          else if (est_factura == 2) //ANULADA
          {
            $("#btn-anular-factura").prop("disabled",true);
            $("#btn-pendiente-factura").prop("disabled",false);
            $("#btn-cancelar-factura").prop("disabled",false);
          }
          else if (est_factura == 3) //PENDIENTE DE PAGO
          {
            $("#btn-anular-factura").prop("disabled",false);
            $("#btn-pendiente-factura").prop("disabled",true);
            $("#btn-cancelar-factura").prop("disabled",false);
          }
          else if (est_factura == 4) //CANCELADA
          {
            $("#btn-anular-factura").prop("disabled",false);
            $("#btn-pendiente-factura").prop("disabled",false);
            $("#btn-cancelar-factura").prop("disabled",true);
          }

          $('select[name="facturacion_estado"]').prop("disabled",true);

          $('input[name="id_factura"]').val(id_factura);

          $("#btn-anular-factura").attr("js-id",id_factura);
          $("#btn-pendiente-factura").attr("js-id",id_factura);
          $("#btn-cancelar-factura").attr("js-id",id_factura);

          $('select[name="facturacion_series"]').val(data_json[0]["SERIE"]);
          $('select[name="facturacion_series"]').trigger("change");
          $('select[name="facturacion_series"]').prop("disabled",true);

          $('input[name="facturacion_nro"]').val(data_json[0]["CODIGO_CORRELATIVO"]);
          $('input[name="facturacion_nro"]').prop("disabled",true);

          $('select[name="facturacion_estado"]').val(est_factura);
          $('input[name="facturacion_valcliente"]').focus();
          $('input[name="facturacion_fecha"]').val(data_json[0]["FECREG"]);
          $('select[name="facturacion_usuario"]').val(data_json[0]["USER_ID"]);
          $('select[name="facturacion_usuario"]').trigger("change");
          $('input[name="facturacion_usuarioid"]').val(data_json[0]["USER_ID"]);

          $('input[name="facturacion_cliente"]').val(data_json[0]["CLIENTID"]);
          $('input[name="facturacion_valcliente"]').val(data_json[0]["CLIENTNAME"]);
          
          $('input[name="facturacion_cliruc"]').val(data_json[0]["CLIENTRUC"]);
          $('input[name="facturacion_clidirecc"]').val(data_json[0]["CLIENTADDR"]);
          $('input[name="facturacion_clirefer"]').val(data_json[0]["CLIENTREFER"]);

          $('select[name="facturacion_formpagotext"]').val(data_json[0]["PAY_DAYS"]);
          $('select[name="facturacion_formpagotext"]').trigger("change");

          if ($('select[name="facturacion_formpagotext"]').val() == null) {
            $('select[name="facturacion_formpagotext"]').val("Otro");
            $('select[name="facturacion_formpagotext"]').trigger("change");
            $('#div_diaspago').show();
            $('input[name="facturacion_formpago"]').prop("required",true);
            $('input[name="facturacion_formpago"]').val(data_json[0]["PAY_DAYS"] );
          }

          $('input[name="facturacion_fecentrega"]').val(data_json[0]["DELIV_DATE"]);
          $('select[name="facturacion_almacen_id"]').data("pending-warehouse", data_json[0]["WAREHOUSE_ID"] || 1).val(data_json[0]["WAREHOUSE_ID"] || 1);

          $('select[name="facturacion_tipmon"]').val(data_json[0]["CURRENCY"]);
          $('select[name="facturacion_tipmon"]').trigger("change");

          $('input[name="facturacion_porcdesc"]').val(data_json[0]["DESC_RATE"]);
          $('input[name="facturacion_cantdesc"]').val(data_json[0]["DESC_VAL"]);
          $('input[name="facturacion_opergrab"]').val(parseFloat(data_json[0]["TOTAL_SUB"]).toFixed(2));
          $('input[name="facturacion_igv"]').val(parseFloat(data_json[0]["TOTAL_TAX"]).toFixed(2));
          $('input[name="facturacion_total"]').val(parseFloat(data_json[0]["TOTAL_NET"]).toFixed(2));

          total_temporal = data_json[0]["TOTAL_NET"];
          codigo_idfac = data_json[0]["CODIGOID"];

          $.post(
            "../../modules/facturacion/consultar-detalle-boleta.php",
            { FAC_ID: codigo_idfac },
            function(data) {
              $('select[name="facturacion_producto"]').val("");
              $('select[name="facturacion_producto"]').trigger("change");
              $('input[name="facturacion_proddesc"]').val("");
              $('input[name="facturacion_prodprecio"]').val("");
              $('input[name="facturacion_prodcant"]').val(0);
              $("#btn-add-prodtofactura").prop("disabled", true);
              //$("#btn-save-facturaprod").prop("disabled", false);
  
              tbl_prodfactura.clear().draw();
              detafact_prods = JSON.parse(data);
              for (i = 0; i < detafact_prods.length; i++) {
                var precio = parseFloat(detafact_prods[i]["PRECIOUNIT"]).toFixed(2);
                var importe = parseFloat(detafact_prods[i]["IMPORTE"]).toFixed(2);

                tbl_prodfactura.rows
                  .add([
                    {
                      0: detafact_prods[i]["IDPROD"],
                      1: detafact_prods[i]["CODPROD"],
                      2: detafact_prods[i]["NOMBRE"],
                      3: detafact_prods[i]["DESCRIP"],
                      4: precio,
                      5: detafact_prods[i]["CANTIDAD"],
                      6: importe
                    }
                  ])
                  .draw();
              }
            }
          ).then(function() {
            Swal.close();
          });
        }
        $("#col-btn-save-facturaprod").hide("fast");
        $("#col-btn-anular-factura").show("fast");        
        $("#col-btn-pendiente-factura").show("fast");        
        $("#col-btn-cancelar-factura").show("fast");
      }
    );

  }
});

$("#btn-select-cotizacion").click(function() {

  var DATA_ID = $('select[name="facturacion_listadocotiz"]').val();
  
  if (DATA_ID != "" && DATA_ID != null) {

    $('input[name="id_factura"]').val("");
    $('select[name="facturacion_estado"]').val("1");

    Swal.fire({
      html: "<h4>Cargando datos de boleta</h4>",
      allowOutsideClick: false,
      onBeforeOpen: () => {
        Swal.showLoading();
      }
    });

    buscarCorrelativo();

    $.post("../../modules/cotizaciones/consultar-cotizacion.php",
      { FILTER: DATA_ID, ESTADO: "ALL" }, function(data) {

        var data_json = JSON.parse(data);
        $('input[name="facturacion_valcliente"]').focus();
        $('input[name="facturacion_fecha"]').val(data_json[0]["FECREG"]);
        $('select[name="facturacion_usuario"]').val(data_json[0]["USER_ID"]);
        $('select[name="facturacion_usuario"]').trigger("change");
        $('input[name="facturacion_usuarioid"]').val(data_json[0]["USER_ID"]);

        $('input[name="facturacion_cliente"]').val(data_json[0]["CLIENTID"]);
        $('input[name="facturacion_valcliente"]').val(data_json[0]["CLIENTNAME"]);
        
        $('input[name="facturacion_cliruc"]').val(data_json[0]["CLIENTRUC"]);
        $('input[name="facturacion_clidirecc"]').val(data_json[0]["CLIENTADDR"]);
        $('input[name="facturacion_clirefer"]').val(data_json[0]["CLIENTREFER"]);

        $('select[name="facturacion_formpagotext"]').val(data_json[0]["PAY_DAYS"]);
        $('select[name="facturacion_formpagotext"]').trigger("change");

        if ($('select[name="facturacion_formpagotext"]').val() == null) {
          $('select[name="facturacion_formpagotext"]').val("Otro");
          $('select[name="facturacion_formpagotext"]').trigger("change");
          $('#div_diaspago').show();
          $('input[name="facturacion_formpago"]').prop("required",true);
          $('input[name="facturacion_formpago"]').val(data_json[0]["PAY_DAYS"] );
        }

        $('input[name="facturacion_fecentrega"]').val(data_json[0]["DELIV_DATE"]);

        $('select[name="facturacion_tipmon"]').val(data_json[0]["CURRENCY"]);
        $('select[name="facturacion_tipmon"]').trigger("change");

        $('input[name="facturacion_porcdesc"]').val(data_json[0]["DESC_RATE"]);
        $('input[name="facturacion_cantdesc"]').val(data_json[0]["DESC_VAL"]);
        $('input[name="facturacion_opergrab"]').val(parseFloat(data_json[0]["TOTAL_SUB"]).toFixed(2));
        $('input[name="facturacion_igv"]').val(parseFloat(data_json[0]["TOTAL_TAX"]).toFixed(2));
        $('input[name="facturacion_total"]').val(parseFloat(data_json[0]["TOTAL_NET"]).toFixed(2));

        total_temporal = data_json[0]["TOTAL_NET"];
        codigo_idcotiz = data_json[0]["CODIGOID"];

        $.post("../../modules/cotizaciones/consultar-detalle-cotizacion.php",
          { IDCOTIZ: codigo_idcotiz }, function(data) {
            $('select[name="facturacion_producto"]').val("");
            $('select[name="facturacion_producto"]').trigger("change");
            $('input[name="facturacion_proddesc"]').val("");
            $('input[name="facturacion_prodprecio"]').val("");
            $('input[name="facturacion_prodcant"]').val(0);
            $("#btn-add-prodtofactura").prop("disabled", true);
            $("#btn-save-facturaprod").prop("disabled", false);

            tbl_prodfactura.clear().draw();
            detacotiz_json = JSON.parse(data);
            for (i = 0; i < detacotiz_json.length; i++) {
              var precio = parseFloat(detacotiz_json[i]["PRECIOUNIT"]).toFixed(2);
              var importe = parseFloat(detacotiz_json[i]["IMPORTE"]).toFixed(2);

              tbl_prodfactura.rows
                .add([
                  {
                    0: detacotiz_json[i]["IDPROD"],
                    1: detacotiz_json[i]["CODPROD"],
                    2: detacotiz_json[i]["NOMBRE"],
                    3: detacotiz_json[i]["DESCRIP"],
                    4: precio,
                    5: detacotiz_json[i]["CANTIDAD"],
                    6: importe
                  }
                ])
                .draw();
            }
          }
        );
      }
    ).then(function() {
      Swal.close();
    });
  }

});

$("#btn-select-orden-venta").click(function() {
  var orderId = $('select[name="facturacion_listado_orden_venta"]').val();
  if (!orderId) return;

  buscarCorrelativo();
  $.getJSON("../../modules/ventas/ordenes.php", { order_id: orderId }, function(response) {
    var order = response.order;
    $('select[name="facturacion_almacen_id"]').data("pending-warehouse", order.warehouse_id || 1).val(order.warehouse_id || 1).trigger("change");
    $('input[name="facturacion_orden_venta"]').val(order.id);
    $('select[name="facturacion_listadocotiz"]').val("").trigger("change");
    $('select[name="facturacion_estado"]').val("1");
    $('input[name="facturacion_fecha"]').val(order.issue_date);
    $('select[name="facturacion_usuario"]').val(order.seller_id).trigger("change");
    $('input[name="facturacion_usuarioid"]').val(order.seller_id);
    $('input[name="facturacion_cliente"]').val(order.customer_id);
    $('input[name="facturacion_valcliente"]').val(order.customer_name);
    $('input[name="facturacion_cliruc"]').val(order.ruc);
    $('input[name="facturacion_clidirecc"]').val(order.address);
    $('input[name="facturacion_clirefer"]').val(order.reference);
    $('select[name="facturacion_formpagotext"]').val(String(order.payment_days)).trigger("change");
    $('input[name="facturacion_formpago"]').val(order.payment_days);
    $('input[name="facturacion_fecentrega"]').val(order.delivery_date);
    $('select[name="facturacion_tipmon"]').val(order.currency).trigger("change");
    $('input[name="facturacion_porcdesc"]').val(order.discount_rate);
    $('input[name="facturacion_cantdesc"]').val(order.discount_value);
    $('input[name="facturacion_opergrab"]').val(parseFloat(order.total_sub).toFixed(2));
    $('input[name="facturacion_igv"]').val(parseFloat(order.total_tax).toFixed(2));
    $('input[name="facturacion_total"]').val(parseFloat(order.total_net).toFixed(2));
    total_temporal = order.total_net;
    tbl_prodfactura.clear();
    (response.details || []).forEach(function(detail) {
      tbl_prodfactura.rows.add([{ 0: detail.item_id, 1: detail.item_code, 2: detail.item_name, 3: detail.item_description, 4: parseFloat(detail.item_unit_price).toFixed(2), 5: detail.item_quantity, 6: parseFloat(detail.item_total).toFixed(2), 7: detail.tax_affectation_code || "10" }]);
    });
    tbl_prodfactura.draw();
    $("#btn-save-facturaprod").prop("disabled", false);
  });
});

$("#table-productsfactura").on("dblclick", "tr", function() {
  var data_row = tbl_prodfactura.row(this).data();
  var row_id = data_row[0];
  var importe_prod = data_row[6];

  opergrab =
    $('input[name="facturacion_opergrab"]').val() != ""
      ? $('input[name="facturacion_opergrab"]').val()
      : 0;
  importe_totactual = parseFloat(opergrab);
  importe_totactual -= importe_prod;
  new_igv = Math.max(0, Number($('input[name="facturacion_igv"]').val() || 0) - (String(data_row[7] || "10") === "10" ? Number(importe_prod) * 0.18 : 0));
  new_total = importe_totactual + new_igv;

  total_temporal = new_total;

  $('input[name="facturacion_opergrab"]').val(importe_totactual.toFixed(2));
  $('input[name="facturacion_igv"]').val(new_igv.toFixed(2));
  $('input[name="facturacion_total"]').val(new_total.toFixed(2));

  tbl_prodfactura
    .rows(tbl_prodfactura.row(this))
    .remove()
    .draw();

  tbl_data = tbl_prodfactura
    .rows()
    .data()
    .toArray();

  $('input[name="facturacion_prodcant"]').val(0);
  $("#btn-add-prodtofactura").prop("disabled", true);

  $.Notification.notify(
    "success",
    "bottom-right",
    "Producto eliminado",
    "El producto ha sido eliminado correctamente"
  );

  if (tbl_data.length > 0) {
    //$("#btn-save-facturaprod").prop("disabled", false);
    porc_desc = parseFloat($('input[name="facturacion_porcdesc"]').val()) / 100;
    val_desc = new_total * porc_desc;
    $('input[name="facturacion_cantdesc"]').val(val_desc.toFixed(3));
  } else {
    $("#btn-save-facturaprod").prop("disabled", true);
    $('input[name="facturacion_cantdesc"]').val(0);
    $('input[name="facturacion_porcdesc"]').val(0);
    total_temporal = 0;
  }
});

$('input[name="facturacion_porcdesc"]').on("change", function() {
  num_desc = parseFloat($(this).val());
  if (isNaN(num_desc)) num_desc=0;

  porc_desc = num_desc / 100;
  total_actual = parseFloat($('input[name="facturacion_total"]').val());
  val_desc = total_actual * porc_desc;
  $('input[name="facturacion_cantdesc"]').val(val_desc.toFixed(3));

  total_desc = total_temporal - val_desc;
  $('input[name="facturacion_total"]').val(total_desc.toFixed(2));
});

$("#FRM_INSERT_FACTURA").submit(function(e) {
  e.preventDefault();
  var form = $(this);
  var idform = form.attr("id");
  var url = form.attr("action");
  tbl_data = tbl_prodfactura.rows().data().toArray();
  var formElement = document.getElementById(idform);
  var formData_rec = new FormData(formElement);
  formData_rec.append("facturacion_prods", JSON.stringify(tbl_data));
  $.ajax({
    type: "POST",
    url: url,
    data: formData_rec,
    contentType: false,
    cache: false,
    processData: false,
    error: function(xhr) { Swal.close(); var response=xhr.responseJSON || {}; toastr.error(response.message || response.error || 'No se pudo guardar. Revisa los datos e inténtalo nuevamente.'); },
    beforeSend: function() {
      Swal.fire({
        html: "<h4>Guardando boleta</h4>",
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
          "No se pudo guardar la boleta"
        );
        Swal.close();

      } else if (data == "OK_INSERT") {
        $('input[name="facturacion_valcliente"]').val("");
        $('input[name="facturacion_fecha"]').focus();
        $.Notification.notify(
          "success",
          "bottom-right",
          "Boleta guardada",
          "Datos almacenados"
        );

        postCambioEstado();

        $.post("../../modules/facturacion/listar-boletas.php", function(data) {
        $('select[name="facturas_listado"]').empty();
        $('select[name="facturas_listado"]').select2({
          data: JSON.parse(data)
          });
        });

        Swal.close();
      }
    }
  });
});

$("#btn-nuevafac").click(function (e) {
    e.preventDefault();
    location.reload();
});

$("#btn-anular-factura").click(function() {
  element = $(this);
  id_val = element.attr("js-id");
  if (id_val != "" && id_val != null) {
    Swal.fire({
      title: "¿Está seguro de ANULAR esta boleta?",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Anular",
      cancelButtonText: "Cancelar"
    }).then(result => {
      if (result.value) {
        $.post(
          "../../modules/facturacion/cambiar-estado-doc.php",
          { TIPO_DOC: 'RECEIPT', ID_DOC: id_val, ESTADO_DOC : 2, COMENTARIO: window.prompt("Motivo de anulacion de la boleta (obligatorio):", "") || ""},
          function(data) {
            if (data == true) {
              postCambioEstado();
              
              $.Notification.notify(
                "success",
                "bottom-right",
                "Boleta Anulada",
                "La boleta fue ANULADA con éxito"
              );
            }else{
              $.Notification.notify(
                "error",
                "bottom-right",
                "Error",
                "La boleta no pudo ser ANULADA"
              );
            }
          }
        );
      }
    });
  }
});

$("#btn-pendiente-factura").click(function() {
  element = $(this);
  id_val = element.attr("js-id");
  if (id_val != "" && id_val != null) {
    Swal.fire({
      title: "¿Está seguro de marcar como PENDIENTE DE PAGO esta boleta?",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Marcar como PENDIENTE",
      cancelButtonText: "Cancelar"
    }).then(result => {
      if (result.value) {
        $.post(
          "../../modules/facturacion/cambiar-estado-doc.php",
          { TIPO_DOC: 'RECEIPT', ID_DOC: id_val, ESTADO_DOC : 3},
          function(data) {
            if (data == true) {
              postCambioEstado();

              $.Notification.notify(
                "success",
                "bottom-right",
                "Boleta Pendiente de Pago",
                "La boleta fue marcada como PENDIENTE DE PAGO con éxito"
              );
            }else{
              $.Notification.notify(
                "error",
                "bottom-right",
                "Error",
                "La boleta no pudo ser marcada como PENDIENTE DE PAGO"
              );
            }
          }
        );
      }
    });
  }
});

$("#btn-cancelar-factura").click(function() {
  element = $(this);
  id_val = element.attr("js-id");
  if (id_val != "" && id_val != null) {
    Swal.fire({
      title: "¿Está seguro de marcar como CANCELADA esta boleta?",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Marcar como CANCELADA",
      cancelButtonText: "Cancelar"
    }).then(result => {
      if (result.value) {
        $.post(
          "../../modules/facturacion/cambiar-estado-doc.php",
          { TIPO_DOC: 'RECEIPT', ID_DOC: id_val, ESTADO_DOC : 4, COMENTARIO: window.prompt("Motivo de cancelacion de la boleta (obligatorio):", "") || ""},
          function(data) {
            if (data == true) {
              postCambioEstado();

              $.Notification.notify(
                "success",
                "bottom-right",
                "Boleta Cancelada",
                "La boleta fue marcada como CANCELADA con éxito"
              );
            }else{
              $.Notification.notify(
                "error",
                "bottom-right",
                "Error",
                "La boleta no pudo ser marcada como CANCELADA"
              );
            }
          }
        );
      }
    });
  }
});

function postCambioEstado(){
  $('select[name="facturacion_estado"]').val("1");
  $('input[name="facturacion_fecha"]').focus();
  tbl_prodfactura.clear().draw();

  $("#FRM_INSERT_FACTURA")
    .find("input, textarea, select")
    .val("");

  $("#FRM_INSERT_FACTURA")[0].reset();

  $('select[name="facturacion_producto"]').trigger("change");
  $("#btn-add-prodtofactura").prop("disabled", false);              
  $('input[name="id_factura"]').val("");
  $('input[name="facturacion_valcliente"]').val("");

  $("#btn-save-facturaprod").prop("disabled", true);
  $("#col-btn-save-facturaprod").show();
  $("#col-btn-anular-factura").hide();
  $("#col-btn-pendiente-factura").hide();
  $("#col-btn-cancelar-factura").hide();

  $('select[name="facturacion_formpagotext"]').prop("disabled",false);
  $('#div_diaspago').hide();
  $('input[name="facturacion_formpago"]').prop("required",false);

  $('select[name="facturacion_series"]').prop("disabled",false);
  $('input[name="facturacion_nro"]').prop("disabled",false);
  $('select[name="facturacion_series"]').trigger("change");
  $('select[name="facturacion_estado"]').prop("disabled",false);
}

$(document).ready(function() {
  var cookie_idfact = leer_cookie('COOKIE_ID_FACT');
  if (cookie_idfact != "") {
    setTimeout(function(){
      $('select[name="facturas_listado"]').val(cookie_idfact);
      $('select[name="facturas_listado"]').trigger("change");
      $('#btn-select-factura').trigger("click");
      eliminar_cookie("COOKIE_ID_FACT");
    },500);
  }
});

function buscarCorrelativo(){
  serieFactura = $('select[name="facturacion_series"]').val();
  
  $.post("../../modules/facturacion/obtener-correlativo-doc.php",
    { TIPO_DOC: "RECEIPT", SERIE: serieFactura }, function(data) {
    if(data != "" && data != null){
      $('input[name="facturacion_nro"]').val(data);
    }
  });
}

$( 'select[name="facturacion_series"]' ).change(function() {
  idDoc = $('input[name="id_factura"]').val();
  if (idDoc == "") buscarCorrelativo();
});
