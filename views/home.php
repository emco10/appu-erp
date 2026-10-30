<?php

require_once dirname(__DIR__) . '/global/connection.php';
require_once dirname(__DIR__) . '/global/reporting.php';

$dashboardMonthNames = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre',
];
$dashboardCurrentMonth = new DateTimeImmutable('first day of this month');
$dashboardStartDate = $dashboardCurrentMonth->modify('-5 months');
$dashboardEndDate = $dashboardCurrentMonth->modify('+1 month');
$dashboardMonths = [];

for ($monthOffset = 0; $monthOffset < 6; $monthOffset++) {
    $month = $dashboardStartDate->modify("+$monthOffset months");
    $dashboardMonths[$month->format('Y-m')] = [
        'label' => $dashboardMonthNames[(int) $month->format('n')] . ' ' . $month->format('Y'),
        'sales' => [],
        'documents' => [],
        'credit_notes' => 0,
    ];
}

$dashboardCurrencySql=app_currency_sql('currency');
$dashboardSalesQuery = $pdo->prepare(
    "SELECT DATE_FORMAT(sales.date, '%Y-%m') AS month, sales.currency,
            SUM(sales.net_amount) AS net_amount,
            SUM(sales.document_count) AS document_count,
            SUM(sales.credit_note_count) AS credit_note_count
     FROM (
         SELECT date, {$dashboardCurrencySql} currency, total_net AS net_amount, 1 AS document_count, 0 AS credit_note_count
         FROM tbl_invoice
         WHERE status NOT IN (2,4) AND document_subtype<>'ADVANCE' AND date >= ? AND date < ?
         UNION ALL
         SELECT date, {$dashboardCurrencySql} currency, total_net AS net_amount, 1 AS document_count, 0 AS credit_note_count
         FROM tbl_receipt
         WHERE status NOT IN (2,4) AND date >= ? AND date < ?
         UNION ALL
         SELECT date, {$dashboardCurrencySql} currency, -total_net AS net_amount, 0 AS document_count, 1 AS credit_note_count
         FROM tbl_credit_note
         WHERE status NOT IN (2,4) AND date >= ? AND date < ?
           AND NOT EXISTS(SELECT 1 FROM tbl_invoice advance_invoice WHERE tbl_credit_note.referenced_doc_type_id=1 AND advance_invoice.id=tbl_credit_note.referenced_doc_id AND advance_invoice.document_subtype='ADVANCE')
     ) AS sales
     GROUP BY month, sales.currency
     ORDER BY month, sales.currency"
);
$dashboardSalesQuery->execute([
    $dashboardStartDate->format('Y-m-d'),
    $dashboardEndDate->format('Y-m-d'),
    $dashboardStartDate->format('Y-m-d'),
    $dashboardEndDate->format('Y-m-d'),
    $dashboardStartDate->format('Y-m-d'),
    $dashboardEndDate->format('Y-m-d'),
]);

$dashboardCurrencies = [];
$dashboardHasSales = false;
foreach ($dashboardSalesQuery->fetchAll() as $sale) {
    $dashboardHasSales = true;
    $monthKey = $sale['month'];
    $currency = (string) $sale['currency'];
    if (!isset($dashboardMonths[$monthKey])) {
        continue;
    }

    $dashboardCurrencies[$currency] = $currency;
    $dashboardMonths[$monthKey]['sales'][$currency] = (float) $sale['net_amount'];
    $dashboardMonths[$monthKey]['documents'][$currency] = (int) $sale['document_count'];
    $dashboardMonths[$monthKey]['credit_notes'] += (int) $sale['credit_note_count'];
}

if ($dashboardCurrencies === []) {
    $dashboardCurrencies['PEN'] = 'PEN';
}

