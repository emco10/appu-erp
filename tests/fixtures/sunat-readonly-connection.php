<?php
/** In-memory database boundary for the HTTP regression test; never connects to a database. */
if (PHP_SAPI !== 'cli-server' || !getenv('APPU_SUNAT_TEST_ROOT')) {
    http_response_code(404);
    exit;
}

function app_require_permission(string $permission): void
{
    if ($permission !== 'sales.view') {
        throw new RuntimeException('Unexpected permission: ' . $permission);
    }
}

final class SunatReadOnlyTestStatement extends PDOStatement
{
    private array $parameters = [];

    public function __construct(private readonly string $sql) {}

    public function execute(?array $params = null): bool
    {
        $this->parameters = $params ?? [];
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'tbl_sunat_document')) {
            if (($_GET['scenario'] ?? '') === 'missing-artifact') return false;
            return [
                'id' => 101, 'status' => 'accepted', 'sunat_code' => '0',
                'sunat_description' => 'Aceptado', 'digest_value' => 'fixture-digest',
                'xml_path' => getenv('APPU_SUNAT_TEST_ROOT') . '/sample.xml',
                'cdr_path' => getenv('APPU_SUNAT_TEST_ROOT') . '/sample.zip',
                'submitted_at' => '2026-11-05 12:00:00', 'accepted_at' => '2026-11-05 12:00:01',
            ];
        }
        if (($this->parameters[0] ?? null) !== 1) return false;

        $row = str_contains($this->sql, 'tbl_remission_guide')
            ? ['number' => 'T001-0000001', 'status' => 'Registrada', 'recipient_name' => 'PRIVATE RECIPIENT']
            : ['series' => 'F001', 'number' => '0000001', 'name' => 'PRIVATE CUSTOMER', 'address' => 'PRIVATE ADDRESS'];
        // Simulate SELECT projections so HTTP assertions catch accidental SELECT * disclosures.
        preg_match('/^SELECT (.+) FROM /', $this->sql, $matches);
        if (($matches[1] ?? '*') === '*') return $row;
        $projection = array_map('trim', explode(',', $matches[1]));
        return array_intersect_key($row, array_flip($projection));
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return match ($_GET['scenario'] ?? 'enabled') {
            'missing-series' => false,
            'internal-series' => 0,
            default => 1,
        };
    }
}

final class SunatReadOnlyTestPDO extends PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!str_starts_with($query, 'SELECT ')) throw new RuntimeException('Read-only test blocked a write.');
        return new SunatReadOnlyTestStatement($query);
    }
}

$pdo = new SunatReadOnlyTestPDO();
