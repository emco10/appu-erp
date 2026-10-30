<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-box"></i>&nbsp;&nbsp;Historial de Movimientos de Almacén
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div class="card card-primary mt-1">
                <div class="card-header">
                    Movimientos
                </div>
                <div class="card-body">
                    <form id="movement-filters" class="form-row align-items-end mb-3">
                        <div class="form-group col-md-2"><label>Almacén</label><select name="warehouse_id" id="movement-warehouse" class="form-control"><option value="">Todos</option></select></div>
                        <div class="form-group col-md-3"><label>Artículo</label><select name="product_id" id="movement-product" class="form-control"><option value="">Todos</option></select></div>
                        <div class="form-group col-md-2"><label>Tipo</label><select name="movement_type" class="form-control"><option value="">Todos</option><option value="1">Entrada de inventario / mercancías</option><option value="2">Ajuste</option><option value="3">Salida por venta</option><option value="4">Devolución</option><option value="5">Reversión</option><option value="6">Transferencia salida</option><option value="7">Transferencia ingreso</option><option value="8">Ajuste de costo</option><option value="9">Salida de inventario</option></select></div>
                        <div class="form-group col-md-2"><label>Desde</label><input type="date" name="date_from" class="form-control"></div>
                        <div class="form-group col-md-2"><label>Hasta</label><input type="date" name="date_to" class="form-control"></div>
                        <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit" title="Filtrar"><i class="fas fa-filter"></i></button></div>
                    </form>
                    <div class="table-responsive">
                        <table id="table-movimientos" class="table table-bordered table-hover" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Almacén</th>
                                    <th>Cantidad</th>
                                    <th>Proveedor</th>
                                    <th>Vencimiento</th>
                                    <th>Guía/Orden</th>
                                    <th>Observación</th>
                                    <th>Usuario</th>
                                    <th>F. Operación</th>
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