$dashboardCurrentKey = $dashboardCurrentMonth->format('Y-m');
$dashboardCurrentSales = $dashboardMonths[$dashboardCurrentKey]['sales'];
$dashboardCurrentDocuments = $dashboardMonths[$dashboardCurrentKey]['documents'];
$dashboardCurrentCreditNotes = $dashboardMonths[$dashboardCurrentKey]['credit_notes'];
$dashboardCurrencyLabel = static function (string $currency): string {
    return match ($currency) {
        'MN', 'PEN' => 'S/',
        'ME', 'USD' => 'US$',
        default => $currency,
    };
};
$dashboardFormatAmounts = static function (array $amounts) use ($dashboardCurrencyLabel): string {
    if ($amounts === []) {
        return 'S/ 0.00';
    }

    $formatted = [];
    foreach ($amounts as $currency => $amount) {
        $formatted[] = $dashboardCurrencyLabel((string) $currency) . ' ' . number_format((float) $amount, 2, '.', ',');
    }

    return implode(' · ', $formatted);
};
$dashboardAverageTicket = [];
foreach ($dashboardCurrentDocuments as $currency => $documentCount) {
    if ($documentCount > 0) {
        $dashboardAverageTicket[$currency] = ($dashboardCurrentSales[$currency] ?? 0) / $documentCount;
    }
}

$dashboardReceivableBalances = [];
foreach ($pdo->query("SELECT currency, COALESCE(SUM(balance_amount),0) AS amount FROM tbl_accounts_receivable WHERE status IN ('Pendiente','Parcial') GROUP BY currency")->fetchAll() as $balance) {
    $currency=app_currency_code((string)$balance['currency']);$dashboardReceivableBalances[$currency]=($dashboardReceivableBalances[$currency]??0)+(float)$balance['amount'];
}
$dashboardPayableBalances = [];
foreach ($pdo->query("SELECT currency, COALESCE(SUM(balance_amount),0) AS amount FROM tbl_accounts_payable WHERE status IN ('Pendiente','Parcial') GROUP BY currency")->fetchAll() as $balance) {
    $currency=app_currency_code((string)$balance['currency']);$dashboardPayableBalances[$currency]=($dashboardPayableBalances[$currency]??0)+(float)$balance['amount'];
}
$dashboardOverdueReceivable = (int) $pdo->query("SELECT COUNT(*) FROM tbl_accounts_receivable WHERE status IN ('Pendiente','Parcial') AND balance_amount > 0 AND due_date < CURDATE()")->fetchColumn();
$dashboardOverduePayable = (int) $pdo->query("SELECT COUNT(*) FROM tbl_accounts_payable WHERE status IN ('Pendiente','Parcial') AND balance_amount > 0 AND due_date < CURDATE()")->fetchColumn();

$dashboardOperational = [
    'pending_orders' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_sales_order WHERE status = 'Pendiente'")->fetchColumn(),
    'open_quotations' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_quotation quotation LEFT JOIN tbl_sales_order sales_order ON sales_order.quotation_id = quotation.id AND sales_order.status <> 'Anulada' WHERE quotation.status = 1 AND sales_order.id IS NULL")->fetchColumn(),
    'pending_purchases' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_purchase WHERE status IN ('Pendiente','Rechazado')")->fetchColumn(),
    'pending_purchase_requests' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_purchase_request WHERE status IN ('Pendiente','Aprobada')")->fetchColumn(),
    'guides_transit' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_remission_guide WHERE status = 'En transito'")->fetchColumn(),
    'low_stock' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_product product LEFT JOIN (SELECT product_id, COALESCE(SUM(quantity - reserved_quantity),0) AS available_quantity FROM tbl_warehouse_stock GROUP BY product_id) stock ON stock.product_id = product.id WHERE product.active_status = 1 AND COALESCE(stock.available_quantity, product.stock_quantity) <= 5")->fetchColumn(),
    'expiring_lots' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_product_lot WHERE quantity - reserved_quantity > 0 AND expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'active_warehouses' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_warehouse WHERE active_status = 1")->fetchColumn(),
    'monthly_transfers' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_inventory_transfer WHERE status <> 'Anulada' AND transfer_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
    'receivable_balance' => $dashboardReceivableBalances,
    'payable_balance' => $dashboardPayableBalances,
];

