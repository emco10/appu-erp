<?php
function app_purchase_order_product_rows_from_ui(array $rows): array
{
    foreach ($rows as &$row) {
        if (!is_array($row) || !array_key_exists(9, $row)) {
            throw new DomainException('Vuelve a seleccionar los artículos de la orden de compra.');
        }
        $productId = filter_var($row[9], FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            throw new DomainException('La orden contiene un artículo inválido.');
        }
        $row[0] = (int) $productId;
    }
    unset($row);
    return $rows;
}

function app_validate_purchase_request_conversion(PDO $pdo, int $requestId, array $postedRows, bool $existingLink = false): array
{
    $statement=$pdo->prepare('SELECT id,number,status FROM tbl_purchase_request WHERE id=? FOR UPDATE');$statement->execute([$requestId]);$request=$statement->fetch();
    if(!$request)throw new DomainException('La solicitud de compra no existe.');
    $allowed=$existingLink?['Aprobada','Convertida']:['Aprobada'];
    if(!in_array($request['status'],$allowed,true))throw new DomainException('La solicitud debe estar aprobada antes de convertirse en orden de compra.');
    $details=$pdo->prepare('SELECT product_id,SUM(quantity) quantity FROM tbl_purchase_request_detail WHERE request_id=? GROUP BY product_id');$details->execute([$requestId]);$expected=[];foreach($details->fetchAll() as $row)$expected[(int)$row['product_id']]=(float)$row['quantity'];
    $received=[];foreach($postedRows as $row){$productId=filter_var($row[0]??null,FILTER_VALIDATE_INT);$quantity=filter_var($row[6]??null,FILTER_VALIDATE_FLOAT);if($productId===false||$quantity===false||$quantity<=0||isset($received[(int)$productId]))throw new DomainException('El detalle de la orden no coincide con la solicitud aprobada.');$received[(int)$productId]=(float)$quantity;}
    ksort($expected);ksort($received);if(array_keys($expected)!==array_keys($received))throw new DomainException('Los articulos de la orden deben coincidir con la solicitud aprobada.');
    foreach($expected as $productId=>$quantity)if(abs($quantity-$received[$productId])>0.0001)throw new DomainException('Las cantidades de la orden deben coincidir con la solicitud aprobada.');
    return $request;
}
