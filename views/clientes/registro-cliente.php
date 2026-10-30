<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-user"></i>&nbsp;&nbsp;Registro de Cliente (Padrón Comercial)
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="appu-document-toolbar appu-master-toolbar" data-table="#table-clientes" data-save="#btn-save-client" role="toolbar" aria-label="Herramientas de clientes">
        <div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">MAESTRO</span><span class="appu-document-toolbar__title">Clientes</span><span class="appu-document-toolbar__hint">Crea, busca y navega entre clientes registrados.</span></div>
        <div class="appu-document-toolbar__actions"><button type="button" class="btn btn-sm btn-light appu-master-new"><i class="fas fa-file"></i><span>Nuevo</span></button><button type="button" class="btn btn-sm btn-light appu-master-find"><i class="fas fa-search"></i><span>Buscar</span></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-light appu-master-first" title="Primero"><i class="fas fa-angle-double-left"></i></button><button type="button" class="btn btn-sm btn-light appu-master-prev" title="Anterior"><i class="fas fa-angle-left"></i></button><span class="appu-master-position text-muted" aria-live="polite">0 de 0</span><button type="button" class="btn btn-sm btn-light appu-master-next" title="Siguiente"><i class="fas fa-angle-right"></i></button><button type="button" class="btn btn-sm btn-light appu-master-last" title="Último"><i class="fas fa-angle-double-right"></i></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-primary appu-master-save"><i class="fas fa-save"></i><span>Guardar</span></button></div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div style="max-width: 1140px;margin: 0 auto;">
                <form id="FRM_INSERT_CLIENTE" method="post" action="<?php echo $functions->direct_sistema(); ?>/modules/clientes/insert-update-cliente.php" enctype="multipart/form-data">
                    <input type="hidden" name="cliente_id">
                    <div class="card card-primary">
                        <div class="card-header ">
                            <div class="card-title">Datos de Cliente</div>
                            <div class="float-right" style="height: 2rem; width: 150px">
                                <input type="text" placeholder="Código de cliente" class="form-control" name="cliente_codigo" readonly>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>RUC</label>
                                        <input minlength="11" maxlength="11" type="text" class="form-control" placeholder="Ingrese RUC" name="cliente_ruc" required>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Razón Social</label>
                                        <input type="text" class="form-control" placeholder="Ingrese razón social" name="cliente_razsoc" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Nombre Comercial</label>
                                        <input type="text" class="form-control" placeholder="Ingrese nombre comercial" name="cliente_nomcom">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Teléfono</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-phone"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" pattern="[0-9--]{0,20}" placeholder="Ingrese teléfono fijo" name="cliente_telfij">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Celular</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-mobile-alt"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" pattern="[0-9--]{0,20}" placeholder="Ingrese teléfono celular" name="cliente_telcel">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Fecha de Registro</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="date" class="form-control" name="cliente_fecreg" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-danger">
                        <div class="card-header">
                            <div class="card-title">Otros datos</div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Departamento</label>
                                        <select class="form-control select2" style="width: 100%;" name="cliente_departamento" required>
                                            <option value="">Seleccione un departamento</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Provincia</label>
                                        <select class="form-control select2" style="width: 100%;" name="cliente_provincia" required>
                                            <option value="">Seleccione una provincia</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Distrito</label>
                                        <select class="form-control select2" style="width: 100%;" name="cliente_distrito" required>
                                            <option value="">Seleccione un distrito</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Dirección</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-home"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" placeholder="Ingrese una dirección" name="cliente_direccion" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Correo Electrónico</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                            </div>
                                            <input type="email" class="form-control" placeholder="Ingrese un correo electrónico" name="cliente_correo">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Pago de Comisión</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </span>
                                            </div>
                                            <input type="number" class="form-control" placeholder="Ingrese pago de comisión" name="cliente_pagocomision">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-info">
                                <div class="card-header">
                                    <div class="card-title">Contacto 1</div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Nombre</label>
                                                <input type="text" class="form-control" placeholder="Ingrese nombre de contacto" name="cliente_nomcont_1">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Celular</label>
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <i class="fas fa-mobile-alt"></i>
                                                        </span>
                                                    </div>
                                                    <input type="phone" class="form-control" name="cliente_celcont_1" pattern="[0-9--]{0,20}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-info">
                                <div class="card-header">
                                    <div class="card-title">Contacto 2</div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Nombre</label>
                                                <input type="text" class="form-control" placeholder="Ingrese nombre de contacto" name="cliente_nomcont_2">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Celular</label>
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <i class="fas fa-mobile-alt"></i>
                                                        </span>
                                                    </div>
                                                    <input type="phone" class="form-control" name="cliente_celcont_2" pattern="[0-9--]{0,20}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> 
                        </div>
                    </div>
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-handshake mr-1"></i>Condiciones comerciales</h3></div>
                        <div class="card-body"><div class="row">
                            <div class="col-md-2 form-group"><label>Moneda</label><select class="form-control" name="cliente_moneda"><option value="PEN">PEN</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div>
                            <div class="col-md-2 form-group"><label>Días de pago</label><input class="form-control" type="number" min="0" max="3650" name="cliente_dias_pago" value="0"></div>
                            <div class="col-md-2 form-group"><label>Límite de crédito</label><input class="form-control" type="number" min="0" step="0.01" name="cliente_limite_credito" value="0"></div>
                            <div class="col-md-3 form-group"><label>Lista de precios</label><select class="form-control" name="cliente_lista_precio_id"><option value="">Sin asignar</option><?php foreach ($pdo->query('SELECT id,code,name,currency FROM tbl_price_list WHERE active_status=1 ORDER BY name')->fetchAll() as $priceList): ?><option value="<?php echo (int)$priceList['id']; ?>"><?php echo e($priceList['code'].' - '.$priceList['name'].' ('.$priceList['currency'].')'); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3 form-group"><label>Vendedor asignado</label><select class="form-control" name="cliente_vendedor_id"><option value="">Sin asignar</option><?php foreach ($pdo->query("SELECT id,CONCAT(name,' ',last_name_1,' ',last_name_2) employee_name FROM tbl_employee WHERE active_status=1 ORDER BY name,last_name_1")->fetchAll() as $employee): ?><option value="<?php echo (int)$employee['id']; ?>"><?php echo e($employee['employee_name']); ?></option><?php endforeach; ?></select></div>
                        </div><small class="text-muted">Estos valores se proponen automáticamente en los nuevos documentos y pueden modificarse en cada operación.</small></div>
                    </div>
                    <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title"><i class="fas fa-toggle-on mr-1"></i>Control del registro</h3></div><div class="card-body"><div class="row"><div class="col-md-3 form-group"><label>Estado</label><select class="form-control" name="cliente_active_status"><option value="1">Activo</option><option value="0">Inactivo</option></select><small class="form-text text-muted">Los documentos nuevos solo permiten seleccionar clientes activos.</small></div></div></div></div>
                    <?php $customerCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='customer' AND active_status=1 ORDER BY id")->fetchAll(); if ($customerCustomFields): ?>
                    <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-1"></i>Campos adicionales</h3></div><div class="card-body"><div class="row"><?php foreach ($customerCustomFields as $customField): ?><div class="col-md-4 form-group"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="customer-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="customer-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
                    <?php endif; ?>
                    <?php $partnerDetailType = 'customer'; require __DIR__ . '/../template/business-partner-details.php'; ?>
                    <div class="row">
                        <div id="col-btn-save-client" class="col-md-12">
                            <button type="submit" id="btn-save-client" class="btn btn-success btn-block"><i class="fa fa-save fa-1x"></i>&nbsp;&nbsp;<font>Guardar cliente</font></button>
                        </div>
                        <div id="col-btn-delete-client" class="col-md-6">
                            <button type="button" js-id="" id="btn-delete-client" class="btn btn-outline-danger btn-block"><i class="fa fa-ban fa-1x"></i>&nbsp;&nbsp;Desactivar cliente</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-clientes" class="table table-bordered table-hover" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>RUC</th>
                                    <th>Razón Social</th>
                                    <th>Nombre Comercial</th>
                                    <th>Teléfono</th>
                                    <th>Celular</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <th>Código</th>
                                    <th>RUC</th>
                                    <th>Razón Social</th>
                                    <th>Nombre Comercial</th>
                                    <th>Teléfono</th>
                                    <th>Celular</th>
                                    <th>Estado</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
