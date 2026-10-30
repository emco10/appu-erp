<?php

$web_root_dir = dirname(__DIR__, 2);
require_once $web_root_dir . '/global/config.php';

// Set the default timezone
// This is important for date and time functions to work correctly
date_default_timezone_set("America/Lima");

if (isset($_GET['url'])) {

    // Initialize and check session
    include $web_root_dir.'/global/session.php';
    // Todas las vistas y sus campos adicionales comparten esta conexión.
    require_once $web_root_dir . '/global/connection.php';

    // Load Web Functions
    include $web_root_dir."/modules/web_functions.php";
    $functions = new WebFunctions();

    // Web Header (Navbar)
    include $web_root_dir."/views/template/header.php";

    // Web Sidebar
    include $web_root_dir."/views/template/sidebar.php";

    // Web View (Content)
    $route = trim((string) $_GET['url'], '/');
    $routeParts = explode('/', $route);

    if (
        !preg_match('#^views(?:/[a-z0-9-]+){1,2}$#i', $route)
        || $routeParts[0] !== 'views'
    ) {
        http_response_code(404);
        $routeParts = ['views', 'home'];
    }

    $viewParts = array_slice($routeParts, 1);
    $page_name = end($viewParts);
    $viewPath = realpath($web_root_dir . '/views/' . implode('/', $viewParts) . '.php');
    $viewsRoot = realpath($web_root_dir . '/views') . DIRECTORY_SEPARATOR;

    if (
        $viewPath === false
        || !str_starts_with($viewPath, $viewsRoot)
        || str_starts_with($viewPath, $viewsRoot . 'template' . DIRECTORY_SEPARATOR)
    ) {
        http_response_code(404);
        $page_name = 'home';
        $viewPath = $web_root_dir . '/views/home.php';
    }

    $requiredPermission = app_view_permission($route);
    if ($requiredPermission !== null && !app_can($requiredPermission)) {
        http_response_code(403);
        $page_name = 'forbidden';
        $viewPath = $web_root_dir . '/views/forbidden.php';
    }

    $page_script = $page_name;
    include $viewPath;

    // Web Footer
    include $web_root_dir."/views/template/footer.php";

    // AJAX Directory Path
    $script_name = isset($page_script) && preg_match('/^[a-z0-9-]+$/i', (string) $page_script) ? (string) $page_script : $page_name;
    $ajax_dir_path = $functions->direct_sistema()."/ajax/".$script_name.".js?v=".SCRIPT_VER;

    // AJAX Directory Relative Path
    $ajax_dir_rel_path = $functions->directorio_carpetas()."/ajax/".$script_name.".js";

    // Check if an AJAX file for requested view exists 
    if (file_exists($ajax_dir_rel_path)) {
        echo '<script src="' . $ajax_dir_path . '"></script>';
    }

} else {
    // Show login page
    include $web_root_dir . "/views/login.php";
}

?>
