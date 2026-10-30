<?php
require '../../global/connection.php';
require_once '../../global/sales-delivery-billing.php';
require_once '../../global/document-flow-query.php';
header('Content-Type: application/json; charset=utf-8');
app_require_permission('sales.view');
try {
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if ($method==='GET' && ($_GET['action']??'')==='flow') {
        $type=is_string($_GET['type']??null)?$_GET['type']:'';$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT)?:0;
        app_require_permission(app_document_flow_permission($type));
        echo json_encode(app_query_document_flow($pdo,$type,$id,true),JSON_UNESCAPED_UNICODE);exit;
    }
    if ($method==='GET' && ($_GET['action']??'')==='delivery_billing_preview') {
        $ids=$_GET['guide_ids']??[]; if (!is_array($ids)) $ids=explode(',',(string)$ids);
        echo json_encode(app_delivery_billing_preview($pdo,$ids),JSON_UNESCAPED_UNICODE); exit;
    }
    if ($method!=='POST') { http_response_code(405); echo json_encode(['message'=>'Método no permitido.']); exit; }
    app_require_permission('sales.manage');
    if (($_POST['action']??'')!=='invoice_from_deliveries') throw new InvalidArgumentException('Acción documental no válida.');
    $pdo->beginTransaction();
    $userId=(int)$_SESSION['loggedInUser']['USERID'];
    $result=app_document_request_start($pdo,'invoice_from_deliveries',$_POST,$userId);
    if ($result===null) {
        $result=app_invoice_from_deliveries($pdo,$_POST,$userId);
        app_document_request_finish($pdo,'invoice_from_deliveries',$_POST,$userId,'invoice',(int)$result['invoice_id'],$result);
    }
    $pdo->commit(); http_response_code(201); echo json_encode($result,JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU document engine error: '.$error->getMessage());
    app_api_error($error,'No se pudo procesar el documento.');
}
