<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$contracts = [
    'ajax/registro-cotizacion.js' => ['appuOpenRequestedRecord', 'cotizacion_listado', 'btn-select-cotizacion'],
    'ajax/registro-factura.js' => ['appuOpenRequestedRecord', 'facturas_listado', 'btn-select-factura'],
    'ajax/registro-boleta.js' => ['appuOpenRequestedRecord', 'facturas_listado', 'btn-select-factura'],
    'ajax/registro-nota-credito.js' => ['appuOpenRequestedRecord', 'facturas_listado', 'btn-select-factura'],
    'ajax/orden-de-compra.js' => ['appuOpenRequestedRecord', 'orden_listado', 'btn-select-orden'],
    'ajax/resumen-ordenes-venta.js' => ['record_id', 'table-primary', 'scrollIntoView'],
    'ajax/resumen-compras.js' => ['record_id', 'table-primary', 'scrollIntoView'],
    'ajax/solicitudes.js' => ['record_id', 'table-primary', 'scrollIntoView'],
    'ajax/documentos.js' => ['Ver relaciones', 'Abrir módulo', 'record_id', 'initialType', 'initialId'],
    'ajax/flujo-documental.js' => ['Ver mapa', 'incoming_payment', 'outgoing_payment'],
];

foreach ($contracts as $relativePath => $needles) {
    $contents = file_get_contents($root . '/' . $relativePath);
    if ($contents === false) throw new RuntimeException('No se pudo leer ' . $relativePath);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) throw new RuntimeException("Falta {$needle} en {$relativePath}");
    }
}

$routes = [
    'views/cotizacion/registro-cotizacion.php', 'views/ventas/resumen-ordenes-venta.php',
    'views/facturacion/registro-factura.php', 'views/facturacion/registro-boleta.php',
    'views/facturacion/registro-nota-credito.php', 'views/compras/solicitudes.php',
    'views/ordenes/orden-de-compra.php', 'views/compras/resumen-compras.php',
];
foreach ($routes as $route) {
    if (!is_file($root . '/' . $route)) throw new RuntimeException('Ruta documental inexistente: ' . $route);
}

$sidebar = file_get_contents($root . '/views/template/sidebar.php');
$header = file_get_contents($root . '/views/template/header.php');
$salesOrderView = file_get_contents($root . '/views/ventas/resumen-ordenes-venta.php');
$navigationScript = file_get_contents($root . '/js/footer-functions.js');
if ($sidebar === false || $header === false || $salesOrderView === false || $navigationScript === false) throw new RuntimeException('No se pudo leer la navegación principal.');
preg_match_all('/id="(m_[A-Za-z0-9_-]+)"/', $sidebar, $menuMatches);
$menuIds = array_fill_keys($menuMatches[1], true);
foreach (glob($root . '/ajax/*.js') as $scriptPath) {
    $contents = file_get_contents($scriptPath);
    if ($contents === false) throw new RuntimeException('No se pudo leer ' . $scriptPath);
    preg_match_all('/#(m_[A-Za-z0-9_-]+)/', $contents, $selectorMatches);
    foreach (array_unique($selectorMatches[1]) as $menuId) {
        if (!isset($menuIds[$menuId])) {
            throw new RuntimeException('Selector de menú inexistente #' . $menuId . ' en ' . basename($scriptPath));
        }
    }
}
foreach ([
    "\$submenu.find('.nav-link.active')",
    "\$group.toggleClass('menu-open', hasActiveLink)",
    'window.setTimeout(appuCollapseNavigation, 750)',
    'route.indexOf("resumen-ordenes-venta") !== -1 ? "#btn-new-sales-order"',
    '$target.trigger("click")',
] as $navigationRule) {
    if (!str_contains($navigationScript, $navigationRule)) {
        throw new RuntimeException('Falta regla de navegación activa: ' . $navigationRule);
    }
}
foreach (['customers.manage', 'inventory.manage', 'employees.manage', 'users.manage', 'sales.manage', 'purchases.manage'] as $createPermission) {
    if (!str_contains($header, "app_can('" . $createPermission . "')")) {
        throw new RuntimeException('El menú Crear no valida el permiso ' . $createPermission);
    }
}
if (substr_count($header, '<button type="button" class="nav-link dropdown-toggle border-0 bg-transparent"') !== 3) {
    throw new RuntimeException('Crear, Buscar y Navegar deben usar botones sin href javascript:void(0).');
}
if (!str_contains($header, 'd-none d-sm-inline-block appu-quick-nav')) {
    throw new RuntimeException('Navegar debe estar disponible en pantallas de computadora compactas.');
}
if (preg_match('/<a\b[^>]*class="[^"]*dropdown-toggle[^"]*"[^>]*href="javascript:void\(0\)"/', $header)) {
    throw new RuntimeException('Los accesos desplegables de navegación usan enlaces con destino javascript:void(0).');
}
if (preg_match('/<a\b[^>]*class="nav-link"[^>]*href="[^"]*facturacion\/registro-nota-credito"/', $header)) {
    throw new RuntimeException('Se encontró un acceso de nota de crédito sin control dentro de la barra superior.');
}
if (!str_contains($salesOrderView, 'id="btn-new-sales-order"')) {
    throw new RuntimeException('El resumen de órdenes no contiene el control de creación esperado.');
}

echo 'PASS document map direct navigation contract' . PHP_EOL;
