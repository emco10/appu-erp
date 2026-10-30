<?php
require '../../global/connection.php';

header('Content-Type: application/json; charset=utf-8');

$statement = $pdo->query(
    "SELECT ord.id, ord.number, ord.issue_date, provider.business_name
     FROM tbl_order ord
     LEFT JOIN tbl_provider provider ON provider.id = ord.provider_id
     LEFT JOIN tbl_purchase purchase ON purchase.purchase_order_id = ord.id AND purchase.status NOT IN ('Anulado','Anulada','Cancelada')
     WHERE ord.type = 1
       AND ord.status IN ('Aprobado','Parcial','Atendido','Convertida')
       AND purchase.id IS NULL
     ORDER BY ord.issue_date DESC, ord.id DESC"
);

$options = [['id' => '', 'text' => 'Sin orden de compra']];
foreach ($statement->fetchAll() as $row) {
    $label = $row['number'] . ' | ' . date('d-m-Y', strtotime($row['issue_date']));
    if (!empty($row['business_name'])) {
        $label .= ' | ' . $row['business_name'];
    }
    $options[] = ['id' => (int) $row['id'], 'text' => $label];
}

echo json_encode($options, JSON_UNESCAPED_UNICODE);
