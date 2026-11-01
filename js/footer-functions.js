$(document).ready(function() {
    // Orden operativo SAP B1: ventas, compras, inventario/logistica y tesoreria.
    // Se reubican bloques completos, conservando los permisos que ya filtro el servidor.
    var $sidebarMenu = $('.main-sidebar .nav-sidebar').first();
    if ($sidebarMenu.length) {
      var $logisticsHeader = $sidebarMenu.children('.nav-header').filter(function () {
        return $(this).text().toUpperCase().indexOf('INVENTARIO') !== -1;
      }).first();
      var $financeHeader = $sidebarMenu.children('.nav-header').filter(function () {
        return $(this).text().toUpperCase().indexOf('TESORERIA') !== -1;
      }).first();
      if ($logisticsHeader.length && $financeHeader.length) {
        var $logisticsBlock = $logisticsHeader.add($logisticsHeader.nextUntil('.nav-header'));
        $logisticsBlock.each(function () { $(this).insertBefore($financeHeader); });
      }
      // Orden operativo: primero compras/cxp y luego inventario/logistica.
      var $purchaseHeader = $sidebarMenu.children('.nav-header').filter(function () {
        return $(this).text().toUpperCase().indexOf('COMPRAS') !== -1;
      }).first();
      $logisticsHeader = $sidebarMenu.children('.nav-header').filter(function () {
        return $(this).text().toUpperCase().indexOf('INVENTARIO') !== -1;
      }).first();
      if ($purchaseHeader.length && $logisticsHeader.length) {
        var $purchaseBlock = $purchaseHeader.add($purchaseHeader.nextUntil('.nav-header'));
        $purchaseBlock.each(function () { $(this).insertBefore($logisticsHeader); });
      }
      // Los almacenes son configuración maestra y permanecen dentro de
      // Gestión; no se mezclan con las operaciones diarias de inventario.
    }

    // Navegacion uniforme para todos los grupos del lateral. Se evita que un
    // estado residual menu-close deje modulos sin desplegar al cambiar de
    // pantalla; la logica queda centralizada en lugar de repetirse por vista.
    $('.main-sidebar .has-treeview > a[href="#"]').each(function () {
      var $toggle = $(this);
      $toggle.off('click.appuTree').on('click.appuTree', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var $group = $toggle.closest('.has-treeview');
        var shouldOpen = !$group.hasClass('menu-open');
        $group.toggleClass('menu-open', shouldOpen).toggleClass('menu-close', !shouldOpen);
        $group.children('.nav-treeview').stop(true, true)[shouldOpen ? 'slideDown' : 'slideUp'](120);
        $toggle.attr('aria-expanded', shouldOpen ? 'true' : 'false');
      });
    });
    $('.main-sidebar .has-treeview > a:not([href="#"])').each(function () {
      var $link = $(this);
      $link.off('click.appuTreeArrow').on('click.appuTreeArrow', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var $group = $link.closest('.has-treeview');
        var shouldOpen = !$group.hasClass('menu-open');
        $group.toggleClass('menu-open', shouldOpen).toggleClass('menu-close', !shouldOpen);
        $group.children('.nav-treeview').stop(true, true)[shouldOpen ? 'slideDown' : 'slideUp'](120);
        $link.attr('aria-expanded', shouldOpen ? 'true' : 'false');
      });
    });
    function appuCollapseNavigation() {
      $('.main-sidebar .has-treeview').each(function () {
        var $group = $(this);
        var $link = $group.children('a');
        var $submenu = $group.children('.nav-treeview');
        var hasActiveLink = $link.hasClass('active') || $submenu.find('.nav-link.active').length > 0;
        $group.toggleClass('menu-open', hasActiveLink).toggleClass('menu-close', !hasActiveLink);
        $submenu.stop(true, true)[hasActiveLink ? 'slideDown' : 'slideUp'](120);
        $link.attr('aria-expanded', hasActiveLink ? 'true' : 'false');
      });
      var sidebar = $('.main-sidebar .sidebar')[0];
      if (sidebar) sidebar.scrollTop = 0;
    }
    // Conserva abierto el grupo de la vista actual y cierra estados heredados
    // de otros módulos después de que cargue el script específico de la vista.
    window.setTimeout(appuCollapseNavigation, 750);
    $('.main-header .dropdown-toggle').off('click.appuDropdown').on('click.appuDropdown', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var $toggle = $(this), $menu = $toggle.next('.dropdown-menu');
      $('.main-header .dropdown-menu').not($menu).removeClass('show').parent('.dropdown').removeClass('show');
      var open = !$menu.hasClass('show');
      $menu.toggleClass('show', open).parent('.dropdown').toggleClass('show', open);
      $toggle.attr('aria-expanded', open ? 'true' : 'false');
    });
    // Delegación de respaldo: algunas vistas cargan la cabecera después de
    // inicializar los plugins de AdminLTE. Así el menú nunca cae en una URL
    // terminada en # aunque el listener de Bootstrap no se haya registrado.
    $(document).off('click.appuDropdownDelegated').on('click.appuDropdownDelegated', '.main-header .dropdown-toggle', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var $toggle = $(this), $menu = $toggle.next('.dropdown-menu');
      $('.main-header .dropdown-menu').not($menu).removeClass('show').parent('.dropdown').removeClass('show');
      var open = !$menu.hasClass('show');
      $menu.toggleClass('show', open).parent('.dropdown').toggleClass('show', open);
      $toggle.attr('aria-expanded', open ? 'true' : 'false');
    });
    $(document).off('click.appuDropdownClose').on('click.appuDropdownClose', function (event) {
      if ($(event.target).closest('.main-header .dropdown').length) return;
      $('.main-header .dropdown-menu').removeClass('show').parent('.dropdown').removeClass('show');
    });

    function appuSelect2Matcher(params, data) {
      var term = $.trim(params.term || "").replace(/^\*+|\*+$/g, "").toLowerCase();
      if (!term) return data;
      if (data.children) {
        var matchingChildren = [];
        $.each(data.children, function (_, child) {
          var match = appuSelect2Matcher({ term: term }, child);
          if (match) matchingChildren.push(match);
        });
        if (matchingChildren.length) return $.extend({}, data, { children: matchingChildren });
        return null;
      }
      var text = String(data.text || "").toLowerCase();
      return text.indexOf(term) !== -1 ? data : null;
    }
    window.appuSelect2Matcher = appuSelect2Matcher;
    if ($.fn.select2 && $.fn.select2.defaults) $.fn.select2.defaults.set("matcher", appuSelect2Matcher);
    $('.select2').select2({
      language: "es",
      matcher: appuSelect2Matcher
    });

    // Socio de negocio en documentos de ventas: selector tipo SAP B1 para
    // facturas, boletas y notas de crédito que aún usan el campo legado.
    var $bpName = $("input[name='facturacion_valcliente']").first();
    if ($bpName.length && !$bpName.closest('.appu-bp-picker').length) {
      var $group = $bpName.wrap('<div class="input-group appu-bp-picker"></div>').parent();
      $group.append('<div class="input-group-append"><button type="button" class="btn btn-outline-primary appu-bp-choose" title="Elegir cliente registrado"><i class="fas fa-list"></i></button><button type="button" class="btn btn-outline-primary appu-bp-new" title="Registrar nuevo cliente"><i class="fas fa-user-plus"></i></button></div>');
      $('body').append('<div class="modal fade" id="appu-bp-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Elegir cliente registrado</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><label>Buscar por razón social, nombre comercial o RUC</label><select id="appu-bp-select" class="form-control" style="width:100%"></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="button" id="appu-bp-use" class="btn btn-primary">Usar cliente</button></div></div></div></div>');
      function applyBusinessPartner(customer) {
        var fn = window.appuAplicarClienteFactura || window.appuAplicarClienteBoleta || window.appuAplicarClienteNotaCredito;
        if (typeof fn === 'function') return fn(customer);
        $('input[name="facturacion_cliente"]').val(customer.client_id || '');
        $('input[name="facturacion_cliruc"]').val(customer.ruc || '');
        $('input[name="facturacion_valcliente"]').val(customer.business_name || customer.trade_name || '');
        $('input[name="facturacion_clidirecc"]').val(customer.address || '');
      }
      $group.on('click', '.appu-bp-new', function () { window.location.href = '../../views/clientes/registro-cliente'; });
      $group.on('click', '.appu-bp-choose', function () {
        $.getJSON('../../modules/clientes/obtener-clientes.php', { q: '' }).done(function (rows) {
          var data = (rows || []).filter(function (row) { return row.id; }).map(function (row) { return { id: row.id, text: row.label }; });
          $('#appu-bp-select').empty().select2({ data: data, language: 'es', matcher: appuSelect2Matcher, dropdownParent: $('#appu-bp-modal'), placeholder: 'Seleccione un cliente' });
          $('#appu-bp-modal').modal('show');
        }).fail(function () { toastr.error('No se pudo cargar el maestro de clientes.'); });
      });
      $(document).on('click.appuBusinessPartner', '#appu-bp-use', function () {
        var id = $('#appu-bp-select').val(); if (!id) return;
        $.getJSON('../../modules/clientes/obtener-clientes.php', { client_id: id }).done(function (response) {
          if (!response.found || !response.customer) return toastr.warning('El cliente ya no está activo.');
          applyBusinessPartner(response.customer); $('#appu-bp-modal').modal('hide');
        });
      });
    }

    // El catálogo de artículos se mantiene centralizado: desde cualquier
    // documento se puede abrir el maestro para registrar un artículo faltante.
    $("select[name='facturacion_producto'], select[name='orden_producto_select']").each(function () {
      var $select = $(this), $host = $select.closest('.form-group');
      if (!$host.length || $host.find('.appu-product-new').length || $host.closest('.modal').length) return;
      $('<button type="button" class="btn btn-sm btn-outline-primary mt-2 appu-product-new"><i class="fas fa-plus mr-1"></i>Nuevo artículo</button>')
        .appendTo($host)
        .on('click', function () { window.location.href = '../../views/productos/registro-producto'; });
    });
    $("select[name='orden_proveedor']").each(function () {
      var $select = $(this), $host = $select.closest('.form-group');
      if (!$host.length || $host.find('.appu-provider-new').length) return;
      $('<button type="button" class="btn btn-sm btn-outline-primary mt-2 appu-provider-new"><i class="fas fa-plus mr-1"></i>Nuevo proveedor</button>')
        .appendTo($host)
        .on('click', function () { window.location.href = '../../views/proveedores/registro-proveedor'; });
    });
        
    $("#table-clientes").DataTable();
    $("#table-proveedores").DataTable();
    $("#table-productos").DataTable();
    $("#table-empleados").DataTable();
    $("#table-usuarios").DataTable();
    $("#table-ordenes").DataTable();
    $("#table-cotizaciones").DataTable();
    $("#table-facturas").DataTable();

    if(!localStorage.getItem('darkMode')) {
      populateStorage();
    } else {
      setStyle();
    }
});

