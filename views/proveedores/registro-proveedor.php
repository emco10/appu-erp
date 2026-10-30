<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-people-carry"></i>&nbsp;&nbsp;Registro de Proveedor
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="appu-document-toolbar appu-master-toolbar" data-table="#table-proveedores" data-save="#btn-save-proveedor" role="toolbar" aria-label="Herramientas de proveedores">
        <div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">MAESTRO</span><span class="appu-document-toolbar__title">Proveedores</span><span class="appu-document-toolbar__hint">Crea, busca y navega entre proveedores registrados.</span></div>
        <div class="appu-document-toolbar__actions"><button type="button" class="btn btn-sm btn-light appu-master-new"><i class="fas fa-file"></i><span>Nuevo</span></button><button type="button" class="btn btn-sm btn-light appu-master-find"><i class="fas fa-search"></i><span>Buscar</span></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-light appu-master-first" title="Primero"><i class="fas fa-angle-double-left"></i></button><button type="button" class="btn btn-sm btn-light appu-master-prev" title="Anterior"><i class="fas fa-angle-left"></i></button><span class="appu-master-position text-muted" aria-live="polite">0 de 0</span><button type="button" class="btn btn-sm btn-light appu-master-next" title="Siguiente"><i class="fas fa-angle-right"></i></button><button type="button" class="btn btn-sm btn-light appu-master-last" title="Último"><i class="fas fa-angle-double-right"></i></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-primary appu-master-save"><i class="fas fa-save"></i><span>Guardar</span></button></div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div style="max-width: 1140px;margin: 0 auto;">
                <form id="FRM_INSERT_PROVEEDOR" method="post" action="<?php echo $functions->direct_sistema(); ?>/modules/proveedores/insert-update-proveedor.php" enctype="multipart/form-data">
                    <input type="hidden" name="proveedor_id" value="">
                    <div class="card card-primary">
                        <div class="card-header">
                            <div class="card-title">Datos de Empresa</div>
                            <div class="card-tools">
                                <div class="float-left" style="height: 2rem; width: 170px">
                                    <input type="text" placeholder="Código de proveedor" class="form-control" name="proveedor_codigo" readonly>
                                </div>
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar">
                                     <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>RUC Proveedor</label>
                                        <input type="text" class="form-control" name="proveedor_numero" 
                                        placeholder="Número de proveedor" required>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Razón Social</label>
                                        <input type="text" class="form-control" placeholder="Ingrese razón social" name="proveedor_razsoc" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>País</label>
                                        <input type="text" class="form-control" placeholder="Ingrese país" name="proveedor_pais" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Ciudad</label>
                                        <input type="text" class="form-control" placeholder="Ingrese ciudad" name="proveedor_ciudad" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Distrito</label>
                                        <input type="text" class="form-control" placeholder="Ingrese distrito" name="proveedor_distrito" >
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label>Dirección</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-home"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" placeholder="Ingrese dirección" name="proveedor_direccion" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fecha de Registro</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="date" class="form-control" name="proveedor_fecreg" value="<?php echo date('Y-m-d'); ?>" readonly required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    
                                </div>
                                <div class="col-md-6">
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-info">
                                <div class="card-header">
                                    <div class="card-title">Contacto 1</div>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Nombre</label>
                                                <input type="text" class="form-control" placeholder="Ingrese nombre de contacto" name="proveedor_contnom_1">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Teléfono</label>
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <i class="fas fa-phone"></i>
                                                        </span>
                                                    </div>
                                                    <input type="phone" class="form-control" name="proveedor_conttelef_1">
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
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Nombre</label>
                                                <input type="text" class="form-control" placeholder="Ingrese nombre de contacto" name="proveedor_contnom_2">
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Teléfono</label>
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <i class="fas fa-phone"></i>
                                                        </span>
                                                    </div>
                                                    <input type="phone" class="form-control" name="proveedor_conttelef_2">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-danger">
                                <div class="card-header">
                                    <div class="card-title">Cuenta Bancaria 1</div>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label><i class="fas fa-landmark"></i> Banco</label>
                                        <select name="proveedor_banco_1" class="form-control select2" style="width:100%"></select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-coins"></i> Tipo de moneda</label>
                                        <select name="proveedor_tipmoneda_1" class="select2" style="width:100%"></select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-piggy-bank"></i> Cta. Corriente</label>
                                        <input type="text" name="proveedor_ctacorriente_1" class="form-control" placeholder="Ingrese cuenta corriente">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-user-alt"></i> Titular Cta.</label>
                                        <input type="text" name="proveedor_titularcta_1" class="form-control" placeholder="Ingrese titular de cuenta corriente">
                                    </div>
                                </div>
                            </div> 
                        </div>
                        <div class="col-md-6">
                            <div class="card card-danger">
                                <div class="card-header">
                                    <div class="card-title">Cuenta Bancaria 2</div>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label><i class="fas fa-landmark"></i> Banco</label>
                                        <select name="proveedor_banco_2" class="form-control select2" style="width:100%"></select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-coins"></i> Tipo de moneda</label>
                                        <select name="proveedor_tipmoneda_2" class="select2" style="width:100%"></select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-piggy-bank"></i> Cta. Corriente</label>
                                        <input type="text" name="proveedor_ctacorriente_2" class="form-control" placeholder="Ingrese cuenta corriente">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-user-alt"></i> Titular Cta.</label>
                                        <input type="text" name="proveedor_titularcta_2" class="form-control" placeholder="Ingrese titular de cuenta corriente">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-handshake mr-1"></i>Condiciones comerciales</h3></div>
                        <div class="card-body"><div class="row">
                            <div class="col-md-3 form-group"><label>Moneda</label><select class="form-control" name="proveedor_moneda"><option value="PEN">PEN</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div>
                            <div class="col-md-3 form-group"><label>Días de pago</label><input class="form-control" type="number" min="0" max="3650" name="proveedor_dias_pago" value="0"></div>
                            <div class="col-md-3 form-group"><label>Límite de crédito otorgado</label><input class="form-control" type="number" min="0" step="0.01" name="proveedor_limite_credito" value="0"></div>
                            <div class="col-md-3 form-group"><label>Comprador asignado</label><select class="form-control" name="proveedor_comprador_id"><option value="">Sin asignar</option><?php foreach ($pdo->query("SELECT id,CONCAT(name,' ',last_name_1,' ',last_name_2) employee_name FROM tbl_employee WHERE active_status=1 ORDER BY name,last_name_1")->fetchAll() as $employee): ?><option value="<?php echo (int)$employee['id']; ?>"><?php echo e($employee['employee_name']); ?></option><?php endforeach; ?></select></div>
                        </div><small class="text-muted">Estos valores se proponen automáticamente en órdenes y facturas de compra.</small></div>
                    </div>
                    <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title"><i class="fas fa-toggle-on mr-1"></i>Control del registro</h3></div><div class="card-body"><div class="row"><div class="col-md-3 form-group"><label>Estado</label><select class="form-control" name="proveedor_active_status"><option value="1">Activo</option><option value="0">Inactivo</option></select><small class="form-text text-muted">Los documentos nuevos solo permiten seleccionar proveedores activos.</small></div></div></div></div>
                    <?php $providerCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='provider' AND active_status=1 ORDER BY id")->fetchAll(); if ($providerCustomFields): ?>
                    <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-1"></i>Campos adicionales</h3></div><div class="card-body"><div class="row"><?php foreach ($providerCustomFields as $customField): ?><div class="col-md-4 form-group"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><input type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"> Sí / No<?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
                    <?php endif; ?>
                    <?php $partnerDetailType = 'provider'; require __DIR__ . '/../template/business-partner-details.php'; ?>
                    <div class="row">
                        <div id="col-btn-save-proveedor" class="col-md-12">
                            <button type="submit" id="btn-save-proveedor" class="btn btn-success btn-block"><i class="fa fa-save fa-1x"></i>&nbsp;&nbsp;<font>Guardar proveedor</font></button>
                        </div>
                        <div id="col-btn-delete-proveedor" class="col-md-6">
                            <button type="button" js-id="" id="btn-delete-proveedor" class="btn btn-outline-danger btn-block"><i class="fa fa-ban fa-1x"></i>&nbsp;&nbsp;Desactivar proveedor</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card mt-3">
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-proveedores" class="table table-bordered table-hover" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>N° Proveedor</th>
                                    <th>Razón Social</th>
                                    <th>Dirección</th>
                                    <th>Ciudad</th>
                                    <th>País</th>
                                    <th>Fec. Registro</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <!-- /.card-body -->
            </div>
        </div>
    </div>
</div>
