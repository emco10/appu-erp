<?php $greCanManage=app_can('logistics.manage'); ?>
<div class="content-wrapper" id="gre-workspace" data-can-manage="<?php echo $greCanManage?'1':'0'; ?>">
<div class="content-header"><div class="container-fluid">
<h1><i class="fas fa-truck mr-2"></i>Guías electrónicas SUNAT</h1>
<p class="text-muted">Prepara los datos de una guía registrada, valida su contenido y consulta el ticket y CDR de SUNAT.</p>
</div></div>
<div class="content"><div class="container-fluid">
<div id="gre-message" class="alert alert-info" role="status">Selecciona una guía para revisar su estado.</div>
<div class="card"><div class="card-body">
<div class="form-row align-items-end">
<div class="form-group col-md-9"><label for="gre-guide">Últimas 200 guías registradas</label><select id="gre-guide" class="form-control"><option value="">Selecciona una guía</option></select></div>
<div class="form-group col-md-3"><button id="gre-refresh-list" class="btn btn-outline-secondary" type="button">Actualizar lista</button></div>
</div>
<p id="gre-submission" class="mb-1"></p><p id="gre-ticket" class="text-muted"></p>
<div class="btn-group flex-wrap">
<button id="gre-preview" type="button" class="btn btn-info" disabled>Validar datos SUNAT</button>
<?php if($greCanManage): ?>
<button id="gre-submit" type="button" class="btn btn-primary" disabled>Solicitar envío GRE</button>
<button id="gre-consult" type="button" class="btn btn-outline-primary" disabled>Consultar ticket</button>
<?php endif; ?>
<button id="gre-refresh-state" type="button" class="btn btn-outline-secondary" disabled>Actualizar estado local</button>
<a id="gre-xml" class="btn btn-outline-secondary disabled" aria-disabled="true">Descargar XML</a>
<a id="gre-cdr" class="btn btn-outline-success disabled" aria-disabled="true">Descargar CDR</a>
</div>
<div id="gre-validation" class="mt-3" aria-live="polite"></div>
</div></div>
<div class="card"><div class="card-header">Datos de la guía <span id="gre-number" class="ml-2"></span></div><div class="card-body">
<p class="small text-muted">La numeración y las líneas originales se conservan. Una guía con un envío reservado mantiene su XML inmutable, incluso si el resultado está pendiente.</p>
<form id="gre-prepare-form" novalidate>
<input type="hidden" name="guide_id">
<fieldset id="gre-fields" disabled>
<div class="form-row">
<div class="form-group col-md-3"><label>Peso bruto total</label><input name="gross_weight" class="form-control" inputmode="decimal"></div>
<div class="form-group col-md-3"><label>Unidad de peso</label><select name="weight_unit" class="form-control"><option value="KGM">Kilogramos (KGM)</option><option value="TNE">Toneladas (TNE)</option></select></div>
<div class="form-group col-md-3"><label>Modalidad</label><select name="transport_mode" class="form-control"><option value="PRIVADO">Privado</option><option value="PUBLICO">Público</option><option value="02">02 - Privado</option><option value="01">01 - Público</option></select></div>
<div class="form-group col-md-3"><label>Motivo</label><select name="transfer_reason_code" class="form-control"><option value="VENTA">Venta</option><option value="TRASLADO_ENTRE_ALMACENES">Entre establecimientos</option><option value="DEVOLUCION">Devolución</option><option value="OTROS">Otros</option><option value="01">01 - Venta</option><option value="04">04 - Entre establecimientos</option><option value="06">06 - Devolución</option><option value="13">13 - Otros</option></select></div>
</div>
<div class="form-row">
<?php foreach(['transfer_reason'=>'Descripción del motivo','recipient_ruc'=>'Documento destinatario','recipient_name'=>'Destinatario','recipient_address'=>'Dirección destinatario','origin_address'=>'Dirección de partida','origin_ubigeo'=>'Ubigeo de partida','origin_establishment_code'=>'Establecimiento de partida','destination_address'=>'Dirección de llegada','destination_ubigeo'=>'Ubigeo de llegada','destination_establishment_code'=>'Establecimiento de llegada','carrier_ruc'=>'RUC transportista (público)','carrier_name'=>'Transportista (público)','vehicle_plate'=>'Placa (privado)','driver_doc_number'=>'Documento conductor','driver_name'=>'Nombres conductor','driver_last_name'=>'Apellidos conductor','driver_license'=>'Licencia conductor'] as $field=>$label): ?>
<div class="form-group col-md-4"><label><?php echo e($label); ?></label><input name="<?php echo e($field); ?>" class="form-control" maxlength="<?php echo in_array($field,['origin_address','destination_address','recipient_address'],true)?300:($field==='driver_license'?10:200); ?>"></div>
<?php endforeach; ?>
<div class="form-group col-md-4"><label>Tipo documento conductor</label><select name="driver_doc_type" class="form-control"><option value="">Selecciona</option><option value="1">DNI</option><option value="4">Carné de extranjería</option><option value="7">Pasaporte</option></select></div>
</div>
<?php if($greCanManage): ?><button id="gre-save" class="btn btn-primary" type="submit">Guardar datos GRE</button><?php endif; ?>
</fieldset>
</form>
<div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Código</th><th>Descripción</th><th>Unidad</th><th>Cantidad</th></tr></thead><tbody id="gre-lines"></tbody></table></div>
</div></div>
</div></div></div>
