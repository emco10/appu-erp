<?php

/**
 * Obtiene y reserva el siguiente número de una serie administrada en Gestión.
 * Debe ejecutarse dentro de la transacción del documento para que el bloqueo
 * FOR UPDATE garantice que dos usuarios no reciban el mismo correlativo.
 */
function app_next_document_number(PDO $pdo, string $documentType, string $fallbackPrefix, ?string $selectedSeries = null): string
{
    $tableMap = [
        'QUOTATION' => 'tbl_quotation',
        'SALES_ORDER' => 'tbl_sales_order',
        'PURCHASE' => 'tbl_purchase',
        'PURCHASE_REQUEST' => 'tbl_purchase_request',
        'ORDER' => 'tbl_order',
        'GOODS_RECEIPT' => 'tbl_goods_receipt',
        'REMISSION_GUIDE' => 'tbl_remission_guide',
        'INCOMING_PAYMENT' => 'tbl_incoming_payment',
        'OUTGOING_PAYMENT' => 'tbl_outgoing_payment',
        'STOCK_TRANSFER' => 'tbl_inventory_transfer',
        'LANDED_COST' => 'tbl_landed_cost',
        'INVENTORY_ENTRY' => 'tbl_inventory_document',
        'INVENTORY_ISSUE' => 'tbl_inventory_document',
    ];
    $documentType = strtoupper(trim($documentType));
    $fallbackPrefix = strtoupper(trim($fallbackPrefix));
    $sql = 'SELECT id, series, next_number, number_padding FROM tbl_document_series WHERE document_type = ? AND active_status = 1';
    $parameters = [$documentType];
    if ($selectedSeries !== null && $selectedSeries !== '') { $sql .= ' AND series = ?'; $parameters[] = $selectedSeries; }
    $statement = $pdo->prepare($sql . ' ORDER BY id LIMIT 1 FOR UPDATE');
    $statement->execute($parameters);
    $series = $statement->fetch();
    if (!$series) {
        if ($selectedSeries !== null && $selectedSeries !== '') throw new DomainException('La serie seleccionada no existe o está inactiva.');
        return $fallbackPrefix . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    $nextNumber = max(1, (int) $series['next_number']);
    $seriesCode = (string) $series['series'];
    if (isset($tableMap[$documentType])) {
        $numberColumn = in_array($documentType, ['INCOMING_PAYMENT', 'OUTGOING_PAYMENT'], true) ? 'payment_number' : 'number';
        // Hostinger puede tener columnas/cadenas con collations distintas (p. ej. bin vs unicode_ci).
        // Normalizamos ambos operandos para que la reserva del correlativo no falle con error 1267.
        $maxStatement = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX({$numberColumn}, '-', -1) AS UNSIGNED)), 0) FROM {$tableMap[$documentType]} WHERE {$numberColumn} COLLATE utf8mb4_unicode_ci LIKE CONCAT(CAST(? AS CHAR), '-%') COLLATE utf8mb4_unicode_ci");
        $maxStatement->execute([$seriesCode]);
        $nextNumber = max($nextNumber, (int) $maxStatement->fetchColumn() + 1);
    }
    $number = $seriesCode . '-' . str_pad((string) $nextNumber, (int) $series['number_padding'], '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE tbl_document_series SET next_number = ? WHERE id = ?')->execute([$nextNumber + 1, (int) $series['id']]);
    return $number;
}
