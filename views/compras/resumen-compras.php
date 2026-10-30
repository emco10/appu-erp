<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-receipt"></i>&nbsp;&nbsp;Resumen de Compras
                    </div>
                </div>
            </div>
            <div style="max-width: 1140px; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div style="max-width: 1140px; margin: 0 auto;">
                <div class="card card-primary">
                    <div class="card-header">
                        <div class="row">
                            <!--
                            <div class="col-md-4">
                                <div class="card-title">Filtrar por tipo de orden:</div>
                            </div>
                            <div class="col-md-4">
                                <div class="" style="height: 2.2rem;">
                                    <div class="input-group mb-3">
                                        <select class="select2 form-control" name="ordenes_tipo">
                                            <option value="">Seleccione</option>
                                            <option value="COMPRA">Compra</option>
                                            <option value="SERVICIO">Servicio</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            -->
                            <div class="col-md-4"></div>
                            <div class="col-md-4"></div>
                            <div class="col-md-4">
                                <button id="btn-nuevo" js-type="" type="button" class="btn btn-success btn-block"><i class="fa fa-plus fa-1x"></i> Nueva Compra</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label>N° Compra</label>
                                    <input type="text" name="orden_numero" class="form-control" placeholder="Nro de compra">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label>Proveedor</label>
                                    <input type="text" name="orden_proveedor" class="form-control" placeholder="Nombre de proveedor">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label>Fecha Inicio</label>
                                    <input type="date" name="orden_fecinic" class="form-control">
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="form-group">
                                    <label>Fecha Fin</label>
                                    <input type="date" name="orden_fecfin" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <button id="btn-reset" class="btn btn-primary btn-block">
                                    <i class="fa fa-broom fa-1x"></i>&nbsp;&nbsp;Limpiar filtros
                                </button>
                            </div>
                            <div class="col-md-8">
                                <button id="btn-buscar" class="btn btn-success btn-block" type="button">
                                   <i class="fa fa-search"></i>&nbsp;&nbsp;Buscar Compras
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div>
                            <label>Haga clic derecho sobre un ítem para ver opciones disponibles.</label>
                        </div>
                        <div class="table-responsive">
                            <table id="table-ordenes" class="table table-bordered table-hover" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nro. Compra</th>
                                        <th>Referencia proveedor</th>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Moneda</th>
                                        <th>Total Neto</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
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
</div>

<div class="modal fade" id="goods-receipt-modal" tabindex="-1" role="dialog" aria-labelledby="goods-receipt-title" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <form id="goods-receipt-form" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="goods-receipt-title">Recepci&oacute;n de mercanc&iacute;a</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="purchase_id">
                <div id="goods-receipt-purchase" class="alert alert-info"></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="goods-receipt-items">
                        <thead>
                            <tr>
                                <th>C&oacute;digo</th>
                                <th>Producto</th>
                                <th>Pedido</th>
                                <th>Recibido</th>
                                <th>Pendiente</th>
                                <th style="width: 140px;">Recibir ahora</th>
                                <th>Lote</th>
                                <th>Vencimiento</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="goods-receipt-warehouse">Almacén de destino</label>
                        <select id="goods-receipt-warehouse" name="warehouse_id" class="form-control" required></select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="goods-receipt-date">Fecha de recepci&oacute;n</label>
                        <input id="goods-receipt-date" name="receipt_date" type="date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-group col-md-9">
                        <label for="goods-receipt-notes">Observaciones</label>
                        <input id="goods-receipt-notes" name="notes" class="form-control" maxlength="500">
                    </div>
                </div>
                <?php $goodsReceiptCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='goods_receipt' AND active_status=1 ORDER BY id")->fetchAll(); if ($goodsReceiptCustomFields): ?>
                <div class="card card-outline card-secondary mb-0"><div class="card-header"><strong>Campos adicionales</strong></div><div class="card-body"><div class="form-row"><?php foreach ($goodsReceiptCustomFields as $customField): ?><div class="form-group col-md-4"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="goods-receipt-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="goods-receipt-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="btn-save-goods-receipt" type="submit" class="btn btn-primary"><i class="fas fa-boxes mr-1"></i> Registrar recepci&oacute;n</button>
            </div>
        </form>
    </div>
</div>
