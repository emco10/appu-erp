<?php
// Compatibilidad con formularios antiguos: las ordenes no se eliminan fisicamente.
// Se redirige al flujo auditado de anulacion para conservar trazabilidad.
if (!isset($_POST['ID_ORDEN'], $_POST['reason'])) {
    http_response_code(422);
    echo false;
    exit;
}
require __DIR__ . '/anular-orden.php';
