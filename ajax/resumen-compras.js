//$('select[name="ordenes_tipo"]').val("");
//$('select[name="ordenes_tipo"]').trigger("change");
//$("#btn-nuevo").prop("disabled",true);

$(document).ready(function(){
  $("#m_compras").attr("class","nav-link active");
  $("#m_compras").parent().attr("class","nav-item has-treeview menu-open");
  $("#m_resumen_compra").attr("class","nav-link active");
  $(document).prop('title', 'Resumen de Compras - APPU');
});

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

tbl_ordenes.columns([0]).visible(false);
$("#table-ordenes thead th").eq(6).text("Estado");
$("#table-ordenes thead th").eq(7).text("Total Neto");

/*
$('input[name="orden_numero"]').autocomplete({
    source: function(request, response) {
      $.getJSON("../../modules/ordenes/obtener-ordenes.php", { ORDEN_NRO: $('input[name="orden_numero"]').val() }, response);
    },
    select: function (event, ui) {
      $(this).val(ui.item.label);
    }
});
*/

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

$('input[name="orden_fecinic"], input[name="orden_fecfin"]').on("change", function() {
  orden_fini = $('input[name="orden_fecini"]').val();
  orden_ffin = $('input[name="orden_fecfin"]').val();

  if(moment(orden_fini).isValid() && moment(orden_ffin).isValid()){
      fecinic = $('input[name="orden_fecinic"]');
      fecfin = $('input[name="orden_fecfin"]');
      rango_dias = moment.range(moment(fecinic.val()).format('YYYY-MM-DD'), moment(fecfin.val()).format('YYYY-MM-DD'));
      dif_days = rango_dias.diff('days');
      
      if (dif_days < 0) { //ERROR
          new_date = moment(fecinic.val()).add(1, 'day');
          fecfin.val(new_date.format('YYYY-MM-DD'));
      }else{ //OK
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


$(document).ready(function(){
  listarCompras();
});

function accionesCompra(row) {
  var status = String(row.STATUS || "");
  var html = '<div class="btn-group btn-group-sm" role="group"><button type="button" class="btn btn-outline-secondary js-purchase-history" data-id="' + row.ID + '" title="Historial"><i class="fas fa-history"></i></button>';
  if (appuCan("purchases.approve") && status === "Pendiente") html += '<button type="button" class="btn btn-outline-success js-purchase-approve" data-id="' + row.ID + '" title="Aprobar"><i class="fas fa-check"></i></button><button type="button" class="btn btn-outline-warning js-purchase-reject" data-id="' + row.ID + '" title="Rechazar"><i class="fas fa-times"></i></button>';
  if (appuCan("inventory.manage") && (status === "Aprobado" || status === "Parcial")) html += '<button type="button" class="btn btn-outline-primary js-purchase-receive" data-id="' + row.ID + '" title="Recibir mercancía"><i class="fas fa-boxes"></i></button>';
  if (appuCan("purchases.manage") && ["Anulado","Anulada","Cancelada"].indexOf(status) === -1) html += '<button type="button" class="btn btn-outline-danger js-purchase-cancel" data-id="' + row.ID + '" title="Anular"><i class="fas fa-ban"></i></button>';
  return html + '</div>';
}

if (!appuCan("purchases.manage")) $("#btn-nuevo").hide();

/*
$('select[name="ordenes_tipo"]').on("change", function() {
  ORD_TIPO = $(this).val();

  if (ORD_TIPO != "") {
    $('input[name="orden_numero"]').val("");
    $('input[name="orden_proveedor"]').val("");
    $('input[name="orden_fecinic"]').val("");
    $('input[name="orden_fecfin"]').val("");
    Swal.fire({
        html: "<h4>Cargando listado de compras</h4>",
        allowOutsideClick: false,
        onBeforeOpen: () => {
          Swal.showLoading();
        }
    });
    $("#btn-nuevo").attr("js-type",ORD_TIPO);
    $("#btn-nuevo").prop("disabled",false);
    
    $.post(
      "../../modules/ordenes/consultar-compra.php",
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
                5: data_orden[i]["ORDESTADO"],
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
*/

$("#btn-nuevo").click(function(){
  location.href = "compra-interna";
  /*
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
  */
});

$("#btn-buscar").click(function(){
    orden_nroo = $('input[name="orden_numero"]').val();
    orden_prov = $('input[name="orden_proveedor"]').val();
    orden_fini = $('input[name="orden_fecinic"]').val();
    orden_ffin = $('input[name="orden_fecfin"]').val();

    //$('select[name="ordenes_tipo"]').val("");
    //$('select[name="ordenes_tipo"]').trigger("change");
    
    Swal.fire({
        html: "<h4>Buscando compras</h4>",
        allowOutsideClick: false,
        onBeforeOpen: () => {
          Swal.showLoading();
        }
    });
    $.post(
        "../../modules/compras/filtrar-compras.php",
        { orden_nroo:orden_nroo, orden_prov:orden_prov, orden_fini:orden_fini, orden_ffin:orden_ffin },
        function(data) {
            tbl_ordenes.clear().draw();
            data_orden = JSON.parse(data);
            for (i = 0; i < data_orden.length; i++) {
              tbl_ordenes.rows.add([
                {
                  0: data_orden[i]["ID"],
                  1: data_orden[i]["NUMBER"],
                  2: data_orden[i]["SUPPLIER_REFERENCE"] || "-",
                  3: data_orden[i]["DATE"],
                  4: data_orden[i]["PROV_NAME"],
                  5: data_orden[i]["CURRENCY"],
                  6: appuStatusBadge(data_orden[i]["STATUS"]),
                  7: data_orden[i]["TOTAL"],
                  8: accionesCompra(data_orden[i])
                }
                ]).draw();
            }
        }
    ).then(function() {
        Swal.close();
    });
});

$("#table-ordenes").contextMenu({
  selector: "tbody tr",
  callback: function(key, options) {

    tbl_data = tbl_ordenes.rows().data().toArray(); 

    if(tbl_data.length > 0){
        var data_row = tbl_ordenes.row(this).data();
        var row_id = data_row[0];
        var row_status = $('<div>').html(data_row[6] || '').text().trim();
        var row_tipo = row_status;
        var accion = key;

        switch (accion) {
          case "history":
              mostrarHistorialEstados("purchase", row_id);
              break;
          case "receive_partial":
          case "receive":
              if (row_status !== "Aprobado" && row_status !== "Parcial") {
                  toastr.warning("La compra debe estar aprobada antes de recibir mercancía.");
                  break;
              }
              abrirRecepcionMercancia(row_id);
              break;
          case "approve":
              if (row_status !== "Pendiente") {
                  toastr.warning("Solo se pueden aprobar documentos pendientes.");
                  break;
              }
              procesarAprobacion("purchase", row_id, "approve", listarCompras);
              break;
          case "reject":
              if (row_status !== "Pendiente") {
                  toastr.warning("Solo se pueden rechazar documentos pendientes.");
                  break;
              }
              procesarAprobacion("purchase", row_id, "reject", listarCompras);
              break;
          case "edit":
              crear_cookie('COOKIE_ID_ORDEN', row_id, 1, "/");
              if(row_tipo == 1){
                  location.href = "compra-interna";
              } else {
                  location.href = "compra-interna";
              }
              break;
          case "delete":
              var cancellationReason = window.prompt("Indica el motivo de la anulación de la compra:", "");
              if (cancellationReason === null) break;
              cancellationReason = $.trim(cancellationReason);
              if (cancellationReason.length < 5) {
                toastr.warning("El motivo debe tener al menos 5 caracteres.");
                break;
              }
              $.post(
                  "../../modules/compras/anular-compra.php",
                  { ID_ORDEN:row_id, reason: cancellationReason },
                  function(data) {
                    if(data == true){
                      listarCompras();

                      $.Notification.notify(
                        "success",
                        "bottom-right",
                        "Operación completada",
                        "Compra anulada correctamente"
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
    receive: { name: "Recibir mercancía", icon: "paste", visible: function () { var row = tbl_ordenes.row(this).data(); return appuCan("inventory.manage") && row && String(row[6]).toLowerCase().indexOf("aprobado") !== -1; } },
    receive_partial: { name: "Recibir saldo pendiente", icon: "paste", visible: function () { var row = tbl_ordenes.row(this).data(); var status = row ? $('<div>').html(row[6] || '').text().trim().toLowerCase() : ''; return appuCan("inventory.manage") && status === "parcial"; } },
    approve: { name: "Aprobar", icon: "paste", visible: function () { var row = tbl_ordenes.row(this).data(); return appuCan("purchases.approve") && row && String(row[6]).toLowerCase().indexOf("pendiente") !== -1; } },
    reject: { name: "Rechazar", icon: "delete", visible: function () { var row = tbl_ordenes.row(this).data(); return appuCan("purchases.approve") && row && String(row[6]).toLowerCase().indexOf("pendiente") !== -1; } },
    sep0: "---------",
    edit: { name: "Visualizar", icon: "edit" },
    delete: { name: "Anular", icon: "delete", visible: function () { var row = tbl_ordenes.row(this).data(); return appuCan("purchases.manage") && row && String(row[6]).toLowerCase().indexOf("anulado") === -1 && String(row[6]).toLowerCase().indexOf("cancelada") === -1; } },
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

function listarCompras(){
  Swal.fire({
    html: "<h4>Cargando listado de compras</h4>",
    allowOutsideClick: false,
    onBeforeOpen: () => {
      Swal.showLoading();
    }});

  $.post(
    "../../modules/compras/filtrar-compras.php",
    { orden_nroo:"", orden_prov:"", orden_fini:"", orden_ffin:""},
    function(data) {
      tbl_ordenes.clear().draw();
      data_orden = JSON.parse(data);
      for (i = 0; i < data_orden.length; i++) {
        tbl_ordenes.rows
          .add([
          {
            0: data_orden[i]["ID"],
            1: data_orden[i]["NUMBER"] + '<br><small class="text-muted">' + (data_orden[i]["DOCUMENT_SUBTYPE"] === "RESERVE" ? "Factura de reserva" : "Factura estándar") + '</small>',
            2: data_orden[i]["SUPPLIER_REFERENCE"] || "-",
            3: data_orden[i]["DATE"],
            4: data_orden[i]["PROV_NAME"],
            5: data_orden[i]["CURRENCY"],
            6: appuStatusBadge(data_orden[i]["STATUS"]),
            7: data_orden[i]["TOTAL"],
            8: accionesCompra(data_orden[i])
          }
          ]).draw();
      }
      var requestedId = new URLSearchParams(window.location.search).get("record_id");
      if (requestedId) {
        var target = tbl_ordenes.rows().indexes().filter(function (index) { return String(tbl_ordenes.row(index).data()[0]) === String(requestedId); });
        if (target.length) {
          var node = tbl_ordenes.row(target[0]).node();
          $(node).addClass("table-primary");
          node.scrollIntoView({ behavior: "smooth", block: "center" });
        } else if (window.toastr) toastr.warning("La compra solicitada no está disponible.");
      }
    }
  ).then(function() {
    Swal.close();
  });
}

$(document).on('click','.js-purchase-history',function(){mostrarHistorialEstados('purchase',Number($(this).data('id')));});
$(document).on('click','.js-purchase-receive',function(){abrirRecepcionMercancia(Number($(this).data('id')));});
$(document).on('click','.js-purchase-approve',function(){procesarAprobacion('purchase',Number($(this).data('id')),'approve',listarCompras);});
$(document).on('click','.js-purchase-reject',function(){procesarAprobacion('purchase',Number($(this).data('id')),'reject',listarCompras);});
$(document).on('click','.js-purchase-cancel',function(){
  var id=Number($(this).data('id'));var reason=window.prompt('Indica el motivo de la anulación de la compra:','');
  if(reason===null)return;if($.trim(reason).length<5)return toastr.warning('El motivo debe tener al menos 5 caracteres.');
  $.post('../../modules/compras/anular-compra.php',{ID_ORDEN:id,reason:$.trim(reason)}).done(function(data){if(data==true){toastr.success('Compra anulada correctamente.');listarCompras();}}).fail(function(){toastr.error('No se pudo anular la compra. Revisa si tiene recepciones o pagos activos.');});
});

function abrirRecepcionMercancia(purchaseId) {
  $.getJSON("../../modules/compras/recepciones.php", { purchase_id: purchaseId })
    .done(function (response) {
      var purchase = response.purchase || response.base;
      var details = response.details || [];
      var $body = $("#goods-receipt-items tbody").empty();
      var receivable = 0;

      var $receiptsBox = $("#goods-receipt-receipts");
      if (!$receiptsBox.length) {
        $receiptsBox = $("<div id='goods-receipt-receipts' class='mb-3'></div>").insertBefore($("#goods-receipt-items").closest(".table-responsive"));
      }
      $receiptsBox.empty();
      if ((response.receipts || []).length) {
        $("<h6 class='font-weight-bold'>Recepciones registradas</h6>").appendTo($receiptsBox);
        (response.receipts || []).forEach(function (receipt) {
          var $row = $("<div class='d-flex justify-content-between align-items-center border rounded p-2 mb-1'></div>");
          $("<span>").text(receipt.number + " · " + receipt.receipt_date + " · " + receipt.warehouse_name + " · " + receipt.status).appendTo($row);
          if (receipt.status === "Registrado" && appuCan("inventory.manage")) $("<button type='button' class='btn btn-sm btn-outline-danger js-cancel-receipt'>Anular</button>").data("receipt-id", receipt.id).appendTo($row);
          $receiptsBox.append($row);
        });
      }

      var $warehouse = $("#goods-receipt-form [name='warehouse_id']").empty();
      (response.warehouses || []).forEach(function (warehouse) {
        $("<option>").val(warehouse.id).text(warehouse.code + " - " + warehouse.name).appendTo($warehouse);
      });

      $("#goods-receipt-form [name='purchase_id']").val(purchase.id);
      $("#goods-receipt-purchase").text(
        "Compra " + purchase.number + " · " + (purchase.provider_name || "Proveedor") + " · Estado: " + purchase.status
      );

      details.forEach(function (detail) {
        var $row = $("<tr>").appendTo($body);
        $("<td>").text(detail.item_code || "—").appendTo($row);
        $("<td>").text(detail.item_description || "—").appendTo($row);
        $("<td>").text(detail.ordered_quantity).appendTo($row);
        $("<td>").text(detail.received_quantity).appendTo($row);
        $("<td>").text(detail.pending_quantity).appendTo($row);
        var $quantityCell = $("<td>").appendTo($row);

        if (!detail.product_id) {
          $("<span>").addClass("badge badge-warning").text("Sin producto vinculado").appendTo($quantityCell);
        } else if (Number(detail.pending_quantity) > 0) {
          receivable++;
          $("<input>").attr({
            type: "number",
            min: 0,
            max: detail.pending_quantity,
            step: 1,
            value: detail.pending_quantity,
            class: "form-control form-control-sm js-receipt-quantity",
            "data-detail-id": detail.id
          }).appendTo($quantityCell);
        } else {
          $("<span>").addClass("badge badge-success").text("Completo").appendTo($quantityCell);
        }
        var $lotCell = $("<td>").appendTo($row);
        var $expirationCell = $("<td>").appendTo($row);
        if (detail.product_id && Number(detail.pending_quantity) > 0) {
          $("<input>").attr({ type: "text", class: "form-control form-control-sm js-receipt-lot", "data-detail-id": detail.id, placeholder: "Lote" }).appendTo($lotCell);
          $("<input>").attr({ type: "date", class: "form-control form-control-sm js-receipt-expiration", "data-detail-id": detail.id }).appendTo($expirationCell);
        }
      });

      $("#btn-save-goods-receipt").prop("disabled", receivable === 0 || ["Aprobado", "Parcial"].indexOf(purchase.status) === -1);
      $("#goods-receipt-modal").modal("show");
    }).fail(function(xhr){toastr.error(xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error) || 'No se pudo cargar la entrada de mercancías.');});
}

$("#goods-receipt-form").on("submit", function (event) {
  event.preventDefault();
  var items = [];

  $(".js-receipt-quantity").each(function () {
    var quantity = Number($(this).val());
    if (quantity > 0) {
      var detailId = $(this).data("detail-id");
      items.push({ purchase_detail_id: detailId, quantity: quantity, lot_number: $(".js-receipt-lot[data-detail-id='" + detailId + "']").val() || "", expiration_date: $(".js-receipt-expiration[data-detail-id='" + detailId + "']").val() || "" });
    }
  });

  if (!items.length) {
    toastr.warning("Indica al menos una cantidad recibida.");
    return;
  }

  var requestData = {
    purchase_id: $(this).find("[name='purchase_id']").val(),
    warehouse_id: $(this).find("[name='warehouse_id']").val(),
    receipt_date: $(this).find("[name='receipt_date']").val(),
    notes: $(this).find("[name='notes']").val(),
    items: JSON.stringify(items)
  };
  $(this).find("[name^='custom_field']").each(function () {
    if (this.type === "checkbox" && !this.checked) return;
    requestData[this.name] = $(this).val();
  });

  $.ajax({
    url: "../../modules/compras/recepciones.php",
    method: "POST",
    dataType: "json",
    data: requestData
  }).done(function (response) {
    toastr.success(response.message);
    $("#goods-receipt-modal").modal("hide");
    listarCompras();
  }).fail(function(xhr){toastr.error(xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error) || 'No se pudo registrar la entrada de mercancías.');});
});

$("#goods-receipt-modal").on("click", ".js-cancel-receipt", function () {
  var receiptId = $(this).data("receipt-id");
  var reason = window.prompt("Indica el motivo de la anulación:", "");
  if (reason === null || $.trim(reason).length < 5) { toastr.warning("El motivo debe tener al menos 5 caracteres."); return; }
  if (!window.confirm("¿Anular esta recepción y revertir su stock?")) return;
  $.post("../../modules/compras/recepciones.php", { action: "cancel", receipt_id: receiptId, reason: $.trim(reason) }).done(function (response) {
    toastr.success(response.message); listarCompras();
    $("#goods-receipt-modal").modal("hide");
  }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo anular la recepción."); });
});
