<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.view');
$summary=['accepted'=>0,'observed'=>0,'rejected'=>0,'pending'=>0,'technical_error'=>0];
foreach($pdo->query("SELECT status,COUNT(*) total FROM tbl_sunat_document GROUP BY status")->fetchAll() as $row){
 $key=in_array($row['status'],['queued','processing','draft'],true)?'pending':$row['status'];
 if(isset($summary[$key]))$summary[$key]+=(int)$row['total'];
}
$sql="SELECT s.id,s.source_type,s.source_id,s.sunat_type,s.series,s.number,s.status,s.sunat_code,s.sunat_description,s.xml_path,s.cdr_path,s.digest_value,s.submitted_at,s.accepted_at,s.created_at,
 CASE s.source_type WHEN 'invoice' THEN i.name WHEN 'receipt' THEN r.name WHEN 'credit-note' THEN n.name ELSE '' END customer_name,
 CASE s.source_type WHEN 'invoice' THEN i.total_net WHEN 'receipt' THEN r.total_net WHEN 'credit-note' THEN n.total_net ELSE 0 END total_net
 FROM tbl_sunat_document s
 LEFT JOIN tbl_invoice i ON s.source_type='invoice' AND i.id=s.source_id
 LEFT JOIN tbl_receipt r ON s.source_type='receipt' AND r.id=s.source_id
 LEFT JOIN tbl_credit_note n ON s.source_type='credit-note' AND n.id=s.source_id
 ORDER BY s.id DESC LIMIT 500";
$documents=$pdo->query($sql)->fetchAll();
$series=$pdo->query("SELECT id,document_type,series,next_number,number_padding,sunat_enabled,active_status FROM tbl_document_series WHERE document_type IN('INVOICE','RECEIPT','CREDIT_NOTE') ORDER BY document_type,sunat_enabled DESC,series")->fetchAll();
echo json_encode(compact('summary','documents','series'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
