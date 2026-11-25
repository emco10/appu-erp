<?php

require_once __DIR__ . '/document-engine.php';

function app_send_sales_creation_result(array $result, bool $asJson): void
{
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return;
    }
    echo 'OK_INSERT';
}
