  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">

    <!-- Brand Logo -->
    <a href="<?php echo $functions->direct_paginas()."home" ?>" class="brand-link appu-brand-link">
      <img src="<?php echo $functions->direct_sistema(); ?>/img/appu-logo-oficial.jpg" alt="Logo oficial de APPU" class="appu-sidebar-logo">
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

      <!-- SidebarSearch Form -->
      <div class="form-inline mt-2">
        <div class="input-group" data-widget="sidebar-search" data-not-found-text="No se encontraron resultados">
          <input class="form-control form-control-sidebar" type="search" placeholder="Buscar" aria-label="Search">
          <div class="input-group-append">
            <button class="btn btn-sidebar">
              <i class="fas fa-search fa-fw"></i>
            </button>
          </div>
        </div>
      </div>
      
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <?php if (app_can('dashboard.view')): ?>
          <li class="nav-item">
            <a id="m_inicio" href="<?php echo $functions->direct_paginas()."home" ?>" class="nav-link">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Inicio</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (app_can('customers.view') || app_can('providers.view') || app_can('inventory.view')): ?>
          <li class="nav-header">MAESTROS</li>
          <?php endif; ?>

          <?php if (app_can('customers.view')): ?>
          <li class="nav-item">
            <a id="m_clientes" href="<?php echo $functions->direct_paginas()."clientes/registro-cliente" ?>" class="nav-link">
              <i class="nav-icon fas fa-user"></i>
              <p>Clientes</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (app_can('inventory.view')): ?>
          <li class="nav-item has-treeview menu-close">
            <a id="m_almacen" href="<?php echo $functions->direct_paginas()."productos/listado-producto" ?>" class="nav-link">
              <i class="nav-icon fas fa-box"></i>
              <p>
                Almacén
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-header">ARTÍCULOS</li>
              <li class="nav-item">
                <a id="m_registro_producto" href="<?php echo $functions->direct_paginas()."productos/registro-producto" ?>" class="nav-link">
                  <i class="nav-icon fas fa-cube"></i>
                  <p>Maestro de artículos</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_listado_producto" href="<?php echo $functions->direct_paginas()."productos/listado-producto" ?>" class="nav-link">
                  <i class="nav-icon fas fa-list"></i>
                  <p>Listado de artículos</p>
                </a>
              </li>
              <li class="nav-header">OPERACIONES DE INVENTARIO</li>
              <li class="nav-item">
                <a id="m_entrada_inventario" href="<?php echo $functions->direct_paginas()."productos/entrada-inventario" ?>" class="nav-link">
                  <i class="nav-icon fas fa-arrow-circle-down"></i>
                  <p>Entrada de mercancías</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_salida_inventario" href="<?php echo $functions->direct_paginas()."productos/salida-inventario" ?>" class="nav-link">
                  <i class="nav-icon fas fa-arrow-circle-up"></i>
                  <p>Salida de mercancías</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_transferencias" href="<?php echo $functions->direct_paginas()."productos/transferencias" ?>" class="nav-link">
                  <i class="nav-icon fas fa-exchange-alt"></i>
                  <p>Transferencia de stock</p>
                </a>
              </li>
              <li class="nav-header">CONSULTAS DE INVENTARIO</li>
              <li class="nav-item">
                <a id="m_stock_almacen" href="<?php echo $functions->direct_paginas()."productos/stock-por-almacen" ?>" class="nav-link">
                  <i class="nav-icon fas fa-warehouse"></i>
                  <p>Stock por almacén y lote</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_historial_movimiento" href="<?php echo $functions->direct_paginas()."productos/historial-movimiento" ?>" class="nav-link">
                  <i class="nav-icon fas fa-history"></i>
                  <p>Auditoría de movimientos</p>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (app_can('providers.view')): ?>
          <li class="nav-item">
            <a id="m_proveedores" href="<?php echo $functions->direct_paginas()."proveedores/registro-proveedor" ?>" class="nav-link">
              <i class="nav-icon fas fa-people-carry"></i>
              <p>Proveedores</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (app_can('sales.view')): ?>
          <li class="nav-header">VENTAS / CUENTAS POR COBRAR</li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_cotizacion" href="<?php echo $functions->direct_paginas()."cotizacion/resumen-cotizaciones" ?>" class="nav-link">
              <i class="nav-icon fas fa-file-invoice-dollar"></i>
              <p>
                Cotización
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_registro_cotizacion" href="<?php echo $functions->direct_paginas()."cotizacion/registro-cotizacion" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Registro de Cotización</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_cotizacion" href="<?php echo $functions->direct_paginas()."cotizacion/resumen-cotizaciones" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen Cotizaciones</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a id="m_ordenes_venta" href="<?php echo $functions->direct_paginas()."ventas/resumen-ordenes-venta" ?>" class="nav-link">
              <i class="nav-icon fas fa-clipboard-list"></i>
              <p>Ordenes de venta</p>
            </a>
          </li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_facturacion" href="<?php echo $functions->direct_paginas()."facturacion/resumen-facturas" ?>" class="nav-link">
            <i class="nav-icon fas fa-file-invoice"></i>
              <p>
                Factura
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_registro_factura" href="<?php echo $functions->direct_paginas()."facturacion/registro-factura" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Registro de Factura</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_factura" href="<?php echo $functions->direct_paginas()."facturacion/resumen-facturas" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen de Facturas</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a id="m_panel_sunat" href="<?php echo $functions->direct_paginas()."facturacion/panel-sunat" ?>" class="nav-link">
              <i class="nav-icon fas fa-satellite-dish"></i>
              <p>Panel SUNAT</p>
            </a>
          </li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_boleta" href="<?php echo $functions->direct_paginas()."facturacion/resumen-boletas" ?>" class="nav-link">
            <i class="nav-icon fas fa-file-invoice"></i>
              <p>
                Boleta
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_registro_boleta" href="<?php echo $functions->direct_paginas()."facturacion/registro-boleta" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Registro de Boleta</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_boleta" href="<?php echo $functions->direct_paginas()."facturacion/resumen-boletas" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen de Boletas</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_nota_credito" href="<?php echo $functions->direct_paginas()."facturacion/resumen-notas-credito" ?>" class="nav-link">
            <i class="nav-icon fas fa-file-invoice"></i>
              <p>
                Nota de Crédito
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_registro_nota_credito" href="<?php echo $functions->direct_paginas()."facturacion/registro-nota-credito" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Registro de NC</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_nota_credito" href="<?php echo $functions->direct_paginas()."facturacion/resumen-notas-credito" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen de NC</p>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (app_can('purchases.view')): ?>
          <li class="nav-header">COMPRAS / CUENTAS POR PAGAR</li>

          <li class="nav-item">
            <a id="m_solicitudes_compra" href="<?php echo $functions->direct_paginas()."compras/solicitudes" ?>" class="nav-link">
              <i class="nav-icon fas fa-clipboard-check"></i>
              <p>Solicitudes de compra</p>
            </a>
          </li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_ordenes" href="<?php echo $functions->direct_paginas()."ordenes/resumen-ordenes" ?>" class="nav-link">
              <i class="nav-icon fas fa-receipt"></i>
              <p>
                Órdenes de compra
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_orden_compra" href="<?php echo $functions->direct_paginas()."ordenes/orden-de-compra" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Orden de Compra</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_orden_servicio" href="<?php echo $functions->direct_paginas()."ordenes/orden-de-servicio" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Orden de Servicio</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_orden" href="<?php echo $functions->direct_paginas()."ordenes/resumen-ordenes" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen de Órdenes</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a id="m_entrada_mercancias" href="<?php echo $functions->direct_paginas()."compras/recepciones" ?>" class="nav-link">
              <i class="nav-icon fas fa-truck-loading"></i>
              <p>Entrada de mercancías</p>
            </a>
          </li>

          <li class="nav-item has-treeview menu-close">
            <a id="m_compras" href="<?php echo $functions->direct_paginas()."compras/resumen-compras" ?>" class="nav-link">
              <i class="nav-icon fas fa-receipt"></i>
              <p>
                Facturas de proveedor
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a id="m_compra_interna" href="<?php echo $functions->direct_paginas()."compras/compra-interna" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-danger"></i>
                  <p>Factura de proveedor</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_resumen_compra" href="<?php echo $functions->direct_paginas()."compras/resumen-compras" ?>" class="nav-link">
                  <i class="nav-icon far fa-circle text-info"></i>
                  <p>Resumen de Compras</p>
                </a>
              </li>
              <li class="nav-item"><a id="m_costos_entrega" href="<?php echo $functions->direct_paginas()."compras/costos-entrega" ?>" class="nav-link"><i class="nav-icon far fa-circle text-warning"></i><p>Costos de entrega</p></a></li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (app_can('logistics.view')): ?>
          <li class="nav-header">INVENTARIO / LOGÍSTICA</li>
          <li class="nav-item">
            <a id="m_logistica_guias" href="<?php echo $functions->direct_paginas()."logistica/guias" ?>" class="nav-link">
              <i class="nav-icon fas fa-truck-loading"></i>
              <p>Guía de remisión</p>
            </a>
          </li>
          <li class="nav-item">
            <a id="m_logistica_documentos" href="<?php echo $functions->direct_paginas()."logistica/documentos" ?>" class="nav-link">
              <i class="nav-icon fas fa-truck"></i>
              <p>Seguimiento de despachos</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if (app_can('finance.view')): ?>
          <li class="nav-header">TESORERIA / BANCOS</li>
          <li class="nav-item has-treeview menu-close">
            <a id="m_finanzas" href="<?php echo $functions->direct_paginas()."finanzas/cuentas" ?>" class="nav-link" title="Abrir resumen financiero"><i class="nav-icon fas fa-money-check-alt"></i><p>Finanzas <i class="right fas fa-angle-left nav-icon"></i></p></a>
            <ul class="nav nav-treeview">
              <li class="nav-item"><a id="m_cuentas_cobrar" href="<?php echo $functions->direct_paginas()."finanzas/cuentas-por-cobrar" ?>" class="nav-link"><i class="nav-icon far fa-circle text-info"></i><p>Cuentas por cobrar</p></a></li>
              <li class="nav-item"><a id="m_cuentas_pagar" href="<?php echo $functions->direct_paginas()."finanzas/cuentas-por-pagar" ?>" class="nav-link"><i class="nav-icon far fa-circle text-warning"></i><p>Cuentas por pagar</p></a></li>
              <li class="nav-item"><a id="m_cuentas_general" href="<?php echo $functions->direct_paginas()."finanzas/cuentas" ?>" class="nav-link"><i class="nav-icon far fa-circle text-secondary"></i><p>Resumen financiero</p></a></li>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (app_can('reports.view') || app_can('employees.view') || app_can('users.view') || app_can('management.view')): ?>
          <li class="nav-header"><i class="nav-icon fas fa-layer-group"></i> SISTEMA</li>
          <?php endif; ?>

          <?php if (app_can('management.view') || app_can('employees.view') || app_can('users.view')): ?>
          <li class="nav-header">CONFIGURACIÓN</li>
          <li class="nav-item has-treeview menu-close">
            <a id="m_gestion" href="<?php echo app_can('management.view') ? $functions->direct_paginas()."gestion/index" : '#' ?>" class="nav-link">
              <i class="nav-icon fas fa-sliders-h"></i>
              <p>Gestión</p>
            </a>
            <ul class="nav nav-treeview">
              <?php if (app_can('management.view')): ?>
              <li class="nav-item"><a id="m_gestion_general" href="<?php echo $functions->direct_paginas()."gestion/index" ?>" class="nav-link"><i class="nav-icon far fa-circle text-info"></i><p>Configuracion general</p></a></li>
              <li class="nav-item"><a id="m_almacenes" href="<?php echo $functions->direct_paginas()."productos/almacenes" ?>" class="nav-link"><i class="nav-icon fas fa-warehouse"></i><p>Almacenes</p></a></li>
              <?php endif; ?>
              <?php if (app_can('employees.view')): ?><li class="nav-item"><a id="m_empleados" href="<?php echo $functions->direct_paginas()."empleados/registro-empleado" ?>" class="nav-link"><i class="nav-icon fas fa-user-tie"></i><p>Empleados</p></a></li><?php endif; ?>
              <?php if (app_can('users.view')): ?><li class="nav-item"><a id="m_usuarios" href="<?php echo $functions->direct_paginas()."usuarios/registro-usuario" ?>" class="nav-link"><i class="nav-icon fas fa-user-cog"></i><p>Usuarios y perfiles</p></a></li><?php endif; ?>
            </ul>
          </li>
          <?php endif; ?>

          <?php if (app_can('reports.view')): ?>
          <li class="nav-item has-treeview menu-close">
            <a id="m_reportes" href="#" class="nav-link">
              <i class="nav-icon fas fa-chart-line"></i>
              <p>
                Reportes
                <i class="right fas fa-angle-left nav-icon"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
              <a id="m_rpt_clientes" href="<?php echo $functions->direct_paginas()."reportes/clientes" ?>" class="nav-link">
                <i class="fas fa-file-alt nav-icon"></i>
                <p>Clientes</p>
              </a>
              </li>
              <li class="nav-item">
                <a id="m_rpt_productos" href="<?php echo $functions->direct_paginas()."reportes/productos" ?>" class="nav-link">
                  <i class="fas fa-file-alt nav-icon"></i>
                  <p>Inventario y productos</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_ventas_periodo" href="<?php echo $functions->direct_paginas()."reportes/ventas-periodo" ?>" class="nav-link">
                  <i class="fas fa-chart-bar nav-icon"></i>
                  <p>Análisis de ventas</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_gastos_compras" href="<?php echo $functions->direct_paginas()."reportes/gastos-compras" ?>" class="nav-link">
                  <i class="fas fa-chart-bar nav-icon"></i>
                  <p>Gastos en Compras</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_finanzas_reporte" href="<?php echo $functions->direct_paginas()."reportes/finanzas" ?>" class="nav-link">
                  <i class="fas fa-money-check-alt nav-icon"></i>
                  <p>Reporte financiero</p>
                </a>
              </li>
              <li class="nav-item">
                <a id="m_flujo_documental" href="<?php echo $functions->direct_paginas()."trazabilidad/flujo" ?>" class="nav-link">
                  <i class="fas fa-project-diagram nav-icon"></i>
                  <p>Mapa de relaciones</p>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>