// Abre un registro específico al llegar desde el mapa de documentos.
// Se invoca después de cargar el catálogo del formulario para evitar carreras AJAX.
window.appuOpenRequestedRecord = function (selectSelector, buttonSelector) {
  if (window.appuRequestedRecordHandled) return false;
  var requestedId = new URLSearchParams(window.location.search).get("record_id");
  if (!requestedId || !/^\d+$/.test(requestedId)) return false;
  var $select = $(selectSelector).first();
  if (!$select.length || !$select.find("option[value='" + String(requestedId).replace(/'/g, "\\'") + "']").length) {
    if (window.toastr) toastr.warning("El documento solicitado no está disponible en el listado actual.");
    return false;
  }
  window.appuRequestedRecordHandled = true;
  $select.val(String(requestedId)).trigger("change");
  window.setTimeout(function () { $(buttonSelector).first().trigger("click"); }, 30);
  return true;
};

// Apache supplies ?url internally; it is normally absent from the browser URL.
window.appuCurrentRoute = function () {
  var route = new URLSearchParams(window.location.search).get('url');
  if (!route) { try { route = decodeURIComponent(window.location.pathname); } catch (_) { route = window.location.pathname; } }
  return String(route || '').toLowerCase();
};
window.appuNewDocumentUrl = function () {
  var url = new URL(window.location.href);
  ['record_id','receipt_id','payment_id','id','base_type','base_id','purchase_id','source_type','source_id','view_id'].forEach(function (key) { url.searchParams.delete(key); });
  url.searchParams.set('mode','create'); url.hash = '';
  return url.href;
};
window.appuDocumentTypeForRoute = function (route) {
  var page = String(route || '').toLowerCase().split('/').pop().replace(/\.php$/, '');
  if (page.indexOf('recepcion') !== -1 || page === 'ingreso-almacen') return 'goods_receipt';
  if (page.indexOf('cotizacion') !== -1) return 'quotation';
  if (page.indexOf('nota-credito') !== -1) return 'credit_note';
  if (page.indexOf('boleta') !== -1) return 'receipt';
  if (page.indexOf('factura') !== -1) return 'invoice';
  if (page.indexOf('orden-de') !== -1) return 'order';
  if (page.indexOf('compra') !== -1) return 'purchase';
  if (page.indexOf('solicitudes') !== -1) return 'purchase_request';
  if (page.indexOf('cliente') !== -1) return 'customer';
  if (page.indexOf('proveedor') !== -1) return 'provider';
  if (page.indexOf('producto') !== -1) return 'product';
  if (page.indexOf('usuario') !== -1) return 'user';
  if (page.indexOf('empleado') !== -1) return 'employee';
  return '';
};

$('form input').keydown(function (e) {
    if (e.keyCode == 13) {
        var inputs = $(this).parents("form").eq(0).find(":input");
        if (inputs[inputs.index(this) + 1] != null) {                    
            inputs[inputs.index(this) + 1].focus();
        }
        e.preventDefault();
        return false;
    }
});

$('#switch-dark-mode-item').on('click', function (e) {
  e.stopPropagation();

  switchMode();
  localStorage.setItem('darkMode', $('#switchDarkMode').prop('checked'));
});

function populateStorage() {
  localStorage.setItem('darkMode', "false");
}

function setStyle() {
  var currentMode = localStorage.getItem('darkMode');

  if (currentMode == "true"){
    $('body').addClass('dark-mode');
    $('#switchDarkMode').prop('checked', true);
  }
}

function switchMode() {
  if ( $('#switchDarkMode').is(":checked") ){
    $('body').removeClass('dark-mode');
    $('#switchDarkMode').prop('checked', false);
  }else{
    $('body').addClass('dark-mode');
    $('#switchDarkMode').prop('checked', true);
  }
}

function crear_cookie(name, value, days2expire, path) {
    var date = new Date();
    date.setTime(date.getTime() + (days2expire * 24 * 60 * 60 * 1000));
    var expires = date.toUTCString();
    document.cookie = name + '=' + value + ';' +
      'expires=' + expires + ';' +
      'path=' + path + ';';
}
  
function leer_cookie(name) {
  var cookie_value = "",
    current_cookie = "",
    name_expr = name + "=",
    all_cookies = document.cookie.split(';'),
    n = all_cookies.length;

  for (var i = 0; i < n; i++) {
    current_cookie = all_cookies[i].trim();
    if (current_cookie.indexOf(name_expr) == 0) {
      cookie_value = current_cookie.substring(name_expr.length, current_cookie.length);
      break;
    }
  }
  return cookie_value;
}
  
function eliminar_cookie(name) {
  document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
}

function appuCan(permission) {
  var auth = window.APPU_AUTH || {};
  return auth.role === "admin" || (auth.permissions || []).indexOf(permission) !== -1;
}

function procesarAprobacion(documentType, documentId, action, onSuccess) {
  var isReject = action === "reject";
  Swal.fire({
    title: isReject ? "Rechazar documento" : "Aprobar documento",
    text: isReject ? "Indica el motivo del rechazo." : "Puedes agregar un comentario de aprobación.",
    input: "textarea",
    inputPlaceholder: isReject ? "Motivo del rechazo" : "Comentario opcional",
    showCancelButton: true,
    confirmButtonColor: isReject ? "#d33" : "#28a745",
    confirmButtonText: isReject ? "Rechazar" : "Aprobar",
    cancelButtonText: "Cancelar",
    inputValidator: function (value) {
      if (isReject && !String(value || "").trim()) return "El motivo del rechazo es obligatorio.";
    }
  }).then(function (result) {
    if (!result.value && result.value !== "") return;

    $.ajax({
      url: "../../modules/aprobaciones/procesar.php",
      method: "POST",
      dataType: "json",
      data: {
        document_type: documentType,
        document_id: documentId,
        approval_action: action,
        comment: result.value || ""
      }
    }).done(function (response) {
      toastr.success(response.message || "Estado actualizado.");
      if (typeof onSuccess === "function") onSuccess(response);
    }).fail(function (xhr) {
      var response = xhr && xhr.responseJSON || {};
      var message = response.message || "No se pudo actualizar la aprobación.";
      if (!xhr || xhr.status === 0) message = "No hubo conexión con el servidor. Vuelve a intentarlo.";
      toastr.error(message);
    });
  });
}

function appuEscapeHtml(value) {
  return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    }[character];
  });
}

