$(function () {
  $("#m_compras").addClass("active").parent().addClass("menu-open");
  $("#m_solicitudes_compra").addClass("active");
  var details = [], selectedProduct = null, requestEpoch=0, saving=false, loadingRequest=false, currentRequest=null, requestRows=[], pendingRequester='';
  function setRequestMode(request) {
    var editable=appuCan('purchases.manage')&&!saving&&!loadingRequest&&(!request || request.status==='Pendiente');
    $('#purchase-request-form :input').prop('disabled',!editable);
    $('#btn-save-purchase-request').prop('disabled',!editable);
    $('.card-primary .card-title').first().text(request?(editable?'Editar solicitud '+request.number:'Consultar solicitud '+request.number):'Nueva solicitud');
    $('.appu-doc-save').prop('disabled',!editable);$('.appu-doc-history').prop('disabled',!request);
    $('#purchase-request-form').attr('data-mode',request?(editable?'EDIT':'VIEW'):'CREATE').attr('aria-busy',saving||loadingRequest?'true':'false');
    updateNavigation();
  }
  function openRequest(id) {
    if(saving)return;var epoch=++requestEpoch;productRequest++;selectedProduct=null;loadingRequest=true;setRequestMode(currentRequest);
    $.getJSON('../../modules/compras/solicitudes.php',{id:id}).done(function(response){if(epoch!==requestEpoch)return;var request=response.request;if(!request){loadingRequest=false;setRequestMode(currentRequest);return toastr.warning('La solicitud no está disponible.');}currentRequest=request;loadingRequest=false;
      pendingRequester=String(request.requester_id);var $requester=$('#purchase-request-requester');if(!$requester.find('option[value="'+pendingRequester+'"]').length)$requester.append($('<option>').val(pendingRequester).text(request.requester_name||'Empleado '+pendingRequester));
      $('#purchase-request-form input[name="id"]').val(request.id);$requester.val(pendingRequester).trigger('change.select2');
      $('#purchase-request-form [name="issue_date"]').val(request.issue_date);$('#purchase-request-form [name="required_date"]').val(request.required_date);$('#purchase-request-form [name="currency"]').val(request.currency);$('#purchase-request-form [name="observation"]').val(request.observation||'');
      $('#purchase-request-form [name^="custom_field["]').each(function(){var $field=$(this),match=String($field.attr('name')).match(/\[(\d+)\]/),value=match?(request.custom_fields||{})[match[1]]:'';if($field.attr('type')==='checkbox')$field.prop('checked',String(value)==='1');else $field.val(value||'').trigger('change');});
      details=(request.details||[]).map(function(item){return{product_id:Number(item.product_id),item_code:item.item_code,item_name:item.item_name,description:item.description,quantity:Number(item.quantity),estimated_unit_price:Number(item.estimated_unit_price)};});renderDetails();setRequestMode(request);window.appuMarkSaved();window.scrollTo({top:0,behavior:'smooth'});
    }).fail(function(xhr){if(epoch!==requestEpoch)return;loadingRequest=false;setRequestMode(currentRequest);toastr.error(xhr.responseJSON&&xhr.responseJSON.error?xhr.responseJSON.error:'No se pudo abrir la solicitud.');});
  }
  function money(value) { return Number(value || 0).toFixed(2); }
  function renderDetails() {
    var $body = $("#purchase-request-detail-table tbody").empty();
    if (!details.length) { $body.append('<tr><td colspan="7" class="text-center text-muted">Aún no hay artículos agregados.</td></tr>'); $("#purchase-request-total").text("0.00"); return; }
    var total = 0;
    details.forEach(function (item, index) {
      var line = Number(item.quantity) * Number(item.estimated_unit_price); total += line;
      $("<tr>").append($("<td>").text(item.item_code || "-")).append($("<td>").text(item.item_name)).append($("<td>").text(item.description || "-")).append($("<td class='text-right'>").text(item.quantity)).append($("<td class='text-right'>").text(money(item.estimated_unit_price))).append($("<td class='text-right'>").text(money(line))).append($("<td>").append($("<button type='button' class='btn btn-sm btn-outline-danger btn-remove-line'><i class='fas fa-times'></i></button>").prop('disabled',saving||loadingRequest||!appuCan('purchases.manage')||(currentRequest&&currentRequest.status!=='Pendiente')).on("click", function () { if(saving||loadingRequest||!appuCan('purchases.manage')||(currentRequest&&currentRequest.status!=='Pendiente'))return;details.splice(index, 1); renderDetails();if(window.appuMarkDirty)window.appuMarkDirty(); }))).appendTo($body);
    });
    $("#purchase-request-total").text(money(total));
  }
  $.getJSON("../../modules/compras/solicitudes.php", { employees: 1 }).done(function (response) { var $select=$("#purchase-request-requester"),selected=pendingRequester||$select.val();$select.select2({ data: response.employees || [], language: "es" });if(selected&&!$select.find('option[value="'+selected+'"]').length)$select.append($('<option>').val(selected).text('Empleado '+selected));$select.val(selected||'').trigger('change.select2'); }).fail(function(){toastr.error('No se pudo cargar el catálogo de solicitantes.');});
  $.post("../../modules/productos/listar-productos-xprov.php", { ESTADO: 1 }, null, "json").done(function (products) {
    $("#purchase-request-product").empty().select2({ data: products || [], language: "es", placeholder: "Buscar por código o descripción" });
  }).fail(function () {
    toastr.error("No se pudo cargar el catálogo de artículos.");
  });
  var productRequest = 0;
  $("#purchase-request-product").on("change", function () {
    var productId = $(this).val();
    var requestId = ++productRequest;
    selectedProduct = null;
    $("#purchase-request-product-code, #purchase-request-product-description").val("");
    $("#purchase-request-add").prop("disabled", true);
    if (!productId) return;
    $.ajax({
      url: "../../modules/productos/consultar-productos.php",
      method: "POST",
      dataType: "json",
      data: { FILTER: "PROD-" + productId, ESTADO: "1" }
    }).done(function (products) {
      if (requestId !== productRequest) return;
      var product = products && products[0];
      if (!product) {
        toastr.warning("No se encontraron los datos del artículo seleccionado.");
        $("#purchase-request-product").val("").trigger("change.select2");
        return;
      }
      selectedProduct = { id: Number(productId), code: product.CODPROD, name: product.NOMBRE, description: product.DESCRIPTION || "" };
      $("#purchase-request-product-code").val(selectedProduct.code);
      $("#purchase-request-product-description").val(selectedProduct.description);
      if (!Number($("#purchase-request-price").val())) $("#purchase-request-price").val(Number(product.PRECIO || 0).toFixed(2));
      $("#purchase-request-add").prop("disabled", false);
    }).fail(function () {
      if (requestId !== productRequest) return;
      toastr.error("No se pudieron cargar los datos del artículo.");
      $("#purchase-request-product").val("").trigger("change.select2");
    });
  });
  $("#purchase-request-add").on("click", function () {
    if(saving||loadingRequest||!appuCan('purchases.manage')||(currentRequest&&currentRequest.status!=='Pendiente'))return;
    if (!selectedProduct) return toastr.warning("Busca y selecciona un artículo registrado.");
    var quantity = Number($("#purchase-request-quantity").val()), price = Number($("#purchase-request-price").val());
    if (!Number.isInteger(quantity) || quantity < 1 || !Number.isFinite(price) || price < 0) return toastr.warning("Ingresa una cantidad entera y un precio válidos.");
    var existing=details.find(function(item){return Number(item.product_id)===Number(selectedProduct.id);});
    if(existing){existing.quantity=Number(existing.quantity)+quantity;existing.estimated_unit_price=price;}else details.push({ product_id: Number(selectedProduct.id), item_code: selectedProduct.code || "", item_name: selectedProduct.name, description: selectedProduct.description || "", quantity: quantity, estimated_unit_price: price });
    selectedProduct = null; productRequest++; $("#purchase-request-product").val("").trigger("change.select2"); $("#purchase-request-product-code, #purchase-request-product-description").val(""); $("#purchase-request-add").prop("disabled", true); $("#purchase-request-quantity").val(1); $("#purchase-request-price").val(0); renderDetails();
  });
  $("#purchase-request-form").on("submit", function (event) {
    event.preventDefault();if(saving||loadingRequest||!appuCan('purchases.manage')||(currentRequest&&currentRequest.status!=='Pendiente'))return; if (!details.length) return toastr.warning("Agrega al menos un artículo.");
    var data = $(this).serializeArray(); data.push({ name: "details", value: JSON.stringify(details) });
    saving=true;setRequestMode(currentRequest);
    $.post("../../modules/compras/solicitudes.php", data).done(function (response) { toastr.success(response.message);requestEpoch++;currentRequest=null;pendingRequester=''; details = []; $("#purchase-request-form")[0].reset(); $('#purchase-request-form input[name="id"]').val(''); $("#purchase-request-requester").val("").trigger("change.select2"); setRequestMode(null); window.appuMarkSaved(); renderDetails(); load(); }).fail(function (xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : "No se pudo guardar la solicitud."); }).always(function(){saving=false;setRequestMode(currentRequest);});
  });
  function load() {
    $.getJSON("../../modules/compras/solicitudes.php").done(function (response) {
      requestRows=response.requests||[];
      var $body = $("#purchase-request-table tbody").empty();
      (response.requests || []).forEach(function (item) {
        var $row = $("<tr>").append($("<td>").text(item.number)).append($("<td>").html(appuStatusBadge(item.status))).append($("<td>").text(item.requester_name || "-")).append($("<td>").text(item.issue_date)).append($("<td>").text(item.required_date)).append($("<td class='text-right'>").text(money(item.total_estimate))).appendTo($body);
        if (String(new URLSearchParams(window.location.search).get("record_id") || "") === String(item.id)) {
          $row.addClass("table-primary");
          window.setTimeout(function () { $row[0].scrollIntoView({ behavior: "smooth", block: "center" }); }, 30);
        }
        var $actions = $("<td>");
        $("<button type='button' class='btn btn-sm btn-outline-primary mr-1'><i class='fas fa-folder-open'></i> Abrir</button>").on("click",function(){window.appuConfirmDiscard(function(){openRequest(item.id);});}).appendTo($actions);
        if (item.status === "Pendiente" && appuCan("purchases.approve")) {
          $("<button type='button' class='btn btn-sm btn-outline-success mr-1'>Aprobar</button>").on("click", function () { procesarAprobacion("purchase_request", item.id, "approve", load); }).appendTo($actions);
          $("<button type='button' class='btn btn-sm btn-outline-warning mr-1'>Rechazar</button>").on("click", function () { procesarAprobacion("purchase_request", item.id, "reject", load); }).appendTo($actions);
        }
        if ((item.status === "Pendiente" || item.status === "Aprobada")&&appuCan('purchases.manage')) $("<button type='button' class='btn btn-sm btn-outline-danger'>Cancelar</button>").on("click", function () { if (!window.confirm("¿Cancelar esta solicitud?")) return; var reason = window.prompt("Indica el motivo de cancelación:", ""); if (reason === null || $.trim(reason).length < 5) { toastr.warning("El motivo debe tener al menos 5 caracteres."); return; } $.post("../../modules/compras/solicitudes.php", { action: "cancel", id: item.id, reason: reason }).done(function (r) { toastr.success(r.message); load();if(currentRequest&&Number(currentRequest.id)===Number(item.id))openRequest(item.id); }).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||'No se pudo cancelar la solicitud.');}); }).appendTo($actions);
        $actions.appendTo($row);
      });
      filterRows();updateNavigation();
    }).fail(function(){toastr.error('No se pudieron cargar las solicitudes.');});
  }
  function updateNavigation(){var index=currentRequest?requestRows.findIndex(function(row){return Number(row.id)===Number(currentRequest.id);}):-1;$('.appu-doc-first,.appu-doc-prev').prop('disabled',saving||loadingRequest||!requestRows.length||index===0);$('.appu-doc-next,.appu-doc-last').prop('disabled',saving||loadingRequest||!requestRows.length||index===requestRows.length-1);}
  function filterRows(){var term=String($('#purchase-request-search').val()||'').replace(/\*/g,'').toLowerCase();$('#purchase-request-table tbody tr').each(function(){$(this).toggle(!term||$(this).text().toLowerCase().indexOf(term)!==-1);});}
  $('#purchase-request-search').on('input',filterRows);
  $('.appu-doc-search').off('click').on('click',function(){$('#purchase-request-search').trigger('focus');$('#purchase-request-table')[0].scrollIntoView({block:'center'});});
  [['first',0],['prev',-1],['next',1],['last',0]].forEach(function(action){$('.appu-doc-'+action[0]).off('click').on('click',function(){if(!requestRows.length)return;var index=currentRequest?requestRows.findIndex(function(row){return Number(row.id)===Number(currentRequest.id);}):-1;index=action[0]==='first'?0:action[0]==='last'?requestRows.length-1:index<0?0:index+action[1];index=Math.max(0,Math.min(requestRows.length-1,index));window.appuConfirmDiscard(function(){openRequest(requestRows[index].id);});});});
  $('.appu-doc-history').off('click').on('click',function(){if(currentRequest)mostrarHistorialEstados('purchase_request',currentRequest.id);});
  $("#purchase-request-refresh").on("click", load); renderDetails(); setRequestMode(null); load();
  var requestedId=Number(new URLSearchParams(window.location.search).get('record_id')||0);if(requestedId)openRequest(requestedId);
});
