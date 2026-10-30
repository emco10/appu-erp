<?php
require '../../global/connection.php';
$c_idprov = $_POST['proveedor_id'];
$sqlStatement = $pdo->prepare("UPDATE tbl_provider SET active_status = 0 WHERE id=:PROVID");
$sqlStatement->bindParam("PROVID", $c_idprov, PDO::PARAM_INT);
if ($sqlStatement) {
    $sqlStatement->execute();
    echo true;
} else {
    echo false;
}
