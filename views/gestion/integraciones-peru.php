<div class="content-wrapper"><div class="content-header"><div class="container-fluid"><h1>Integraciones Perú</h1></div></div><div class="content"><div class="container-fluid">
<div class="alert alert-info">Las consultas RUC/DNI se invocan con el botón Consultar del maestro. GRE y SIRE requieren claves propias en el servidor. Activar una opción aquí no habilita por sí sola envíos a SUNAT.</div>
<div id="peru-message" class="alert d-none" role="status"></div>
<div class="card"><div class="card-header">Habilitación de funciones</div><div class="card-body"><form id="peru-settings-form">
<?php foreach(['identity_enabled'=>'Consulta manual RUC/DNI','gre_enabled'=>'GRE: envío manual y consulta de ticket','sire_enabled'=>'SIRE: consulta y exportación de propuestas','retention_enabled'=>'Asistencia de retenciones IGV','detraction_enabled'=>'Asistencia de detracciones SPOT'] as $key=>$label): ?>
<div class="form-check mb-2"><input id="<?php echo e($key); ?>" class="form-check-input" type="checkbox" name="<?php echo e($key); ?>" value="1"><label class="form-check-label" for="<?php echo e($key); ?>"><?php echo e($label); ?></label></div>
<?php endforeach; ?>
<label for="company-retention-agent">La empresa es agente de retención designado por SUNAT</label><select id="company-retention-agent" name="company_retention_agent" class="form-control mb-3"><option value="">Sin verificar</option><option value="1">Sí, verificado</option><option value="0">No, verificado</option></select>
<?php if(app_can('management.manage')): ?><button class="btn btn-primary" type="submit">Guardar configuración</button> <button class="btn btn-outline-info" type="button" id="peru-check">Consultar configuración de la API</button><?php endif; ?>
</form><pre id="peru-readiness" class="mt-3" style="white-space:pre-wrap"></pre>
<a class="btn btn-outline-primary" href="<?php echo $functions->direct_paginas(); ?>gestion/sire">Consultar SIRE</a> <a class="btn btn-outline-primary" href="<?php echo $functions->direct_paginas(); ?>logistica/gre">Gestionar GRE</a>
</div></div>
<div class="card"><div class="card-header">Evaluación de retención o detracción por operación</div><div class="card-body">
<p>Revisa el régimen, las exclusiones y las condiciones del socio a la fecha de la operación. Esta asistencia calcula y guarda una evaluación; el comprobante, los pagos y los asientos conservan sus importes hasta el registro contable correspondiente.</p>
<form id="peru-tax-form"><div class="form-row">
<div class="form-group col-md-3"><label>Régimen a evaluar</label><select name="regime" class="form-control"><option value="retention">Retención IGV</option><option value="detraction">Detracción SPOT</option></select></div>
<div class="form-group col-md-3"><label>Operación</label><select name="operation_type" class="form-control"><option value="purchase">Compra / pago a proveedor</option><option value="sale">Venta / cobro a cliente</option></select></div>
<div class="form-group col-md-3"><label>Referencia de documento</label><input name="document_reference" class="form-control" maxlength="80" placeholder="RUC + serie y número"></div>
<div class="form-group col-md-3"><label>Fecha del pago / evaluación</label><input name="operation_date" class="form-control" type="date" value="<?php echo e(date('Y-m-d')); ?>"></div>
</div><div class="form-row">
<div class="form-group col-md-6"><label>Fecha de nacimiento de la obligación tributaria del IGV</label><input name="tax_obligation_date" class="form-control" type="date"><small class="form-text text-muted">Verifica el momento legal según la operación y sus documentos. Esta fecha determina la tasa aplicable; puede ser distinta de la fecha del pago.</small></div>
<div class="form-group col-md-6"><label>Criterio del depósito SPOT (solo detracciones)</label><select name="spot_rounding" class="form-control"><option value="cents">Depósito con centavos</option><option value="soles">Depósito redondeado a soles (opción SUNAT)</option></select><small class="form-text text-muted">Para un depósito previo, elige el mismo criterio utilizado en la constancia. El redondeo a soles es opcional según la R.S. 178-2005/SUNAT.</small></div>
</div><div class="form-row">
<div class="form-group col-md-4"><label>Total de operación, incluido IGV (S/)</label><input name="gross_operation" class="form-control" inputmode="decimal"></div>
<div class="form-group col-md-4"><label>Pago bruto, incluido IGV (S/)</label><input name="gross_payment" class="form-control" inputmode="decimal"></div>
<div class="form-group col-md-4"><label>Detracción ya depositada (S/)</label><input name="already_deposited" class="form-control" inputmode="decimal" value="0"></div>
</div><div class="form-row">
<?php foreach(['buyer_retention_agent'=>'Comprador agente retenedor (para ventas)','supplier_retention_agent'=>'Proveedor agente retenedor','supplier_perception_agent'=>'Proveedor agente de percepción','supplier_good_taxpayer'=>'Proveedor buen contribuyente'] as $key=>$label): ?>
<div class="form-group col-md-3"><label><?php echo e($label); ?></label><select name="<?php echo e($key); ?>" class="form-control"><option value="">Sin verificar</option><option value="1">Sí</option><option value="0">No</option></select></div>
<?php endforeach; ?>
</div><div class="form-row">
<div class="form-group col-md-5"><label>Aplicación del SPOT a esta operación</label><select name="spot_applicability" class="form-control"><option value="unknown">Sin revisar</option><option value="applicable">Sí corresponde detracción</option><option value="not_applicable">No corresponde detracción</option></select></div>
<div class="form-group col-md-7"><label>Servicio del Anexo 3 (solo para detracciones)</label><select name="spot_category" id="spot-category" class="form-control"><option value="">Selecciona tras revisar la operación</option></select></div>
</div>
<div class="form-check mb-2"><input id="tax-igv" name="igv_taxed" class="form-check-input" type="checkbox" value="1"><label for="tax-igv" class="form-check-label">He verificado que la operación está gravada con IGV.</label></div>
<div class="form-check mb-2"><input id="tax-exclusions" name="exclusions_reviewed" class="form-check-input" type="checkbox" value="1"><label for="tax-exclusions" class="form-check-label">He revisado las exclusiones, la designación vigente y los documentos de sustento.</label></div>
<div class="form-check mb-3"><input id="tax-credit" name="fiscal_credit_document" class="form-check-input" type="checkbox" value="1"><label for="tax-credit" class="form-check-label">Para SPOT: el documento permite crédito fiscal o sustento de costo/gasto; he revisado las excepciones del Anexo 3.</label></div>
<?php if(app_can('management.manage')&&app_can('finance.manage')): ?><button class="btn btn-info" type="submit">Evaluar operación</button> <button class="btn btn-outline-primary" type="button" id="peru-save-evaluation" disabled>Guardar evaluación</button><?php endif; ?>
</form><div id="peru-tax-result" class="mt-3" role="status"></div>
<p class="small text-muted mt-3">Cobertura inicial: retención IGV y servicios del Anexo 3 en soles. Tasas de retención para obligaciones IGV desde el 01/03/2014 y catálogo SPOT desde el 01/04/2018, revisados al 06/11/2026; fechas anteriores o futuras requieren revisión. Bienes, IVAP, transporte terrestre de carga, operaciones especiales y comprobantes electrónicos de retención requieren su tratamiento específico. Una evaluación no acredita un depósito ni emite un comprobante de retención.</p>
</div></div></div></div></div>
