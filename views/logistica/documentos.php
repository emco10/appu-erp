<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 text-dark">Seguimiento de despachos</h1>
            <p class="text-muted mb-0">Consulta documentos comerciales y gestiona sus entregas operativas internas.</p>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i>
                Este mapa conserva la trazabilidad operativa completa, incluso cuando un documento o pago haya sido anulado. Las entregas fiscales se gestionan en Guía de Remisión; la integración electrónica con SUNAT permanece fuera de esta etapa.
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-truck mr-1"></i> Documentos y despachos vinculados</h3></div>
                <div class="card-body">
                    <form id="document-search-form" class="row">
                        <div class="col-md-9">
                            <label for="document-search">Buscar por número, cliente o RUC</label>
                            <input id="document-search" class="form-control" type="search" minlength="2" autocomplete="off" placeholder="Ej.: F001-000001 o razón social">
                        </div>
                        <div class="col-md-3 d-flex align-items-end mt-2 mt-md-0">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search mr-1"></i> Buscar documentos</button>
                        </div>
                    </form>
                    <div id="document-search-error" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
                    <div id="document-search-results" class="list-group mt-3"></div>
                    <div id="document-map" class="mt-3"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-truck-loading mr-1"></i> Entregas operativas vinculadas</h3></div>
                <div class="card-body table-responsive p-0" id="delivery-list"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delivery-modal" tabindex="-1" role="dialog" aria-labelledby="delivery-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="delivery-form" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="delivery-modal-title">Registrar entrega operativa</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_delivery">
                <input type="hidden" name="source_type">
                <input type="hidden" name="source_id">
                <div class="form-group">
                    <label for="delivery-recipient">Destinatario</label>
                    <input id="delivery-recipient" name="recipient_name" class="form-control" maxlength="200" required>
                </div>
                <div class="form-group">
                    <label for="delivery-address">Dirección de entrega</label>
                    <input id="delivery-address" name="delivery_address" class="form-control" maxlength="300" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="delivery-planned-date">Fecha programada</label>
                        <input id="delivery-planned-date" name="planned_date" type="date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="delivery-carrier">Transportista</label>
                        <input id="delivery-carrier" name="carrier" class="form-control" maxlength="150">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="delivery-tracking">Referencia de despacho</label>
                        <input id="delivery-tracking" name="tracking_number" class="form-control" maxlength="100">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="delivery-notes">Observaciones</label>
                    <textarea id="delivery-notes" name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Registrar despacho</button>
            </div>
        </form>
    </div>
</div>