function appuStatusBadge(status) {
  var value = String(status == null ? "" : status).trim() || "Sin estado";
  var normalized = value.toLowerCase();
  var tone = "secondary";
  if (["pendiente", "pendiente de pago", "registrada"].indexOf(normalized) !== -1) tone = "info";
  if (["aprobado", "confirmada", "atendida", "atendido", "pagada", "en transito", "en tránsito"].indexOf(normalized) !== -1) tone = "success";
  if (["parcial", "en preparacion", "en preparación"].indexOf(normalized) !== -1) tone = "warning";
  if (["anulado", "anulada", "rechazado", "rechazada"].indexOf(normalized) !== -1) tone = "danger";
  if (["cancelada", "convertida"].indexOf(normalized) !== -1) tone = "secondary";
  return '<span class="badge badge-' + tone + ' appu-status-badge">' + appuEscapeHtml(value) + '</span>';
}

function mostrarHistorialEstados(documentType, documentId) {
  $.getJSON("../../modules/trazabilidad/listar-historial.php", {
    document_type: documentType,
    document_id: documentId
  }).done(function (response) {
    var history = response.history || [];
    var rows = history.map(function (entry) {
      var transition = entry.previous_status
        ? appuEscapeHtml(entry.previous_status) + " &rarr; " + appuEscapeHtml(entry.new_status)
        : appuEscapeHtml(entry.new_status);

      return "<tr>" +
        "<td>" + appuEscapeHtml(entry.registration_date) + "</td>" +
        "<td>" + appuEscapeHtml(entry.action) + "</td>" +
        "<td>" + transition + "</td>" +
        "<td>" + appuEscapeHtml(entry.user_name) + "</td>" +
        "<td>" + appuEscapeHtml(entry.comment || "-") + "</td>" +
        "</tr>";
    }).join("");

    if (!rows) {
      rows = '<tr><td colspan="5" class="text-center text-muted">Aun no hay cambios registrados.</td></tr>';
    }

    Swal.fire({
      title: "Historial de estados",
      html: '<div class="table-responsive text-left"><table class="table table-sm table-bordered">' +
        '<thead><tr><th>Fecha</th><th>Accion</th><th>Estado</th><th>Usuario</th><th>Comentario</th></tr></thead>' +
        "<tbody>" + rows + "</tbody></table></div>",
      width: "900px",
      confirmButtonText: "Cerrar"
    });
  });
}

