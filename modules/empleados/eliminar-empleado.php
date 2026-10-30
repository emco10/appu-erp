<?php
require '../../global/connection.php';
$c_idemp = $_POST['empleado_id'];
$userStatement = $pdo->prepare('SELECT COUNT(*) FROM tbl_user WHERE employee_id = ? AND active_status = 1');
$userStatement->execute([(int) $c_idemp]);
if ((int) $userStatement->fetchColumn() > 0) {
    http_response_code(409);
    echo 'EMPLOYEE_HAS_ACTIVE_USER';
    exit;
}
$sqlStatement = $pdo->prepare("UPDATE tbl_employee SET active_status = 0 WHERE id=:EMPID");
$sqlStatement->bindParam("EMPID", $c_idemp, PDO::PARAM_INT);
if ($sqlStatement) {
    $sqlStatement->execute();
    echo true;
} else {
    echo false;
}
