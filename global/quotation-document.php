<?php

require_once __DIR__ . '/document-history.php';

function app_quotation_custom_fields(PDO $pdo): array
{
    return array_values(array_filter(
        app_custom_field_definitions($pdo, 'quotation'),
        static fn (array $field): bool => (int) ($field['visible'] ?? 1) === 1
    ));
}

/** Call in a transaction. Writers lock the quotation before reading or changing its contents. */
function app_quotation_snapshot(PDO $pdo, int $id, bool $lock = false): ?array
{
    $lockClause = $lock ? ' FOR UPDATE' : '';
    $statement = $pdo->prepare('SELECT * FROM tbl_quotation WHERE id = ?' . $lockClause);
    $statement->execute([$id]);
    $quotation = $statement->fetch();
    if (!$quotation) return null;

    $statement = $pdo->prepare('SELECT * FROM tbl_quotation_detail WHERE quotation_id = ? ORDER BY id' . $lockClause);
    $statement->execute([$id]);
    $details = $statement->fetchAll();

    $statement = $pdo->prepare("SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = 'quotation' AND record_id = ? ORDER BY field_definition_id" . $lockClause);
    $statement->execute([$id]);
    $customValues = (object) $statement->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'quotation' => $quotation,
        'details' => $details,
        'custom_values' => $customValues,
        'version' => hash('sha256', json_encode([$quotation, $details, $customValues], JSON_THROW_ON_ERROR)),
    ];
}
