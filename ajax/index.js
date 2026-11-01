$(function () {
  "use strict";
  $("#m_gestion").addClass("active");
  var $rolesCard = $("#roles-list").closest(".card");
  var $settingsGrid = $("#custom-field-form").closest(".col-lg-7").parent(".row");
  if ($rolesCard.length && $settingsGrid.length) $rolesCard.detach().insertAfter($settingsGrid);

  var managementSections = [
    { selector: "#company-form", id: "management-company", label: "Empresa", icon: "fa-building" },
    { selector: "#value-list-form", id: "management-values", label: "Valores", icon: "fa-list" },
    { selector: "#custom-field-form", id: "management-fields", label: "Campos", icon: "fa-tags" },
    { selector: "#roles-list", id: "management-profiles", label: "Perfiles", icon: "fa-user-shield" },
    { selector: "#document-series-form", id: "management-series", label: "Series", icon: "fa-hashtag" },
    { selector: "#financial-account-form", id: "management-accounts", label: "Cajas y bancos", icon: "fa-university" },
    { selector: "#price-list-form", id: "management-prices", label: "Precios", icon: "fa-tags" },
    { selector: "#external-integration-form", id: "management-integrations", label: "Integraciones", icon: "fa-plug" }
  ];
  var $managementNav = $('<nav class="appu-management-nav" aria-label="Secciones de gestión"><div class="container-fluid"><div class="nav nav-pills" role="navigation"></div></div></nav>');
  managementSections.forEach(function (section) {
    var $target = $(section.selector).closest(".card").first();
    if (!$target.length) return;
    $target.attr("id", section.id).addClass("appu-management-section");
    $('<a class="nav-link" role="link">').attr("href", "#" + section.id)
      .append($("<i>").addClass("fas " + section.icon + " mr-1"))
      .append(document.createTextNode(section.label))
      .appendTo($managementNav.find(".nav"));
  });
  $(".content-header").after($managementNav);
  if ($rolesCard.length) {
    $('<div class="alert alert-info appu-profile-help" role="note">Cada perfil reúne permisos por módulo. Asigna solo los accesos necesarios; los cambios de permisos se aplican en la siguiente solicitud del usuario.</div>')
      .prependTo($rolesCard.find(".card-body"));
  }
  $('<div class="form-row"><div class="form-group col-md-3"><label>Ubigeo SUNAT</label><input name="ubigeo" class="form-control" maxlength="6" pattern="[0-9]{6}" required></div><div class="form-group col-md-3"><label>Departamento</label><input name="department" class="form-control" maxlength="100" required></div><div class="form-group col-md-3"><label>Provincia</label><input name="province" class="form-control" maxlength="100" required></div><div class="form-group col-md-3"><label>Distrito</label><input name="district" class="form-control" maxlength="100" required></div></div>').insertBefore('#company-form button[type="submit"]');
  function esc(v) { return appuEscapeHtml(String(v == null ? "" : v)); }
  function fail(x, message) { var response = x.responseJSON || {}; toastr.error(response.message || (typeof response.error === 'string' ? response.error : message)); }
  function post(form, url, done) { var $form=$(form); if($form.data('submitting'))return; appuClearValidation(form); var data=$form.serialize();$form.data('submitting',true).find('[type=submit]').prop('disabled',true);$.post(url, data).done(function (r) { $form.data('management-dirty',false);toastr.success(r.message || "Configuración guardada."); if (done) done(r); }).fail(function (x) { if (x.responseJSON && x.responseJSON.errors) appuShowValidation(form, x.responseJSON); else fail(x, "No se pudo guardar la configuración."); }).always(function(){$form.data('submitting',false).find('[type=submit]').prop('disabled',false);}); }
  $(document).on('input change','.content-wrapper form :input',function(){$(this).closest('form').data('management-dirty',true);});
  $('<details class="appu-management-advanced mb-3"><summary><i class="fas fa-plus-circle mr-1"></i>Crear un perfil nuevo</summary><form id="role-create-form" class="border rounded p-3 mt-2"><input type="hidden" name="action" value="role_create"><div class="form-row"><div class="form-group col-md-3"><label>Código del nuevo perfil</label><input name="code" class="form-control" maxlength="50" pattern="[a-z][a-z0-9_]{1,49}" placeholder="ventas_corporativas" required></div><div class="form-group col-md-4"><label>Nombre</label><input name="name" class="form-control" maxlength="100" required></div><div class="form-group col-md-5"><label>Descripción</label><input name="description" class="form-control" maxlength="255"></div></div><button class="btn btn-primary" type="submit">Crear perfil</button><small class="form-text text-muted">El perfil nuevo inicia sin permisos. Asígnalos en la tarjeta del perfil y guarda.</small></form></details>').insertBefore('#roles-list');
  $('<input type="hidden" name="option_id">').appendTo('#value-option-form');
  $('<button type="button" class="btn btn-outline-secondary ml-2" id="value-option-new">Nueva opción</button>').appendTo('#value-option-form');
  [['LANDED_COST','Costos de entrega'],['INVENTORY_ENTRY','Entrada de inventario'],['INVENTORY_ISSUE','Salida de inventario']].forEach(function(option){$('<option>').val(option[0]).text(option[1]).appendTo('#document-series-form [name=document_type]');});

  var $customConfig = $('#custom-field-form');
  var $metadata = $('<div class="row"></div>');
  [
    ['default_value', 'Valor por defecto', 'text'], ['max_length', 'Longitud máxima', 'number'],
    ['min_value', 'Mínimo numérico', 'number'], ['max_value', 'Máximo numérico', 'number'],
    ['display_order', 'Orden', 'number'], ['section', 'Sección', 'text'],
    ['validation_pattern', 'Patrón de validación (opcional)', 'text']
  ].forEach(function (entry) {
    var $group = $('<div class="form-group col-md-4">').appendTo($metadata);
    $('<label>').attr('for', 'custom-config-' + entry[0]).text(entry[1]).appendTo($group);
    $('<input class="form-control">').attr({ id: 'custom-config-' + entry[0], name: entry[0], type: entry[2], step: 'any' }).appendTo($group);
  });
  [['searchable', 'Usar en búsquedas', '0'], ['copy_to_target', 'Copiar a documento destino compatible', '0'], ['visible', 'Visible', '1']].forEach(function (entry) {
    var $group = $('<div class="form-group col-md-4">').appendTo($metadata);
    $('<label>').attr('for', 'custom-config-' + entry[0]).text(entry[1]).appendTo($group);
    $('<select class="form-control"><option value="0">No</option><option value="1">Sí</option></select>').attr({ id: 'custom-config-' + entry[0], name: entry[0] }).val(entry[2]).appendTo($group);
  });
  var $advancedFields = $('<details class="appu-management-advanced mb-3"><summary><i class="fas fa-sliders-h mr-1"></i>Opciones avanzadas del campo</summary><div class="row pt-3"></div></details>');
  $advancedFields.find('.row').append($metadata.children());
  $customConfig.find('[type=submit]').before($advancedFields);
  $customConfig.find('[name=visible] option[value="1"]').prop('defaultSelected', true);
  $customConfig.attr('novalidate', 'novalidate');
  $(document).on('click', '.js-edit-custom-field', function () {
    var field = $(this).data('field');
    Object.keys(field).forEach(function (key) { $customConfig.find('[name]').filter(function () { return this.name === key; }).val(field[key] == null ? '' : field[key]); });
    $customConfig.find('[name=field_type]').trigger('change');
    $customConfig[0].scrollIntoView({ block: 'center' }); $customConfig.find('[name=label]').trigger('focus');
  });

  function renderLists(lists) {
    var selectedList=$('#value-list-select').val();
    $("#value-list-select").html(lists.filter(function (l) { return Number(l.active_status) === 1; }).map(function (l) { return "<option value='" + Number(l.id) + "'>" + esc(l.name) + " (" + esc(l.code) + ")</option>"; }).join(""));
    var $host = $("#value-lists").empty();
    lists.forEach(function (list) {
      var $box = $("<div class='mb-3 border rounded p-2'><strong></strong><div class='table-responsive mt-2'><table class='table table-sm table-bordered mb-0'><thead><tr><th>Opción</th><th>Código</th><th>Estado</th><th>Acción</th></tr></thead><tbody></tbody></table></div></div>").appendTo($host);
      $box.find("strong").text(list.name + " (" + list.code + ")");
      (list.options || []).forEach(function (o) { var active = Number(o.active_status) === 1; var $button = $("<button type='button' class='btn btn-sm js-toggle-value-option'></button>").addClass(active ? "btn-outline-danger" : "btn-outline-success").text(active ? "Desactivar" : "Activar").data({ id: o.id, active: active ? 0 : 1 });var $edit=$('<button type="button" class="btn btn-sm btn-outline-primary mr-1 js-edit-value-option">Editar</button>').data('option',o); $("<tr>").append($("<td>").text(o.label)).append($("<td>").text(o.value_code)).append($("<td>").text(active ? "Activo" : "Inactivo")).append($("<td>").append($edit,$button)).appendTo($box.find("tbody")); });
    });
    if (!lists.length) $host.html("<span class='text-muted'>Aún no hay listas configuradas.</span>");
    if(selectedList)$('#value-list-select').val(selectedList);
  }

  function renderFields(fields) {
    var labels = {}; $("#custom-field-form [name='entity'] option").each(function () { labels[$(this).val()] = $(this).text(); });
    var rows = fields.map(function (f) { var active = Number(f.active_status) === 1; return "<tr><td>" + esc(labels[f.entity] || f.entity) + "</td><td>" + esc(f.label) + " <small class='text-muted'>[" + esc(f.field_key) + "]</small></td><td>" + esc(f.field_type) + (f.value_list_code ? " · " + esc(f.value_list_code) : "") + "</td><td>" + (Number(f.required_status) === 1 ? "Sí" : "No") + "</td><td>" + (active ? "Activo" : "Inactivo") + "</td><td><button type='button' class='btn btn-sm " + (active ? "btn-outline-danger" : "btn-outline-success") + " js-toggle-custom-field' data-id='" + Number(f.id) + "' data-active='" + (active ? 0 : 1) + "'>" + (active ? "Desactivar" : "Activar") + "</button></td></tr>"; });
    $("#custom-fields").html(rows.length ? "<div class='table-responsive'><table class='table table-sm table-bordered'><thead><tr><th>Entidad</th><th>Campo</th><th>Tipo/lista</th><th>Obligatorio</th><th>Estado</th><th>Acción</th></tr></thead><tbody>" + rows.join("") + "</tbody></table></div>" : "<span class='text-muted'>Aún no hay campos definidos.</span>");
  }

  function addCustomFieldEditButtons(fields) {
    $('#custom-fields tbody tr').each(function (index) {
      $('<button type="button" class="btn btn-sm btn-outline-primary mr-1 js-edit-custom-field">Editar</button>').data('field', fields[index]).prependTo($(this).children('td').last());
    });
  }

  function permissionGroupName(code) {
    var labels = { dashboard: "Inicio", customers: "Clientes", providers: "Proveedores", employees: "Empleados", users: "Usuarios", sales: "Ventas", purchases: "Compras", inventory: "Inventario", logistics: "Logística", finance: "Finanzas", management: "Gestión", reports: "Reportes" };
    var key = String(code || "").split(".")[0];
    return { key: key || "other", label: labels[key] || "Otros permisos" };
  }
  function syncPermissionGroup($group) {
    var $checks = $group.find(".js-role-permission");
    var checked = $checks.filter(":checked").length;
    $group.find(".js-role-category-all").prop("checked", checked === $checks.length && checked > 0).prop("indeterminate", checked > 0 && checked < $checks.length);
    var $form = $group.closest(".role-form");
    $form.find(".appu-role-card__header .badge").text($form.find(".js-role-permission:checked").length + " permisos");
  }
  function renderRoles(roles, permissions) {
    var markup = roles.map(function (r) {
      var selected = (r.permission_ids || []).map(Number);
      var groups = {};
      permissions.forEach(function (p) {
        var category = permissionGroupName(p.code);
        if (!groups[category.key]) groups[category.key] = { label: category.label, permissions: [] };
        groups[category.key].permissions.push(p);
      });
      var groupMarkup = Object.keys(groups).sort(function (a, b) { return groups[a].label.localeCompare(groups[b].label, "es"); }).map(function (key) {
        var group = groups[key];
        var checks = group.permissions.map(function (p) {
          var checked = selected.indexOf(Number(p.id)) !== -1;
          return "<div class='col-sm-6 col-lg-4'><label class='appu-permission-option'><input type='checkbox' class='js-role-permission' data-category='" + esc(key) + "' name='permission_ids[]' value='" + Number(p.id) + "'" + (checked ? " checked" : "") + "> <span>" + esc(p.name) + "</span><small>" + esc(p.code) + "</small></label></div>";
        }).join("");
        return "<fieldset class='appu-permission-group'><legend><span>" + esc(group.label) + "</span><label class='appu-permission-select-all'><input type='checkbox' class='js-role-category-all'> Seleccionar todos</label></legend><div class='row'>" + checks + "</div></fieldset>";
      }).join("");
      return "<form class='role-form appu-role-card mb-3'><input type='hidden' name='action' value='role'><input type='hidden' name='role_id' value='" + Number(r.id) + "'><div class='appu-role-card__header'><div><h4>" + esc(r.name) + "</h4><span class='text-muted'>" + esc(r.description || "Sin descripción") + " · Código: " + esc(r.code) + "</span></div><span class='badge badge-secondary'>" + selected.length + " permisos</span></div><div class='form-row'><div class='form-group col-md-6'><label>Nombre del perfil</label><input name='name' class='form-control' value='" + esc(r.name) + "' required></div><div class='form-group col-md-6'><label>Descripción</label><input name='description' class='form-control' maxlength='255' value='" + esc(r.description) + "'></div></div><fieldset><legend class='appu-permissions-heading'>Accesos por módulo</legend><div class='row'>" + groupMarkup + "</div></fieldset><button class='btn btn-primary' type='submit'><i class='fas fa-save mr-1'></i>Guardar perfil y permisos</button></form>";
    }).join("");
    $("#roles-list").html(markup || "<div class='alert alert-light border mb-0'>No hay perfiles configurados.</div>");
    $("#roles-list .appu-permission-group").each(function () { syncPermissionGroup($(this)); });
  }

  $(document).on("change", ".js-role-category-all", function () {
    var $group = $(this).closest(".appu-permission-group");
    $group.find(".js-role-permission").prop("checked", this.checked);
    syncPermissionGroup($group);
  });
  $(document).on("change", ".js-role-permission", function () { syncPermissionGroup($(this).closest(".appu-permission-group")); });

  function renderAccounts(accounts) { $("#financial-accounts-table tbody").html(accounts.map(function (a) { return "<tr><td>" + esc(a.code) + "</td><td>" + esc(a.name) + "</td><td>" + esc(a.account_type) + "</td><td>" + esc(a.currency) + "</td><td>" + esc(a.account_number || "-") + "</td><td>" + (Number(a.active_status) === 1 ? "Activo" : "Inactivo") + "</td><td><button type='button' class='btn btn-sm btn-outline-primary financial-account-edit' data-account='" + esc(JSON.stringify(a)) + "'>Editar</button></td></tr>"; }).join("")); }
  function renderPriceLists(lists) { $("#price-lists-table tbody").html(lists.map(function (a) { return "<tr><td>"+esc(a.code)+"</td><td>"+esc(a.name)+"</td><td>"+esc(a.currency)+"</td><td>"+Number(a.factor).toFixed(4)+"</td><td>"+(Number(a.active_status)===1?"Activo":"Inactivo")+"</td><td><button type='button' class='btn btn-sm btn-outline-primary price-list-edit' data-list='"+esc(JSON.stringify(a))+"'>Editar</button></td></tr>"; }).join("")); }

  function renderConnectedUsers(users){$("#connected-users").html(users.length ? users.map(function(u){return "<span class='badge badge-secondary mr-2 mb-1'><i class='fas fa-circle text-success mr-1'></i>"+esc(u.display_name)+" ("+esc(u.username)+")</span>";}).join('') : "<span class='text-muted'>No hay usuarios activos en los últimos 5 minutos.</span>");}
  function load() { return $.getJSON("../../modules/gestion/configuracion.php").done(function (d) { if(!$('#company-form').data('management-dirty'))Object.keys(d.company || {}).forEach(function (key) { $("#company-form [name='" + key + "']").val(d.company[key]); }); renderLists(d.lists || []); renderFields(d.fields || []); addCustomFieldEditButtons(d.fields || []); if(!$('.role-form').filter(function(){return $(this).data('management-dirty');}).length)renderRoles(d.roles || [], d.permissions || []); renderAccounts(d.financial_accounts || []); renderPriceLists(d.price_lists || []); renderConnectedUsers(d.connected_users||[]); }).fail(function (x) { fail(x, "No se pudo cargar Gestión."); }); }

  function loadSeries() { return $.getJSON("../../modules/gestion/series.php").done(function (d) { $("#document-series-table tbody").html((d.series || []).map(function (s) { return "<tr><td>" + esc(s.document_type) + "</td><td>" + esc(s.series) + "</td><td><input class='form-control form-control-sm series-next' type='number' min='1' value='" + Number(s.next_number) + "'></td><td><input class='form-control form-control-sm series-padding' type='number' min='1' max='12' value='" + Number(s.number_padding) + "'></td><td><select class='form-control form-control-sm series-active'><option value='1'" + (Number(s.active_status) === 1 ? " selected" : "") + ">Sí</option><option value='0'" + (Number(s.active_status) !== 1 ? " selected" : "") + ">No</option></select></td><td><button type='button' class='btn btn-sm btn-primary series-save' data-id='" + Number(s.id) + "'>Guardar</button></td></tr>"; }).join("")); }).fail(function (x) { fail(x, "No se pudieron cargar las series."); }); }

  function loadIntegrations() { return $.getJSON("../../modules/gestion/integraciones.php").done(function (d) { $("#external-integrations-list").html((d.integrations || []).map(function (i) { var active = Number(i.active_status) === 1, hasUrl = String(i.base_url || "").trim() !== ""; return "<div class='d-inline-block border rounded p-2 mr-2 mb-2'><strong>" + esc(i.name) + "</strong><br><small>" + esc(i.integration_type) + " · " + (active ? "Activo" : "Inactivo") + (hasUrl ? " · URL configurada" : " · Sin URL") + "</small><br><button type='button' class='btn btn-sm mt-1 js-toggle-integration " + (active ? "btn-outline-danger" : "btn-outline-success") + "' data-integration='" + esc(JSON.stringify(i)) + "' data-active='" + (active ? 0 : 1) + "'>" + (active ? "Desactivar" : "Activar") + "</button> " + (hasUrl ? "<button type='button' class='btn btn-sm btn-outline-primary mt-1 js-test-integration' data-code='" + esc(i.code) + "'>Probar</button>" : "") + "</div>"; }).join("") || "<span class='text-muted'>No hay integraciones configuradas.</span>"); }); }

  $("#company-form").on("submit", function (e) { e.preventDefault(); post(this, "../../modules/gestion/configuracion.php", load); });
  $('#role-create-form').on('submit',function(e){e.preventDefault();var form=this;post(form,'../../modules/gestion/configuracion.php',function(){form.reset();load();});});
  $(document).on('click','.js-edit-value-option',function(){var option=$(this).data('option'),$form=$('#value-option-form');Object.keys(option).forEach(function(key){$form.find('[name]').filter(function(){return this.name===key;}).val(option[key]);});$form.find('[name=option_id]').val(option.id);$form.find('[name=value_code]').prop('readonly',true);$form.find('[name=label]').trigger('focus');});
  $('#value-option-new').on('click',function(){$('#value-option-form')[0].reset();$('#value-option-form [name=option_id]').val('');$('#value-option-form').data('management-dirty',false).find('[name=value_code]').prop('readonly',false);});
  $("#value-list-form,#value-option-form,#custom-field-form,#financial-account-form,#price-list-form").on("submit", function (e) { e.preventDefault(); var form = this; post(form, "../../modules/gestion/configuracion.php", function () { form.reset();$(form).find('[name=id],[name=option_id]').val('');$(form).find('[name=value_code]').prop('readonly',false); load(); }); });
  $(document).on("submit", ".role-form", function (e) { e.preventDefault(); post(this, "../../modules/gestion/configuracion.php", load); });
  $(document).on("click", ".js-toggle-custom-field", function () { $.post("../../modules/gestion/configuracion.php", { action: "custom_field_toggle", field_id: $(this).data("id"), active_status: $(this).data("active") }).done(function (r) { toastr.success(r.message); load(); }).fail(function (x) { fail(x, "No se pudo actualizar el campo."); }); });
  $(document).on("click", ".js-toggle-value-option", function () { $.post("../../modules/gestion/configuracion.php", { action: "value_option_toggle", option_id: $(this).data("id"), active_status: $(this).data("active") }).done(function (r) { toastr.success(r.message); load(); }).fail(function (x) { fail(x, "No se pudo actualizar la opción."); }); });
  $(document).on("click", ".financial-account-edit", function () { var a = $(this).data("account"); if (typeof a === "string") a = JSON.parse(a); Object.keys(a).forEach(function (key) { $("#financial-account-form [name='" + key + "']").val(a[key]); }); });
  $(document).on("click", ".price-list-edit", function () { var a=$(this).data("list"); if(typeof a==="string")a=JSON.parse(a); Object.keys(a).forEach(function(key){$("#price-list-form [name='"+key+"']").val(a[key]);}); });
  $(document).on("click", ".series-save", function () { var $r = $(this).closest("tr"); $.post("../../modules/gestion/series.php", { id: $(this).data("id"), next_number: $r.find(".series-next").val(), number_padding: $r.find(".series-padding").val(), active_status: $r.find(".series-active").val() }).done(function (x) { toastr.success(x.message); loadSeries(); }).fail(function (x) { fail(x, "No se pudo actualizar la serie."); }); });
  $("#document-series-form").on("submit", function (e) { e.preventDefault(); var form = this; $.post("../../modules/gestion/series.php", $(form).serialize() + "&action=create").done(function (r) { toastr.success(r.message); form.reset(); loadSeries(); }).fail(function (x) { fail(x, "No se pudo crear la serie."); }); });
  $("#external-integration-form").on("submit", function (e) { e.preventDefault(); var form = this; post(form, "../../modules/gestion/integraciones.php", function () { form.reset(); loadIntegrations(); }); });
  $(document).on("click", ".js-toggle-integration", function () { var i = $(this).data("integration"); if (typeof i === "string") i = JSON.parse(i); $.post("../../modules/gestion/integraciones.php", { code: i.code, name: i.name, integration_type: i.integration_type, base_url: i.base_url || "", active_status: $(this).data("active") }).done(function (r) { toastr.success(r.message); loadIntegrations(); }).fail(function (x) { fail(x, "No se pudo actualizar la integración."); }); });
  $(document).on("click", ".js-test-integration", function () { var $b = $(this).prop("disabled", true).text("Probando..."); $.post("../../modules/gestion/integraciones.php", { action: "test", code: $b.data("code") }).done(function (r) { toastr.success((r.message || "Integración disponible") + (r.http_status ? " HTTP " + r.http_status : "") + " · " + Number(r.elapsed_ms || 0) + " ms"); }).fail(function (x) { fail(x, "No se pudo probar la integración."); }).always(function () { $b.prop("disabled", false).text("Probar"); }); });
  $("#custom-field-form [name='field_type']").on("change", function () { var list = $(this).val() === "select"; $("#custom-field-form [name='value_list_code']").prop("required", list).closest(".form-group").toggle(list); }).trigger("change");
  load(); loadSeries(); loadIntegrations(); window.setInterval(function(){$.getJSON('../../modules/gestion/configuracion.php').done(function(data){renderConnectedUsers(data.connected_users||[]);});}, 60000);
});
