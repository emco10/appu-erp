<?php

/** Sidebar entries are keyed by the IDs used by screen controllers and quick actions. */
function app_sidebar_catalog(): array
{
    return [
        'm_inicio' => ['Inicio', 'tachometer-alt', 'home'],
        'm_gestion' => ['Gestión', 'sliders-h', [
            'm_gestion_general' => ['Configuración general', 'cog', 'gestion/index'],
            'm_almacenes' => ['Almacenes', 'warehouse', 'productos/almacenes'],
            'm_empleados' => ['Empleados', 'user-tie', 'empleados/registro-empleado'],
            'm_usuarios' => ['Usuarios y perfiles', 'user-cog', 'usuarios/registro-usuario'],
            'm_integraciones_peru' => ['Integraciones Perú', 'plug', 'gestion/integraciones-peru'],
            'm_sire' => ['Consulta SIRE', 'book', 'gestion/sire'],
        ]],
        'm_socios_negocio' => ['Socios de negocio', 'users', [
            'm_clientes' => ['Clientes', 'user', 'clientes/registro-cliente'],
            'm_proveedores' => ['Proveedores', 'people-carry', 'proveedores/registro-proveedor'],
        ]],
        'm_ventas' => ['Ventas', 'shopping-cart', [
            'm_cotizacion' => ['Ofertas de venta', 'file-invoice-dollar', [
                'm_registro_cotizacion' => ['Registrar oferta', 'file', 'cotizacion/registro-cotizacion'],
                'm_resumen_cotizacion' => ['Consultar ofertas', 'list', 'cotizacion/resumen-cotizaciones'],
            ]],
            'm_ordenes_venta' => ['Órdenes de venta', 'clipboard-list', 'ventas/resumen-ordenes-venta'],
            'm_logistica_guias' => ['Entregas y guías de remisión', 'truck-loading', 'logistica/guias'],
            'm_facturacion' => ['Facturas de clientes', 'file-invoice', [
                'm_registro_factura' => ['Registrar factura', 'file', 'facturacion/registro-factura'],
                'm_resumen_factura' => ['Consultar facturas', 'list', 'facturacion/resumen-facturas'],
            ]],
            'm_boleta' => ['Boletas de venta', 'receipt', [
                'm_registro_boleta' => ['Registrar boleta', 'file', 'facturacion/registro-boleta'],
                'm_resumen_boleta' => ['Consultar boletas', 'list', 'facturacion/resumen-boletas'],
            ]],
            'm_nota_credito' => ['Notas de crédito', 'file-invoice', [
                'm_registro_nota_credito' => ['Registrar nota de crédito', 'file', 'facturacion/registro-nota-credito'],
                'm_resumen_nota_credito' => ['Consultar notas de crédito', 'list', 'facturacion/resumen-notas-credito'],
            ]],
            'm_panel_sunat' => ['Panel SUNAT', 'satellite-dish', 'facturacion/panel-sunat'],
        ]],
        'm_modulo_compras' => ['Compras', 'shopping-basket', [
            'm_solicitudes_compra' => ['Solicitudes de compra', 'clipboard-check', 'compras/solicitudes'],
            'm_ordenes' => ['Órdenes de compra', 'clipboard-list', [
                'm_orden_compra' => ['Registrar orden de compra', 'file', 'ordenes/orden-de-compra'],
                'm_orden_servicio' => ['Registrar orden de servicio', 'file', 'ordenes/orden-de-servicio'],
                'm_resumen_orden' => ['Consultar órdenes', 'list', 'ordenes/resumen-ordenes'],
            ]],
            'm_entrada_mercancias' => ['Recepción de proveedor', 'truck-loading', 'compras/recepciones'],
            'm_compras' => ['Facturas de proveedor', 'file-invoice', [
                'm_compra_interna' => ['Registrar factura', 'file', 'compras/compra-interna'],
                'm_resumen_compra' => ['Consultar facturas', 'list', 'compras/resumen-compras'],
            ]],
            'm_costos_entrega' => ['Costos de entrega', 'coins', 'compras/costos-entrega'],
        ]],
        'm_almacen' => ['Inventario', 'boxes-stacked', [
            'm_registro_producto' => ['Maestro de artículos', 'cube', 'productos/registro-producto'],
            'm_listado_producto' => ['Listado de artículos', 'list', 'productos/listado-producto'],
            'm_entrada_inventario' => ['Entrada de inventario', 'arrow-circle-down', 'productos/entrada-inventario'],
            'm_salida_inventario' => ['Salida de inventario', 'arrow-circle-up', 'productos/salida-inventario'],
            'm_transferencias' => ['Transferencia de stock', 'exchange-alt', 'productos/transferencias'],
            'm_stock_almacen' => ['Stock por almacén y lote', 'warehouse', 'productos/stock-por-almacen'],
            'm_historial_movimiento' => ['Auditoría de movimientos', 'history', 'productos/historial-movimiento'],
        ]],
        'm_finanzas' => ['Finanzas', 'money-check-alt', [
            'm_cuentas_cobrar' => ['Cuentas por cobrar', 'hand-holding-usd', 'finanzas/cuentas-por-cobrar'],
            'm_cuentas_pagar' => ['Cuentas por pagar', 'file-invoice-dollar', 'finanzas/cuentas-por-pagar'],
            'm_cuentas_general' => ['Resumen financiero', 'chart-bar', 'finanzas/cuentas'],
        ]],
        'm_logistica' => ['Logística', 'truck', [
            'm_logistica_documentos' => ['Seguimiento de despachos', 'truck', 'logistica/documentos'],
            'm_gre' => ['Guías electrónicas (GRE)', 'file-invoice', 'logistica/gre'],
        ]],
        'm_reportes' => ['Reportes', 'chart-line', [
            'm_rpt_clientes' => ['Clientes', 'file-alt', 'reportes/clientes'],
            'm_rpt_productos' => ['Inventario y productos', 'file-alt', 'reportes/productos'],
            'm_ventas_periodo' => ['Análisis de ventas', 'chart-bar', 'reportes/ventas-periodo'],
            'm_gastos_compras' => ['Gastos de compras', 'chart-bar', 'reportes/gastos-compras'],
            'm_finanzas_reporte' => ['Reporte financiero', 'money-check-alt', 'reportes/finanzas'],
            'm_flujo_documental' => ['Mapa de relaciones', 'project-diagram', 'trazabilidad/flujo'],
        ]],
    ];
}

