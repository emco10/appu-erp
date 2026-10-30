<?php

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400); echo 'ERROR'; exit;
}

require '../../global/connection.php';
require_once '../../global/document-history.php';
require_once '../../global/product-master.php';
require_once '../../global/document-engine.php';

$jsonResponse = ($_POST['response_format'] ?? '') === 'json';

$productId = filter_var($_POST['producto_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$code = strtoupper(trim((string) ($_POST['producto_code'] ?? '')));
$name = trim((string) ($_POST['producto_nombre'] ?? ''));
$description = trim((string) ($_POST['producto_description'] ?? ''));
$brand = trim((string) ($_POST['producto_marca'] ?? ''));
$unitValue = trim((string) ($_POST['producto_unitvalue'] ?? ''));
$price = filter_var($_POST['producto_precio'] ?? null, FILTER_VALIDATE_FLOAT);
$barcode = trim((string) ($_POST['producto_barcode'] ?? ''));
$sunatCode = trim((string) ($_POST['producto_sunat_code'] ?? ''));
$sunatUnit = trim((string) ($_POST['producto_sunat_unit'] ?? ''));
$taxCode = trim((string) ($_POST['producto_tax_code'] ?? ''));
$osceCode = trim((string) ($_POST['producto_osce_code'] ?? ''));
$manufacturerCode = trim((string) ($_POST['producto_manufacturer_code'] ?? ''));
$model = trim((string) ($_POST['producto_model'] ?? ''));
$active = isset($_POST['producto_estado']) && $_POST['producto_estado'] === 'on' ? 0 : 1;
$customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];

try {
    $errors=app_product_master_errors($pdo,$_POST);
    if ($errors) throw new AppValidationException($errors);

    $pdo->beginTransaction();
    $userId=(int)($_SESSION['loggedInUser']['USERID']??0);
    $replay=app_document_request_start($pdo,'product_master_save',$_POST,$userId);
    if ($replay!==null) {
        $pdo->commit(); header('Content-Type: application/json; charset=utf-8');
        echo json_encode($replay,JSON_UNESCAPED_UNICODE); return;
    }
    if ($productId) {
        $snapshot=app_product_master_snapshot($pdo,$productId,true);
        if ($jsonResponse && empty($_POST['version'])) throw new AppValidationException([app_field_error('version','REQUIRED','Vuelve a consultar el artículo antes de editarlo.')]);
        if (isset($_POST['version']) && !hash_equals($snapshot['version'],(string)$_POST['version']))
            throw new DomainException('Otro usuario modificó este artículo. Consulta nuevamente la ficha antes de editar.',409);
        if ($jsonResponse) {
            foreach (app_custom_field_definitions($pdo,'product') as $definition) {
                if ((int)($definition['visible']??1)===0 && array_key_exists($definition['id'],$snapshot['custom_fields']))
                    $customValues[$definition['id']]=$snapshot['custom_fields'][$definition['id']];
            }
            app_validate_custom_fields($pdo,'product',$customValues);
        }
    }
    $duplicate = $pdo->prepare("SELECT code,name,barcode FROM tbl_product WHERE (code=? OR name=? OR (?<>'' AND barcode=?)) AND (? IS NULL OR id<>?)");
    $duplicate->execute([$code,$name,$barcode,$barcode,$productId,$productId]);
    $duplicates=$duplicate->fetchAll();
    if ($duplicates) {
        if (!$jsonResponse) { $pdo->rollBack(); echo 'EXISTE'; return; }
        $errors=[];
        foreach (['producto_code'=>[$code,'code','Código'], 'producto_nombre'=>[$name,'name','Nombre'], 'producto_barcode'=>[$barcode,'barcode','Código de barras']] as $path=>$field) {
            if ($field[0]==='') continue;
            $check=$pdo->prepare('SELECT id FROM tbl_product WHERE '.$field[1].'=? AND (? IS NULL OR id<>?) LIMIT 1');
            $check->execute([$field[0],$productId,$productId]);
            if ($check->fetchColumn()) $errors[]=app_field_error($path,'UNIQUE',$field[2].' ya está registrado en otro artículo.');
        }
        throw new AppValidationException($errors ?: [app_field_error('producto_code','UNIQUE','Ya existe un artículo con estos datos.')]);
    }

    $values = [$code,$brand,$name,$description,$price,$unitValue,$active,$barcode,$sunatCode,$sunatUnit,$taxCode,$osceCode,$manufacturerCode,$model];
    if (!$productId) {
        $statement = $pdo->prepare('INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status,barcode,sunat_item_code,sunat_unit_code,tax_affectation_code,osce_catalog_code,manufacturer_code,model) VALUES(?,?,?,?,0,?,?,?,?,?,?,?,?,?,?)');
        $statement->execute($values);
        $productId = (int) $pdo->lastInsertId();
        $result = 'OK_INSERT';
    } else {
        $exists = $pdo->prepare('SELECT id FROM tbl_product WHERE id=? LIMIT 1');
        $exists->execute([$productId]);
        if (!$exists->fetchColumn()) throw new InvalidArgumentException('El artículo que intentas editar ya no existe.');
        $statement = $pdo->prepare('UPDATE tbl_product SET code=?,brand=?,name=?,description=?,unit_price=?,unit_value=?,active_status=?,barcode=?,sunat_item_code=?,sunat_unit_code=?,tax_affectation_code=?,osce_catalog_code=?,manufacturer_code=?,model=? WHERE id=?');
        $statement->execute(array_merge($values, [$productId]));
        $result = 'OK_UPDATE';
    }
    app_save_custom_fields($pdo, 'product', $productId, $customValues, $jsonResponse);
    $snapshot=$jsonResponse ? app_product_master_snapshot($pdo,$productId) : null;
    $response=['success'=>true,'id'=>$productId,'result'=>$result,'snapshot'=>$snapshot];
    app_document_request_finish($pdo,'product_master_save',$_POST,$userId,'product',$productId,$response);
    $pdo->commit();
    if ($jsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response,JSON_UNESCAPED_UNICODE);
    } else echo $result;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU product save error: ' . $exception->getMessage());
    if ($jsonResponse) app_api_error($exception,'No se pudo guardar el artículo.');
    else { http_response_code($exception instanceof InvalidArgumentException ? 422 : 500); echo 'ERROR'; }
}