$dashboardMaxSales = [];
foreach ($dashboardCurrencies as $currency) {
    $dashboardMaxSales[$currency] = 0;
    foreach ($dashboardMonths as $month) {
        $dashboardMaxSales[$currency] = max($dashboardMaxSales[$currency], (float) ($month['sales'][$currency] ?? 0));
    }
}

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 text-dark">Inicio</h1>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="card card-primary card-outline appu-welcome-card">
            <div class="card-header">
              <h5 class="m-1 appu-welcome-title"><i class="far fa-hand-spock"></i> ¡Bienvenido(a)! <strong><?php echo e($_SESSION['loggedInUser']['EMPLOYEE_NAME']); ?></strong></h5>
            </div>
          </div>
        </div>
      </div>

      <div class="row dashboard-kpis">
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['expiring_lots'])); ?></h3><p>Lotes por vencer en 30 dias</p></div><div class="icon"><i class="fas fa-hourglass-half"></i></div><a href="<?php echo $functions->direct_paginas(); ?>productos/stock-por-almacen" class="small-box-footer">Ver lotes <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e($dashboardFormatAmounts($dashboardOperational['receivable_balance'])); ?></h3><p>Saldo por cobrar</p></div><div class="icon"><i class="fas fa-hand-holding-usd"></i></div><a href="<?php echo $functions->direct_paginas(); ?>finanzas/cuentas-por-cobrar" class="small-box-footer">Ver CxC <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e($dashboardFormatAmounts($dashboardOperational['payable_balance'])); ?></h3><p>Saldo por pagar</p></div><div class="icon"><i class="fas fa-file-invoice-dollar"></i></div><a href="<?php echo $functions->direct_paginas(); ?>finanzas/cuentas-por-pagar" class="small-box-footer">Ver CxP <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['pending_purchase_requests'])); ?></h3><p>Solicitudes de compra abiertas</p></div><div class="icon"><i class="fas fa-clipboard-check"></i></div><a href="<?php echo $functions->direct_paginas(); ?>compras/solicitudes" class="small-box-footer">Ver solicitudes <i class="fas fa-arrow-circle-right"></i></a></div></div>
      </div>

      <div class="card card-outline card-warning mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between">
          <span class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Alertas de vencimiento</span>
          <span class="mr-3"><a href="<?php echo $functions->direct_paginas(); ?>finanzas/cuentas-por-cobrar" class="text-dark"><strong><?php echo e(number_format($dashboardOverdueReceivable)); ?></strong> por cobrar vencidas</a></span>
          <span><a href="<?php echo $functions->direct_paginas(); ?>finanzas/cuentas-por-pagar" class="text-dark"><strong><?php echo e(number_format($dashboardOverduePayable)); ?></strong> por pagar vencidas</a></span>
        </div>
      </div>

      <div class="row dashboard-kpis">
        <div class="col-lg-3 col-6">
          <div class="small-box bg-info">
            <div class="inner">
              <h3 id="dashboard-net-sales" class="dashboard-kpi-value"><?php echo e($dashboardFormatAmounts($dashboardCurrentSales)); ?></h3>
              <p>Ventas netas del mes</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-success">
            <div class="inner">
              <h3 id="dashboard-sales-documents"><?php echo e(number_format(array_sum($dashboardCurrentDocuments))); ?></h3>
              <p>Facturas y boletas emitidas</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-warning">
            <div class="inner">
              <h3 id="dashboard-credit-notes"><?php echo e(number_format($dashboardCurrentCreditNotes)); ?></h3>
              <p>Notas de crédito del mes</p>
            </div>
            <div class="icon"><i class="fas fa-undo-alt"></i></div>
          </div>
        </div>
        <div class="col-lg-3 col-6">
          <div class="small-box bg-danger">
            <div class="inner">
              <h3 class="dashboard-kpi-value"><?php echo e($dashboardFormatAmounts($dashboardAverageTicket)); ?></h3>
              <p>Ticket promedio del mes</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
          </div>
        </div>
      </div>

      <div class="row dashboard-kpis">
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['pending_orders'])); ?></h3><p>Órdenes de venta pendientes</p></div><div class="icon"><i class="fas fa-clipboard-list"></i></div><a href="<?php echo $functions->direct_paginas(); ?>ventas/resumen-ordenes-venta" class="small-box-footer">Ver órdenes <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['open_quotations'])); ?></h3><p>Cotizaciones abiertas</p></div><div class="icon"><i class="fas fa-file-signature"></i></div><a href="<?php echo $functions->direct_paginas(); ?>cotizacion/resumen-cotizaciones" class="small-box-footer">Ver cotizaciones <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['pending_purchases'])); ?></h3><p>Compras por gestionar</p></div><div class="icon"><i class="fas fa-shopping-cart"></i></div><a href="<?php echo $functions->direct_paginas(); ?>compras/resumen-compras" class="small-box-footer">Ver compras <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['guides_transit'])); ?></h3><p>Guías en tránsito</p></div><div class="icon"><i class="fas fa-truck"></i></div><a href="<?php echo $functions->direct_paginas(); ?>logistica/guias" class="small-box-footer">Ver guías <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['low_stock'])); ?></h3><p>Artículos con stock bajo</p></div><div class="icon"><i class="fas fa-exclamation-triangle"></i></div><a href="<?php echo $functions->direct_paginas(); ?>productos/listado-producto" class="small-box-footer">Ver inventario <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['active_warehouses'])); ?></h3><p>Almacenes activos</p></div><div class="icon"><i class="fas fa-warehouse"></i></div><a href="<?php echo $functions->direct_paginas(); ?><?php echo app_can('management.view') ? 'gestion/index' : 'productos/stock-por-almacen'; ?>" class="small-box-footer"><?php echo app_can('management.view') ? 'Gestionar almacenes' : 'Ver stock por almacén'; ?> <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-primary"><div class="inner"><h3><?php echo e(number_format($dashboardOperational['monthly_transfers'])); ?></h3><p>Transferencias del mes</p></div><div class="icon"><i class="fas fa-exchange-alt"></i></div><a href="<?php echo $functions->direct_paginas(); ?>productos/transferencias" class="small-box-footer">Ver transferencias <i class="fas fa-arrow-circle-right"></i></a></div></div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i> Ventas netas de los últimos 6 meses</h3>
            </div>
            <div class="card-body">
              <?php if (!$dashboardHasSales): ?>
                <p class="text-muted mb-0">Aún no hay ventas recientes. Al emitir facturas o boletas, la tendencia aparecerá aquí.</p>
              <?php else: ?>
                <?php foreach ($dashboardMonths as $month): ?>
                  <?php foreach ($dashboardCurrencies as $currency): ?>
                    <?php
                      $amount = (float) ($month['sales'][$currency] ?? 0);
                      $maximum = $dashboardMaxSales[$currency];
                      $barWidth = $maximum > 0 ? min(100, max(0, $amount / $maximum * 100)) : 0;
                    ?>
                    <div class="dashboard-sales-row">
                      <div class="dashboard-sales-label"><?php echo e($month['label']); ?> <span class="text-muted"><?php echo e($dashboardCurrencyLabel($currency)); ?></span></div>
                      <div class="progress dashboard-sales-progress" role="progressbar" aria-label="<?php echo e($month['label'] . ' ' . $dashboardCurrencyLabel($currency)); ?>" aria-valuenow="<?php echo e((string) round($barWidth)); ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-info" style="width: <?php echo e((string) $barWidth); ?>%"></div>
                      </div>
                      <div class="dashboard-sales-amount"><?php echo e($dashboardFormatAmounts([$currency => $amount])); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="card-footer text-muted">Los documentos anulados no se incluyen; las notas de crédito se descuentan de las ventas.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
