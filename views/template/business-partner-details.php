<div class="card card-outline card-secondary appu-partner-details mt-3" data-partner-type="<?php echo e($partnerDetailType); ?>" data-endpoint="<?php echo e($functions->direct_sistema() . '/modules/' . ($partnerDetailType === 'customer' ? 'clientes' : 'proveedores') . '/datos-socio.php'); ?>">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-address-book mr-2"></i>Direcciones y personas de contacto</h3></div>
    <div class="card-body">
        <div class="alert alert-light border appu-partner-empty">Guarda o selecciona el socio de negocio para administrar sus datos relacionados.</div>
        <div class="appu-partner-content d-none">
            <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Direcciones</h4><button type="button" class="btn btn-sm btn-outline-primary appu-address-new"><i class="fas fa-plus mr-1"></i>Nueva dirección</button></div>
            <div class="table-responsive mb-4"><table class="table table-sm table-hover"><thead><tr><th>Código</th><th>Tipo</th><th>Nombre</th><th>Dirección</th><th>Estado</th><th></th></tr></thead><tbody class="appu-address-rows"></tbody></table></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Personas de contacto</h4><button type="button" class="btn btn-sm btn-outline-primary appu-contact-new"><i class="fas fa-plus mr-1"></i>Nuevo contacto</button></div>
            <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Nombre</th><th>Cargo</th><th>Teléfono</th><th>Correo</th><th>Estado</th><th></th></tr></thead><tbody class="appu-contact-rows"></tbody></table></div>
        </div>
    </div>
</div>
