<?php
require '../../global/connection.php';
require_once '../../global/product-master.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $action=(string)($_GET['action']??'search');
    if ($action==='catalog') {
        $result=['custom_fields'=>app_custom_field_definitions($pdo,'product')];
    } elseif ($action==='get') {
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
        if (!$id || $id<1) throw new InvalidArgumentException('Selecciona un artículo válido.');
        $pdo->beginTransaction();
        // Consistent master + UDF snapshot, without changing stock.
        $result=app_product_master_snapshot($pdo,$id);
        $result['definitions']=app_custom_field_definitions($pdo,'product');
        $pdo->commit();
    } elseif ($action==='search') {
        $result=app_product_master_search($pdo,$_GET);
    } else throw new InvalidArgumentException('Acción de consulta inválida.');
    echo json_encode($result,JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU product master read: '.$error->getMessage());
    app_api_error($error,'No se pudo consultar el maestro de artículos.');
}
