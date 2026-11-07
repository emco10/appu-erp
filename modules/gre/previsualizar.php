<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('GET');
try {
    $id=app_gre_id(); app_gre_guide($pdo,$id);
    app_gre_reply(['success'=>true,'data'=>app_gre_api('GET','/api/v1/documents/despatch/'.$id.'/preview')]);
} catch(Throwable $error) { app_gre_failure($error); }
