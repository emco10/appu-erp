<?php
declare(strict_types=1);
require __DIR__ . '/_common.php';
app_gre_method('GET');
try {
    $id=app_gre_id(); app_gre_guide($pdo,$id); $kind=(string)($_GET['kind']??'');
    if(!in_array($kind,['xml','cdr'],true))throw new DomainException('Selecciona XML o CDR.');
    $data=app_gre_api('GET','/api/v1/gre/'.$id.'/'.$kind);
    $filename=(string)($data['filename']??''); $encoded=$data['content_base64']??null;
    $pattern=$kind==='xml'?'/^[0-9]{11}-09-T[A-Z0-9]{3}-[1-9][0-9]{0,7}\.xml$/D':'/^R-[0-9]{11}-09-T[A-Z0-9]{3}-[1-9][0-9]{0,7}\.zip$/D';
    if(!preg_match($pattern,$filename)||!is_string($encoded)||strlen($encoded)>8_000_000||($bytes=base64_decode($encoded,true))===false
        ||!hash_equals((string)($data['sha256']??''),hash('sha256',$bytes)))throw new RuntimeException('GRE artifact integrity validation failed');
    header('Content-Type: '.($kind==='xml'?'application/xml; charset=utf-8':'application/zip'));
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: '.strlen($bytes)); echo $bytes; exit;
} catch(Throwable $error) { app_gre_failure($error); }
