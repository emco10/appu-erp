<?php
require '../../global/connection.php';
require_once '../../global/reporting.php';

header('Content-Type: application/json; charset=utf-8');

$dateFrom = $_POST['DATE_FROM'] ?? date('Y-m-01', strtotime('-11 months'));
$dateTo = $_POST['DATE_TO'] ?? date('Y-m-d');
$validDate = static function ($date): bool {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
};

if (!$validDate($dateFrom) || !$validDate($dateTo) || $dateFrom > $dateTo) {
    http_response_code(422);
    echo json_encode(['error' => 'Indica un rango de fechas válido.']);
    exit;
}

$start = new DateTimeImmutable($dateFrom);
$end = new DateTimeImmutable($dateTo);
if ($start->diff($end)->days > 3660) {
    http_response_code(422);
    echo json_encode(['error' => 'El rango máximo permitido es de 10 años.']);
    exit;
}

$normalizedCurrency=app_report_currency_sql('currency');
$salesUnion = "
    SELECT 'Factura' AS document_type, date, {$normalizedCurrency} currency, total_net AS amount, seller_id, ruc, name
    FROM tbl_invoice
    WHERE status NOT IN (2,4) AND document_subtype<>'ADVANCE' AND date BETWEEN ? AND ?
    UNION ALL
    SELECT 'Boleta' AS document_type, date, {$normalizedCurrency} currency, total_net AS amount, seller_id, ruc, name
    FROM tbl_receipt
    WHERE status NOT IN (2,4) AND date BETWEEN ? AND ?
    UNION ALL
    SELECT 'Nota de crédito' AS document_type, date, {$normalizedCurrency} currency, -total_net AS amount, seller_id, ruc, name
    FROM tbl_credit_note
    WHERE status NOT IN (2,4) AND date BETWEEN ? AND ?
      AND NOT EXISTS(SELECT 1 FROM tbl_invoice advance_invoice WHERE tbl_credit_note.referenced_doc_type_id=1 AND advance_invoice.id=tbl_credit_note.referenced_doc_id AND advance_invoice.document_subtype='ADVANCE')
";
$dateParams = [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo];

$summaryStatement = $pdo->prepare(
    "SELECT currency, document_type, COUNT(*) AS document_count, SUM(amount) AS net_amount
     FROM ($salesUnion) sales
     GROUP BY currency, document_type
     ORDER BY currency, document_type"
);
$summaryStatement->execute($dateParams);
$summary = [];
$byType = [];
foreach ($summaryStatement->fetchAll() as $row) {
    $currency = (string) $row['currency'];
    $summary[$currency] ??= ['net_sales' => 0.0, 'sales_documents' => 0, 'credit_notes' => 0];
    $summary[$currency]['net_sales'] += (float) $row['net_amount'];
    if ($row['document_type'] === 'Nota de crédito') {
        $summary[$currency]['credit_notes'] = (int) $row['document_count'];
    } else {
        $summary[$currency]['sales_documents'] += (int) $row['document_count'];
    }
    $byType[] = [
        'currency' => $currency,
        'document_type' => $row['document_type'],
        'document_count' => (int) $row['document_count'],
        'net_amount' => (float) $row['net_amount'],
    ];
}

$monthlyStatement = $pdo->prepare(
    "SELECT DATE_FORMAT(date, '%Y-%m') AS month, currency, SUM(amount) AS net_amount
     FROM ($salesUnion) sales
     GROUP BY month, currency
     ORDER BY month, currency"
);
$monthlyStatement->execute($dateParams);
$monthly = $monthlyStatement->fetchAll();

$periodDays = $start->diff($end)->days + 1;
$comparisonEnd = $start->modify('-1 day');
$comparisonStart = $comparisonEnd->modify('-' . ($periodDays - 1) . ' days');
$comparisonStatement = $pdo->prepare(
    "SELECT currency, document_type, COUNT(*) AS document_count, SUM(amount) AS net_amount
     FROM ($salesUnion) sales
     GROUP BY currency, document_type
     ORDER BY currency, document_type"
);
$comparisonStatement->execute([
    $comparisonStart->format('Y-m-d'),
    $comparisonEnd->format('Y-m-d'),
    $comparisonStart->format('Y-m-d'),
    $comparisonEnd->format('Y-m-d'),
    $comparisonStart->format('Y-m-d'),
    $comparisonEnd->format('Y-m-d'),
]);
$comparisonSummary = [];
foreach ($comparisonStatement->fetchAll() as $row) {
    $currency = (string) $row['currency'];
    $comparisonSummary[$currency] ??= ['net_sales' => 0.0, 'sales_documents' => 0, 'credit_notes' => 0];
    $comparisonSummary[$currency]['net_sales'] += (float) $row['net_amount'];
    if ($row['document_type'] === 'Nota de crédito') {
        $comparisonSummary[$currency]['credit_notes'] = (int) $row['document_count'];
    } else {
        $comparisonSummary[$currency]['sales_documents'] += (int) $row['document_count'];
    }
}

