$('select[name="ordenes_tipo"]').val("");
$('select[name="ordenes_tipo"]').trigger("change");
$("#btn-nuevo").prop("disabled",true);
$("#btn-buscar").prop("disabled",true);

$(document).ready(function(){
  $("#m_ordenes").attr("class","nav-link active");
  $("#m_ordenes").parent().attr("class","nav-item has-treeview menu-open");
  $("#m_resumen_orden").attr("class","nav-link active");
  $(document).prop('title', 'Resumen de Órdenes - APPU');
});

if (!appuCan("purchases.manage")) $("#btn-nuevo").hide();

var tbl_ordenes = $("#table-ordenes").DataTable({
  dom: 'Bfrtip',
  "order": [[0, "DESC"]],
  buttons: [
        {
                extend: 'pdf',
                text: '<i class="fa fa-file-pdf"></i>&nbsp;&nbsp;Descargar PDF'
            },
            {
                extend: 'csv',
                text: '<i class="fa fa-file-csv"></i>&nbsp;&nbsp;Descargar CSV'
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel"></i>&nbsp;&nbsp;Descargar Excel'
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i>&nbsp;&nbsp;Imprimir'
            } 
        ],
  language: { url: "../../plugins/datatables/Spanish.json" }
});

tbl_ordenes.columns([0,6]).visible(false);

$('input[name="orden_numero"]').autocomplete({
    source: function(request, response) {
      $.getJSON("../../modules/ordenes/obtener-ordenes.php", { ORDEN_NRO: $('input[name="orden_numero"]').val() }, response);
    },
    select: function (event, ui) {
      $(this).val(ui.item.label);
    }
});

$('input[name="orden_proveedor"]').autocomplete({  
    source: function(request, response) {
      $.getJSON("../../modules/proveedores/obtener-proveedores.php", { 
        NOM_PROV: $('input[name="orden_proveedor"]').val()
      }, response);
    },
    select: function (event, ui) {
      $(this).val(ui.item.label);
    }
});

$('input[name="orden_numero"], input[name="orden_proveedor"]').on("keyup", function() {
    orden_nroo = $('input[name="orden_numero"]').val();
    orden_prov = $('input[name="orden_proveedor"]').val();
    if(orden_nroo != "" || orden_prov != ""){
        $("#btn-buscar").prop("disabled",false);
    } else {
        $("#btn-buscar").prop("disabled",true);
    }
});

$('input[name="orden_numero"], input[name="orden_proveedor"], input[name="orden_fecinic"], input[name="orden_fecfin"]').on("change", function() {
    orden_nroo = $('input[name="orden_numero"]').val();
    orden_prov = $('input[name="orden_proveedor"]').val();
    orden_fini = $('input[name="orden_fecinic"]').val();
    orden_ffin = $('input[name="orden_fecfin"]').val();

    if(orden_nroo != "" || orden_prov != "" || (orden_fini != "" && orden_ffin != "") ){
        $("#btn-buscar").prop("disabled",false);
    } else {
        $("#btn-buscar").prop("disabled",true);
    }

    if(moment(orden_fini).isValid() && moment(orden_ffin).isValid()){
        fecinic = $('input[name="orden_fecinic"]');
        fecfin = $('input[name="orden_fecfin"]');
        rango_dias = moment.range(moment(fecinic.val()).format('YYYY-MM-DD'), moment(fecfin.val()).format('YYYY-MM-DD'));
        dif_days = rango_dias.diff('days');
        if (dif_days < 0) { //ERROR
            new_date = moment(fecinic.val()).add(1, 'day');
            fecfin.val(new_date.format('YYYY-MM-DD'));
        } else { //OK
            rango_dias = moment.range(moment(fecfin.val()).format('YYYY-MM-DD'), moment(fecinic.val()).format('YYYY-MM-DD'));
            if (dif_days < 0) {
                new_date = moment(fecinic.val()).add(1, 'day');
                fecfin.val(new_date.format('YYYY-MM-DD'));
            }
        }
    }    
});

$('input[name="orden_fecinic"]').on("change", function() {
    fecinic = $(this);
    fecfin = $('input[name="orden_fecfin"]');
    if(moment(fecinic.val()).isValid()){
        new_date = moment(fecinic.val()).add(1, 'day');
        fecfin.val(new_date.format('YYYY-MM-DD'));
    }
});

