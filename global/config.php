<?php

define('APP_NAME', 'APPU');
define('SCRIPT_VER', '261101_382');

$databaseConfig = [];
$privateConfigOverride = getenv('APPU_PRIVATE_CONFIG');
$privateConfigPath = $privateConfigOverride !== false && $privateConfigOverride !== ''
    ? $privateConfigOverride
    : dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . '.appu-erp' . DIRECTORY_SEPARATOR . 'database.php';

if (is_file($privateConfigPath)) {
    $loadedDatabaseConfig = require $privateConfigPath;
    if (!is_array($loadedDatabaseConfig)) {
        throw new RuntimeException('The private APPU database config must return an array.');
    }
    foreach (['host', 'user', 'password', 'database'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $loadedDatabaseConfig) || !is_string($loadedDatabaseConfig[$requiredKey])) {
            throw new RuntimeException('The private APPU database config is missing a valid ' . $requiredKey . ' value.');
        }
    }
    $databaseConfig = $loadedDatabaseConfig;
} elseif ($privateConfigOverride !== false && $privateConfigOverride !== '') {
    throw new RuntimeException('The configured APPU_PRIVATE_CONFIG file does not exist.');
}

$databaseSetting = static function (string $environmentKey, string $configKey, string $fallback) use ($databaseConfig): string {
    $environmentValue = getenv($environmentKey);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }
    return $databaseConfig[$configKey] ?? $fallback;
};

define('SERVER', $databaseSetting('APPU_DB_HOST', 'host', 'localhost'));
define('DB_USER', $databaseSetting('APPU_DB_USER', 'user', 'root'));
define('DB_PASSWORD', $databaseSetting('APPU_DB_PASSWORD', 'password', ''));
define('DB', $databaseSetting('APPU_DB_NAME', 'database', 'db_appu'));
date_default_timezone_set('America/Lima');

error_reporting(E_ALL);
ini_set('display_errors', getenv('APPU_ENV') === 'development' ? '1' : '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

function app_base_path(): string
{
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $applicationRoot = realpath(dirname(__DIR__));

    if ($documentRoot !== false && $applicationRoot !== false) {
        $normalizedDocumentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $normalizedApplicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');

        if (strcasecmp($normalizedApplicationRoot, $normalizedDocumentRoot) === 0) {
            return '';
        }

        $documentRootPrefix = $normalizedDocumentRoot . '/';
        if (strncasecmp($normalizedApplicationRoot, $documentRootPrefix, strlen($documentRootPrefix)) === 0) {
            $basePath = substr($normalizedApplicationRoot, strlen($normalizedDocumentRoot));
        }
    }

    if (!isset($basePath) && $applicationRoot !== false) {
        $scriptFilename = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($scriptFilename !== false) {
            $normalizedScriptFilename = str_replace('\\', '/', $scriptFilename);
            $applicationRootPrefix = rtrim(str_replace('\\', '/', $applicationRoot), '/') . '/';
            if (strncasecmp($normalizedScriptFilename, $applicationRootPrefix, strlen($applicationRootPrefix)) === 0) {
                $relativeScriptPath = substr($normalizedScriptFilename, strlen($applicationRootPrefix));
                $scriptName = rawurldecode(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
                $relativeScriptSuffix = '/' . $relativeScriptPath;
                if (
                    strlen($scriptName) >= strlen($relativeScriptSuffix)
                    && strcasecmp(substr($scriptName, -strlen($relativeScriptSuffix)), $relativeScriptSuffix) === 0
                ) {
                    $basePath = substr($scriptName, 0, -strlen($relativeScriptSuffix));
                }
            }
        }
    }

    if (!isset($basePath)) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }

    $relativePath = trim($basePath, '/');
    if ($relativePath === '' || $relativePath === '.') {
        return '';
    }

    return '/' . implode('/', array_map('rawurlencode', explode('/', $relativePath)));
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $basePath = app_base_path();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $basePath . '/modules',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $basePath ?: '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
