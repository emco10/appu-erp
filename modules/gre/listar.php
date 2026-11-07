<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('GET');
try {
    $ready=app_peru_table_exists($pdo,'tbl_gre_submission');
    $join=$ready?' LEFT JOIN tbl_gre_submission gre ON gre.source_id=guide.id':'';
    $select=$ready?'gre.status AS gre_status,gre.ticket,gre.sunat_code':"NULL AS gre_status,NULL AS ticket,NULL AS sunat_code";
    $rows=$pdo->query("SELECT guide.id,guide.number,guide.status,guide.source_type,guide.source_id,guide.recipient_name,guide.transfer_date,guide.transport_mode,{$select} FROM tbl_remission_guide guide{$join} ORDER BY guide.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    app_gre_reply(['success'=>true,'data'=>$rows,'gre_enabled'=>app_gre_enabled($pdo),'schema_ready'=>$ready]);
} catch(Throwable $error) { app_gre_failure($error); }
