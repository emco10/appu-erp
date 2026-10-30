<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 text-dark">Inventario y productos</h1>
            <p class="text-muted mb-0">Existencias actuales, movimientos recientes y vencimientos registrados.</p>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div id="inventory-report-error" class="alert alert-danger d-none" role="alert"></div>
            <div id="inventory-report-loading" class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin mr-2"></i>Cargando información de inventario…
            </div>
            <div id="inventory-kpis" class="row"></div>
            <div id="inventory-integrity-card" class="card card-outline card-danger d-none"><div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1"></i> Alertas de integridad de inventario</h3></div><div class="card-body table-responsive p-0" id="inventory-integrity"></div><div class="card-footer text-muted small">Compara el saldo maestro con la suma por almacenes y valida que las reservas no superen la existencia.</div></div>

            <div class="row">
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-boxes mr-1"></i> Existencias actuales</h3>
                            <div class="card-tools">
                                <select id="inventory-warehouse-select" class="form-control form-control-sm d-inline-block mr-2" style="width:210px">
                                    <option value="">Todos los almacenes</option>
                                </select>
                                <button id="btn-product-list" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list mr-1"></i> Catálogo
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0" id="inventory-stock"></div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-times mr-1"></i> Vencimientos registrados</h3></div>
                        <div class="card-body table-responsive p-0" id="inventory-expirations"></div>
                        <div class="card-footer text-muted small">Lotes de ingreso con vencimiento pasado o dentro de 180 días. El detalle por almacén y lote muestra existencias disponibles y reservadas.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i> Movimientos de los últimos 90 días</h3></div>
                <div class="card-body table-responsive p-0" id="inventory-movements"></div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Reportes de productos</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse" aria-label="Mostrar u ocultar reportes">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Unidades vendidas por cliente</h5>
                            <div class="form-group">
                                <label for="inventory-product-select">Producto</label>
                                <select id="inventory-product-select" class="form-control select2" name="product_list"></select>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <label for="inventory-date-from">Desde</label>
                                    <input id="inventory-date-from" type="date" name="date_from" class="form-control">
                                </div>
                                <div class="col-sm-6">
                                    <label for="inventory-date-to">Hasta</label>
                                    <input id="inventory-date-to" type="date" name="date_to" class="form-control">
                                </div>
                            </div>
                            <button id="btn-rpt-unidades-vendidas-cliente" class="btn btn-success btn-block mt-3" type="button">
                                <i class="fas fa-file-alt mr-1"></i> Ver reporte PDF
                            </button>
                        </div>
                        <div class="col-md-6">
                            <h5>Productos más y menos vendidos</h5>
                            <p class="text-muted">Abrir el reporte PDF de los 20 primeros productos.</p>
                            <button id="btn-rpt-top-mas-vendido" class="btn btn-outline-success btn-block" type="button">
                                <i class="fas fa-arrow-up mr-1"></i> Top 20 más vendidos
                            </button>
                            <button id="btn-rpt-top-menos-vendido" class="btn btn-outline-danger btn-block" type="button">
                                <i class="fas fa-arrow-down mr-1"></i> Top 20 menos vendidos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
