<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(getenv('APPU_DB_NAME')!=='db_appu_qa')throw new RuntimeException('Use db_appu_qa only.');
require __DIR__.'/../global/document-numbering.php';
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=db_appu_qa;charset=utf8mb4',getenv('APPU_DB_USER')?:'root',getenv('APPU_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->beginTransaction();
try{
    do{$series='T'.strtoupper(substr(bin2hex(random_bytes(3)),0,3));$q=$pdo->prepare("SELECT COUNT(*) FROM tbl_document_series WHERE document_type='REMISSION_GUIDE' AND series=?");$q->execute([$series]);}while($q->fetchColumn());
    $pdo->prepare("INSERT INTO tbl_document_series(document_type,series,next_number,number_padding,active_status) VALUES('REMISSION_GUIDE',?,37,7,1)")->execute([$series]);
    if(app_next_document_number($pdo,'REMISSION_GUIDE','GI',$series)!==$series.'-0000037')throw new RuntimeException('An explicitly selected GRE series must not use the older internal series.');
    if(app_next_document_number($pdo,'REMISSION_GUIDE','GI',$series)!==$series.'-0000038')throw new RuntimeException('The selected series did not advance.');
    try{app_next_document_number($pdo,'REMISSION_GUIDE','GI','NONEXISTENT');throw new RuntimeException('A missing selected series silently fell back.');}catch(DomainException){}
    $pdo->prepare("UPDATE tbl_document_series SET active_status=0 WHERE document_type='REMISSION_GUIDE' AND series=?")->execute([$series]);
    try{app_next_document_number($pdo,'REMISSION_GUIDE','GI',$series);throw new RuntimeException('An inactive selected series was used.');}catch(DomainException){}
    echo "PASS GRE explicit series allocation, increment, inactive/missing rejection; all changes rolled back\n";
}finally{$pdo->rollBack();}
