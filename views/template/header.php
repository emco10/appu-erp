<!DOCTYPE html>
<html lang="es" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="APPU">
  <meta name="csrf-token" content="<?php echo e($_SESSION['csrf_token']); ?>">
  <script>
    window.APPU_AUTH = <?php echo json_encode([
      'role' => $_SESSION['loggedInUser']['ROLE_CODE'],
      'permissions' => $_SESSION['loggedInUser']['PERMISSIONS'],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.APPU_CONFIG = <?php
      $appuBaseCurrency = 'PEN';
      if (isset($pdo)) {
        try { $appuBaseCurrency = (string) ($pdo->query("SELECT currency FROM tbl_company_settings WHERE id = 1")->fetchColumn() ?: 'PEN'); } catch (Throwable $ignored) {}
      }
      echo json_encode(['baseCurrency' => $appuBaseCurrency], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;
  </script>

  <title>APPU</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
  
  <!-- Select2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

  <!-- Toastr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css">

  <!-- Datatables -->
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/plugins/datatables-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/buttons.dataTables.min.css">
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/buttons.bootstrap4.min.css">
 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/8.11.8/sweetalert2.css">
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/plugins/notifyjs/dist/notify.css">
  <!-- jQuery UI -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

  <!-- Context Menu -->
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/plugins/context-menu/jquery.contextMenu.css">

  <!-- AdminLTE Template Style -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">

  <!-- APPU se carga al final para que la identidad y los ajustes adaptables
       prevalezcan sobre los estilos base de los componentes. -->
  <link rel="stylesheet" href="<?php echo $functions->direct_sistema(); ?>/css/style-appu.css?v=<?php echo rawurlencode(SCRIPT_VER); ?>">

  <!-- Favicons -->
  <link rel="icon" href="<?php echo $functions->direct_sistema(); ?>/img/appu-logo-oficial.jpg" type="image/jpeg">

</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    
    <!-- Left navbar links -->
    <ul class="navbar-nav">

      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>

      <?php if (app_can('dashboard.view')): ?>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?php echo $functions->direct_paginas()."home" ?>" class="nav-link">Inicio</a>
      </li>
      <?php endif; ?>

      <li class="nav-item dropdown d-none d-sm-inline-block appu-work-nav">
        <button type="button" class="nav-link dropdown-toggle border-0 bg-transparent" aria-haspopup="true" aria-expanded="false"><i class="fas fa-plus mr-1"></i>Crear</button>
        <div class="dropdown-menu">
          <h6 class="dropdown-header">Nuevo documento</h6>
          <?php if (app_can('customers.manage')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."clientes/registro-cliente" ?>"><i class="fas fa-user-plus mr-2"></i>Socio de negocio</a><?php endif; ?>
          <?php if (app_can('inventory.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."productos/registro-producto" ?>"><i class="fas fa-box-open mr-2"></i>Artículo</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."productos/entrada-inventario" ?>"><i class="fas fa-arrow-circle-down mr-2"></i>Entrada de mercancías - Inventario</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."productos/salida-inventario" ?>"><i class="fas fa-arrow-circle-up mr-2"></i>Salida de mercancías - Inventario</a>
          <?php endif; ?>
          <?php if (app_can('employees.manage')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."empleados/registro-empleado" ?>"><i class="fas fa-user-tie mr-2"></i>Empleado</a><?php endif; ?>
          <?php if (app_can('users.manage')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."usuarios/registro-usuario" ?>"><i class="fas fa-user-cog mr-2"></i>Usuario / perfil</a><?php endif; ?>
          <?php if (app_can('sales.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."cotizacion/registro-cotizacion" ?>"><i class="fas fa-file-signature mr-2"></i>Cotización</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."facturacion/registro-factura" ?>"><i class="fas fa-file-invoice mr-2"></i>Factura de venta</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."facturacion/registro-boleta" ?>"><i class="fas fa-receipt mr-2"></i>Boleta de venta</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."facturacion/registro-nota-credito" ?>"><i class="fas fa-file-circle-minus mr-2"></i>Nota de crédito</a>
          <?php endif; ?>
          <?php if (app_can('purchases.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ordenes/orden-de-compra" ?>"><i class="fas fa-shopping-cart mr-2"></i>Orden de compra</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."compras/compra-interna" ?>"><i class="fas fa-file-invoice-dollar mr-2"></i>Factura de proveedor</a>
          <?php endif; ?>
          <?php if(app_can('purchases.view') && app_can('inventory.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."compras/recepciones" ?>"><i class="fas fa-truck-loading mr-2"></i>Entrada de mercancías - Compras</a>
          <?php endif; ?>
        </div>
      </li>

      <li class="nav-item dropdown d-none d-sm-inline-block appu-work-nav">
        <button type="button" class="nav-link dropdown-toggle border-0 bg-transparent" aria-haspopup="true" aria-expanded="false"><i class="fas fa-search mr-1"></i>Buscar</button>
        <div class="dropdown-menu">
          <h6 class="dropdown-header">Consultas</h6>
          <?php if (app_can('customers.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."clientes/registro-cliente" ?>"><i class="fas fa-users mr-2"></i>Socios de negocio</a><?php endif; ?>
          <?php if (app_can('inventory.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."productos/listado-producto" ?>"><i class="fas fa-boxes-stacked mr-2"></i>Productos y stock</a><?php endif; ?>
          <?php if (app_can('employees.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."empleados/registro-empleado" ?>"><i class="fas fa-user-tie mr-2"></i>Empleados</a><?php endif; ?>
          <?php if (app_can('users.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."usuarios/registro-usuario" ?>"><i class="fas fa-user-cog mr-2"></i>Usuarios y perfiles</a><?php endif; ?>
          <?php if (app_can('sales.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ventas/resumen-ordenes-venta" ?>"><i class="fas fa-clipboard-list mr-2"></i>Órdenes de venta</a><?php endif; ?>
          <?php if (app_can('purchases.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ordenes/resumen-ordenes" ?>"><i class="fas fa-list-check mr-2"></i>Órdenes de compra</a><?php endif; ?>
          <?php if (app_can('reports.view')): ?><a class="dropdown-item" href="<?php echo $functions->direct_paginas()."trazabilidad/flujo" ?>"><i class="fas fa-diagram-project mr-2"></i>Mapa de relaciones</a><?php endif; ?>
        </div>
      </li>

      <li class="nav-item dropdown d-none d-sm-inline-block appu-quick-nav">
        <button type="button" class="nav-link dropdown-toggle border-0 bg-transparent" aria-haspopup="true" aria-expanded="false"><i class="fas fa-compass mr-1"></i>Navegar</button>
        <div class="dropdown-menu">
          <h6 class="dropdown-header">Accesos rápidos</h6>
          <?php if (app_can('sales.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."cotizacion/registro-cotizacion" ?>"><i class="fas fa-file-invoice-dollar mr-2"></i>Nueva cotización</a>
          <?php endif; ?>
          <?php if (app_can('sales.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ventas/resumen-ordenes-venta" ?>"><i class="fas fa-clipboard-list mr-2"></i>Órdenes de venta</a>
          <?php endif; ?>
          <?php if (app_can('purchases.manage')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ordenes/orden-de-compra" ?>"><i class="fas fa-shopping-cart mr-2"></i>Nueva orden de compra</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."compras/compra-interna" ?>"><i class="fas fa-file-invoice mr-2"></i>Factura de proveedor</a>
          <?php endif; ?>
          <?php if (app_can('purchases.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."ordenes/resumen-ordenes" ?>"><i class="fas fa-list-check mr-2"></i>Órdenes de compra</a>
          <?php endif; ?>
          <?php if (app_can('inventory.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."productos/stock-por-almacen" ?>"><i class="fas fa-layer-group mr-2"></i>Stock por almacén</a>
          <?php endif; ?>
          <?php if (app_can('logistics.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."logistica/guias" ?>"><i class="fas fa-truck-loading mr-2"></i>Guias de remision</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."logistica/documentos" ?>"><i class="fas fa-truck mr-2"></i>Seguimiento de despachos</a>
          <?php endif; ?>
          <?php if (app_can('reports.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."reportes/ventas-periodo" ?>"><i class="fas fa-chart-line mr-2"></i>Reportes de gestion</a>
          <?php endif; ?>
          <?php if (app_can('finance.view')): ?>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."finanzas/cuentas-por-cobrar" ?>"><i class="fas fa-hand-holding-usd mr-2"></i>Cuentas por cobrar</a>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."finanzas/cuentas-por-pagar" ?>"><i class="fas fa-file-invoice-dollar mr-2"></i>Cuentas por pagar</a>
          <?php endif; ?>
          <?php if (app_can('management.view')): ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo $functions->direct_paginas()."gestion/index" ?>"><i class="fas fa-sliders-h mr-2"></i>Gestión del sistema</a>
          <?php endif; ?>
        </div>
      </li>

    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <!-- Dropdown User Menu -->
      <li class="nav-item dropdown user-menu">
        <a class="nav-link" data-toggle="dropdown" data-bs-toggle="dropdown" href="javascript:void(0)" aria-expanded="false">
          <?php
            $user_avatar = basename($_SESSION['loggedInUser']['PHOTO_URL'] ?: 'default-avatar.png');
            $user_avatar_url = $functions->direct_sistema() . '/img/avatars/' . rawurlencode($user_avatar);
          ?>
          <img src="<?php echo e($user_avatar_url); ?>" class="user-image img-circle elevation-2 bg-default" alt="Avatar de usuario">
          <span class="d-none d-md-inline"><?php echo e($_SESSION['loggedInUser']['EMPLOYEE_NAME']); ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
          <!-- Menu Header -->
          <a href="#" class="dropdown-item">
            <div class="media">
              <img src="<?php echo e($user_avatar_url); ?>" alt="Avatar de usuario" class="img-size-50 mr-3 img-circle">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  <?php echo e($_SESSION['loggedInUser']['EMPLOYEE_NAME']); ?>
                  <!--
                  <span class="float-right text-sm text-success">
                    <i class="fas fa-star"></i>
                  </span>
                  -->
                </h3>
                <p class="text-sm"><i class="fa-solid fa-user mr-1"></i><?php echo e($_SESSION['loggedInUser']['USERNAME']); ?></p>
                <p class="text-sm"><?php echo e(strtoupper($_SESSION['loggedInUser']['JOB'])); ?></p>
                <p class="text-sm text-muted"><i class="fas fa-user-shield mr-1"></i><?php echo e($_SESSION['loggedInUser']['ROLE_NAME']); ?></p>
              </div>
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <!-- Menu Body -->
          <a id="switch-dark-mode-item" class="dropdown-item">
            <i class="fa-solid fa-moon"></i> Modo Oscuro
            <span class="float-right text-sm">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="switchDarkMode">
                <label class="custom-control-label" for="switchDarkMode"></label>
              </div>
            </span>
          </a>
          <div class="dropdown-divider"></div>
          <!-- Menu Footer -->
          <a id="logout-btn" href="<?php echo $functions->direct_sistema(); ?>/modules/end_session.php" class="dropdown-item dropdown-footer">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
          </a>
        </div>
        <!-- 
        <ul class="dropdown-menu">
           User image 
          <li class="user-header" style="background-color: #f8f9fa;">
            <img src="<?php echo $user_avatar_url; ?>" class="img-circle elevation-2" alt="User Image">
            <p>
              <?php
                $user_name_string = $_SESSION['loggedInUser']['EMPLOYEE_NAME'];
                echo $user_name_string;
              ?>
              <small>@<?php echo ($_SESSION['loggedInUser']['USERNAME']); ?></small>
              <small><?php echo strtoupper($_SESSION['loggedInUser']['JOB']); ?></small>
            </p>
          </li>
           Menu Body 
          <li class="user-body" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="customSwitch1">
                <label class="custom-control-label" for="customSwitch1">Toggle this custom switch element</label>
              </div>
            </div>
          </li>
        </ul>
        -->
      </li>
      
    </ul>
  </nav>
  <!-- /.navbar -->
