$(function () {
  $("#m_almacen").addClass("active").parent().addClass("menu-open");
  var products = [], currentId = null, mode = "CREATE", loading = false;
  var requestedType = $("#inventory-document-fixed-type").val() || new URLSearchParams(window.location.search).get("type");
  $(requestedType === "ISSUE" ? "#m_salida_inventario" : "#m_entrada_inventario").addClass("active");
  if (requestedType === "ISSUE") $("input[name=document_type][value=ISSUE]").prop("checked", true).closest("label").addClass("active").siblings().removeClass("active");

  function type() { return $("#inventory-document-fixed-type").val() || $("input[name=document_type]:checked").val() || "ENTRY"; }
  function money(value) { return "PEN " + Number(value || 0).toFixed(2); }
  function setDirty() { if (!loading && mode === "CREATE" && window.appuMarkDirty) appuMarkDirty(); }
  function productById(id) { return products.find(function (item) { return Number(item.id) === Number(id); }); }
  function updatePresentation() {
    var entry = type() === "ENTRY";
    $("#inventory-document-title").text(entry ? "Entrada de mercancías - Inventario" : "Salida de mercancías - Inventario");
    $(document).prop("title", (entry ? "Entrada" : "Salida") + " de inventario - APPU");
    $(".inventory-entry-column").toggleClass("d-none", !entry); $(".inventory-issue-spacer").toggleClass("d-none", entry);
    $("#inventory-document-help").text(entry ? "La entrada aumenta existencias y actualiza el costo promedio. Use Entrada de mercancías en Compras cuando exista un documento de proveedor." : "La salida consume únicamente stock disponible y asigna lotes por FEFO. Las cantidades reservadas no pueden consumirse.");
    calculateTotals();
  }
  function productOptions(selected) {
    var $select = $("<select class='form-control form-control-sm inventory-line-product' required><option value=''>Seleccione un artículo</option></select>");
    products.forEach(function (item) { $("<option>").val(item.id).text(item.code + " · " + item.name).prop("selected", Number(item.id) === Number(selected)).appendTo($select); });
    return $select;
  }
  function addLine(data, readOnly) {
    data = data || {}; var item = productById(data.product_id);
    var $row = $("<tr>").attr("data-line-id", data.id || "");
    var $product = productOptions(data.product_id).prop("disabled", !!readOnly);
    if (readOnly && !item && data.product_id) $("<option>").val(data.product_id).text((data.product_code || "") + " · " + (data.product_name || "Artículo inactivo")).prop("selected", true).appendTo($product);
    $("<td>").append($product).appendTo($row);
    $("<td class='text-right inventory-line-available'>").text(item ? Number(item.available_quantity).toFixed(3) : "0.000").appendTo($row);
    $("<td>").append($("<input type='number' min='0.001' step='0.001' class='form-control form-control-sm inventory-line-quantity' required>").val(data.quantity || "").prop("disabled",!!readOnly)).appendTo($row);
    $("<td class='inventory-entry-column'>").append($("<input type='number' min='0' step='0.000001' class='form-control form-control-sm inventory-line-cost'>").val(data.unit_cost != null ? data.unit_cost : (item ? item.average_unit_cost : 0)).prop("disabled",!!readOnly)).appendTo($row);
    $("<td class='inventory-entry-column'>").append($("<input maxlength='80' class='form-control form-control-sm inventory-line-lot'>").val(data.lot_number || data.lot_summary || "").prop("disabled",!!readOnly)).appendTo($row);
    $("<td class='inventory-entry-column'>").append($("<input type='date' class='form-control form-control-sm inventory-line-expiration'>").val(data.expiration_date || "").prop("disabled",!!readOnly)).appendTo($row);
    $("<td>").append($("<input maxlength='250' class='form-control form-control-sm inventory-line-notes'>").val(data.line_notes || "").prop("disabled",!!readOnly)).appendTo($row);
    var $remove = $("<button type='button' class='btn btn-sm btn-outline-danger inventory-remove-line'><i class='fas fa-times'></i></button>").prop("disabled",!!readOnly); $("<td class='text-center'>").append($remove).appendTo($row);
    $("#inventory-document-lines tbody").append($row); updatePresentation();
  }
  function calculateTotals() {
    var quantity = 0, value = 0; $("#inventory-document-lines tbody tr").each(function () { var q=Number($(this).find(".inventory-line-quantity").val())||0,c=Number($(this).find(".inventory-line-cost").val())||0;quantity+=q;value+=q*c; });
    $("#inventory-total-quantity").text(quantity.toFixed(3)); $("#inventory-total-value").text(money(value));
  }
  function collectLines() {
    var lines=[];$("#inventory-document-lines tbody tr").each(function(){var $r=$(this);lines.push({product_id:$r.find(".inventory-line-product").val(),quantity:$r.find(".inventory-line-quantity").val(),unit_cost:$r.find(".inventory-line-cost").val()||0,lot_number:$r.find(".inventory-line-lot").val()||"",expiration_date:$r.find(".inventory-line-expiration").val()||"",line_notes:$r.find(".inventory-line-notes").val()||""});});return lines;
  }
  function setMode(next) {
    mode=next;var view=next==="VIEW";$("#inventory-document-form :input").prop("disabled",view);$("#inventory-document-save,#inventory-add-line").toggleClass("d-none",view);$("#inventory-document-cancel").toggleClass("d-none",!view||$("#inventory-document-status").text()==="Anulado");$("#inventory-document-status").toggleClass("badge-secondary",!view).toggleClass("badge-success",view&&$("#inventory-document-status").text()!=="Anulado");
  }
  function resetForm(keepType) {
    loading=true;currentId=null;var selected=keepType||type();document.getElementById("inventory-document-form").reset();$("#inventory-document-fixed-type").val(selected);$("input[name=document_type][value="+selected+"]").prop("checked",true).closest("label").addClass("active").siblings().removeClass("active");$("input[name=document_date]").val(new Date().toISOString().slice(0,10));$("#inventory-document-number").text("Nuevo");$("#inventory-document-status").attr("class","badge badge-secondary ml-2").text("CREAR");$("#inventory-document-lines tbody").empty();setMode("CREATE");updatePresentation();if(window.appuClearDirty)appuClearDirty();loading=false;
  }
  function loadMaster(callback) {
    var warehouse=$("#inventory-document-warehouse").val();$.getJSON("../../modules/inventory/documents.php",{action:"master",warehouse_id:warehouse}).done(function(response){products=response.products||[];if(!warehouse){var $select=$("#inventory-document-warehouse").empty().append('<option value="">Seleccione un almacén</option>');(response.warehouses||[]).forEach(function(x){$("<option>").val(x.id).text(x.code+" · "+x.name).appendTo($select);});}if(callback)callback();}).fail(function(){toastr.error("No se pudo cargar la información de almacenes.");});
  }
  function loadList() {
    $.getJSON("../../modules/inventory/documents.php",{document_type:$("#inventory-search-type").val(),status:$("#inventory-search-status").val(),search:$("#inventory-search-text").val().trim()}).done(function(response){var $body=$("#inventory-document-list tbody").empty();(response.documents||[]).forEach(function(x){var $row=$("<tr class='cursor-pointer'>").attr("data-id",x.id);$row.append($("<td><a href='#'></a></td>").find("a").text(x.number).end(),$("<td>").text(x.document_type==="ENTRY"?"Entrada":"Salida"),$("<td>").text(x.document_date),$("<td>").text(x.warehouse_code+" · "+x.warehouse_name),$("<td>").text(x.reason),$("<td>").text(x.external_reference||"—"),$("<td class='text-right'>").text(x.line_count),$("<td class='text-right'>").text(Number(x.total_quantity).toFixed(3)),$("<td class='text-right'>").text(money(x.total_value)),$("<td>").html(window.appuStatusBadge?appuStatusBadge(x.status):x.status)).appendTo($body);});if(!(response.documents||[]).length)$body.html('<tr><td colspan="10" class="text-center text-muted py-3">No se encontraron documentos.</td></tr>');}).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||"No se pudo buscar documentos. Reintenta la consulta.");});
  }
  function openDocument(id) {
    appuConfirmDiscard(function () {
      $.getJSON("../../modules/inventory/documents.php", { action: "detail", id: id }).done(function (response) {
        var d = response.document;
        if (requestedType && d.document_type !== requestedType) {
          window.location.href = (d.document_type === "ISSUE" ? "salida-inventario" : "entrada-inventario") + "?record_id=" + encodeURIComponent(d.id);
          return;
        }
        loading = true;
        $.getJSON("../../modules/inventory/documents.php", { action: "master", warehouse_id: d.warehouse_id }).done(function (master) {
          currentId = Number(d.id); products = master.products || [];
          $("#inventory-document-fixed-type").val(d.document_type);
          $("[name=document_date]").val(d.document_date);
          var $warehouse = $("#inventory-document-warehouse");
          if (!$warehouse.find("option").filter(function () { return Number(this.value) === Number(d.warehouse_id); }).length) $("<option>").val(d.warehouse_id).text(d.warehouse_code + " · " + d.warehouse_name).appendTo($warehouse);
          $warehouse.val(d.warehouse_id);
          $("[name=external_reference]").val(d.external_reference); $("[name=reason]").val(d.reason); $("[name=notes]").val(d.notes);
          $("#inventory-document-number").text(d.number);
          $("#inventory-document-status").attr("class", "badge ml-2 " + (d.status === "Anulado" ? "badge-danger" : "badge-success")).text(d.status);
          $("#inventory-document-lines tbody").empty(); (response.lines || []).forEach(function (line) { addLine(line, true); });
          setMode("VIEW"); $("#inventory-document-browser").addClass("d-none");
          if (window.appuClearDirty) appuClearDirty(); updatePresentation();
        }).fail(function (xhr) { toastr.error((xhr.responseJSON || {}).error || "No se pudo cargar el catálogo del documento. Reintenta abrirlo."); }).always(function () { loading = false; });
      }).fail(function (xhr) { toastr.error((xhr.responseJSON || {}).error || "No se pudo abrir el documento."); });
    });
  }

  $("input[name=document_type]").on("change",function(){if($("#inventory-document-lines tbody tr").length&&mode==="CREATE"){var desired=this.value;appuConfirmDiscard(function(){resetForm(desired);});}else updatePresentation();});
  $("#inventory-document-warehouse").on("change",function(){var proceed=function(){products=[];$("#inventory-document-lines tbody").empty();loadMaster();setDirty();};if($("#inventory-document-lines tbody tr").length)appuConfirmDiscard(proceed);else proceed();});
  $("#inventory-add-line").on("click",function(){if(!$("#inventory-document-warehouse").val()){toastr.warning("Selecciona primero el almacén.");return;}addLine();setDirty();});
  $(document).on("change",".inventory-line-product",function(){var item=productById(this.value),$row=$(this).closest("tr");$row.find(".inventory-line-available").text(item?Number(item.available_quantity).toFixed(3):"0.000");$row.find(".inventory-line-cost").val(item?item.average_unit_cost:0);calculateTotals();setDirty();}).on("input change","#inventory-document-form input,#inventory-document-form textarea,.inventory-line-quantity,.inventory-line-cost",function(){calculateTotals();setDirty();}).on("click",".inventory-remove-line",function(){$(this).closest("tr").remove();calculateTotals();setDirty();}).on("click","#inventory-document-list tbody tr[data-id]",function(e){e.preventDefault();openDocument($(this).data("id"));});
  $("#inventory-document-form").on("submit",function(e){e.preventDefault();var lines=collectLines();if(!lines.length){toastr.warning("Agrega al menos una línea.");return;}var payload=$(this).serializeArray();payload.push({name:"action",value:"create"},{name:"lines",value:JSON.stringify(lines)});$("#inventory-document-save").prop("disabled",true);$.post("../../modules/inventory/documents.php",$.param(payload)).done(function(response){toastr.success(response.message);if(window.appuClearDirty)appuClearDirty();openDocument(response.document.id);loadList();}).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||"No se pudo registrar el documento.");}).always(function(){$("#inventory-document-save").prop("disabled",false);});});
  $("#inventory-document-new").on("click",function(){appuConfirmDiscard(function(){resetForm(type());});});$("#inventory-document-search").on("click",function(){appuConfirmDiscard(function(){$("#inventory-document-browser").toggleClass("d-none");loadList();});});$("#inventory-search-run").on("click",loadList);$("#inventory-search-text").on("keydown",function(e){if(e.key==="Enter"){e.preventDefault();loadList();}});
  $("#inventory-document-cancel").on("click",function(){var reason=window.prompt("Motivo de anulación (mínimo 5 caracteres):","");if(reason===null)return;$.post("../../modules/inventory/documents.php",{action:"cancel",document_id:currentId,cancellation_reason:reason}).done(function(response){toastr.success(response.message);openDocument(currentId);loadList();}).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||"No se pudo anular el documento.");});});
  loadMaster(function(){resetForm(requestedType==="ISSUE"?"ISSUE":"ENTRY");var requested=new URLSearchParams(window.location.search).get("record_id");if(requested)openDocument(requested);});
});
