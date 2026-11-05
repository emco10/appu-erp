<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
app_require_permission('sales.view');
$type=trim((string)($_GET['source_type']??''));$id=filter_var($_GET['source_id']??null,FILTER_VALIDATE_INT);
if(!in_array($type,['invoice','receipt','credit-note','despatch'],true)||$id===false){http_response_code(422);echo json_encode(['error'=>'Documento inválido.']);exit;}
$seriesType=['invoice'=>'INVOICE','receipt'=>'RECEIPT','credit-note'=>'CREDIT_NOTE','despatch'=>'REMISSION_GUIDE'][$type];
$tables=['invoice'=>'tbl_invoice','receipt'=>'tbl_receipt','credit-note'=>'tbl_credit_note','despatch'=>'tbl_remission_guide'];
$q=$pdo->prepare("SELECT * FROM {$tables[$type]} WHERE id=?");$q->execute([$id]);$document=$q->fetch();
if(!$document){http_response_code(404);echo json_encode(['error'=>'Documento no encontrado.']);exit;}
$sunatEnabled=true;$series=(string)($document['series']??'');if($type!=='despatch'){$q=$pdo->prepare('SELECT sunat_enabled FROM tbl_document_series WHERE document_type=? AND series=?');$q->execute([$seriesType,$series]);$value=$q->fetchColumn();$sunatEnabled=$value===false||(int)$value===1;}
$q=$pdo->prepare('SELECT id,status,sunat_code,sunat_description,xml_path,cdr_path,digest_value,submitted_at,accepted_at FROM tbl_sunat_document WHERE source_type=? AND source_id=?');$q->execute([$type,$id]);$sunat=$q->fetch()?:null;
echo json_encode(['sunat_enabled'=>$sunatEnabled,'document'=>$document,'sunat'=>$sunat],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
