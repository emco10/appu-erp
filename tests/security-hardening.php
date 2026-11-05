<?php

$root = dirname(__DIR__);
$config = file_get_contents($root . '/global/config.php');
$connection = file_get_contents($root . '/global/connection.php');
$security = file_get_contents($root . '/global/security.php');
$login = file_get_contents($root . '/modules/login.php');
$htaccess = file_get_contents($root . '/.htaccess');
$migration = file_get_contents($root . '/db/migrations/20261104_add_login_rate_limiting.sql');

$requirements = [
    [$config, "session.use_strict_mode", 'La sesión no activa el modo estricto.'],
    [$config, "DB_USER === 'root'", 'Producción no bloquea las credenciales de desarrollo.'],
    [$config, "'httponly' => true", 'La cookie de sesión no exige HttpOnly.'],
    [$config, "'samesite' => 'Lax'", 'La cookie de sesión no define SameSite.'],
    [$connection, 'app_send_security_headers()', 'Las cabeceras de seguridad no se aplican globalmente.'],
    [$security, "header_remove('X-Powered-By')", 'Se expone innecesariamente la versión de PHP.'],
    [$security, 'Content-Security-Policy', 'Falta una política CSP defensiva.'],
    [$security, 'Strict-Transport-Security', 'Falta HSTS para HTTPS.'],
    [$security, 'app_login_is_rate_limited', 'Falta el limitador de intentos de acceso.'],
    [$login, 'http_response_code(429)', 'El login no responde 429 al exceder intentos.'],
    [$login, 'app_record_failed_login', 'El login no registra intentos fallidos.'],
    [$login, 'password_needs_rehash', 'El login no actualiza hashes de contraseña obsoletos.'],
    [$htaccess, 'RewriteRule (^|/)\\.', 'No se bloquean archivos ocultos.'],
    [$migration, 'idx_login_attempt_pair_time', 'La tabla de intentos no tiene el índice esperado.'],
];

foreach ($requirements as [$source, $needle, $message]) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

require_once $root . '/global/security.php';
if (app_login_fingerprint(' Admin ') !== app_login_fingerprint('admin')) {
    throw new RuntimeException('La huella de usuario no se normaliza de forma estable.');
}

echo "Security hardening contract: OK\n";