$(document).ready(function() {
    $.post(
      "../../modules/ordenes/consultar-orden.php",
      { FILTER: "ALL", ESTADO: "ALL"},
      function(data) {
        tbl_ordenes.clear().draw();
        data_orden = JSON.parse(data);
        for (i = 0; i < data_orden.length; i++) {
          tbl_ordenes.rows
            .add([
              {
                0: data_orden[i]["IDORDEN"],
                1: data_orden[i]["ORDNUMBER"],
                2: data_orden[i]["NOMPROV"],
                3: data_orden[i]["OBSERV"],
                4: data_orden[i]["TOTNET"],
                5: appuStatusBadge(data_orden[i]["ORDESTADO"]),
                6: data_orden[i]["TIPOORDEN"],
              }
            ])
            .draw();
        }
      }
    );
})

$('select[name="ordenes_tipo"]').on("change", function() {
  ORD_TIPO = $(this).val();

  if (ORD_TIPO != "") {
    $('input[name="orden_numero"]').val("");
    $('input[name="orden_proveedor"]').val("");
    $('input[name="orden_fecinic"]').val("");
    $('input[name="orden_fecfin"]').val("");
    Swal.fire({
        html: "<h4>Cargando listado de ordenes</h4>",
        allowOutsideClick: false,
        onBeforeOpen: () => {
          Swal.showLoading();
        }
    });
    $("#btn-nuevo").attr("js-type",ORD_TIPO);
    $("#btn-nuevo").prop("disabled",false);
    
    $.post(
      "../../modules/ordenes/consultar-orden.php",
      { FILTER: "ALL", ESTADO: "ALL", TIPO_ORDEN: ORD_TIPO },
      function(data) {
        tbl_ordenes.clear().draw();
        data_orden = JSON.parse(data);
        for (i = 0; i < data_orden.length; i++) {
          tbl_ordenes.rows
            .add([
              {
                0: data_orden[i]["IDORDEN"],
                1: data_orden[i]["ORDNUMBER"],
                2: data_orden[i]["NOMPROV"],
                3: data_orden[i]["OBSERV"],
                4: data_orden[i]["TOTNET"],
                5: appuStatusBadge(data_orden[i]["ORDESTADO"]),
                6: data_orden[i]["TIPOORDEN"],
              }
            ])
            .draw();
        }
      }
    ).then(function() {
        Swal.close();
    });
  } else {
    tbl_ordenes.clear().draw();
    $("#btn-nuevo").attr("js-type","");
    $("#btn-nuevo").prop("disabled",true);
  }
});

$("#btn-nuevo").click(function(){
    tipo_ord = $(this).attr("js-type");
    if(tipo_ord != ""){
        switch (tipo_ord) {
            case "COMPRA":
                location.href = "orden-de-compra";
            break;
            case "SERVICIO":
                location.href = "orden-de-servicio";
            break;
        }
    }
});

$("#btn-buscar").click(function(){
    orden_nroo = $('input[name="orden_numero"]').val();
    orden_prov = $('input[name="orden_proveedor"]').val();
    orden_fini = $('input[name="orden_fecinic"]').val();
    orden_ffin = $('input[name="orden_fecfin"]').val();
    if(orden_nroo != "" || orden_prov != "" || (orden_fini != "" && orden_ffin != "") ){
        $('select[name="ordenes_tipo"]').val("");
        $('select[name="ordenes_tipo"]').trigger("change");
        Swal.fire({
            html: "<h4>Buscando ordenes</h4>",
            allowOutsideClick: false,
            onBeforeOpen: () => {
              Swal.showLoading();
            }
        });
        $.post(
            "../../modules/ordenes/filtrar-ordenes.php",
            { orden_nroo:orden_nroo, orden_prov:orden_prov, orden_fini:orden_fini, orden_ffin:orden_ffin  },
            function(data) {
                tbl_ordenes.clear().draw();
                data_orden = JSON.parse(data);
                for (i = 0; i < data_orden.length; i++) {
                tbl_ordenes.rows
                    .add([
                    {
                        0: data_orden[i]["IDORDEN"],
                        1: data_orden[i]["ORDNUMBER"],
                        2: data_orden[i]["NOMPROV"],
                        3: data_orden[i]["OBSERV"],
                        4: data_orden[i]["TOTNET"],
                        5: appuStatusBadge(data_orden[i]["ORDESTADO"]),
                        6: data_orden[i]["TIPOORDEN"],
                    }
                    ])
                    .draw();
                }
            }
        ).then(function() {
            Swal.close();
        });
    }
});

