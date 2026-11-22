<?php
require '../../global/connection.php';
$FAC_ID = $_POST["FAC_ID"];
$sqlStatement = $pdo->prepare("SELECT * FROM tbl_credit_note_detail WHERE credit_note_id =:FACID");
$sqlStatement->bindParam("FACID", $FAC_ID, PDO::PARAM_STR);
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$json_data = array();
if ($rowsNumber > 0) {
    foreach ($sqlStatement as $ROW) {
        $ROWDATA['IDPROD'] = $ROW["item_id"];
        $ROWDATA["CODPROD"] = $ROW["item_code"];
        $ROWDATA['STOCKPROD'] = $ROW["item_quantity"];
        $ROWDATA['NOMBRE'] = $ROW["item_name"];
        $ROWDATA['DESCRIP'] = $ROW["item_description"];
        $ROWDATA['PRECIOUNIT'] = $ROW["item_unit_price"];
        $ROWDATA['CANTIDAD'] = $ROW["item_quantity"];
        $ROWDATA['IMPORTE'] = $ROW["item_discounted_total"];
        $ROWDATA['DESCUENTO_LINEA'] = $ROW["item_discount_rate"];
        $ROWDATA['PROYECTO'] = $ROW["project_code"];
        $ROWDATA['CENTRO_COSTO'] = $ROW["cost_center_code"];
        $ROWDATA['LOTE'] = $ROW["lot_number"];
        $ROWDATA['SERIE'] = $ROW["serial_number"];
        $ROWDATA['VENCIMIENTO'] = $ROW["expiration_date"];
        array_push($json_data, $ROWDATA);
    }
}
//echo json_encode(array("data" => $json_data));
echo json_encode($json_data);
