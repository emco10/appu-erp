<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$script = file_get_contents($root . '/ajax/orden-de-compra.js');
$view = file_get_contents($root . '/views/ordenes/orden-de-compra.php');
$endpoint = file_get_contents($root . '/modules/compras/solicitudes.php');
$handler = file_get_contents($root . '/modules/ordenes/insert-update-orden.php');
$requestList = file_get_contents($root . '/ajax/solicitudes.js');
$orderSummary = file_get_contents($root . '/ajax/resumen-ordenes.js');
$orderList = file_get_contents($root . '/modules/ordenes/consultar-orden.php');
$purchaseForm = file_get_contents($root . '/ajax/compra-interna.js');
$required = [
    [$script, "on(\"select2:select\"", 'El selector de solicitud debe disparar la copia desde la selección explícita.'],
    [$script, 'modules/compras/solicitudes.php", { id: requestId }', 'La copia debe cargar la solicitud persistida.'],
    [$script, '9: Number(detail.product_id)', 'La fila copiada debe conservar el ID del artículo.'],
    [$script, 'window.appuMarkDirty()', 'La copia debe quedar protegida como cambio sin guardar.'],
    [$script, 'query.get("copy_from") === "purchase_request"', 'La ruta debe abrir y copiar desde una solicitud aprobada seleccionada.'],
    [$requestList, 'copy_from=purchase_request&source_id=', 'La solicitud aprobada debe ofrecer una acción visible para copiar a una orden.'],
    [$view, 'Copiar de solicitud aprobada', 'La interfaz debe describir claramente el origen de la copia.'],
    [$script, 'detaorden_json[i]["ITEM_ID"]', 'La consulta de órdenes debe recuperar el ID del artículo.'],
    [$view, '<th>ID artículo</th>', 'La tabla debe incluir la columna técnica oculta del artículo.'],
    [$endpoint, 'product.unit_value AS unit_value', 'La solicitud copiada debe conservar la unidad del artículo.'],
    [$handler, 'app_purchase_order_product_rows_from_ui($ord_lstprods)', 'El servidor debe normalizar las filas de compra antes de validarlas y guardarlas.'],
    [$orderSummary, 'Copiar a entrada', 'La orden aprobada o parcial debe permitir copiar a entrada desde el resumen.'],
    [$orderSummary, 'copy_from=order&source_id=', 'La orden debe ofrecer una acción para copiar a factura de proveedor.'],
    [$orderList, 'AS LINKED_PURCHASE_COUNT', 'El resumen debe saber si la orden ya tiene una factura activa.'],
    [$purchaseForm, "query.get('copy_from') !== 'order'", 'La factura debe preseleccionar la orden recibida en la acción Copiar a.'],
];
foreach ($required as [$source, $token, $message]) {
    if (strpos($source, $token) === false) {
        throw new RuntimeException($message);
    }
}

echo 'PASS purchase request copy-from contract' . PHP_EOL;
