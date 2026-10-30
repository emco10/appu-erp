<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0"><i class="fas fa-chart-line mr-2"></i>Antigüedad de saldos</h1><p class="text-muted mb-0">Cartera abierta de clientes y proveedores, vencimientos y pagos del periodo.</p></div></div>
  <section class="content"><div class="container-fluid">
    <div class="card card-outline card-primary mb-3"><div class="card-body"><form id="finance-report-filter" class="form-row align-items-end">
      <div class="form-group col-lg-2 col-md-4"><label>Tipo de cuenta</label><select id="finance-report-type" class="form-control"><option value="all">CxC y CxP</option><option value="receivable">Cuentas por cobrar</option><option value="payable">Cuentas por pagar</option></select></div>
      <div class="form-group col-lg-2 col-md-4"><label>Moneda</label><select id="finance-report-currency" class="form-control"><option value="">Todas</option></select></div>
      <div class="form-group col-lg-3 col-md-4"><label>Cliente / proveedor</label><input id="finance-report-party" class="form-control" maxlength="100" placeholder="RUC, nombre o documento"></div>
      <div class="form-group col-lg-2 col-md-4"><label>Pagos desde</label><input id="finance-report-from" type="date" class="form-control" required></div>
      <div class="form-group col-lg-2 col-md-4"><label>Pagos hasta</label><input id="finance-report-to" type="date" class="form-control" required></div>
      <div class="form-group col-lg-1 col-md-4"><button class="btn btn-primary btn-block" type="submit" title="Aplicar filtros"><i class="fas fa-filter"></i></button></div>
    </form><div class="mt-2 text-right"><button id="finance-report-export" class="btn btn-sm btn-outline-success" type="button"><i class="fas fa-file-csv mr-1"></i>Exportar detalle</button></div></div></div>
    <div id="finance-report-loading" class="alert alert-light border"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando información financiera...</div><div id="finance-report-summary" class="row"></div>
    <div class="row"><div class="col-xl-6"><div class="card"><div class="card-header"><h3 class="card-title">Antigüedad por rango</h3></div><div class="card-body table-responsive"><table class="table table-sm" id="finance-report-aging"><tbody></tbody></table></div></div></div><div class="col-xl-6"><div class="card"><div class="card-header"><h3 class="card-title">Saldos por socio de negocio</h3></div><div class="card-body table-responsive"><table class="table table-sm" id="finance-report-partners"><tbody></tbody></table></div></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Detalle de documentos abiertos</h3><span id="finance-report-count" class="badge badge-secondary float-right">0</span></div><div class="card-body table-responsive"><table class="table table-sm" id="finance-report-accounts"><tbody></tbody></table></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Cobros y pagos del periodo</h3></div><div class="card-body table-responsive"><table class="table table-sm" id="finance-report-payments"><tbody></tbody></table></div></div>
  </div></section>
</div>