$sellerStatement = $pdo->prepare(
    "SELECT sales.currency, sales.seller_id,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ',
                NULLIF(employee.name, ''),
                NULLIF(employee.last_name_1, ''),
                NULLIF(employee.last_name_2, '')
            )), ''), users.username, 'Sin vendedor') AS seller,
            COUNT(*) AS document_count, SUM(sales.amount) AS net_amount
     FROM ($salesUnion) sales
     LEFT JOIN tbl_user users ON users.id = sales.seller_id
     LEFT JOIN tbl_employee employee ON employee.id = users.employee_id
     GROUP BY sales.currency, sales.seller_id, seller
     ORDER BY sales.currency, net_amount DESC
     LIMIT 100"
);
$sellerStatement->execute($dateParams);
$sellers = $sellerStatement->fetchAll();

$customerStatement = $pdo->prepare(
    "SELECT currency, ruc, name AS customer, COUNT(*) AS document_count, SUM(amount) AS net_amount
     FROM ($salesUnion) sales
     GROUP BY currency, ruc, name
     ORDER BY currency, net_amount DESC
     LIMIT 100"
);
$customerStatement->execute($dateParams);
$customers = $customerStatement->fetchAll();

$invoiceCurrency=app_report_currency_sql('invoice.currency');$receiptCurrency=app_report_currency_sql('receipt.currency');$creditCurrency=app_report_currency_sql('credit_note.currency');
$productUnion = "
    SELECT {$invoiceCurrency} currency, detail.item_id AS product_id, detail.item_code AS product_code,
           detail.item_name AS product, detail.item_quantity AS quantity,
           detail.item_quantity * detail.item_unit_price AS net_amount
    FROM tbl_invoice_detail detail
    JOIN tbl_invoice invoice ON invoice.id = detail.invoice_id
    WHERE invoice.status NOT IN (2,4) AND invoice.document_subtype<>'ADVANCE' AND invoice.date BETWEEN ? AND ?
    UNION ALL
    SELECT {$receiptCurrency} currency, detail.item_id AS product_id, detail.item_code AS product_code,
           detail.item_name AS product, detail.item_quantity AS quantity,
           detail.item_quantity * detail.item_unit_price AS net_amount
    FROM tbl_receipt_detail detail
    JOIN tbl_receipt receipt ON receipt.id = detail.receipt_id
    WHERE receipt.status NOT IN (2,4) AND receipt.date BETWEEN ? AND ?
    UNION ALL
    SELECT {$creditCurrency} currency, detail.item_id AS product_id, detail.item_code AS product_code,
           detail.item_name AS product, -detail.item_quantity AS quantity,
           -detail.item_quantity * detail.item_unit_price AS net_amount
    FROM tbl_credit_note_detail detail
    JOIN tbl_credit_note credit_note ON credit_note.id = detail.credit_note_id
    WHERE credit_note.status NOT IN (2,4) AND credit_note.date BETWEEN ? AND ?
      AND NOT EXISTS(SELECT 1 FROM tbl_invoice advance_invoice WHERE credit_note.referenced_doc_type_id=1 AND advance_invoice.id=credit_note.referenced_doc_id AND advance_invoice.document_subtype='ADVANCE')
";
$productStatement = $pdo->prepare(
    "SELECT currency, product_id, product_code, product, SUM(quantity) AS net_units,
            SUM(net_amount) AS line_amount
     FROM ($productUnion) products
     GROUP BY currency, product_id, product_code, product
     ORDER BY currency, line_amount DESC
     LIMIT 100"
);
$productStatement->execute($dateParams);

echo json_encode([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'summary' => $summary,
    'comparison' => [
        'date_from' => $comparisonStart->format('Y-m-d'),
        'date_to' => $comparisonEnd->format('Y-m-d'),
        'summary' => $comparisonSummary,
    ],
    'by_type' => $byType,
    'monthly' => $monthly,
    'sellers' => $sellers,
    'customers' => $customers,
    'products' => $productStatement->fetchAll(),
], JSON_UNESCAPED_UNICODE);
