<?php
// Compatibilidad con el endpoint legado: una cotizacion se anula para preservar la trazabilidad.
$_POST['ID_COTIZ'] = $_POST['ID_COTIZ'] ?? $_POST['id'] ?? null;
$_POST['reason'] = trim((string) ($_POST['reason'] ?? ''));
require __DIR__ . '/anular-cotizacion.php';
