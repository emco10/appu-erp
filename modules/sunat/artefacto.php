<?php
require '../../global/connection.php';app_require_permission('sales.view');
$type=trim((string)($_GET['source_type']??''));$id=filter_var($_GET['source_id']??null,FILTER_VALIDATE_INT);$kind=trim((string)($_GET['kind']??''));
if(!in_array($type,['invoice','receipt','credit-note'],true)||$id===false||!in_array($kind,['xml','cdr'],true)){http_response_code(422);exit('Solicitud inválida.');}
$q=$pdo->prepare('SELECT xml_path,cdr_path FROM tbl_sunat_document WHERE source_type=? AND source_id=?');$q->execute([$type,$id]);$row=$q->fetch();$path=$row[$kind.'_path']??'';
if(!$row||$path===''||!is_file($path)||!is_readable($path)){http_response_code(404);exit('El archivo todavía no está disponible.');}
$name=basename($path);$mime=$kind==='xml'?'application/xml':'application/zip';header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Content-Disposition: '.(($_GET['download']??'0')==='1'?'attachment':'inline').'; filename="'.str_replace('"','',$name).'"');header('Cache-Control: private, no-store');readfile($path);
