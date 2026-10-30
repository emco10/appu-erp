<?php
require __DIR__.'/../../global/connection.php';
require_once __DIR__.'/../../global/document-flow-query.php';
header('Content-Type: application/json; charset=utf-8');
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['message'=>'Método no permitido.']);exit;}
    $type=is_string($_GET['type']??null)?$_GET['type']:'';
    app_require_permission(app_document_flow_permission($type));
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT)?:0;
    echo json_encode(app_query_document_flow($pdo,$type,$id,true),JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){app_api_error($error,'No se pudo consultar el flujo documental.');}
