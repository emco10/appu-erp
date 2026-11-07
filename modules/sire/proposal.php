<?php
require __DIR__ . '/_common.php';
app_sire_require_method('GET', 'management.view');
try {
    $parameters = app_sire_parameters('proposal', $_GET);
    echo json_encode(app_sire_forward('GET', 'proposal', $parameters), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) { app_sire_error($error); }
