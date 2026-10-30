<?php
require '../../global/connection.php';
require_once __DIR__.'/../../global/document-flow-query.php';

header('Content-Type: application/json; charset=utf-8');

$jsonResponse = static function (int $status, array $payload): void {
    if(isset($payload['payments'])&&!app_can('finance.view')){$payload['payments']=[];$payload['payable']=null;}
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};
$validDocumentTypes = ['invoice', 'receipt', 'quotation', 'sales_order', 'purchase_request', 'purchase_order', 'purchase', 'credit_note'];
$statusLabels = [
    1 => 'Vigente',
    2 => 'Anulada',
    3 => 'Pendiente de pago',
    4 => 'Cancelada',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'deliveries';

    if ($action === 'search') {
        $term = trim((string) ($_GET['q'] ?? ''), "* ");
        if (mb_strlen($term) < 2) {
            $jsonResponse(200, ['documents' => []]);
        }

        $pattern = '%' . $term . '%';
        $query = "
            SELECT 'invoice' AS type, 'Factura' AS type_label, id, CONCAT(series, '-', number) AS number,
                   date, delivery_date AS planned_date, name AS customer, currency, total_net AS amount, status, quotation_id
            FROM tbl_invoice
            WHERE CONCAT(series, '-', number) LIKE ? OR name LIKE ? OR ruc LIKE ?
            UNION ALL
            SELECT 'receipt' AS type, 'Boleta' AS type_label, id, CONCAT(series, '-', number) AS number,
                   date, delivery_date AS planned_date, name AS customer, currency, total_net AS amount, status, quotation_id
            FROM tbl_receipt
            WHERE CONCAT(series, '-', number) LIKE ? OR name LIKE ? OR ruc LIKE ?
            UNION ALL
            SELECT 'quotation' AS type, 'Cotización' AS type_label, id, number,
                   date, delivery_date AS planned_date, name AS customer, currency, total_net AS amount, status, id AS quotation_id
            FROM tbl_quotation
            WHERE number LIKE ? OR name LIKE ? OR ruc LIKE ?
            UNION ALL
            SELECT 'sales_order' AS type, 'Orden de venta' AS type_label, id, number,
                   issue_date AS date, delivery_date AS planned_date, customer_name AS customer, currency, total_net AS amount,
                   CASE
                     WHEN status = 'Anulada' THEN 2
                     WHEN status IN ('Atendida', 'Cerrada') THEN 4
                     WHEN status IN ('Confirmada', 'Parcial') THEN 1
                     ELSE 3
                   END AS status, quotation_id
            FROM tbl_sales_order
            WHERE number LIKE ? OR customer_name LIKE ? OR ruc LIKE ?
            UNION ALL
            SELECT 'purchase' AS type, 'Compra' AS type_label, purchase.id, purchase.number,
                   purchase.issue_date AS date, purchase.delivery_date AS planned_date, provider.business_name AS customer,
                   purchase.currency, purchase.total_net AS amount,
                   CASE
                     WHEN purchase.status IN ('Anulada', 'Cancelada') THEN 2
                     WHEN purchase.status IN ('Pagada', 'Atendida', 'Cerrada') THEN 4
                     WHEN purchase.status IN ('Aprobada', 'Parcial') THEN 1
                     ELSE 3
                   END AS status, 0 AS quotation_id
            FROM tbl_purchase purchase
            LEFT JOIN tbl_provider provider ON provider.id = purchase.provider_id
            WHERE purchase.number LIKE ? OR purchase.supplier_reference LIKE ? OR provider.business_name LIKE ?
            UNION ALL
            SELECT 'purchase_request' AS type, 'Solicitud de compra' AS type_label, request.id, request.number,
                   request.issue_date AS date, request.required_date AS planned_date, '' AS customer, request.currency,
                   request.total_estimate AS amount,
                   CASE WHEN request.status IN ('Cancelada', 'Rechazada') THEN 2 WHEN request.status IN ('Convertida', 'Aprobada') THEN 1 ELSE 3 END AS status,
                   request.id AS quotation_id
            FROM tbl_purchase_request request
            WHERE request.number LIKE ? OR request.observation LIKE ?
            UNION ALL
            SELECT 'purchase_order' AS type, 'Orden de compra' AS type_label, ord.id, ord.number,
                   ord.issue_date AS date, ord.delivery_date AS planned_date, provider.business_name AS customer,
                   ord.currency, ord.total_net AS amount,
                   CASE WHEN ord.status IN ('Anulado', 'Anulada', 'Cancelada') THEN 2 WHEN ord.status IN ('Aprobado', 'Convertida') THEN 1 ELSE 3 END AS status,
                   ord.purchase_request_id AS quotation_id
            FROM tbl_order ord
            LEFT JOIN tbl_provider provider ON provider.id = ord.provider_id
            WHERE ord.type = 1 AND (ord.number LIKE ? OR provider.business_name LIKE ? OR ord.observation LIKE ?)
            UNION ALL
            SELECT 'credit_note' AS type, 'Nota de crédito' AS type_label, id, CONCAT(series, '-', number) AS number,
                   date, delivery_date AS planned_date, name AS customer, currency, -total_net AS amount, status, 0 AS quotation_id
            FROM tbl_credit_note
            WHERE CONCAT(series, '-', number) LIKE ? OR name LIKE ? OR ruc LIKE ?
            ORDER BY date DESC, id DESC
            LIMIT 50";
        $statement = $pdo->prepare($query);
        $statement->execute(array_fill(0, 23, $pattern));
        $documents = $statement->fetchAll();
        $documents=array_values(array_filter($documents,static fn(array $document):bool=>app_can(app_document_flow_permission($document['type']))));
        foreach ($documents as &$document) {
            $document['status_label'] = $statusLabels[(int) $document['status']] ?? 'Estado ' . $document['status'];
            $document['amount'] = (float) $document['amount'];
        }
        unset($document);
        $jsonResponse(200, ['documents' => $documents]);
    }

    if ($action === 'map') {
        $type = (string) ($_GET['type'] ?? '');
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($type, $validDocumentTypes, true) || $id === false || $id < 1) {
            $jsonResponse(422, ['error' => 'Selecciona un documento válido.']);
        }
        app_require_permission(app_document_flow_permission($type));

        $tables = [
            'invoice' => 'tbl_invoice',
            'receipt' => 'tbl_receipt',
            'quotation' => 'tbl_quotation',
            'sales_order' => 'tbl_sales_order',
            'purchase_request' => 'tbl_purchase_request',
            'purchase_order' => 'tbl_order',
            'purchase' => 'tbl_purchase',
            'credit_note' => 'tbl_credit_note',
        ];
        $numberExpression = in_array($type, ['quotation', 'sales_order', 'purchase_request', 'purchase_order', 'purchase'], true) ? 'number' : "CONCAT(series, '-', number)";
        if ($type === 'credit_note') {
            $relationshipFields = '0 AS quotation_id, referenced_doc_id, referenced_doc_type_id';
        } elseif ($type === 'sales_order') {
            $relationshipFields = 'quotation_id, id AS sales_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        } elseif ($type === 'purchase') {
            $relationshipFields = '0 AS quotation_id, 0 AS sales_order_id, purchase_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        } elseif ($type === 'purchase_order') {
            $relationshipFields = 'purchase_request_id, id AS purchase_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        } elseif ($type === 'purchase_request') {
            $relationshipFields = 'id AS purchase_request_id, 0 AS purchase_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        } elseif ($type === 'quotation') {
            $relationshipFields = 'id AS quotation_id, 0 AS sales_order_id, 0 AS purchase_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        } else {
            $relationshipFields = 'quotation_id, sales_order_id, 0 AS referenced_doc_id, 0 AS referenced_doc_type_id';
        }
        $selectedDate = in_array($type, ['sales_order', 'purchase_request', 'purchase_order', 'purchase'], true) ? 'issue_date' : 'date';
        $selectedPlannedDate = $type === 'purchase_request' ? 'required_date' : 'delivery_date';
        $selectedCustomer = $type === 'sales_order' ? 'customer_name' : ($type === 'purchase' || $type === 'purchase_order' ? 'provider.business_name' : ($type === 'purchase_request' ? "''" : 'name'));
        $selectedAddress = in_array($type, ['purchase', 'purchase_order', 'purchase_request'], true) ? "''" : 'address';
        $selectedAmount = $type === 'purchase_request' ? 'total_estimate' : 'total_net';
        $selectedId = $type === 'purchase' ? 'tbl_purchase.id' : ($type === 'purchase_order' ? 'tbl_order.id' : 'id');
        $selectedNumber = $type === 'purchase' ? 'tbl_purchase.number' : ($type === 'purchase_order' ? 'tbl_order.number' : $numberExpression);
        $selectedWhereId = $type === 'purchase' ? 'tbl_purchase.id' : ($type === 'purchase_order' ? 'tbl_order.id' : 'id');
        $selectedStatus = $type === 'sales_order'
            ? "CASE WHEN status = 'Anulada' THEN 2 WHEN status IN ('Atendida', 'Cerrada') THEN 4 WHEN status IN ('Confirmada', 'Parcial') THEN 1 ELSE 3 END"
            : ($type === 'purchase'
                ? "CASE WHEN tbl_purchase.status IN ('Anulada', 'Cancelada') THEN 2 WHEN tbl_purchase.status IN ('Pagada', 'Atendida', 'Cerrada') THEN 4 WHEN tbl_purchase.status IN ('Aprobada', 'Parcial') THEN 1 ELSE 3 END"
                : ($type === 'purchase_order'
                    ? "CASE WHEN tbl_order.status IN ('Anulado', 'Anulada', 'Cancelada') THEN 2 WHEN tbl_order.status IN ('Aprobado', 'Convertida') THEN 1 ELSE 3 END"
                    : ($type === 'purchase_request'
                        ? "CASE WHEN tbl_purchase_request.status IN ('Cancelada', 'Rechazada') THEN 2 WHEN tbl_purchase_request.status IN ('Convertida', 'Aprobada') THEN 1 ELSE 3 END"
                        : 'status')));
        $documentStatement = $pdo->prepare(
            "SELECT $selectedId AS id, $selectedNumber AS display_number, $selectedDate AS date, $selectedPlannedDate AS planned_date,
                    $selectedCustomer AS customer, $selectedAddress AS address, currency,
                    $selectedAmount AS amount, $selectedStatus AS status, $relationshipFields
             FROM {$tables[$type]}
             " . ($type === 'purchase' ? 'LEFT JOIN tbl_provider provider ON provider.id = tbl_purchase.provider_id' : ($type === 'purchase_order' ? 'LEFT JOIN tbl_provider provider ON provider.id = tbl_order.provider_id' : '')) . "
             WHERE $selectedWhereId = ?"
        );
        $documentStatement->execute([$id]);
        $selected = $documentStatement->fetch();
        if (!$selected) {
            $jsonResponse(404, ['error' => 'No se encontró el documento.']);
        }

        $salesDocuments = [];
        $purchase = null;
        $purchaseOrder = null;
        $purchaseRequest = null;
        $goodsReceipts = [];
        $landedCosts = [];
        $payable = null;
        $payments = [];
        if ($type === 'invoice' || $type === 'receipt') {
            $salesDocuments[] = [
                'type' => $type,
                'id' => (int) $selected['id'],
                'number' => $selected['display_number'],
                'date' => $selected['date'],
                'planned_date' => $selected['planned_date'],
                'customer' => $selected['customer'],
                'address' => $selected['address'] ?? '',
                'currency' => $selected['currency'],
                'amount' => (float) $selected['amount'],
                'status' => (int) $selected['status'],
                'quotation_id' => (int) $selected['quotation_id'],
                'sales_order_id' => (int) ($selected['sales_order_id'] ?? 0),
            ];
        } elseif ($type === 'sales_order') {
            $salesOrderId = (int) $selected['id'];
        } elseif ($type === 'purchase') {
            $purchase = [
                'id' => (int) $selected['id'],
                'number' => $selected['display_number'],
                'date' => $selected['date'],
                'planned_date' => $selected['planned_date'],
                'customer' => $selected['customer'],
                'currency' => $selected['currency'],
                'amount' => (float) $selected['amount'],
                'status' => (int) $selected['status'],
            ];
            $purchaseOrderId = (int) ($selected['purchase_order_id'] ?? 0);
            if ($purchaseOrderId > 0) {
                $purchaseOrderStatement = $pdo->prepare(
                    "SELECT ord.id, ord.number, ord.issue_date AS date, ord.delivery_date AS planned_date,
                            provider.business_name AS customer, ord.currency, ord.total_net AS amount,
                            CASE WHEN ord.status IN ('Anulado', 'Anulada', 'Cancelada') THEN 2 WHEN ord.status IN ('Aprobada', 'Confirmada', 'Parcial') THEN 1 ELSE 3 END AS status,
                            ord.purchase_request_id
                     FROM tbl_order ord LEFT JOIN tbl_provider provider ON provider.id = ord.provider_id
                     WHERE ord.id = ?"
                );
                $purchaseOrderStatement->execute([$purchaseOrderId]);
                $purchaseOrder = $purchaseOrderStatement->fetch() ?: null;
                if ($purchaseOrder && (int) $purchaseOrder['purchase_request_id'] > 0) {
                    $requestStatement = $pdo->prepare(
                        "SELECT id, number, issue_date AS date, required_date AS planned_date, currency, total_estimate AS amount, status
                         FROM tbl_purchase_request WHERE id = ?"
                    );
                    $requestStatement->execute([(int) $purchaseOrder['purchase_request_id']]);
                    $purchaseRequest = $requestStatement->fetch() ?: null;
                }
            }
            $receiptStatement = $pdo->prepare(
                "SELECT receipt.id, receipt.number, receipt.status, receipt.receipt_date AS date,
                        receipt.receipt_date AS planned_date, warehouse.name AS warehouse_name
                 FROM tbl_goods_receipt receipt
                 LEFT JOIN tbl_warehouse warehouse ON warehouse.id = receipt.warehouse_id
                 WHERE receipt.purchase_id = ? ORDER BY receipt.receipt_date, receipt.id"
            );
            $receiptStatement->execute([(int) $selected['id']]);
            foreach ($receiptStatement->fetchAll() as $receipt) {
                $goodsReceipts[] = [
                    'id' => (int) $receipt['id'], 'number' => $receipt['number'], 'date' => $receipt['date'],
                    'planned_date' => $receipt['planned_date'], 'warehouse_name' => $receipt['warehouse_name'],
                    'status' => $receipt['status'],
                ];
            }
            $landedCostStatement = $pdo->prepare(
                "SELECT cost.id, cost.number, cost.cost_date AS date, cost.concept,
                        cost.allocation_method, cost.currency, cost.total_cost, cost.status,
                        receipt.number AS receipt_number
                 FROM tbl_landed_cost cost
                 JOIN tbl_goods_receipt receipt ON receipt.id = cost.goods_receipt_id
                 WHERE receipt.purchase_id = ?
                 ORDER BY cost.cost_date, cost.id"
            );
            $landedCostStatement->execute([(int) $selected['id']]);
            $landedCosts = $landedCostStatement->fetchAll();
            $payableStatement = $pdo->prepare(
                "SELECT id, document_number, issue_date AS date, due_date, currency, total_amount,
                        paid_amount, balance_amount, status, supplier_reference
                 FROM tbl_accounts_payable WHERE document_type = 'purchase' AND document_id = ? LIMIT 1"
            );
            $payableStatement->execute([(int) $selected['id']]);
            $payable = $payableStatement->fetch() ?: null;
            if ($payable) {
                $paymentStatement = $pdo->prepare(
                    "SELECT 'purchase' AS source_type, ? AS source_id, header.payment_number, header.payment_date AS date, header.status,
                            header.payment_method, header.currency, detail.applied_amount AS amount,
                            header.reference
                     FROM tbl_outgoing_payment_detail detail
                     JOIN tbl_outgoing_payment header ON header.id = detail.outgoing_payment_id
                     WHERE detail.account_payable_id = ? ORDER BY header.payment_date, header.id"
                );
                $paymentStatement->execute([(int) $selected['id'], (int) $payable['id']]);
                $payments = $paymentStatement->fetchAll();
            }
        } elseif ($type === 'purchase_order' || $type === 'purchase_request') {
            if ($type === 'purchase_request') {
                $purchaseRequest = [
                    'id' => (int) $selected['id'],
                    'number' => $selected['display_number'],
                    'date' => $selected['date'],
                    'planned_date' => $selected['planned_date'],
                    'currency' => $selected['currency'],
                    'amount' => (float) $selected['amount'],
                    'status' => (int) $selected['status'],
                ];
                $orderStatement = $pdo->prepare(
                    "SELECT ord.id, ord.number, ord.issue_date AS date, ord.delivery_date AS planned_date,
                            provider.business_name AS customer, ord.currency, ord.total_net AS amount,
                            CASE WHEN ord.status IN ('Anulado', 'Anulada', 'Cancelada') THEN 2 WHEN ord.status IN ('Aprobado', 'Convertida') THEN 1 ELSE 3 END AS status,
                            ord.purchase_request_id
                     FROM tbl_order ord LEFT JOIN tbl_provider provider ON provider.id = ord.provider_id
                     WHERE ord.type = 1 AND ord.purchase_request_id = ? ORDER BY ord.id DESC LIMIT 1"
                );
                $orderStatement->execute([(int) $selected['id']]);
                $purchaseOrder = $orderStatement->fetch() ?: null;
            } else {
                $purchaseOrder = [
                    'id' => (int) $selected['id'],
                    'number' => $selected['display_number'],
                    'date' => $selected['date'],
                    'planned_date' => $selected['planned_date'],
                    'customer' => $selected['customer'],
                    'currency' => $selected['currency'],
                    'amount' => (float) $selected['amount'],
                    'status' => (int) $selected['status'],
                    'purchase_request_id' => (int) ($selected['purchase_request_id'] ?? 0),
                ];
                if ((int) $purchaseOrder['purchase_request_id'] > 0) {
                    $requestStatement = $pdo->prepare(
                        "SELECT id, number, issue_date AS date, required_date AS planned_date, currency, total_estimate AS amount, status
                         FROM tbl_purchase_request WHERE id = ?"
                    );
                    $requestStatement->execute([(int) $purchaseOrder['purchase_request_id']]);
                    $request = $requestStatement->fetch();
                    if ($request) {
                        $purchaseRequest = [
                            'id' => (int) $request['id'], 'number' => $request['number'], 'date' => $request['date'],
                            'planned_date' => $request['planned_date'], 'currency' => $request['currency'],
                            'amount' => (float) $request['amount'],
                            'status' => in_array($request['status'], ['Cancelada', 'Rechazada'], true) ? 2 : 1,
                        ];
                    }
                }
            }
            if ($purchaseOrder) {
                $purchaseStatement = $pdo->prepare(
                    "SELECT id, number, issue_date AS date, delivery_date AS planned_date, currency, total_net AS amount, status
                     FROM tbl_purchase WHERE purchase_order_id = ? ORDER BY id DESC LIMIT 1"
                );
                $purchaseStatement->execute([(int) $purchaseOrder['id']]);
                $linkedPurchase = $purchaseStatement->fetch();
                if ($linkedPurchase) {
                    $purchase = [
                        'id' => (int) $linkedPurchase['id'], 'number' => $linkedPurchase['number'], 'date' => $linkedPurchase['date'],
                        'planned_date' => $linkedPurchase['planned_date'], 'customer' => $purchaseOrder['customer'],
                        'currency' => $linkedPurchase['currency'], 'amount' => (float) $linkedPurchase['amount'],
                        'status' => in_array($linkedPurchase['status'], ['Anulada', 'Cancelada'], true) ? 2 : 1,
                    ];
                }
            }
        } elseif ($type === 'credit_note') {
            $sourceDocumentType = (int) $selected['referenced_doc_type_id'];
            if (!in_array($sourceDocumentType, [1, 2], true)) {
                $jsonResponse(422, ['error' => 'La nota de crédito tiene un tipo de documento de origen inválido.']);
            }
            $sourceType = $sourceDocumentType === 1 ? 'invoice' : 'receipt';
            $sourceTable = $tables[$sourceType];
            $sourceStatement = $pdo->prepare(
                "SELECT id, series, number, date, delivery_date AS planned_date,
                        name AS customer, address, currency, total_net AS amount,
                        status, quotation_id
                 FROM $sourceTable
                 WHERE id = ?"
            );
            $sourceStatement->execute([(int) $selected['referenced_doc_id']]);
            $source = $sourceStatement->fetch();
            if ($source) {
                $salesDocuments[] = [
                    'type' => $sourceType,
                    'id' => (int) $source['id'],
                    'number' => trim((string) $source['series']) . '-' . $source['number'],
                    'date' => $source['date'],
                    'planned_date' => $source['planned_date'],
                    'customer' => $source['customer'],
                    'address' => $source['address'] ?? '',
                    'currency' => $source['currency'],
                    'amount' => (float) $source['amount'],
                    'status' => (int) $source['status'],
                    'quotation_id' => (int) $source['quotation_id'],
                    'sales_order_id' => (int) ($source['sales_order_id'] ?? 0),
                ];
            }
        }

        $salesOrderId = $type === 'sales_order'
            ? (int) $selected['id']
            : (int) ($salesDocuments[0]['sales_order_id'] ?? ($selected['sales_order_id'] ?? 0));
        $salesOrder = null;
        if ($salesOrderId > 0) {
            $salesOrderStatement = $pdo->prepare(
                "SELECT id, number, issue_date AS date, delivery_date AS planned_date,
                        customer_name AS customer, address, currency, total_net AS amount,
                        CASE WHEN status = 'Anulada' THEN 2 WHEN status IN ('Atendida', 'Cerrada') THEN 4 WHEN status IN ('Confirmada', 'Parcial') THEN 1 ELSE 3 END AS status,
                        quotation_id
                 FROM tbl_sales_order WHERE id = ?"
            );
            $salesOrderStatement->execute([$salesOrderId]);
            $salesOrder = $salesOrderStatement->fetch();
            if ($salesOrder && $type === 'sales_order') {
                $relatedSalesStatement = $pdo->prepare(
                    "SELECT 'invoice' AS type, id, CONCAT(series, '-', number) AS number,
                             date, delivery_date AS planned_date, name AS customer, address, currency, total_net AS amount, status, quotation_id, sales_order_id
                     FROM tbl_invoice WHERE sales_order_id = ?
                     UNION ALL
                     SELECT 'receipt' AS type, id, CONCAT(series, '-', number) AS number,
                             date, delivery_date AS planned_date, name AS customer, address, currency, total_net AS amount, status, quotation_id, sales_order_id
                     FROM tbl_receipt WHERE sales_order_id = ?
                     ORDER BY date, id"
                );
                $relatedSalesStatement->execute([$salesOrderId, $salesOrderId]);
                $salesDocuments = [];
                foreach ($relatedSalesStatement->fetchAll() as $sale) {
                    $salesDocuments[] = [
                        'type' => $sale['type'], 'id' => (int) $sale['id'], 'number' => $sale['number'],
                        'date' => $sale['date'], 'planned_date' => $sale['planned_date'], 'customer' => $sale['customer'],
                        'address' => $sale['address'], 'currency' => $sale['currency'], 'amount' => (float) $sale['amount'],
                        'status' => (int) $sale['status'], 'quotation_id' => (int) $sale['quotation_id'],
                        'sales_order_id' => (int) $sale['sales_order_id'],
                    ];
                }
            }
        }

        $quotationId = $type === 'quotation'
            ? (int) $selected['id']
            : (int) ($salesOrder['quotation_id'] ?? ($salesDocuments[0]['quotation_id'] ?? 0));
        if ($quotationId > 0 && ($type === 'quotation' || $salesDocuments !== [])) {
            $quotationStatement = $pdo->prepare(
                "SELECT id, number, date, name AS customer, currency, total_net AS amount, status
                 FROM tbl_quotation
                 WHERE id = ?"
            );
            $quotationStatement->execute([$quotationId]);
            $quotation = $quotationStatement->fetch();

            $relatedSalesStatement = $pdo->prepare(
                "SELECT 'invoice' AS type, id, CONCAT(series, '-', number) AS number,
                         date, delivery_date AS planned_date, name AS customer, address, currency, total_net AS amount, status, quotation_id
                 FROM tbl_invoice WHERE quotation_id = ?
                 UNION ALL
                 SELECT 'receipt' AS type, id, CONCAT(series, '-', number) AS number,
                         date, delivery_date AS planned_date, name AS customer, address, currency, total_net AS amount, status, quotation_id
                 FROM tbl_receipt WHERE quotation_id = ?
                 ORDER BY date, id"
            );
            $relatedSalesStatement->execute([$quotationId, $quotationId]);
            $salesDocuments = [];
            foreach ($relatedSalesStatement->fetchAll() as $sale) {
                $salesDocuments[] = [
                    'type' => $sale['type'],
                    'id' => (int) $sale['id'],
                    'number' => $sale['number'],
                    'date' => $sale['date'],
                    'planned_date' => $sale['planned_date'],
                    'customer' => $sale['customer'],
                    'address' => $sale['address'],
                    'currency' => $sale['currency'],
                    'amount' => (float) $sale['amount'],
                    'status' => (int) $sale['status'],
                    'quotation_id' => (int) $sale['quotation_id'],
                ];
            }
        } else {
            $quotation = null;
        }

        if ($type === 'quotation' && !isset($quotation)) {
            $quotation = [
                'id' => (int) $selected['id'],
                'number' => $selected['display_number'],
                'date' => $selected['date'],
                'customer' => $selected['customer'],
                'currency' => $selected['currency'],
                'amount' => (float) $selected['amount'],
                'status' => (int) $selected['status'],
            ];
        }

        $notes = [];
        $deliveries = [];
        foreach ($salesDocuments as $sale) {
            $creditType = $sale['type'] === 'invoice' ? 1 : 2;
            $notesStatement = $pdo->prepare(
                "SELECT id, series, number, date, name AS customer, currency, total_net AS amount, status
                 FROM tbl_credit_note
                 WHERE referenced_doc_id = ? AND referenced_doc_type_id = ?
                 ORDER BY date, id"
            );
            $notesStatement->execute([$sale['id'], $creditType]);
            foreach ($notesStatement->fetchAll() as $note) {
                $notes[] = [
                    'id' => (int) $note['id'],
                    'number' => trim((string) $note['series']) . '-' . $note['number'],
                    'date' => $note['date'],
                    'customer' => $note['customer'],
                    'currency' => $note['currency'],
                    'amount' => (float) $note['amount'],
                    'status' => (int) $note['status'],
                    'source_type' => $sale['type'],
                    'source_id' => (int) $sale['id'],
                ];
            }

            $deliveryStatement = $pdo->prepare(
                "SELECT id, source_type, source_id, status, planned_date, dispatched_at,
                        delivered_at, recipient_name, delivery_address, carrier, tracking_number, notes
                 FROM tbl_delivery
                 WHERE source_type = ? AND source_id = ?"
            );
            $deliveryStatement->execute([$sale['type'], $sale['id']]);
            $delivery = $deliveryStatement->fetch();
            if ($delivery) {
                $delivery['id'] = (int) $delivery['id'];
                $delivery['status'] = (int) $delivery['status'];
                $deliveries[] = $delivery;
            }
            $accountReceivableStatement = $pdo->prepare(
                "SELECT 'receivable' AS source_type, ? AS source_id, header.payment_number, header.payment_date AS date,
                        header.status, header.payment_method, header.currency, detail.applied_amount AS amount, header.reference
                 FROM tbl_incoming_payment_detail detail
                 JOIN tbl_incoming_payment header ON header.id = detail.incoming_payment_id
                 JOIN tbl_accounts_receivable account ON account.id = detail.account_receivable_id
                 WHERE account.document_type = ? AND account.document_id = ?
                 ORDER BY header.payment_date, header.id"
            );
            $accountReceivableStatement->execute([$sale['id'], $sale['type'], $sale['id']]);
            foreach ($accountReceivableStatement->fetchAll() as $payment) {
                $payments[] = $payment;
            }
        }

        $selected['type'] = $type;
        $selected['number'] = $selected['display_number'];
        $selected['id'] = (int) $selected['id'];
        $selected['status'] = (int) $selected['status'];
        $selected['amount'] = (float) $selected['amount'];
        $jsonResponse(200, [
            'selected' => $selected,
            'quotation' => $quotation,
            'sales_order' => $salesOrder,
            'purchase' => $purchase,
            'purchase_order' => $purchaseOrder,
            'purchase_request' => $purchaseRequest,
            'goods_receipts' => $goodsReceipts,
            'landed_costs' => $landedCosts,
            'payable' => $payable,
            'payments' => $payments,
            'sales_documents' => $salesDocuments,
            'credit_notes' => $notes,
            'deliveries' => $deliveries,
            'status_labels' => $statusLabels,
        ]);
    }

    if ($action === 'deliveries') {
        $statement = $pdo->query(
            "SELECT delivery.id, delivery.source_type, delivery.source_id, delivery.status,
                    delivery.planned_date, delivery.dispatched_at, delivery.delivered_at,
                    delivery.recipient_name, delivery.delivery_address, delivery.carrier,
                    delivery.tracking_number,
                    COALESCE(invoice.series, receipt.series) AS document_series,
                    COALESCE(invoice.number, receipt.number) AS document_number,
                    COALESCE(invoice.name, receipt.name) AS customer
             FROM tbl_delivery delivery
             LEFT JOIN tbl_invoice invoice
               ON delivery.source_type = 'invoice' AND invoice.id = delivery.source_id
             LEFT JOIN tbl_receipt receipt
               ON delivery.source_type = 'receipt' AND receipt.id = delivery.source_id
             ORDER BY delivery.planned_date DESC, delivery.id DESC
             LIMIT 100"
        );
        $jsonResponse(200, ['deliveries' => $statement->fetchAll()]);
    }

    $jsonResponse(400, ['error' => 'Acción no válida.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $jsonResponse(405, ['error' => 'Método no permitido.']);
}

$action = $_POST['action'] ?? '';
if ($action === 'create_delivery') {
    $sourceType = (string) ($_POST['source_type'] ?? '');
    $sourceId = filter_var($_POST['source_id'] ?? null, FILTER_VALIDATE_INT);
    $plannedDate = (string) ($_POST['planned_date'] ?? '');
    if (!in_array($sourceType, ['invoice', 'receipt'], true) || $sourceId === false || $sourceId < 1) {
        $jsonResponse(422, ['error' => 'Selecciona una factura o boleta válida.']);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedDate)) {
        $jsonResponse(422, ['error' => 'Indica una fecha de entrega válida.']);
    }
    [$year, $month, $day] = array_map('intval', explode('-', $plannedDate));
    if (!checkdate($month, $day, $year)) {
        $jsonResponse(422, ['error' => 'Indica una fecha de entrega válida.']);
    }

    $sourceTable = $sourceType === 'invoice' ? 'tbl_invoice' : 'tbl_receipt';
    $sourceStatement = $pdo->prepare(
        "SELECT name, address FROM $sourceTable WHERE id = ? AND status NOT IN (2,4)"
    );
    $sourceStatement->execute([$sourceId]);
    $source = $sourceStatement->fetch();
    if (!$source) {
        $jsonResponse(404, ['error' => 'El documento de venta no existe o está anulado.']);
    }

    $deliveryExists = $pdo->prepare(
        'SELECT id FROM tbl_delivery WHERE source_type = ? AND source_id = ?'
    );
    $deliveryExists->execute([$sourceType, $sourceId]);
    if ($deliveryExists->fetchColumn()) {
        $jsonResponse(409, ['error' => 'Este documento ya tiene un despacho registrado.']);
    }

    $recipient = trim((string) ($_POST['recipient_name'] ?? '')) ?: $source['name'];
    $address = trim((string) ($_POST['delivery_address'] ?? '')) ?: $source['address'];
    $carrier = trim((string) ($_POST['carrier'] ?? ''));
    $trackingNumber = trim((string) ($_POST['tracking_number'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if (mb_strlen($recipient) > 200 || mb_strlen($address) > 300 || mb_strlen($carrier) > 150 || mb_strlen($trackingNumber) > 100) {
        $jsonResponse(422, ['error' => 'Uno de los campos supera la longitud permitida.']);
    }

    $insert = $pdo->prepare(
        "INSERT INTO tbl_delivery
            (source_type, source_id, status, planned_date, recipient_name, delivery_address,
             carrier, tracking_number, notes, created_by)
         VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insert->execute([
        $sourceType,
        $sourceId,
        $plannedDate,
        $recipient,
        $address,
        $carrier,
        $trackingNumber,
        $notes,
        (int) $_SESSION['loggedInUser']['USERID'],
    ]);
    $jsonResponse(201, ['delivery_id' => (int) $pdo->lastInsertId(), 'message' => 'Despacho interno registrado.']);
}

if ($action === 'advance_delivery') {
    $deliveryId = filter_var($_POST['delivery_id'] ?? null, FILTER_VALIDATE_INT);
    $nextStatus = filter_var($_POST['status'] ?? null, FILTER_VALIDATE_INT);
    if ($deliveryId === false || $deliveryId < 1 || $nextStatus === false || !in_array($nextStatus, [2, 3, 4], true)) {
        $jsonResponse(422, ['error' => 'Indica un estado de despacho válido.']);
    }

    $statement = $pdo->prepare('SELECT status FROM tbl_delivery WHERE id = ?');
    $statement->execute([$deliveryId]);
    $currentStatus = $statement->fetchColumn();
    if ($currentStatus === false) {
        $jsonResponse(404, ['error' => 'No se encontró el despacho.']);
    }
    if ($nextStatus !== (int) $currentStatus + 1) {
        $jsonResponse(409, ['error' => 'El despacho debe avanzar al siguiente estado.']);
    }

    if ($nextStatus === 3) {
        $update = $pdo->prepare(
            'UPDATE tbl_delivery SET status = 3, dispatched_at = NOW() WHERE id = ? AND status = 2'
        );
    } elseif ($nextStatus === 4) {
        $update = $pdo->prepare(
            'UPDATE tbl_delivery SET status = 4, delivered_at = NOW() WHERE id = ? AND status = 3'
        );
    } else {
        $update = $pdo->prepare(
            'UPDATE tbl_delivery SET status = 2 WHERE id = ? AND status = 1'
        );
    }
    $update->execute([$deliveryId]);
    if ($update->rowCount() !== 1) {
        $jsonResponse(409, ['error' => 'El despacho cambió mientras se actualizaba. Recarga e inténtalo de nuevo.']);
    }
    $jsonResponse(200, ['message' => 'Estado del despacho actualizado.']);
}

$jsonResponse(400, ['error' => 'Acción no válida.']);
