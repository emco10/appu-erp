<?php
require __DIR__ . '/_common.php';
app_sire_require_method('POST', 'management.manage');
try {
    $parameters = app_sire_parameters('proposal/export', $_POST);
    echo json_encode(app_sire_forward('POST', 'proposal/export', $parameters), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) { app_sire_error($error); }
