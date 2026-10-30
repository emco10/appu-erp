<div class="content-wrapper">
 <div class="content-header"><div class="container-fluid d-flex flex-wrap justify-content-between align-items-center"><div><h1 class="m-0">Análisis de compras</h1><p class="text-muted mb-0">Documentos de proveedores aprobados, por período y moneda.</p></div><button id="purchase-report-export" type="button" class="btn btn-outline-primary"><i class="fas fa-file-csv mr-1"></i>Exportar CSV</button></div></div>
 <section class="content"><div class="container-fluid">
  <div class="card card-outline card-primary"><div class="card-body"><form id="purchase-report-filter" class="form-row align-items-end">
   <div class="form-group col-md-2"><label>Desde</label><input id="purchase-report-from" type="date" class="form-control" value="<?php echo date('Y-01-01'); ?>" required></div>
   <div class="form-group col-md-2"><label>Hasta</label><input id="purchase-report-to" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
   <div class="form-group col-md-2"><label>Moneda</label><select id="purchase-report-currency" class="form-control"><option value="">Todas</option><option>PEN</option><option>USD</option><option>EUR</option></select></div>
   <div class="form-group col-md-4"><label>Proveedor o documento</label><input id="purchase-report-party" class="form-control" maxlength="100" placeholder="RUC, nombre, documento o referencia"></div>
   <div class="form-group col-md-2"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i>Filtrar</button></div>
  </form></div></div>
  <div id="purchase-report-error" class="alert alert-danger d-none"></div><div id="purchase-report-loading" class="text-muted py-3 d-none"><i class="fas fa-spinner fa-spin mr-1"></i>Consultando compras…</div>
  <div id="purchase-report-summary" class="row"></div>
  <div class="row"><div class="col-xl-5"><div class="card"><div class="card-header"><h3 class="card-title">Compras por mes</h3></div><div class="card-body table-responsive"><table id="purchase-report-monthly" class="table table-sm"><thead><tr></tr></thead><tbody></tbody></table></div></div></div><div class="col-xl-7"><div class="card"><div class="card-header"><h3 class="card-title">Compras por proveedor</h3></div><div class="card-body table-responsive"><table id="purchase-report-providers" class="table table-sm"><thead><tr></tr></thead><tbody></tbody></table></div></div></div></div>
  <div class="card"><div class="card-header"><h3 class="card-title">Detalle de documentos</h3></div><div class="card-body table-responsive"><table id="purchase-report-documents" class="table table-sm table-hover"><thead><tr></tr></thead><tbody></tbody></table></div></div>
  <p class="small text-muted">Excluye pendientes, rechazados y anulados. Cada moneda se presenta por separado; las denominaciones históricas MN y ME corresponden a PEN y USD. No representa pagos de tesorería ni utilidad contable.</p>
 </div></section>
</div>