/* Protección transversal de cambios sin guardar para maestros y documentos. */
window.appuFormDirty = false;
window.appuMarkDirty = function () {
  window.appuFormDirty = true;
  $('body').addClass('appu-has-unsaved-changes');
  $('.appu-document-toolbar__eyebrow').each(function () {
    var $label = $(this);
    if (!$label.data('clean-label')) $label.data('clean-label', $label.text());
    $label.text('MODIFICADO');
  });
};
window.appuMarkSaved = function () {
  window.appuFormDirty = false;
  $('body').removeClass('appu-has-unsaved-changes');
  $('.appu-document-toolbar__eyebrow').each(function () {
    var original = $(this).data('clean-label'); if (original) $(this).text(original);
  });
};
window.appuConfirmDiscard = function (onConfirm) {
  if (!window.appuFormDirty) { onConfirm(); return; }
  if (window.Swal && typeof Swal.fire === 'function') {
    Swal.fire({ title: 'Hay cambios sin guardar', text: 'Si continúas, los cambios realizados en este formulario se perderán.', type: 'warning', showCancelButton: true, confirmButtonText: 'Descartar y continuar', cancelButtonText: 'Seguir editando', focusCancel: true })
      .then(function (result) { if (result.value || result.isConfirmed) { window.appuMarkSaved(); onConfirm(); } });
  } else if (window.confirm('Hay cambios sin guardar. ¿Deseas descartarlos y continuar?')) { window.appuMarkSaved(); onConfirm(); }
};
$(function () {
  var $transactionForm = $('form').filter(function () { return ['sales-order-create-form','remission-guide-form','delivery-billing-form'].includes(this.id) || $(this).find('#btn-save-client,#btn-save-proveedor,#btn-save-product,#btn-save-user,#btn-save-employee,#btn-save-cotizprod,#btn-save-facturaprod,#btn-save-orden,#btn-save-purchase-request,#btn-save-goods-receipt').length; }).first();
  if (!$transactionForm.length) return;
  function markDirty(event) {
    if (event && event.isTrigger) return;
    window.appuMarkDirty();
  }
  $transactionForm.on('input.appuDirty change.appuDirty', ':input:not([type=hidden])', markDirty);
  $transactionForm.on('click.appuDirtyLines', '#btn-add-prodtocotiz,#btn-add-prodtofactura,#btn-add-prodtporden,#purchase-request-add,[data-action="remove-line"],.btn-remove-line,.remove-product', function (event) { if (!$(this).prop('disabled')) markDirty(event); });
  document.addEventListener('click', function (event) {
    if (!window.appuFormDirty || event.__appuConfirmed) return;
    var target=event.target.closest('#btn-select-cotizacion,#btn-select-factura,#btn-select-orden,.appu-master-toolbar~* table tbody tr');
    if (!target) return;
    event.preventDefault(); event.stopImmediatePropagation();
    window.appuConfirmDiscard(function () { window.appuMarkSaved(); var replay=new MouseEvent('click',{bubbles:true,cancelable:true,view:window}); replay.__appuConfirmed=true; target.dispatchEvent(replay); });
  }, true);
  $(window).on('beforeunload.appuDirty', function (event) { if (!window.appuFormDirty) return; event.preventDefault(); event.returnValue=''; return ''; });
  $(document).on('click.appuDirtyLinks', 'a[href]', function (event) {
    var link=this, href=$(link).attr('href');
    if (!window.appuFormDirty || !href || href.charAt(0)==='#' || /^javascript:/i.test(href) || $(link).attr('target')==='_blank') return;
    event.preventDefault(); event.stopImmediatePropagation(); window.appuConfirmDiscard(function () { window.location.href=link.href; });
  });
  $(document).ajaxSuccess(function (_event, xhr, settings) {
    var action=String($transactionForm.attr('action')||'').split('?')[0], requestUrl=String(settings.url||'').split('?')[0];
    var response=String(xhr.responseText||'').trim();
    if (action && requestUrl.indexOf(action) !== -1 && (/^OK_(INSERT|UPDATE)/.test(response) || response === '1')) window.appuMarkSaved();
  });
});

