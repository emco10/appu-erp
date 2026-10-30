<?php

require_once dirname(__DIR__) . '/global/config.php';
app_start_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}

session_destroy();
header('Location: ' . app_base_path() . '/');
exit;
