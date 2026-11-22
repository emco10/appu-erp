<?php
require '../../global/connection.php';
$ID_COTIZ = $_POST["IDCOTIZ"];
$sqlStatement = $pdo->prepare("SELECT * FROM tbl_quotation_detail tqd INNER JOIN tbl_product tp ON tqd.item_id=tp.id  WHERE tqd.quotation_id =:COTIZID");
$sqlStatement->bindParam("COTIZID", $ID_COTIZ, PDO::PARAM_STR);
$sqlStatement->execute();
$rowsNumber = $sqlStatement->rowCount();
$json_data = array();
if ($rowsNumber > 0) {
    foreach ($sqlStatement as $ROW) {
        $ROWDATA['IDPROD'] = $ROW["item_id"];
        $ROWDATA["CODPROD"] = $ROW["code"];
        $ROWDATA['STOCKPROD'] = $ROW["stock_quantity"];
        $ROWDATA['NOMBRE'] = $ROW["item_name"];
        $ROWDATA['DESCRIP'] = $ROW["item_description"];
        $ROWDATA['PRECIOUNIT'] = $ROW["item_unit_price"];
        $ROWDATA['CANTIDAD'] = $ROW["item_quantity"];
        $ROWDATA['DESCUENTO_LINEA'] = $ROW["item_discount_rate"];
        $ROWDATA['IMPORTE'] = $ROW["item_discounted_total"];
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
