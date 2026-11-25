<?php
require_once dirname(__DIR__, 2) . '/global/sidebar-navigation.php';

$sidebarBaseUrl = $functions->direct_paginas();
$sidebarRoute = is_string($_GET['url'] ?? null) ? trim($_GET['url'], '/') : '';
$sidebarItems = app_sidebar_visible(app_sidebar_catalog(), app_sidebar_current_route($sidebarRoute));

$renderSidebarItems = static function (array $items) use ($sidebarBaseUrl, &$renderSidebarItems): void {
    foreach ($items as $id => $item):
        $isGroup = isset($item['children']);
        $isActive = $item['active'];
        ?>
        <li class="nav-item">
            <?php if ($isGroup): ?>
                <details data-appu-module<?= $isActive ? ' open' : '' ?>>
                    <summary id="<?= e($id) ?>" class="nav-link">
                        <i class="nav-icon fas fa-<?= e($item['icon']) ?>" aria-hidden="true"></i>
                        <span class="appu-module-label">
                            <?= e($item['label']) ?><i class="appu-module-chevron fas fa-angle-left" aria-hidden="true"></i>
                        </span>
                    </summary>
                    <ul id="<?= e($id . '_items') ?>" class="nav flex-column appu-module-links">
                        <?php $renderSidebarItems($item['children']); ?>
                    </ul>
                </details>
            <?php else: ?>
                <a id="<?= e($id) ?>" href="<?= e($sidebarBaseUrl . $item['target']) ?>"
                   class="nav-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <i class="nav-icon fas fa-<?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <p><?= e($item['label']) ?></p>
                </a>
            <?php endif; ?>
        </li>
        <?php
    endforeach;
};
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <?php if (isset($sidebarItems['m_inicio'])): ?>
        <a href="<?= e($sidebarBaseUrl . 'home') ?>" class="brand-link appu-brand-link">
            <img src="<?= e($functions->direct_sistema() . '/img/appu-logo-oficial.jpg') ?>" alt="APPU ERP" class="appu-sidebar-logo">
        </a>
    <?php else: ?>
        <span class="brand-link appu-brand-link">
            <img src="<?= e($functions->direct_sistema() . '/img/appu-logo-oficial.jpg') ?>" alt="APPU ERP" class="appu-sidebar-logo">
        </span>
    <?php endif; ?>
    <div class="sidebar">
        <form class="form-inline mt-2" id="appu-sidebar-search" role="search" aria-label="Menú del ERP">
            <div class="input-group">
                <input class="form-control form-control-sidebar" type="search" placeholder="Buscar"
                       aria-label="Buscar en el menú" aria-controls="appu-sidebar-results" autocomplete="off">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-sidebar" aria-label="Buscar en el menú">
                        <i class="fas fa-search fa-fw" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </form>
        <p id="appu-sidebar-search-status" class="appu-sidebar-search-status" role="status"></p>
        <ul class="nav flex-column appu-sidebar-results" id="appu-sidebar-results" aria-label="Resultados de búsqueda" hidden></ul>
        <nav class="mt-2" id="appu-sidebar-navigation" aria-label="Módulos del ERP">
            <ul class="nav nav-pills nav-sidebar flex-column">
                <?php $renderSidebarItems($sidebarItems); ?>
            </ul>
        </nav>
    </div>
</aside>
<script defer src="<?= e($functions->direct_sistema() . '/js/sidebar-navigation.js?v=' . rawurlencode(SCRIPT_VER)) ?>"></script>
