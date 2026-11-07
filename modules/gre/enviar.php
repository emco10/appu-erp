<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('POST');
try {
    app_gre_require_enabled($pdo);
    $id=app_gre_id(); $guide=app_gre_guide($pdo,$id);
    if (!preg_match('/^T[A-Z0-9]{3}-[0-9]{1,8}$/D',(string)$guide['number'])) throw new DomainException('La guía usa una serie interna. Crea una nueva guía con la serie electrónica administrada TXXX.');
    if (in_array((string)$guide['status'],['Anulada','Cancelada'],true)) throw new DomainException('Una guía anulada no puede enviarse a SUNAT.');
    $key='appu-gre-'.$id.'-'.substr(hash('sha256',(string)$guide['number']),0,24);
    $data=app_gre_api('POST','/api/v1/gre/'.$id.'/submit',['Idempotency-Key: '.$key]);
    app_gre_reply(['success'=>true,'message'=>'Se consultó el envío GRE. Revisa el ticket y el CDR antes de iniciar el traslado.','data'=>$data]);
} catch(Throwable $error) { app_gre_failure($error); }
