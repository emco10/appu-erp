<?php
$flowVisibleTypes = [];
if (app_can('sales.view')) $flowVisibleTypes += array_fill_keys(['quotation', 'sales_order', 'invoice', 'receipt', 'credit_note'], true);
if (app_can('logistics.view')) $flowVisibleTypes['remission_guide'] = true;
if (app_can('purchases.view')) $flowVisibleTypes += array_fill_keys(['purchase_request', 'order', 'purchase_order', 'goods_receipt', 'purchase', 'landed_cost'], true);
if (app_can('finance.view')) $flowVisibleTypes += array_fill_keys(['incoming_payment', 'outgoing_payment'], true);
?>
<div class="content-wrapper">
  <div class="content-header"><div class="container-fluid"><h1 class="m-0 text-dark">Mapa de relaciones</h1><p class="text-muted">Busca una relación reciente o abre un documento para explorar su recorrido, aplicaciones por línea y estados.</p></div></div>
  <div class="content"><div class="container-fluid" id="commercial-flow" data-types="<?php echo htmlspecialchars(json_encode($flowVisibleTypes), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (app_can('reports.view')): ?>
    <div class="card">
      <div class="card-header"><strong>Relaciones recientes</strong><button type="button" id="flow-report-refresh" class="btn btn-sm btn-outline-primary float-right">Actualizar lista</button></div>
      <div class="card-body">
        <div id="flow-report-error" class="alert alert-danger d-none" role="alert"></div>
        <div class="row mb-3" id="flow-summary"></div>
        <div class="table-responsive"><table id="flow-table" class="table table-sm table-hover">
          <thead><tr><th>Origen</th><th>Destino</th><th>Relación</th><th>Cantidad aplicada</th><th>Fecha</th><th>Acción</th></tr></thead>
          <tbody><tr><td colspan="6" class="text-muted">Cargando relaciones…</td></tr></tbody>
        </table></div>
      </div>
    </div>
    <?php endif; ?>
    <div id="commercial-flow-error" class="alert alert-danger d-none" role="alert"></div>
    <div class="card"><div class="card-header"><strong id="commercial-flow-title">Mapa interactivo</strong><button type="button" id="commercial-flow-refresh" class="btn btn-sm btn-outline-primary float-right">Actualizar mapa</button></div><div class="card-body">
      <p id="commercial-flow-status" role="status"></p>
      <div class="commercial-flow-scroll"><div id="commercial-flow-canvas"></div></div>
      <p class="small text-muted mt-2">Selecciona un documento de la lista para ver sus relaciones y aplicaciones por línea. Las relaciones anuladas se conservan para auditoría; las entregas no tienen un importe financiero propio.</p>
    </div></div>
    <div class="card"><div class="card-header">Aplicaciones por línea del documento seleccionado</div><div class="card-body table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Origen</th><th>Línea origen</th><th>Destino</th><th>Línea destino</th><th>Etapa</th><th>Cantidad</th><th>Estado</th></tr></thead><tbody id="commercial-flow-lines"></tbody></table></div></div>
  </div></div>
</div>
<style>
.commercial-flow-scroll{overflow-x:auto;padding:8px}.commercial-flow-canvas{position:relative;min-height:180px}.commercial-flow-node{position:absolute;width:220px;height:132px;border:1px solid #b8c2cc;border-radius:8px;background:var(--bs-body-bg,#fff);padding:10px;z-index:1;overflow:hidden;box-shadow:0 2px 5px #0001}.commercial-flow-node.is-selected{border:2px solid #007bff}.commercial-flow-node.is-cancelled{opacity:.65}.commercial-flow-node strong,.commercial-flow-node small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.commercial-flow-node button{border:0;background:none;color:#007bff;text-align:left;padding:0;max-width:100%;font-weight:600}.commercial-flow-node .flow-links{display:flex;justify-content:space-between;font-size:12px;margin-top:5px}.commercial-flow-svg{position:absolute;inset:0;pointer-events:none}.commercial-flow-svg path{fill:none;stroke:#718096;stroke-width:2}.commercial-flow-svg text{font-size:11px;fill:#526071}.dark-mode .commercial-flow-node{background:#343a40;color:#fff}.dark-mode .commercial-flow-svg text{fill:#ddd}
</style>
