<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h1 class="m-0 text-dark">Análisis de ventas</h1>
                    <p class="text-muted mb-0">Ventas netas, documentos y rendimiento comercial por período.</p>
                </div>
                <button type="button" id="btn-export-sales-report" class="btn btn-outline-primary mt-2 mt-sm-0">
                    <i class="fas fa-download mr-1"></i> Exportar CSV
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <form id="sales-report-filter" class="row align-items-end">
                        <div class="col-sm-5 col-md-4">
                            <div class="form-group mb-sm-0">
                                <label for="sales-date-from">Desde</label>
                                <input id="sales-date-from" name="date_from" type="date" class="form-control" value="<?php echo e(date('Y-m-01', strtotime('-11 months'))); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-5 col-md-4">
                            <div class="form-group mb-sm-0">
                                <label for="sales-date-to">Hasta</label>
                                <input id="sales-date-to" name="date_to" type="date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-2 col-md-4 mt-3 mt-sm-0">
                            <button type="submit" id="btn-filter-sales-report" class="btn btn-primary btn-block">
                                <i class="fas fa-filter mr-1"></i> Aplicar filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="sales-report-error" class="alert alert-danger d-none" role="alert"></div>
            <div id="sales-report-loading" class="text-center text-muted py-4 d-none">
                <i class="fas fa-spinner fa-spin mr-2"></i>Calculando indicadores…
            </div>
            <div id="sales-kpis" class="row"></div>

            <div class="row">
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line mr-1"></i> Tendencia mensual</h3>
                        </div>
                        <div class="card-body" id="sales-monthly"></div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Ventas por documento</h3>
                        </div>
                        <div class="card-body table-responsive p-0" id="sales-document-types"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-user-tie mr-1"></i> Rendimiento por vendedor</h3></div>
                        <div class="card-body table-responsive p-0" id="sales-sellers"></div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-box mr-1"></i> Productos con más venta</h3></div>
                        <div class="card-body table-responsive p-0" id="sales-products"></div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-users mr-1"></i> Ventas por cliente</h3></div>
                        <div class="card-body table-responsive p-0" id="sales-customers"></div>
                    </div>
                </div>
            </div>
            <p class="text-muted small">Los documentos anulados se excluyen; las notas de crédito se descuentan. PEN (MN) y moneda extranjera (ME) se presentan por separado. Los importes son ventas, no utilidad contable.</p>
        </div>
    </div>
</div>
