<?php
// Alias heredado: el ingreso de existencias pertenece al documento de inventario,
// no al maestro del artículo. Así se evita mezclar proveedor, stock y vencimiento
// con la creación o edición del producto.
$inventoryDocumentType = 'ENTRY';
$page_name = 'actualizar-stock';
$page_script = 'actualizar-stock';
require __DIR__ . '/actualizar-stock.php';
