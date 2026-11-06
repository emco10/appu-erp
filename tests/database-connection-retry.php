<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/database-connection.php';

final class DatabaseConnectionTestPDO extends PDO
{
    public function __construct() {}
}

function connectionRetryAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function connectionRetryError(?int $driverCode, string $message): PDOException
{
    $error = new PDOException($message);
    if ($driverCode !== null) $error->errorInfo = ['HY000', $driverCode, $message];
    return $error;
}

$connection = new DatabaseConnectionTestPDO();
$calls = 0;
$pauses = [];
$result = app_database_connect(static function () use ($connection, &$calls): PDO {
    $calls++;
    return $connection;
}, static function (int $delay) use (&$pauses): void { $pauses[] = $delay; });
connectionRetryAssert($result === $connection && $calls === 1 && $pauses === [], 'A successful connection must not be repeated or delayed.');

$calls = 0;
$pauses = [];
$result = app_database_connect(static function () use ($connection, &$calls): PDO {
    $calls++;
    if ($calls <= 2) throw connectionRetryError(2002, 'SQLSTATE[HY000] [2002] Operation not permitted');
    return $connection;
}, static function (int $delay) use (&$pauses): void { $pauses[] = $delay; });
connectionRetryAssert($result === $connection && $calls === 3 && $pauses === [1000000, 1000000], 'A transient Hostinger connection refusal must retry twice, one second apart.');

$calls = 0;
$pauses = [];
$lastError = null;
try {
    app_database_connect(static function () use (&$calls, &$lastError): PDO {
        $calls++;
        $lastError = connectionRetryError(2002, 'SQLSTATE[HY000] [2002] Operation not permitted');
        throw $lastError;
    }, static function (int $delay) use (&$pauses): void { $pauses[] = $delay; });
    throw new RuntimeException('A permanent failure was swallowed.');
} catch (PDOException $error) {
    connectionRetryAssert($error === $lastError, 'The original final PDO error must be propagated.');
    connectionRetryAssert($calls === 3 && $pauses === [1000000, 1000000], 'Retries exceeded three attempts or delayed after the last attempt.');
}

foreach ([
    connectionRetryError(1045, 'Access denied for user; Operation not permitted'),
    connectionRetryError(2002, 'SQLSTATE[HY000] [2002] Connection refused'),
    connectionRetryError(2002, 'SQLSTATE[HY000] [2002] Connection timed out'),
    connectionRetryError(1205, 'Operation not permitted'),
    connectionRetryError(null, 'SQLSTATE[HY000] [2002] Operation not permitted'),
    new RuntimeException('Operation not permitted'),
] as $expected) {
    $calls = 0;
    $pauses = [];
    try {
        app_database_connect(static function () use (&$calls, $expected): PDO { $calls++; throw $expected; },
            static function (int $delay) use (&$pauses): void { $pauses[] = $delay; });
        throw new RuntimeException('An unrelated connection error was swallowed.');
    } catch (Throwable $actual) {
        connectionRetryAssert($actual === $expected, 'An unrelated error was replaced.');
        connectionRetryAssert($calls === 1 && $pauses === [], 'An unrelated error was retried.');
    }
}

echo "PASS database connection retry: bounded Hostinger error 2002 retries, no repeated credentials/timeouts/other errors (no database)\n";
