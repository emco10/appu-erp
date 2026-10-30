<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-user-cog"></i>&nbsp;&nbsp;Registro de Usuario
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="appu-document-toolbar appu-master-toolbar" data-table="#table-usuarios" data-save="#btn-save-user" role="toolbar" aria-label="Herramientas de usuarios">
        <div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">SEGURIDAD</span><span class="appu-document-toolbar__title">Usuarios y perfiles</span><span class="appu-document-toolbar__hint">Crea, busca y navega entre usuarios registrados.</span></div>
        <div class="appu-document-toolbar__actions"><button type="button" class="btn btn-sm btn-light appu-master-new"><i class="fas fa-file"></i><span>Nuevo</span></button><button type="button" class="btn btn-sm btn-light appu-master-find"><i class="fas fa-search"></i><span>Buscar</span></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-light appu-master-first" title="Primero"><i class="fas fa-angle-double-left"></i></button><button type="button" class="btn btn-sm btn-light appu-master-prev" title="Anterior"><i class="fas fa-angle-left"></i></button><span class="appu-master-position text-muted" aria-live="polite">0 de 0</span><button type="button" class="btn btn-sm btn-light appu-master-next" title="Siguiente"><i class="fas fa-angle-right"></i></button><button type="button" class="btn btn-sm btn-light appu-master-last" title="Último"><i class="fas fa-angle-double-right"></i></button><span class="appu-document-toolbar__separator"></span><button type="button" class="btn btn-sm btn-primary appu-master-save"><i class="fas fa-save"></i><span>Guardar</span></button></div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div style="max-width: 1140px;margin: 0 auto;">
                <form id="FRM_INSERT_USUARIO" method="post" action="<?php echo $functions->direct_sistema(); ?>/modules/usuarios/insert-update-usuario.php" enctype="multipart/form-data">
                    <input type="hidden" name="usuario_id">
                    <div class="card card-primary">
                        <div class="card-header">
                            <div class="card-title">Datos de Usuario</div>
                            <div class="float-right" style="height: 2rem; width: 150px">
                                <input type="text" placeholder="Código de usuario" class="form-control" name="usuario_codigo" readonly>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php $userCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='user' AND active_status=1 ORDER BY id")->fetchAll(); if ($userCustomFields): ?>
                            <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-1"></i>Campos adicionales</h3></div><div class="card-body"><div class="row"><?php foreach ($userCustomFields as $customField): ?><div class="col-md-4 form-group"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="user-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="user-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Usuario</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-at"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" placeholder="Ingrese nombre de usuario" name="usuario_nombre" pattern="[A-Za-z0-9_-]{3,50}" maxlength="50" autocomplete="username" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><i class="fas fa-user-tie"></i> Empleado</label>
                                        <select class="form-control select2" style="width: 100%;" name="usuario_empleado_id" required>
                                            <option value="">Seleccione empleado</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label><i class="fas fa-user-shield"></i> Perfil</label>
                                        <select class="form-control select2" style="width: 100%;" name="usuario_rol_id" required>
                                            <option value="">Seleccione perfil</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Fecha de Registro</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="date" id="usuario_fecreg" class="form-control" name="usuario_fecreg" defaultValue="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Estado</label>
                                        <select class="form-control" name="usuario_active_status">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card card-secondary" id="password-card">
                                        <div class="card-header">
                                            <div id="password-card-header" class="btn-block" data-card-widget="collapse" data-toggle="tooltip" title="Colapsar"><i class="fas fa-plus"></i>&nbsp;&nbsp;<font>Contraseña</font></div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label id="pass-label-1"><font>Contraseña</font></label>
                                                        <div class="input-group mb-3">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">
                                                                    <i class="fas fa-key"></i>
                                                                </span>
                                                            </div>
                                                            <input type="password" id="usuario_pass" class="form-control" placeholder="Ingrese contraseña" name="usuario_pass" minlength="8" maxlength="72" autocomplete="new-password" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label id="pass-label-2"><font>Confirmar Contraseña</font></label>
                                                        <div class="input-group mb-3">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">
                                                                    <i class="fas fa-key"></i>
                                                                </span>
                                                            </div>
                                                            <input type="password" id="usuario_pass_conf" class="form-control" placeholder="Confirme contraseña" name="usuario_pass_conf" minlength="8" maxlength="72" autocomplete="new-password" required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div id="col-btn-save-user" class="col-md-12" style="alignment-baseline: central;">
                            <button type="submit" id="btn-save-user" class="btn btn-success btn-block"><i class="fa fa-save fa-1x"></i>&nbsp;&nbsp;<font>Crear Usuario</font></button>
                        </div>
                        <div id="col-btn-delete-user" class="col-md-6">
                            <button type="button" id="btn-delete-user" js-id="" class="btn btn-outline-danger btn-block"><i class="fa fa-ban fa-1x"></i>&nbsp;&nbsp;Desactivar usuario</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-usuarios" class="table table-bordered table-hover" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre de Usuario</th>
                                    <th>Cód. Empleado</th>
                                    <th>Empleado</th>
                                    <th>Cargo</th>
                                    <th>Perfil</th>
                                    <th>Fec. Registro</th>
                                    <th>Última actividad</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