function app_sidebar_visible(array $catalog, string $currentRoute): array
{
    $visible = [];

    foreach ($catalog as $id => [$label, $icon, $target]) {
        if (is_array($target)) {
            $children = app_sidebar_visible($target, $currentRoute);
            if ($children === []) {
                continue;
            }
            $visible[$id] = [
                'label' => $label,
                'icon' => $icon,
                'children' => $children,
                'active' => in_array(true, array_column($children, 'active'), true),
            ];
            continue;
        }

        $route = 'views/' . $target;
        // The menu links to the report, independently of any type on the current URL.
        $permission = $target === 'trazabilidad/flujo' ? 'reports.view' : app_view_permission($route);
        if ($permission !== null && app_can($permission)) {
            $visible[$id] = ['label' => $label, 'icon' => $icon, 'target' => $target, 'active' => $currentRoute === $route];
        }
    }

    return $visible;
}

function app_sidebar_current_route(string $route): string
{
    return match ($route) {
        'views/gestion' => 'views/gestion/index',
        'views/productos/mantenimiento-producto', 'views/productos/editar-producto' => 'views/productos/registro-producto',
        'views/productos/ingreso-almacen', 'views/productos/actualizar-stock' => 'views/productos/entrada-inventario',
        'views/ventas/flujo-comercial', 'views/reportes/flujo-documental' => 'views/trazabilidad/flujo',
        default => $route,
    };
}
