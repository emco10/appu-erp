<?php
if (!function_exists('app_can') || !app_can('management.view')) { http_response_code(403); exit('Acceso no autorizado.'); }
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid">
    <h1><i class="fas fa-book mr-2"></i>Consulta SIRE</h1>
    <p class="text-muted">Consulta propuestas y descarga información del Registro de Compras (RCE) y del Registro de Ventas (RVIE).</p>
  </div></div>
  <div class="content"><div class="container-fluid" id="sire-workspace">
    <div id="sire-status" class="alert alert-info" role="status" aria-live="polite">Selecciona un libro y pulsa Consultar. Las consultas se ejecutan únicamente al usar los botones.</div>
    <div class="card card-outline card-primary"><div class="card-body">
      <form id="sire-filters" class="form-row align-items-end">
        <div class="form-group col-md-3"><label for="sire-book">Libro</label><select id="sire-book" class="form-control"><option value="080000">RCE — Compras</option><option value="140000">RVIE — Ventas e ingresos</option></select></div>
        <div class="form-group col-md-3"><label for="sire-period">Período</label><input id="sire-period" type="month" class="form-control" required></div>
        <div class="form-group col-md-6"><button type="button" class="btn btn-outline-primary mr-1 mb-1" id="sire-periods">Consultar períodos</button><button type="submit" class="btn btn-primary mr-1 mb-1" id="sire-tickets">Consultar tickets</button><button type="button" class="btn btn-outline-success mb-1" id="sire-export">Solicitar exportación</button></div>
      </form>
      <small class="text-muted">Solicitar una exportación prepara un archivo de consulta en SUNAT. Esta pantalla no acepta propuestas ni genera o presenta libros.</small>
      <div id="sire-period-list" class="mt-3"></div>
    </div></div>
    <div class="card"><div class="card-header"><h2 class="card-title">Tickets del período</h2></div><div class="card-body">
      <div class="table-responsive"><table class="table table-sm" id="sire-ticket-table"><thead><tr><th>Ticket</th><th>Período</th><th>Estado</th><th>Archivos</th><th>Acciones</th></tr></thead><tbody><tr><td colspan="5" class="text-muted">Pulsa Consultar tickets para obtener los resultados.</td></tr></tbody></table></div>
      <div class="d-flex align-items-center"><button id="sire-ticket-prev" type="button" class="btn btn-sm btn-outline-secondary" disabled>Anterior</button><span id="sire-ticket-page" class="mx-3"></span><button id="sire-ticket-next" type="button" class="btn btn-sm btn-outline-secondary" disabled>Siguiente</button></div>
    </div></div>
    <div class="card"><div class="card-header"><h2 class="card-title">Vista de la propuesta</h2></div><div class="card-body">
      <div class="form-row align-items-end">
        <div class="form-group col-md-5"><label for="sire-ticket">Ticket de exportación</label><input id="sire-ticket" class="form-control" inputmode="numeric" maxlength="14" placeholder="Selecciona un ticket o escribe su número"></div>
        <div class="form-group col-md-3"><label for="sire-per-page">Filas por página</label><select id="sire-per-page" class="form-control"><option value="50">50</option><option value="100">100</option><option value="200">200</option></select></div>
        <div class="form-group col-md-4"><button id="sire-proposal" type="button" class="btn btn-primary mr-1">Consultar propuesta</button><button id="sire-download" type="button" class="btn btn-outline-secondary">Descargar ZIP</button></div>
      </div>
      <p class="small text-muted">La vista utiliza el TXT de una exportación terminada. Si el ticket continúa en proceso, vuelve a consultar su estado cuando lo necesites.</p>
      <div class="table-responsive"><table id="sire-proposal-table" class="table table-sm table-bordered"><thead></thead><tbody></tbody></table></div>
      <div class="d-flex align-items-center"><button id="sire-proposal-prev" type="button" class="btn btn-sm btn-outline-secondary" disabled>Anterior</button><span id="sire-proposal-page" class="mx-3"></span><button id="sire-proposal-next" type="button" class="btn btn-sm btn-outline-secondary" disabled>Siguiente</button></div>
    </div></div>
  </div></div>
</div>
