<?php

function app_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: base-uri 'self'; frame-ancestors 'self'; object-src 'none'; form-action 'self'");
    header('Cross-Origin-Opener-Policy: same-origin');

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

function app_login_fingerprint(string $value): string
{
    return hash('sha256', strtolower(trim($value)));
}

function app_login_is_rate_limited(PDO $pdo, string $username, string $remoteAddress): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT
                SUM(username_hash = :username_hash AND ip_hash = :ip_hash) AS pair_attempts,
                SUM(ip_hash = :ip_hash_only) AS ip_attempts
             FROM tbl_login_attempt
             WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $statement->execute([
            'username_hash' => app_login_fingerprint($username),
            'ip_hash' => app_login_fingerprint($remoteAddress),
            'ip_hash_only' => app_login_fingerprint($remoteAddress),
        ]);
        $attempts = $statement->fetch() ?: [];
        return (int) ($attempts['pair_attempts'] ?? 0) >= 8 || (int) ($attempts['ip_attempts'] ?? 0) >= 30;
    } catch (PDOException $exception) {
        error_log('APPU login rate-limit check unavailable: ' . $exception->getMessage());
        return false;
    }
}

function app_record_failed_login(PDO $pdo, string $username, string $remoteAddress): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO tbl_login_attempt (username_hash, ip_hash, attempted_at) VALUES (?, ?, NOW())'
        );
        $statement->execute([
            app_login_fingerprint($username),
            app_login_fingerprint($remoteAddress),
        ]);
    } catch (PDOException $exception) {
        error_log('APPU failed-login audit unavailable: ' . $exception->getMessage());
    }
}

function app_clear_login_attempts(PDO $pdo, string $username, string $remoteAddress): void
{
    try {
        $statement = $pdo->prepare('DELETE FROM tbl_login_attempt WHERE username_hash = ? AND ip_hash = ?');
        $statement->execute([
            app_login_fingerprint($username),
            app_login_fingerprint($remoteAddress),
        ]);
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM tbl_login_attempt WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    } catch (Throwable $exception) {
        error_log('APPU login attempt cleanup unavailable: ' . $exception->getMessage());
    }
}
