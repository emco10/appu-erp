<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);
app_start_session();
$_SESSION['loggedInUser'] = ['ROLE_CODE' => 'admin', 'PERMISSIONS' => []];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/modules/sunat/panel.php';
$_GET = ['date_from' => date('Y-m-d', strtotime('-29 days')), 'date_to' => date('Y-m-d')];
ob_start();
$cwd = getcwd();
chdir($root . '/modules/sunat');
require 'panel.php';
chdir($cwd ?: $root);
$body = ob_get_clean();
$decoded = json_decode($body, true);
if (!is_array($decoded) || !isset($decoded['summary'], $decoded['documents'], $decoded['series'])) {
    throw new RuntimeException('SUNAT panel endpoint returned invalid JSON: ' . substr($body, 0, 500));
}
echo 'PASS SUNAT panel HTTP JSON contract; documents=' . count($decoded['documents']) . '; series=' . count($decoded['series']) . PHP_EOL;
