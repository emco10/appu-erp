<?php
require '../../global/connection.php';
$FILTER_PROD = $_POST["FILTER"];
$ESTADO_PROD = $_POST["ESTADO"];
$warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
$warehouseId = $warehouseId === false || $warehouseId <= 0 ? null : (int) $warehouseId;

if ($ESTADO_PROD !== 'ALL') {
    $ESTADO_PROD = filter_var($ESTADO_PROD, FILTER_VALIDATE_INT);
    if ($ESTADO_PROD === false) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }
}

if ($FILTER_PROD == "ALL") {

    $sqlquery_adic = "";
    if($ESTADO_PROD != "ALL"){
        $sqlquery_adic = " WHERE tp.active_status = $ESTADO_PROD ";
    }

    $sqlStatement = $pdo->prepare("SELECT tp.id AS IDPROD, tp.code AS CODE, tp.description AS DESCPROD, tp.name AS NOMPROD, tp.brand AS MARCA, tp.stock_quantity AS CANTIDAD, ROUND(tp.unit_price,2) AS PRECIO, tp.unit_value AS VALORMEDIDA, tp.registration_date AS FECREG, tp.active_status AS ESTADO FROM tbl_product tp $sqlquery_adic ORDER BY tp.id DESC");
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();
    if ($rowsNumber > 0) {        
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['ID'] = $ROW["IDPROD"];
            $ROWDATA['CODIGO'] = $ROW["CODE"];
            $ROWDATA['NOMBRE'] = $ROW["NOMPROD"];
            $ROWDATA['DESCPROD'] = $ROW["DESCPROD"];
            $ROWDATA['MARCA'] = $ROW["MARCA"];
            $ROWDATA['CANTIDAD'] = $ROW["CANTIDAD"];
            $ROWDATA['PRECIO'] = $ROW["PRECIO"];
            $ROWDATA['VALORMEDIDA'] = $ROW["VALORMEDIDA"];

            if ($ROW["VALORMEDIDA"] == ""){
                $ROWDATA['VALORMEDIDA'] = "-";
            }else{
                $ROWDATA['VALORMEDIDA'] = $ROW["VALORMEDIDA"];
            }

            $ROWDATA['FECREG'] = date("d/m/Y H:i",strtotime($ROW["FECREG"]));
            $ROWDATA['ESTADO'] = $ROW["ESTADO"]==1?"Activo":"Inactivo";
            
            array_push($json_data, $ROWDATA);
        }        
    }
    echo json_encode(array("data" => $json_data));
} else {
    $ID_REAL = str_replace("PROD-","",$FILTER_PROD);

    $sqlquery_adic = "";
    if($ESTADO_PROD != "ALL"){
        $sqlquery_adic = " AND active_status = $ESTADO_PROD";
    }

    $sqlStatement = $pdo->prepare("SELECT * FROM tbl_product
        WHERE id=:PRODID $sqlquery_adic");
    $sqlStatement->bindParam("PRODID", $ID_REAL, PDO::PARAM_INT);
    $sqlStatement->execute();
    $rowsNumber = $sqlStatement->rowCount();
    $json_data = array();

    if ($rowsNumber > 0) {        
        foreach ($sqlStatement as $ROW) {
            $ROWDATA['CODIGO'] = $ROW["id"];
            $ROWDATA['CODPROD'] = $ROW["code"];
            $ROWDATA['DESCRIPTION'] = $ROW["description"];
            $ROWDATA['NOMBRE'] = $ROW["name"];
            $ROWDATA['MARCA'] = $ROW["brand"];
            $ROWDATA['CANTIDAD'] = $ROW["stock_quantity"];
            if ($warehouseId !== null) {
                $warehouseStock = $pdo->prepare('SELECT quantity FROM tbl_warehouse_stock WHERE warehouse_id = ? AND product_id = ? LIMIT 1');
                $warehouseStock->execute([$warehouseId, (int) $ROW['id']]);
                $warehouseQuantity = $warehouseStock->fetchColumn();
                $ROWDATA['CANTIDAD'] = $warehouseQuantity === false && $warehouseId === 1 ? $ROW['stock_quantity'] : ($warehouseQuantity === false ? 0 : $warehouseQuantity);
            }
            $ROWDATA['UNITVALUE'] = $ROW["unit_value"];
            $ROWDATA['PRECIO'] = $ROW["unit_price"];
            $ROWDATA['BARCODE'] = $ROW["barcode"];
            $ROWDATA['SUNAT_CODE'] = $ROW["sunat_item_code"];
            $ROWDATA['SUNAT_UNIT'] = $ROW["sunat_unit_code"];
            $ROWDATA['TAX_CODE'] = $ROW["tax_affectation_code"];
            $ROWDATA['OSCE_CODE'] = $ROW["osce_catalog_code"];
            $ROWDATA['MANUFACTURER_CODE'] = $ROW["manufacturer_code"];
            $ROWDATA['MODEL'] = $ROW["model"];
            $ROWDATA['ESTADO'] = $ROW["active_status"]==1?false:true;
            $ROWDATA['BARCODE'] = $ROW["barcode"];
            $ROWDATA['SUNAT_CODE'] = $ROW["sunat_item_code"];
            $ROWDATA['SUNAT_UNIT'] = $ROW["sunat_unit_code"];
            $ROWDATA['TAX_CODE'] = $ROW["tax_affectation_code"];
            $ROWDATA['OSCE_CODE'] = $ROW["osce_catalog_code"];
            $ROWDATA['MANUFACTURER_CODE'] = $ROW["manufacturer_code"];
            $ROWDATA['MODEL'] = $ROW["model"];
            $customStatement = $pdo->prepare('SELECT field_definition_id, field_value FROM tbl_custom_field_value WHERE entity = ? AND record_id = ?');
            $customStatement->execute(['product', (int) $ROW['id']]);
            $ROWDATA['CUSTOM_FIELDS'] = [];
            foreach ($customStatement->fetchAll() as $customValue) { $ROWDATA['CUSTOM_FIELDS'][(string) $customValue['field_definition_id']] = $customValue['field_value']; }
            array_push($json_data, $ROWDATA);
        }        
    }
    echo json_encode($json_data);
}
