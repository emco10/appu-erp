<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('GET');
try {
    $id=app_gre_id(); $guide=app_gre_guide($pdo,$id);
    $record=app_gre_record($pdo,$id);
    $details=$pdo->prepare('SELECT item_code,description,unit_code,quantity FROM tbl_remission_guide_detail WHERE remission_guide_id=? ORDER BY id');
    $details->execute([$id]);
    app_gre_reply(['success'=>true,'data'=>['guide'=>$guide,'lines'=>$details->fetchAll(PDO::FETCH_ASSOC),'submission'=>$record,
        'gre_enabled'=>app_gre_enabled($pdo),'can_edit'=>app_can('logistics.manage')&&$record===null&&$guide['status']==='Registrada']]);
} catch(Throwable $error) { app_gre_failure($error); }
