<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../global/config.php';

$mode = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--') && strpos($argument, '=') !== false) {
        [$key, $value] = explode('=', substr($argument, 2), 2);
        $options[$key] = $value;
    }
}

if (!in_array($mode, ['--status', '--apply', '--baseline'], true)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php db/migrate.php --status\n");
    fwrite(STDERR, "  APPU_MIGRATION_ALLOW_PRODUCTION=1 php db/migrate.php --apply\n");
    fwrite(STDERR, "  php db/migrate.php --baseline --through=<migration.sql> --confirm-db=<database> --confirm-backup=YES\n");
    exit(2);
}

$migrationDirectory = __DIR__ . '/migrations';
$files = glob($migrationDirectory . '/*.sql');
sort($files, SORT_STRING);
$migrations = [];
foreach ($files as $file) {
    $version = basename($file);
    if (!preg_match('/^\d{8}_[a-z0-9][a-z0-9_-]*\.sql$/', $version)) {
        throw new RuntimeException('Invalid migration filename: ' . $version);
    }
    $migrations[$version] = hash_file('sha256', $file);
}

if (!$migrations) {
    throw new RuntimeException('No SQL migrations were found in ' . $migrationDirectory);
}

$pdo = new PDO('mysql:host=' . SERVER . ';dbname=' . DB . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

$trackingTable = 'appu_schema_migrations';
$tableExists = static function () use ($pdo, $trackingTable): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$trackingTable]);
    return (int) $statement->fetchColumn() > 0;
};
$createTrackingTable = static function () use ($pdo, $trackingTable): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$trackingTable}` (
            `version` varchar(255) NOT NULL,
            `checksum` char(64) NOT NULL,
            `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
            `baseline` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};

if ($mode === '--baseline') {
    $through = $options['through'] ?? '';
    if (($options['confirm-db'] ?? '') !== DB) {
        throw new RuntimeException('--confirm-db must exactly match the configured database name.');
    }
    if (($options['confirm-backup'] ?? '') !== 'YES') {
        throw new RuntimeException('Confirm a restorable production backup with --confirm-backup=YES.');
    }
    if (!isset($migrations[$through])) {
        throw new RuntimeException('--through must name an existing migration file.');
    }

    $createTrackingTable();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            "INSERT INTO `{$trackingTable}` (`version`, `checksum`, `baseline`) VALUES (?, ?, 1)"
        );
        foreach ($migrations as $version => $checksum) {
            if (strcmp($version, $through) <= 0) {
                $insert->execute([$version, $checksum]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    echo 'Baseline recorded through ' . $through . ' for ' . DB . '. Verify the live schema before relying on it.' . PHP_EOL;
    exit(0);
}

if (!$tableExists()) {
    if ($mode === '--status') {
        echo 'UNINITIALIZED ' . DB . ': migration history is not set up.' . PHP_EOL;
        echo 'Reconcile and verify the live schema before recording a baseline.' . PHP_EOL;
        exit(0);
    }
    throw new RuntimeException('Migration history is not initialized. Reconcile and verify the schema, then record an explicit baseline.');
}

$lockName = 'appu-migrate-' . substr(hash('sha256', DB), 0, 40);
$lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
$lock->execute([$lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    throw new RuntimeException('Could not acquire the database migration lock.');
}

try {
    $applied = [];
    foreach ($pdo->query("SELECT `version`, `checksum`, `baseline` FROM `{$trackingTable}` ORDER BY `version`") as $row) {
        $version = $row['version'];
        if (!isset($migrations[$version])) {
            throw new RuntimeException('Applied migration is missing from this checkout: ' . $version);
        }
        if (!hash_equals($migrations[$version], $row['checksum'])) {
            throw new RuntimeException('Applied migration was changed; create a new migration: ' . $version);
        }
        $applied[$version] = (bool) $row['baseline'];
    }

    $pending = array_diff_key($migrations, $applied);
    if ($applied) {
        $latestApplied = max(array_keys($applied));
        foreach ($pending as $version => $_checksum) {
            if (strcmp($version, $latestApplied) < 0) {
                throw new RuntimeException('A pending migration sorts before the applied baseline. Use a newer migration filename: ' . $version);
            }
        }
    }

    if ($mode === '--status') {
        echo 'Database: ' . DB . PHP_EOL;
        echo 'Applied: ' . count($applied) . '; pending: ' . count($pending) . PHP_EOL;
        foreach ($pending as $version => $_checksum) {
            echo 'PENDING ' . $version . PHP_EOL;
        }
    } else {
        if (!str_ends_with(DB, '_qa') && getenv('APPU_MIGRATION_ALLOW_PRODUCTION') !== '1') {
            throw new RuntimeException('Production migrations require APPU_MIGRATION_ALLOW_PRODUCTION=1.');
        }

        foreach ($pending as $version => $checksum) {
            $sql = file_get_contents($migrationDirectory . '/' . $version);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration is empty or unreadable: ' . $version);
            }
            try {
                $statement = $pdo->query($sql);
                do {
                    if ($statement->columnCount() > 0) {
                        $statement->fetchAll();
                    }
                } while ($statement->nextRowset());
                $statement->closeCursor();
                $insert = $pdo->prepare("INSERT INTO `{$trackingTable}` (`version`, `checksum`) VALUES (?, ?)");
                $insert->execute([$version, $checksum]);
            } catch (Throwable $exception) {
                throw new RuntimeException('Migration failed: ' . $version . '. Inspect the schema before retrying.', 0, $exception);
            }
            echo 'APPLIED ' . $version . PHP_EOL;
        }

        echo 'PASS migrations ' . DB . '; applied=' . count($pending) . PHP_EOL;
    }
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
}
