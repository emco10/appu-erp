<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
$providerId=filter_var($_GET['provider_id']??null,FILTER_VALIDATE_INT);
if(!$providerId){http_response_code(422);echo json_encode(['error'=>true,'message'=>'Proveedor invalido.']);exit;}
$statement=$pdo->prepare("SELECT id,address_type,address_code,address_name,address_line,country,department_id,province_id,district_id,city,reference,is_default FROM tbl_business_partner_address WHERE partner_type='provider' AND partner_id=? AND active_status=1 ORDER BY is_default DESC,address_type,address_name,id");
$statement->execute([$providerId]);
$addresses=$statement->fetchAll();
if(!$addresses){$fallback=$pdo->prepare('SELECT address,country,city,district FROM tbl_provider WHERE id=? AND active_status=1');$fallback->execute([$providerId]);$master=$fallback->fetch();if($master){$addresses[]=['id'=>0,'address_type'=>'billing','address_code'=>'MASTER','address_name'=>'Dirección principal','address_line'=>(string)$master['address'],'country'=>(string)$master['country'],'department_id'=>'','province_id'=>'','district_id'=>'','city'=>(string)$master['city'],'reference'=>'','is_default'=>1];}}
echo json_encode(['error'=>false,'addresses'=>$addresses],JSON_UNESCAPED_UNICODE);
