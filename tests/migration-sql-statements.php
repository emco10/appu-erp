<?php

require __DIR__ . '/../db/sql-statements.php';

$cases = [
    "SELECT 1; SELECT 2;" => 2,
    "INSERT INTO t VALUES ('semi;colon');" => 1,
    "SELECT `semi;colon` FROM t; SELECT 2;" => 2,
    "-- comment; ignored\nSELECT 1; # trailing;\nSELECT 2;" => 2,
    "SELECT 1 /* internal; semicolon */; SELECT 2;" => 2,
];

foreach ($cases as $sql => $expectedCount) {
    $statements = appuSplitSqlStatements($sql);
    if (count($statements) !== $expectedCount) {
        throw new RuntimeException('Unexpected SQL statement count.');
    }
}

$migrationFiles = glob(__DIR__ . '/../db/migrations/*.sql');
foreach ($migrationFiles as $migrationFile) {
    $sql = file_get_contents($migrationFile);
    if ($sql === false || !appuSplitSqlStatements($sql)) {
        throw new RuntimeException('Migration is empty or unreadable: ' . basename($migrationFile));
    }
}

$repairMigration = file_get_contents(__DIR__ . '/../db/migrations/20261101_reconcile_production_schema.sql');
if ($repairMigration === false || count(appuSplitSqlStatements($repairMigration)) !== 5) {
    throw new RuntimeException('The schema repair migration should contain five SQL statements.');
}

$transferRepairMigration = file_get_contents(__DIR__ . '/../db/migrations/20261101_repair_inventory_transfer_schema.sql');
if ($transferRepairMigration === false || count(appuSplitSqlStatements($transferRepairMigration)) !== 4) {
    throw new RuntimeException('The inventory transfer repair migration should contain four SQL statements.');
}

try {
    appuSplitSqlStatements("SELECT 'unterminated;");
    throw new RuntimeException('Unterminated SQL strings must be rejected.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Migration contains an unterminated SQL string or comment.') {
        throw $exception;
    }
}

echo "PASS SQL migration statement splitting\n";
