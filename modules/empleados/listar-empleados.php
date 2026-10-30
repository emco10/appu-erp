<?php
require '../../global/connection.php';
$sqlStatement = $pdo->prepare("SELECT * FROM tbl_employee WHERE active_status = 1 ORDER BY name ASC");
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$DATA = array();
array_push($DATA, ["id"=>"","text"=>"Seleccione un empleado"]);
if ($rowsNumber > 0) {    
    while ($LST = $sqlStatement->fetch()) {
        $ID_EMP = $LST["id"];
        $NOM_EMP = $LST["name"];
        $ROW = [
            "id" => $ID_EMP,
            "text" => $NOM_EMP
        ];
        array_push($DATA, $ROW);
    }
}
echo json_encode($DATA);
