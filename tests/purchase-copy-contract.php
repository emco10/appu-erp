<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$script = file_get_contents($root . '/ajax/orden-de-compra.js');
$view = file_get_contents($root . '/views/ordenes/orden-de-compra.php');
$endpoint = file_get_contents($root . '/modules/compras/solicitudes.php');
$handler = file_get_contents($root . '/modules/ordenes/insert-update-orden.php');
$required = [
    [$script, "on(\"select2:select\"", 'El selector de solicitud debe disparar la copia desde la selección explícita.'],
    [$script, 'modules/compras/solicitudes.php", { id: requestId }', 'La copia debe cargar la solicitud persistida.'],
    [$script, '9: Number(detail.product_id)', 'La fila copiada debe conservar el ID del artículo.'],
    [$script, 'window.appuMarkDirty()', 'La copia debe quedar protegida como cambio sin guardar.'],
    [$script, 'detaorden_json[i]["ITEM_ID"]', 'La consulta de órdenes debe recuperar el ID del artículo.'],
    [$view, '<th>ID artículo</th>', 'La tabla debe incluir la columna técnica oculta del artículo.'],
    [$endpoint, 'product.unit_value AS unit_value', 'La solicitud copiada debe conservar la unidad del artículo.'],
    [$handler, 'app_purchase_order_product_rows_from_ui($ord_lstprods)', 'El servidor debe normalizar las filas de compra antes de validarlas y guardarlas.'],
];
foreach ($required as [$source, $token, $message]) {
    if (strpos($source, $token) === false) {
        throw new RuntimeException($message);
    }
}

echo 'PASS purchase request copy-from contract' . PHP_EOL;