/*
 * Barra de documento estilo ERP: concentra las acciones habituales de SAP B1
 * sin duplicar botones ni modificar la lógica de cada módulo.
 */
$(function () {
  $('.appu-master-toolbar').each(function () {
    var $bar = $(this), $table = $($bar.data('table')), $form = $table.closest('.content').find('form').first(), $save = $($bar.data('save')), cursor = -1;
    if ($bar.attr('data-page-nav') === 'true') return;
    function rows() {
      if (!$table.length) return $();
      if ($.fn.dataTable && $.fn.dataTable.isDataTable($table[0])) return $table.DataTable().rows({ search: 'applied', order: 'applied' }).nodes().to$();
      return $table.find('tbody tr').filter(function () { return $(this).find('td.dataTables_empty').length === 0; });
    }
    function updatePosition() {
      var total = rows().length;
      $bar.find('.appu-master-position').text((cursor >= 0 ? cursor + 1 : 0) + ' de ' + total);
      $bar.find('.appu-master-first,.appu-master-prev').prop('disabled', !total || cursor === 0);
      $bar.find('.appu-master-next,.appu-master-last').prop('disabled', !total || cursor >= total - 1);
    }
    function openRow(index) { var $rows = rows(); if (!$rows.length) { updatePosition(); return toastr.info('No hay registros para navegar.'); } window.appuConfirmDiscard(function(){ cursor = Math.max(0, Math.min($rows.length - 1, index)); updatePosition(); $rows.eq(cursor).trigger('click'); window.appuMarkSaved(); }); }
    $bar.find('.appu-master-new').on('click', function () { window.appuConfirmDiscard(function(){ window.location.reload(); }); });
    $bar.find('.appu-master-find').on('click', function () { var $input = $table.closest('.dataTables_wrapper').find('.dataTables_filter input').first(); if ($input.length) { $input.trigger('focus').select(); } else toastr.info('El buscador aún no está disponible.'); });
    $bar.find('.appu-master-first').on('click', function () { openRow(0); });
    $bar.find('.appu-master-prev').on('click', function () { openRow(cursor < 0 ? 0 : cursor - 1); });
    $bar.find('.appu-master-next').on('click', function () { openRow(cursor < 0 ? 0 : cursor + 1); });
    $bar.find('.appu-master-last').on('click', function () { openRow(rows().length - 1); });
    $bar.find('.appu-master-save').on('click', function () { if ($save.length) $save.trigger('click'); });
    $table.on('click', 'tbody tr', function () { var $rows = rows(), index = $rows.index(this); if (index >= 0) { cursor = index; updatePosition(); } });
    $table.on('draw.dt', function () { cursor = -1; updatePosition(); });
    updatePosition();
    if (!$save.length) $bar.find('.appu-master-save').prop('disabled', true);
  });
  var baseCurrency = String((window.APPU_CONFIG && window.APPU_CONFIG.baseCurrency) || "PEN").toUpperCase();
  var legacyCurrency = baseCurrency === "PEN" ? "MN" : "ME";
  $("select[name='currency']").each(function () { if ($(this).find("option[value='" + baseCurrency + "']").length && !$(this).val()) $(this).val(baseCurrency).trigger("change"); });
  $("select[name='facturacion_tipmon'],select[name='cotizacion_tipmon'],select[name='orden_tipomoneda']").each(function () { if ($(this).find("option[value='" + legacyCurrency + "']").length && (!$(this).val() || $(this).val() === "MN")) $(this).val(legacyCurrency).trigger("change"); });
  var route = window.appuCurrentRoute();
  var $host = $(".content-header").first();
  var $save = $("#btn-save-cotizprod,#btn-save-facturaprod,#btn-save-orden,#btn-save-purchase-request,#btn-save-goods-receipt,#btn-save-client,#btn-save-proveedor,#btn-save-product,#btn-save-user,#btn-save-employee").first();
  if (!$save.length) {
    // Las vistas de resumen también necesitan la barra SAP B1 de trabajo:
    // crear, buscar y actualizar sin obligar al usuario a volver al menú.
    if ($host.length && !$(".appu-list-toolbar").length && route.indexOf("home") === -1) {
      var $listToolbar = $('<div class="appu-document-toolbar appu-list-toolbar" role="toolbar" aria-label="Herramientas de consulta"><div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">CONSULTA</span><span class="appu-document-toolbar__title"></span><span class="appu-document-toolbar__hint">Usa Buscar para localizar registros y Nuevo para iniciar una transacción.</span></div><div class="appu-document-toolbar__actions"><button type="button" class="btn btn-sm btn-primary appu-list-new"><i class="fas fa-plus"></i><span>Nuevo</span></button><button type="button" class="btn btn-sm btn-light appu-list-find"><i class="fas fa-search"></i><span>Buscar</span></button><button type="button" class="btn btn-sm btn-light appu-list-refresh"><i class="fas fa-sync-alt"></i><span>Actualizar</span></button></div></div>');
      $listToolbar.find(".appu-document-toolbar__title").text($host.find("h1,h2,.m-0").first().text().trim() || "Consulta");
      $host.after($listToolbar);
      var newTargets = route.indexOf("resumen-cotizacion") !== -1 ? "#m_registro_cotizacion" : route.indexOf("resumen-ordenes-venta") !== -1 ? "#btn-new-sales-order" : route.indexOf("resumen-factura") !== -1 ? "#m_registro_factura" : route.indexOf("resumen-boleta") !== -1 ? "#m_registro_boleta" : route.indexOf("resumen-nota") !== -1 ? "#m_registro_nota_credito" : route.indexOf("resumen-ordenes") !== -1 ? "#m_orden_compra" : route.indexOf("resumen-compra") !== -1 ? "#m_compra_interna" : route.indexOf("solicitudes") !== -1 ? "#m_solicitudes_compra" : "";
      if (!newTargets || !$(newTargets).length) $listToolbar.find(".appu-list-new").hide();
      $listToolbar.find(".appu-list-new").on("click", function () {
        var $target = $(newTargets);
        if (!$target.length) return;
        var targetHref = $target.attr("href");
        if (targetHref) window.location.href = targetHref;
        else $target.trigger("click");
      });
      $listToolbar.find(".appu-list-find").on("click", function () { var $filter = $(".dataTables_filter input,input[type='search']").filter(":visible").first(); if ($filter.length) { $filter.trigger("focus"); $filter.select(); } else toastr.info("Esta consulta no tiene un buscador disponible."); });
      $listToolbar.find(".appu-list-refresh").on("click", function () { window.location.reload(); });
    }
    return;
  }

  var documentType = window.appuDocumentTypeForRoute(route);
  var $list = $('select[name="cotizacion_listado"],select[name="facturas_listado"],select[name="orden_listado"],select[name="goods_receipt_list"]').first();
  var $select = $("#btn-select-cotizacion,#btn-select-factura,#btn-select-orden,#btn-select-goods-receipt").first();
  var $statusActions = $("#btn-cancelar-factura,#btn-anular-factura,#btn-anular-cotizprod,#btn-anular-goods-receipt,#btn-delete-orden");
  if (!$host.length || $(".appu-document-toolbar").length) return;

  var $toolbar = $('<div class="appu-document-toolbar" role="toolbar" aria-label="Herramientas del documento">' +
    '<div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">DOCUMENTO</span><span class="appu-document-toolbar__title"></span><span class="appu-document-toolbar__hint">Anular invalida el documento; cancelar lo marca como no vigente. Solo se habilita según estado y permisos.</span></div>' +
    '<div class="appu-document-toolbar__actions">' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-new" title="Nuevo documento"><i class="fas fa-file"></i><span>Nuevo</span></button>' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-search" title="Buscar documento"><i class="fas fa-search"></i><span>Buscar</span></button>' +
      '<span class="appu-document-toolbar__separator"></span>' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-first" title="Primer documento"><i class="fas fa-angle-double-left"></i></button>' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-prev" title="Documento anterior"><i class="fas fa-angle-left"></i></button>' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-next" title="Documento siguiente"><i class="fas fa-angle-right"></i></button>' +
      '<button type="button" class="btn btn-sm btn-light appu-doc-last" title="Último documento"><i class="fas fa-angle-double-right"></i></button>' +
      '<span class="appu-document-toolbar__separator"></span>' +
      '<button type="button" class="btn btn-sm btn-primary appu-doc-save" title="Guardar documento"><i class="fas fa-save"></i><span>Guardar</span></button>' +
      '<a class="btn btn-sm btn-outline-secondary appu-doc-relations" title="Ver relaciones">Relaciones</a>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary appu-doc-history" title="Ver historial"><i class="fas fa-history"></i></button>' +
      '<button type="button" class="btn btn-sm btn-outline-danger appu-doc-void" title="Anular documento: revierte stock y saldos cuando corresponda"><i class="fas fa-ban"></i><span>Anular</span></button>' +
      '<button type="button" class="btn btn-sm btn-outline-secondary appu-doc-close" title="Cancelar documento: revierte movimientos y conserva la trazabilidad"><i class="fas fa-times-circle"></i><span>Cancelar</span></button>' +
    '</div></div>');
  var title = $host.find("h1,h2,.m-0").first().text().trim().replace(/\s+/g, " ");
  $toolbar.find(".appu-document-toolbar__title").text(title || "Documento");
  $host.after($toolbar);

  function activateDocument(index) {
    if (!$list.length || !$list.find("option").length) return;
    var options = $list.find("option").filter(function () { return $(this).val() !== ""; });
    if (!options.length) return;
    var current = Math.max(0, options.index($list.find("option:selected")));
    var target = Math.max(0, Math.min(options.length - 1, index));
    window.appuConfirmDiscard(function () {
      $list.val(options.eq(target).val()).trigger("change");
      window.setTimeout(function () { if ($select.length && !$select.prop("disabled")) { $select.trigger("click"); window.appuMarkSaved(); } }, 30);
    });
  }
  function currentDocumentId() {
    var $activeAction = $statusActions.filter(":visible").first();
    var id = $activeAction.attr("js-id") || $save.attr("js-id") || $("input[name='id_cotizacion'],input[name='id_factura'],input[name='orden_id'],#goods-receipt-id,input[name='purchase_request_id']").first().val();
    return id && id !== "0" ? id : "";
  }
  $toolbar.find(".appu-doc-new").on("click", function () { window.appuConfirmDiscard(function(){ window.location.href = window.appuNewDocumentUrl(); }); });
  $toolbar.find(".appu-doc-search").on("click", function () {
    if (!$list.length) { var $legacyList = $("#btn-product-list").first(); if ($legacyList.length) return $legacyList.trigger("click"); return; }
    $list.select2("open");
  });
  $toolbar.find(".appu-doc-first").on("click", function () { activateDocument(0); });
  $toolbar.find(".appu-doc-prev").on("click", function () {
    var options = $list.find("option").filter(function () { return $(this).val() !== ""; });
    activateDocument(Math.max(0, options.index($list.find("option:selected")) - 1));
  });
  $toolbar.find(".appu-doc-next").on("click", function () {
    var options = $list.find("option").filter(function () { return $(this).val() !== ""; });
    activateDocument(Math.min(options.length - 1, options.index($list.find("option:selected")) + 1));
  });
  $toolbar.find(".appu-doc-last").on("click", function () { activateDocument($list.find("option").filter(function () { return $(this).val() !== ""; }).length - 1); });
  $toolbar.find(".appu-doc-save").on("click", function () { $save.trigger("click"); });
  $toolbar.find(".appu-doc-void").on("click", function () {
    var $cancel = $("#btn-anular-factura,#btn-anular-cotizprod,#btn-anular-goods-receipt,#btn-delete-orden").filter(":not(:disabled)").first();
    if ($cancel.length) $cancel.trigger("click");
    else toastr.info("Selecciona un documento para habilitar su cancelación.");
  });
  $toolbar.find(".appu-doc-close").on("click", function () {
    var $close = $("#btn-cancelar-factura").filter(":visible:not(:disabled)").first();
    if ($close.length) $close.trigger("click");
    else toastr.info("Selecciona un documento vigente para habilitar la cancelacion.");
  });
  function syncStatusActions() {
    var record = currentDocumentId(), flowPermission = ['quotation','invoice','receipt','credit_note'].includes(documentType) ? 'sales.view' : 'purchases.view';
    var supportsFlow = ['quotation','invoice','receipt','credit_note','order','purchase','goods_receipt','purchase_request'].includes(documentType) && appuCan(flowPermission);
    $toolbar.find('.appu-doc-relations').toggle(!!record && supportsFlow).attr('href', record && supportsFlow ? '../../views/trazabilidad/flujo?type=' + documentType + '&id=' + encodeURIComponent(record) : null);
    $toolbar.find(".appu-doc-void").prop("disabled", !record || !$("#btn-anular-factura,#btn-anular-cotizprod,#btn-anular-goods-receipt,#btn-delete-orden").filter(":not(:disabled)").length);
    $toolbar.find(".appu-doc-close").prop("disabled", !record || !$("#btn-cancelar-factura").filter(":visible:not(:disabled)").length);
  }
  syncStatusActions();
  window.setInterval(syncStatusActions, 500);
  $toolbar.find(".appu-doc-history").on("click", function () {
    var id = currentDocumentId();
    if (!id || typeof mostrarHistorialEstados !== "function") return toastr.info("Selecciona un documento para consultar su historial.");
    mostrarHistorialEstados(documentType, id);
  });
  if (!$list.length) $toolbar.find(".appu-doc-search,.appu-doc-first,.appu-doc-prev,.appu-doc-next,.appu-doc-last").prop("disabled", true);
  if (!$("#btn-anular-factura,#btn-anular-cotizprod,#btn-anular-goods-receipt,#btn-delete-orden").length) $toolbar.find(".appu-doc-void").prop("disabled", true);
  if (!$("#btn-cancelar-factura").length) $toolbar.find(".appu-doc-close").prop("disabled", true);
  if (["customer", "provider", "product", "user", "employee"].indexOf(documentType) !== -1) $toolbar.find(".appu-doc-history").prop("disabled", true);

  // Atajos consistentes para los formularios documentales (estilo SAP B1).
  $(document).on("keydown.appuDocumentToolbar", function (event) {
    if (event.altKey && event.key === "ArrowLeft" && !$toolbar.find(".appu-doc-prev").prop("disabled")) {
      event.preventDefault(); $toolbar.find(".appu-doc-prev").trigger("click"); return;
    }
    if (event.altKey && event.key === "ArrowRight" && !$toolbar.find(".appu-doc-next").prop("disabled")) {
      event.preventDefault(); $toolbar.find(".appu-doc-next").trigger("click"); return;
    }
    if (!event.ctrlKey || event.altKey || event.shiftKey) return;
    var key = String(event.key || "").toLowerCase();
    if (key === "n") { event.preventDefault(); $toolbar.find(".appu-doc-new").trigger("click"); }
    else if (key === "f") { event.preventDefault(); $toolbar.find(".appu-doc-search").trigger("click"); }
    else if (key === "s") { event.preventDefault(); $toolbar.find(".appu-doc-save").trigger("click"); }
  });
});
