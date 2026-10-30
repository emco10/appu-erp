<?php
require '../../global/connection.php';
header('Content-Type: application/json; charset=utf-8');
$customerId=filter_var($_GET['customer_id']??null,FILTER_VALIDATE_INT);
if(!$customerId){http_response_code(422);echo json_encode(['error'=>true,'message'=>'Cliente inválido.']);exit;}
$statement=$pdo->prepare("SELECT id,address_type,address_code,address_name,address_line,country,department_id,province_id,district_id,city,reference,is_default FROM tbl_business_partner_address WHERE partner_type='customer' AND partner_id=? AND active_status=1 ORDER BY address_type,is_default DESC,address_name");
$statement->execute([$customerId]);
echo json_encode(['error'=>false,'addresses'=>$statement->fetchAll()],JSON_UNESCAPED_UNICODE);
