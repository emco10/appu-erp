<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('POST');
try {
    app_gre_require_enabled($pdo);
    $id=app_gre_id(); app_gre_guide($pdo,$id);
    app_gre_reply(['success'=>true,'message'=>'Se actualizó el resultado del ticket GRE.','data'=>app_gre_api('POST','/api/v1/gre/'.$id.'/reconcile')]);
} catch(Throwable $error) { app_gre_failure($error); }
