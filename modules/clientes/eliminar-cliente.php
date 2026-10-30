<?php
require '../../global/connection.php';
$c_idcli = $_POST['cliente_id'];
$sqlStatement = $pdo->prepare("UPDATE tbl_customer SET active_status = 0 WHERE client_id=:CLIID");
$sqlStatement->bindParam("CLIID", $c_idcli, PDO::PARAM_INT);
if ($sqlStatement) {
    $sqlStatement->execute();
    echo true;
} else {
    echo false;
}
