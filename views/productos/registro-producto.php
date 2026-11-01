<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-box"></i>&nbsp;&nbsp;Registro Maestro de Productos
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="appu-document-toolbar appu-product-toolbar" data-page-nav="true" role="toolbar" aria-label="Herramientas de artículos">
        <div class="appu-document-toolbar__context"><span class="appu-document-toolbar__eyebrow">MAESTRO</span><span class="appu-document-toolbar__title">Artículos</span><span data-master-mode role="status" aria-live="polite"></span></div>
        <div class="appu-document-toolbar__actions">
<button type="button" class="btn btn-sm btn-light appu-product-new" data-master-action="new"><i class="fas fa-file"></i><span>Nuevo</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-find" data-master-action="find"><i class="fas fa-search"></i><span>Buscar</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-first" data-master-action="first"><i class="fas fa-angle-double-left"></i><span>Primero</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-prev" data-master-action="prev"><i class="fas fa-angle-left"></i><span>Anterior</span></button>
<span data-master-position class="text-muted" aria-live="polite">0 de 0</span>
<button type="button" class="btn btn-sm btn-light appu-product-next" data-master-action="next"><i class="fas fa-angle-right"></i><span>Siguiente</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-last" data-master-action="last"><i class="fas fa-angle-double-right"></i><span>Último</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-edit" data-master-action="edit"><i class="fas fa-pen"></i><span>Editar</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-cancel" data-master-action="cancel"><i class="fas fa-undo"></i><span>Cancelar</span></button>
<button type="button" class="btn btn-sm btn-primary appu-product-save" data-master-action="save"><i class="fas fa-save"></i><span>Guardar y consultar</span></button>
<button type="button" class="btn btn-sm btn-light appu-product-save-new" data-master-action="save-new"><i class="fas fa-plus"></i><span>Guardar y nuevo</span></button>
        </div>
    </div>
    <div class="modal fade" id="appu-buscar-producto-modal" tabindex="-1" aria-labelledby="product-search-title" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="product-search-title">Buscar artículos</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
          <form class="row g-2 mb-3">
            <div class="col-md-6"><label for="product-search-query">Código, nombre, marca o código de barras</label><input type="search" id="product-search-query" data-master-query class="form-control" placeholder="Use * para mostrar todos"></div>
            <div class="col-md-3"><label for="product-search-status">Estado</label><select id="product-search-status" data-master-status class="form-control"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-md-3 align-self-end"><button type="submit" class="btn btn-primary">Buscar</button></div>
            <div class="col-md-6"><label for="product-search-field">Campo adicional</label><select id="product-search-field" data-master-field class="form-control"><option value="">Sin campo adicional</option></select></div>
            <div class="col-md-6"><label for="product-search-value">Valor exacto</label><input id="product-search-value" data-master-value class="form-control"></div>
          </form>
          <div data-master-search-error class="text-danger" role="alert"></div>
          <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Código</th><th>Artículo</th><th>Marca</th><th>Estado</th><th>Acción</th></tr></thead><tbody></tbody></table></div>
          <div class="d-flex justify-content-between align-items-center"><button type="button" data-master-prev-page class="btn btn-light">Página anterior</button><span data-master-page role="status" aria-live="polite"></span><button type="button" data-master-next-page class="btn btn-light">Página siguiente</button></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
      </div></div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div style="max-width: 1140px;margin: 0 auto;">
                <form id="FRM_INSERT_PRODUCTO" novalidate method="post" action="<?php echo $functions->direct_sistema(); ?>/modules/productos/insert-update-producto.php" enctype="multipart/form-data">
                    <input type="hidden" name="producto_id" id="">
                    <div class="card card-primary">
                        <div class="card-header">
                            <div class="card-title">Datos del Producto</div>
                            <div class="float-right" style="height: 2rem; width: 150px">
                                <input type="text" class="form-control" placeholder="ID de producto" name="producto_codigo" readonly data-master-readonly>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Código</label>
                                        <input type="text" class="form-control" placeholder="Ingrese código" name="producto_code" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nombre</label>
                                        <input type="text" class="form-control" placeholder="Ingrese nombre de producto" name="producto_nombre" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Marca</label>
                                        <input type="text" class="form-control" placeholder="Ingrese marca de producto" name="producto_marca" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3"><div class="form-group"><label>Código de barras</label><input type="text" class="form-control" name="producto_barcode" maxlength="50"></div></div>
                                <div class="col-md-3"><div class="form-group"><label>Código de artículo SUNAT</label><input type="text" class="form-control" name="producto_sunat_code" maxlength="30"></div></div>
                                <div class="col-md-3"><div class="form-group"><label>Unidad SUNAT</label><input type="text" class="form-control" name="producto_sunat_unit" maxlength="10" placeholder="NIU, KGM..."></div></div>
                                <div class="col-md-3"><div class="form-group"><label>Afectación tributaria SUNAT</label><select class="form-control" name="producto_tax_code" required><option value="10">10 - Gravado (18% IGV)</option><option value="20">20 - Exonerado (0% IGV)</option><option value="30">30 - Inafecto (0% IGV)</option><option value="40">40 - Exportación (0% IGV)</option></select></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4"><div class="form-group"><label>Código catálogo OSCE</label><input type="text" class="form-control" name="producto_osce_code" maxlength="50"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>Código fabricante</label><input type="text" class="form-control" name="producto_manufacturer_code" maxlength="50"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>Modelo</label><input type="text" class="form-control" name="producto_model" maxlength="100"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Descripción</label>
                                        <input type="text" class="form-control" placeholder="Ingrese descripción de producto" name="producto_description">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Valor de Medida</label>
                                        <select class="form-control select2" name="producto_unitvalue">
                                            <option value="">Seleccione</option>
                                            <option value="gr">gr (Gramos)</option>
                                            <option value="mg">mg (Miligramos)</option>
                                            <option value="lt">lt (Litros)</option>
                                            <option value="ml">ml (Mililitros)</option>
                                            <option value="gl">gl (Galones)</option>
                                            <option value="und">und (Unidad)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Precio</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </span>
                                            </div>
                                            <input type="number" min="0" step="any" class="form-control"name="producto_precio" value="" required>
                                        </div>
                                    </div>
                                </div>
                                <!--
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Stock</label>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-box"></i>
                                                </span>
                                            </div>
                                            <input type="number" min="0" class="form-control" name="producto_cantidad" placeholder="Cantidad del producto" value="1" required>
                                        </div>
                                    </div>
                                </div>
                                -->
                                <div class="col-md-10">
                                    <div class="form-group text-right">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" id="prod_estado" name="producto_estado">
                                            <label for="prod_estado" class="custom-control-label">Desactivado</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-outline card-secondary mb-3"><div class="card-header"><h3 class="card-title">Campos adicionales</h3></div><div class="card-body" id="product-custom-fields"></div></div>
                    <div class="row">
                        <div class="col-12">
                            <button type="button" id="btn-new" class="btn btn-primary">
                                <i class="fa fa-broom fa-1x"></i>&nbsp;&nbsp;Limpiar campos
                            </button>
                            <button type="button" id="btn-product-list" class="btn btn-secondary">
                                <i class="fa fa-box fa-1x"></i>&nbsp;&nbsp;Listado de Productos
                            </button>
                            <div class="float-right">
                                <button type="submit" id="btn-save-product" class="btn btn-success btn-md"><i class="fa fa-save fa-1x"></i>&nbsp;&nbsp;<font>Guardar y consultar</font></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
