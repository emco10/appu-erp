<div class="content-wrapper">
 <div class="content-header"><div class="container-fluid"><div class="d-flex justify-content-between align-items-center"><div><h1 class="m-0"><i class="fas fa-exchange-alt mr-2"></i>Transferencia de inventario</h1><p class="text-muted mb-0">Documento interno multilínea para trasladar existencias, lotes y valor entre almacenes.</p></div><span class="badge badge-secondary">No se envía a SUNAT</span></div></div></div>
 <div class="content"><div class="container-fluid">
  <form id="transfer-form"><input type="hidden" name="action" value="create"><input type="hidden" name="lines" id="transfer-lines-json">
   <div class="card card-primary"><div class="card-header"><h3 class="card-title">Cabecera del documento</h3></div><div class="card-body"><div class="form-row">
    <div class="form-group col-md-3"><label>Fecha de contabilización</label><input type="date" name="transfer_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required></div>
    <div class="form-group col-md-3"><label>Fecha del documento</label><input type="date" name="document_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required></div>
    <div class="form-group col-md-3"><label>Almacén origen predeterminado</label><select id="transfer-default-from" class="form-control"></select></div>
    <div class="form-group col-md-3"><label>Almacén destino predeterminado</label><select id="transfer-default-to" class="form-control"></select></div>
   </div><div class="form-group mb-0"><label>Comentarios</label><textarea name="comments" class="form-control" maxlength="500" rows="2"></textarea></div></div></div>
   <div class="card"><div class="card-header"><h3 class="card-title">Contenido</h3><button type="button" id="transfer-add-line" class="btn btn-sm btn-primary float-right"><i class="fas fa-plus mr-1"></i>Agregar línea</button></div><div class="card-body table-responsive p-0"><table id="transfer-lines" class="table table-bordered table-hover mb-0"><thead><tr><th style="min-width:220px">Artículo</th><th style="min-width:170px">Almacén origen</th><th style="min-width:170px">Almacén destino</th><th style="min-width:220px">Lote/serie</th><th style="width:130px">Disponible</th><th style="width:130px">Cantidad</th><th style="width:55px"></th></tr></thead><tbody></tbody></table></div><div class="card-footer d-flex justify-content-between"><span id="transfer-summary" class="text-muted">0 líneas</span><button class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i>Agregar transferencia</button></div></div>
   <?php require __DIR__.'/partial-campos-transferencia.php'; ?>
  </form>
  <div class="card"><div class="card-header"><h3 class="card-title">Transferencias recientes</h3></div><div class="card-body table-responsive p-0"><table id="transfer-table" class="table table-striped table-hover mb-0"><thead><tr><th>Número</th><th>Contabilización</th><th>Documento</th><th>Origen → destino</th><th>Líneas</th><th>Cantidad</th><th>Estado</th><th></th></tr></thead><tbody></tbody></table></div></div>
 </div></div>
</div>
