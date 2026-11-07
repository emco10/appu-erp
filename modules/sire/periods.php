<?php
require __DIR__ . '/_common.php';
app_sire_require_method('GET', 'management.view');
try {
    $parameters = app_sire_parameters('periods', $_GET);
    echo json_encode(app_sire_forward('GET', 'periods', $parameters), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) { app_sire_error($error); }