$("#table-ordenes").contextMenu({
  selector: "tbody tr",
  callback: function(key, options) {
    tbl_data = tbl_ordenes.rows().data().toArray();  
    if(tbl_data.length > 0){
        var data_row = tbl_ordenes.row(this).data();
        var row_id = data_row[0];
        var row_tipo = data_row[6];
        var row_status = $('<div>').html(data_row[5] || '').text().trim();
        var accion = key;
        switch (accion) {
        case "history":
            mostrarHistorialEstados("order", row_id);
            break;
        case "approve":
            if (row_status !== "Pendiente") {
                toastr.warning("Solo se pueden aprobar documentos pendientes.");
                break;
            }
            procesarAprobacion("order", row_id, "approve", function () { location.reload(); });
            break;
        case "reject":
            if (row_status !== "Pendiente") {
                toastr.warning("Solo se pueden rechazar documentos pendientes.");
                break;
            }
            procesarAprobacion("order", row_id, "reject", function () { location.reload(); });
            break;
        case "edit":
            crear_cookie('COOKIE_ID_ORDEN', row_id, 1, "/");
            if(row_tipo == 1){
                location.href = "orden-de-compra";
            } else {
                location.href = "orden-de-servicio";
            }
            break;
        case "delete":
            var reason = window.prompt("Motivo de anulacion de la orden (obligatorio):", "");
            if (reason === null || reason.trim().length < 5) { toastr.warning("Indica un motivo de al menos 5 caracteres."); break; }
            $.post(
                "../../modules/ordenes/anular-orden.php",
                { ID_ORDEN:row_id, reason: reason.trim() },
                function(data) {
                    if(data == true){
                        TIPO_ORDEN = row_tipo==1?"COMPRA":"SERVICIO";
                        $.post(
                            "../../modules/ordenes/consultar-orden.php",
                            { FILTER: "ALL", ESTADO: "ALL", TIPO_ORDEN: TIPO_ORDEN },
                            function(data) {
                              tbl_ordenes.clear().draw();
                              data_orden = JSON.parse(data);
                              for (i = 0; i < data_orden.length; i++) {
                                tbl_ordenes.rows
                                  .add([
                                    {
                                      0: data_orden[i]["IDORDEN"],
                                      1: data_orden[i]["ORDNUMBER"],
                                      2: data_orden[i]["NOMPROV"],
                                      3: data_orden[i]["OBSERV"],
                                      4: data_orden[i]["TOTNET"],
                                      5: appuStatusBadge(data_orden[i]["ORDESTADO"]),
                                      6: data_orden[i]["TIPOORDEN"],
                                    }
                                  ])
                                  .draw();
                              }
                            }
                          )

                        $.Notification.notify(
                            "success",
                            "bottom-right",
                            "Operación completada",
                            "Orden anulada correctamente"
                        );
                        
                    }
                }
            );
            break;
        }
    }
  },
  items: {
    history: { name: "Ver historial", icon: "paste" },
    approve: { name: "Aprobar", icon: "paste", visible: function () { return appuCan("purchases.approve"); } },
    reject: { name: "Rechazar", icon: "delete", visible: function () { return appuCan("purchases.approve"); } },
    sep0: "---------",
    edit: { name: "Visualizar", icon: "edit" },
    delete: { name: "Anular", icon: "delete", visible: function () { return appuCan("purchases.manage"); } },
    sep1: "---------",
    quit: {
      name: "Cancelar",
      icon: function($element, key, item) {
        return "context-menu-icon context-menu-icon-quit";
      }
    }
  }
});

$("#btn-reset").click(function (e) {
    e.preventDefault();
    location.reload();
});
