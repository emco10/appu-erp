<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-clipboard-list"></i>&nbsp;&nbsp;Ordenes de venta
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div class="card card-primary">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">Órdenes de venta directas o creadas desde cotizaciones</div>
                        <div class="col-md-4 text-right"><?php if (app_can('sales.manage')): ?><button id="btn-new-sales-order" class="btn btn-primary btn-sm mr-2"><i class="fas fa-plus mr-1"></i>Nueva orden</button><?php endif; ?>
                            <div class="input-group">
                                <select id="sales-order-status-filter" class="form-control form-control-sm">
                                    <option value="">Todos los estados</option>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Confirmada">Confirmada</option>
                                    <option value="Atendida">Atendida</option>
                                    <option value="Anulada">Anulada</option>
                                </select>
                                <div class="input-group-append"><button id="btn-refresh-sales-orders" class="btn btn-light" title="Actualizar listado"><i class="fas fa-sync-alt"></i></button></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="sales-order-search-form" data-document-mode="SEARCH" novalidate class="mb-3">
                      <div class="row">
                        <div class="form-group col-md-4"><label for="sales-order-search-text">Buscar número, cliente, RUC o referencia</label><input id="sales-order-search-text" name="q" type="search" maxlength="120" class="form-control"></div>
                        <div class="form-group col-md-2"><label>Desde</label><input name="date_from" type="date" class="form-control" aria-label="Fecha desde"></div>
                        <div class="form-group col-md-2"><label>Hasta</label><input name="date_to" type="date" class="form-control" aria-label="Fecha hasta"></div>
                        <div class="form-group col-md-2"><label>Ordenar por</label><select name="sort" class="form-control" aria-label="Ordenar por"><option value="id">Registro</option><option value="date">Fecha</option><option value="number">Número</option><option value="customer">Cliente</option><option value="total">Importe</option></select></div>
                        <div class="form-group col-md-2"><label>Dirección</label><select name="direction" class="form-control" aria-label="Dirección del orden"><option value="DESC">Descendente</option><option value="ASC">Ascendente</option></select></div>
                      </div>
                      <div class="row">
                        <div class="form-group col-md-3"><label for="sales-order-search-field">Campo personalizado</label><select id="sales-order-search-field" name="custom_field_id" class="form-control"><option value="">Sin filtro adicional</option></select></div>
                        <div class="form-group col-md-3"><label for="sales-order-search-value">Valor exacto</label><input id="sales-order-search-value" name="custom_field_value" class="form-control"></div>
                        <div class="form-group col-md-3"><label>Registros por página</label><select name="per_page" class="form-control" aria-label="Registros por página"><option>25</option><option>50</option><option>100</option></select></div>
                        <div class="form-group col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary mr-2">Buscar</button><button id="sales-order-search-clear" type="button" class="btn btn-light">Limpiar filtros</button></div>
                      </div>
                    </form>
                    <div class="alert alert-info">
                        La orden de venta formaliza el pedido comercial. La confirmación compromete stock sin reducir la existencia física. Las órdenes pendientes pueden editarse; las confirmadas se consultan y procesan con documentos relacionados.
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2"><small id="sales-order-page-info" role="status"></small><div><button type="button" id="sales-order-page-prev" class="btn btn-sm btn-light">Página anterior</button><button type="button" id="sales-order-page-next" class="btn btn-sm btn-light">Página siguiente</button></div></div>
                    <small class="text-muted">Las exportaciones incluyen los registros de la página actual.</small>
                    <div class="table-responsive">
                        <table id="table-sales-orders" data-document-mode="LIST" class="table table-bordered table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nro. Orden</th>
                                    <th>Cotizacion</th>
                                    <th>Cliente</th>
                                    <th>Emision</th>
                                    <th>Entrega</th>
                                    <th>Total</th>
                                    <th>Estado comercial</th>
                                    <th>Entrega</th>
                                    <th>Acciones</th>
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

<div class="modal fade" id="sales-order-create-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Orden de venta <span id="sales-order-mode" class="badge badge-info"></span></h5><button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button></div>
    <form id="sales-order-create-form" novalidate><div class="modal-body">
      <div class="form-row"><div class="form-group col-md-6"><label>Cliente</label><select id="sales-order-customer" name="customer_id" class="form-control" required><option value="">Seleccione un cliente</option></select></div><div class="form-group col-md-3"><label>Almacen</label><select id="sales-order-warehouse" name="warehouse_id" class="form-control" required><option value="">Seleccione</option></select></div><div class="form-group col-md-3"><label>Moneda</label><select name="currency" class="form-control"><option value="PEN">PEN</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div></div>
      <div class="form-row"><div class="form-group col-md-12"><label>Dirección de entrega</label><select id="sales-order-shipping-address" name="shipping_address_id" class="form-control"><option value="">Dirección principal del cliente</option></select><small class="form-text text-muted">La orden conservará una copia de esta dirección aunque el maestro cambie posteriormente.</small></div></div>
      <div class="form-row"><div class="form-group col-md-4"><label>Fecha de entrega</label><input type="date" name="delivery_date" class="form-control" required></div><div class="form-group col-md-8"><label>Referencia</label><input name="reference" class="form-control" maxlength="300"></div></div>
      <div class="d-flex justify-content-between align-items-center mb-2"><strong>Articulos</strong><button type="button" id="sales-order-add-line" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus mr-1"></i>Agregar articulo</button></div>
      <div class="row small text-muted mb-1"><div class="col-md-6">Artículo</div><div class="col-md-2">Cantidad</div><div class="col-md-3">Precio sin IGV</div></div><div id="sales-order-lines" data-field-path="details"></div><div class="text-right font-weight-bold mt-2">Total con IGV (18%): <span id="sales-order-total">0.00</span></div>
      <div id="sales-order-custom-fields"></div>
    </div><div class="modal-footer">
      <span id="sales-order-identity" class="mr-auto small"></span>
      <button type="button" id="sales-order-new" class="btn btn-outline-primary">Nuevo</button>
      <a id="sales-order-relations" class="btn btn-outline-secondary" target="_blank" rel="noopener">Ver relaciones</a>
      <a id="sales-order-copy-delivery" class="btn btn-outline-success">Copiar a entrega</a>
      <button type="button" id="sales-order-edit" class="btn btn-outline-primary">Editar</button>
      <button type="button" id="sales-order-cancel-edit" class="btn btn-secondary">Cancelar edición</button>
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      <button type="submit" name="save_behavior" value="new" class="btn btn-outline-primary">Guardar y nuevo</button>
      <button type="submit" name="save_behavior" value="view" class="btn btn-primary">Guardar y visualizar</button>
    </div></form>
  </div></div>
</div>
