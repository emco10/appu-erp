<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/authorization.php';

app_start_session();

if (empty($_SESSION['loggedInUser'])) {
    header('Location: ' . app_base_path() . '/');
    exit;
}

if (!isset($_SESSION['loggedInUser']['ROLE_CODE'], $_SESSION['loggedInUser']['PERMISSIONS'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . app_base_path() . '/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
