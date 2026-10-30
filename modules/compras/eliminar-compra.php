<?php
// Compatibilidad con el formulario antiguo: una compra nunca se elimina fisicamente.
$_POST['ID_ORDEN'] = $_POST['ID_ORDER'] ?? $_POST['ID_ORDEN'] ?? null;
$_POST['reason'] = trim((string) ($_POST['reason'] ?? ''));
require __DIR__ . '/anular-compra.php';
