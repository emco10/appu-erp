<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$providerScript = file_get_contents($root . '/ajax/registro-proveedor.js');
$quotationScript = file_get_contents($root . '/ajax/registro-cotizacion.js');
$guideView = file_get_contents($root . '/views/logistica/guias.php');
$footer = file_get_contents($root . '/views/template/footer.php');
$dismissViews = [
    '/views/compras/resumen-compras.php',
    '/views/cotizacion/registro-cotizacion.php',
    '/views/finanzas/cuentas.php',
    '/views/finanzas/cuentas-por-cobrar.php',
    '/views/finanzas/cuentas-por-pagar.php',
    '/views/logistica/documentos.php',
    '/views/logistica/guias.php',
    '/views/ventas/resumen-ordenes-venta.php',
];
$masterScript = file_get_contents($root . '/js/legacy-master-workspace.js');
if (!str_contains($providerScript, 'appuLegacyMasterWorkspace')
    || !str_contains($providerScript, "label:'proveedor'")
    || !str_contains($providerScript, "deleteEndpoint:'../../modules/proveedores/eliminar-proveedor.php'")
    || !str_contains($masterScript, 'currentCode=options.prefix')
    || !str_contains($masterScript, "dt.rows({search:'applied',order:'applied'}).data().toArray()")) {
    throw new RuntimeException('Proveedores debe utilizar el ciclo común, su etiqueta y la navegación por identidad de datos.');
}

if (strpos($quotationScript, 'if (typeof window.appuMarkDirty === "function") window.appuMarkDirty();') === false) {
    throw new RuntimeException('Elegir un cliente debe marcar la cotización como modificada.');
}
if (strpos($quotationScript, 'No se pudo recuperar el cliente seleccionado.') === false) {
    throw new RuntimeException('La selección de cliente debe informar errores de consulta.');
}

foreach ($dismissViews as $view) {
    $contents = file_get_contents($root . $view);
    if (strpos($contents, 'data-dismiss="modal"') !== false) {
        throw new RuntimeException('Los modales deben usar el atributo de cierre compatible con Bootstrap 5: ' . $view);
    }
}
if (strpos($guideView, 'modal-dialog-scrollable') === false
    || strpos($guideView, 'data-bs-dismiss="modal"') === false
    || strpos($footer, 'click.appuModalDismiss') === false
    || strpos($footer, 'bootstrap.Modal.getOrCreateInstance(modal).hide();') === false) {
    throw new RuntimeException('Los controles de cierre deben cerrar los modales de Bootstrap 5.');
}

echo 'PASS provider and modal action contracts' . PHP_EOL;
