<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0"><i class="fas fa-clipboard-check mr-2"></i>Solicitudes de compra</h1><p class="text-muted mb-0">Requerimientos internos antes de emitir una orden de compra.</p></div></div>
  <div class="content"><div class="container-fluid">
    <div class="card card-primary">
      <div class="card-header"><h3 class="card-title">Nueva solicitud</h3></div>
      <div class="card-body">
        <form id="purchase-request-form">
          <input type="hidden" name="id">
          <div class="form-row">
            <div class="form-group col-md-3"><label>Solicitante</label><select name="requester_id" id="purchase-request-requester" class="form-control" required></select></div>
            <div class="form-group col-md-3"><label>Fecha de solicitud</label><input type="date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="form-group col-md-3"><label>Fecha requerida</label><input type="date" name="required_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="form-group col-md-3"><label>Moneda</label><select name="currency" class="form-control"><option value="PEN">PEN - Soles</option><option value="USD">USD - Dólares</option></select></div>
          </div>
          <div class="form-group"><label>Observación</label><textarea name="observation" class="form-control" maxlength="500" rows="2" placeholder="Motivo o centro de costo de la solicitud"></textarea></div>
          <?php $purchaseRequestCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='purchase_request' AND active_status=1 ORDER BY id")->fetchAll(); if ($purchaseRequestCustomFields): ?>
          <div class="card card-outline card-secondary mb-3"><div class="card-header"><strong>Campos adicionales</strong></div><div class="card-body"><div class="form-row"><?php foreach ($purchaseRequestCustomFields as $customField): ?><div class="form-group col-md-4"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="purchase-request-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="purchase-request-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
          <?php endif; ?>
          <div class="card card-outline card-secondary mb-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-box mr-1"></i>Datos del artículo</h3></div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group col-md-6"><label>Artículo registrado</label><select id="purchase-request-product" class="form-control select2" style="width:100%"><option value="">Buscar por código o descripción</option></select></div>
                <div class="form-group col-md-3"><label>Código</label><input id="purchase-request-product-code" class="form-control" readonly></div>
                <div class="form-group col-md-3"><label>Cantidad</label><input id="purchase-request-quantity" type="number" min="1" step="1" class="form-control" value="1"></div>
                <div class="form-group col-md-8"><label>Descripción</label><input id="purchase-request-product-description" class="form-control" readonly></div>
                <div class="form-group col-md-2"><label>Precio estimado</label><input id="purchase-request-price" type="number" min="0" step="0.01" class="form-control" value="0"></div>
                <div class="form-group col-md-2 d-flex align-items-end"><button type="button" id="purchase-request-add" class="btn btn-primary btn-block" disabled><i class="fas fa-plus mr-1"></i>Agregar artículo</button></div>
              </div>
            </div>
          </div>
          <div class="table-responsive"><table class="table table-sm table-bordered" id="purchase-request-detail-table"><thead><tr><th>Código</th><th>Artículo</th><th>Descripción</th><th class="text-right">Cantidad</th><th class="text-right">Precio estimado</th><th class="text-right">Total</th><th></th></tr></thead><tbody><tr class="empty-row"><td colspan="7" class="text-center text-muted">Aún no hay artículos agregados.</td></tr></tbody></table></div>
          <div class="text-right font-weight-bold mb-3">Total estimado: <span id="purchase-request-total">0.00</span></div>
          <button type="submit" id="btn-save-purchase-request" class="btn btn-success btn-block"><i class="fas fa-save mr-1"></i>Guardar solicitud</button>
        </form>
      </div>
    </div>
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h3 class="card-title">Solicitudes registradas</h3><div class="d-flex"><input id="purchase-request-search" type="search" class="form-control form-control-sm mr-2" placeholder="Buscar solicitud" aria-label="Buscar solicitudes"><button type="button" id="purchase-request-refresh" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt mr-1"></i>Actualizar</button></div></div><div class="card-body table-responsive"><table class="table table-hover table-sm" id="purchase-request-table"><thead><tr><th>Número</th><th>Estado</th><th>Solicitante</th><th>Fecha</th><th>Requerida</th><th class="text-right">Total estimado</th><th></th></tr></thead><tbody></tbody></table></div></div>
  </div></div>
</div>
