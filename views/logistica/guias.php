<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 text-dark"><i class="fas fa-truck-loading mr-2"></i>Guia de Remision</h1>
            <p class="text-muted mb-0">Documento para controlar el traslado de productos. Todavia no es una GRE de SUNAT.</p>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div class="alert alert-info">
                <strong>¿Para qué sirve?</strong> Esta guia acompaña el traslado desde un origen hasta un destino y conserva el documento de origen, productos, transporte y destinatario. La estructura queda preparada para una futura integracion, pero no envia informacion a SUNAT.
            </div>
            <div class="card card-primary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Guias registradas</span>
                        <?php if (app_can('logistics.manage')): ?><button id="btn-new-remission-guide" class="btn btn-light"><i class="fas fa-plus mr-1"></i>Nueva entrega / guía</button><?php endif; ?>
                </div>
                <div class="card-body table-responsive">
                    <table id="table-remission-guides" class="table table-bordered table-hover" style="width:100%">
                        <thead><tr><th>ID</th><th>Numero</th><th>Origen</th><th>Destino</th><th>Traslado</th><th>Transporte</th><th>Almacen</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="remission-guide-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <form id="remission-guide-form" class="modal-content" novalidate data-document-mode="CREATE">
            <div class="modal-header">
            <h5 class="modal-title">Registrar guia de remision</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="request_key">
                <div class="alert alert-warning">Esta Guia de Remision es operativa. Para una GRE futura se validaran catalogos, establecimientos, vehiculo, conductor y comunicacion con SUNAT.</div>
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Documento de origen</label>
                        <select id="guide-source" name="source_key" class="form-control select2" required><option value="">Cargando documentos...</option></select>
                        <input type="hidden" name="source_type">
                        <input type="hidden" name="source_id">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Fecha de traslado</label>
                        <input type="date" name="transfer_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Almacén asociado</label>
                        <select name="warehouse_id" id="guide-warehouse" class="form-control" required>
                            <option value="1" selected>Almacén principal</option>
                        </select>
                        <small class="form-text text-muted">Se conserva como referencia logística del traslado.</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Motivo</label><select name="transfer_reason_code" class="form-control"><option value="VENTA">Venta</option><option value="TRASLADO_ENTRE_ALMACENES">Traslado entre almacenes</option><option value="DEVOLUCION">Devolucion</option><option value="OTROS">Otros</option></select></div>
                    <div class="form-group col-md-4"><label>Descripcion del motivo</label><input name="transfer_reason" class="form-control" value="Venta" maxlength="150"></div>
                    <div class="form-group col-md-4"><label>Modalidad de transporte</label><select name="transport_mode" class="form-control"><option value="PRIVADO">Privado</option><option value="PUBLICO">Publico</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Remitente / empresa</label><input name="sender_name" class="form-control" required maxlength="200"></div>
                    <div class="form-group col-md-4"><label>RUC remitente</label><input name="sender_ruc" class="form-control" maxlength="11"></div>
                    <div class="form-group col-md-4"><label>Direccion remitente</label><input name="sender_address" class="form-control" required maxlength="300"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Destinatario</label><input name="recipient_name" class="form-control" required maxlength="200"></div>
                    <div class="form-group col-md-4"><label>RUC / documento destinatario</label><input name="recipient_ruc" class="form-control" maxlength="11"></div>
                    <div class="form-group col-md-4"><label>Direccion destinatario</label><input name="recipient_address" class="form-control" required maxlength="300"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6"><label>Punto de partida</label><input name="origin_address" class="form-control" required maxlength="300"></div>
                    <div class="form-group col-md-6"><label>Punto de llegada</label><input name="destination_address" class="form-control" required maxlength="300"></div>
                </div>
                <div class="form-group">
                    <label class="mb-1">Productos a trasladar</label>
                    <div class="small text-muted mb-2">Puedes generar varias guias para el mismo documento. Indica solo la cantidad que saldra en esta entrega.</div>
                    <div class="table-responsive"><table id="guide-items" class="table table-bordered table-sm mb-3"><thead><tr><th>Codigo</th><th>Descripcion</th><th>Solicitado</th><th>Entregado</th><th>Pendiente</th><th style="width:150px">Trasladar ahora</th></tr></thead><tbody><tr><td colspan="6" class="text-center text-muted">Selecciona un documento de origen.</td></tr></tbody></table></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Ubigeo partida</label><input name="origin_ubigeo" class="form-control" maxlength="6"></div>
                    <div class="form-group col-md-3"><label>Ubigeo llegada</label><input name="destination_ubigeo" class="form-control" maxlength="6"></div>
                    <div class="form-group col-md-3"><label>Placa</label><input name="vehicle_plate" class="form-control" maxlength="20"></div>
                    <div class="form-group col-md-3"><label>Marca vehiculo</label><input name="vehicle_brand" class="form-control" maxlength="100"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>RUC transportista</label><input name="carrier_ruc" class="form-control" maxlength="11"></div>
                    <div class="form-group col-md-3"><label>Transportista</label><input name="carrier_name" class="form-control" maxlength="200"></div>
                    <div class="form-group col-md-3"><label>Conductor</label><input name="driver_name" class="form-control" maxlength="200"></div>
                    <div class="form-group col-md-3"><label>Licencia</label><input name="driver_license" class="form-control" maxlength="30"></div>
                </div>
                <?php $guideCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='remission_guide' AND active_status=1 ORDER BY id")->fetchAll(); if ($guideCustomFields): ?><div class="card card-outline card-secondary mb-3"><div class="card-header"><strong>Campos adicionales</strong></div><div class="card-body"><div class="form-row"><?php foreach ($guideCustomFields as $customField): ?><div class="form-group col-md-4"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="guide-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="guide-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div><?php endif; ?>
                <div class="form-group mb-0"><label>Observaciones</label><textarea name="notes" class="form-control" rows="2" maxlength="500"></textarea></div>
            </div>
            <div id="guide-custom-fields" class="px-3"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Registrar guia</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="delivery-billing-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <form id="delivery-billing-form" class="modal-content" novalidate data-document-mode="CREATE">
      <input type="hidden" name="request_key">
      <div class="modal-header"><h5 class="modal-title">Copiar entrega a factura interna</h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
      <div class="modal-body">
        <div class="alert alert-info">La mercancía ya salió con la entrega. Esta factura crea la cuenta por cobrar y conserva las líneas de origen, sin descontar inventario nuevamente. Estado fiscal: no aplica.</div>
        <p id="delivery-billing-customer" class="font-weight-bold"></p>
        <div class="row">
          <div class="form-group col-md-3"><label for="delivery-billing-series">Serie</label><select id="delivery-billing-series" name="series" class="form-control"></select></div>
          <div class="form-group col-md-3"><label for="delivery-billing-date">Fecha documento</label><input id="delivery-billing-date" name="document_date" type="date" class="form-control"></div>
          <div class="form-group col-md-6"><label for="delivery-billing-reference">Referencia</label><input id="delivery-billing-reference" name="reference" maxlength="300" class="form-control"></div>
        </div>
        <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Entrega</th><th>Artículo</th><th>Pendiente de facturar</th><th>Precio sin IGV</th><th>Facturar</th></tr></thead><tbody id="delivery-billing-lines"></tbody></table></div>
        <div id="delivery-billing-custom-fields"></div>
        <div id="delivery-billing-total" class="text-right font-weight-bold mb-3"></div>
        <div id="delivery-billing-result" role="status"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button><button type="submit" class="btn btn-primary">Crear factura</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="guide-preview-modal" tabindex="-1" role="dialog" aria-hidden="true" data-document-mode="VIEW">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Consulta de entrega</h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
    <div class="modal-body"><div id="guide-preview-header" role="status"></div><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Código</th><th>Descripción</th><th>Unidad</th><th>Cantidad</th></tr></thead><tbody id="guide-preview-lines"></tbody></table></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button><button type="button" id="guide-preview-print" class="btn btn-primary">Imprimir</button></div>
  </div></div>
</div>
