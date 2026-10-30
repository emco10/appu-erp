<div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><h1 class="m-0"><i class="fas fa-exchange-alt mr-2"></i>Transferencias entre almacenes</h1><p class="text-muted mb-0">Mueve existencias sin perder la trazabilidad del inventario.</p></div></div>
    <div class="content"><div class="container-fluid"><div class="row">
        <div class="col-md-4"><div class="card card-primary"><div class="card-header"><h3 class="card-title">Nueva transferencia</h3></div><div class="card-body">
            <form id="transfer-form">
                <div class="form-group"><label>Artículo</label><select id="transfer-product" name="product_id" class="form-control" required></select></div>
                <div class="form-group"><label>Almacén origen</label><select id="transfer-from" name="from_warehouse_id" class="form-control" required></select></div>
                <div class="form-group"><label>Almacén destino</label><select id="transfer-to" name="to_warehouse_id" class="form-control" required></select></div>
                <div class="form-group"><label>Cantidad</label><input name="quantity" type="number" min="0.001" step="0.001" class="form-control" required></div>
                <?php require __DIR__ . '/partial-campos-transferencia.php'; ?>
                <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save mr-1"></i>Registrar transferencia</button>
            </form>
        </div></div></div>
        <div class="col-md-8"><div class="card"><div class="card-header"><h3 class="card-title">Historial reciente</h3></div><div class="card-body table-responsive"><table id="transfer-table" class="table table-bordered table-sm"><thead><tr><th>Referencia</th><th>Fecha</th><th>Artículo</th><th>Almacén</th><th>Movimiento</th><th>Cantidad</th></tr></thead><tbody></tbody></table></div></div></div>
    </div></div></div>
</div>
