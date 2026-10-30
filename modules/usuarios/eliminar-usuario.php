<?php
require '../../global/connection.php';
$u_iduser = $_POST['user_id'];

if ((int) $u_iduser === (int) $_SESSION['loggedInUser']['USERID']) {
    echo false;
    exit;
}

$roleStatement = $pdo->prepare('SELECT role_id FROM tbl_user WHERE id = ?');
$roleStatement->execute([$u_iduser]);
$roleId = (int) $roleStatement->fetchColumn();
if ($roleId === 1) {
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_user WHERE role_id = 1 AND active_status = 1')->fetchColumn();
    if ($adminCount <= 1) {
        echo false;
        exit;
    }
}

$sqlStatement = $pdo->prepare("UPDATE tbl_user SET active_status = 0 WHERE id=:USERID");
$sqlStatement->bindParam("USERID", $u_iduser, PDO::PARAM_INT);
if ($sqlStatement) {
    $sqlStatement->execute();
    echo true;
} else {
    echo false;
}
