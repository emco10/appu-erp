<?php
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/master-data-validation.php';
    require_once '../../global/document-history.php';
    require_once '../../global/document-numbering.php';
    require_once '../../global/business-partner-details.php';
    require_once '../../global/sales-document-validation.php';

    $c_cotcod = "";
    
    $c_cliid = $_POST['cotizacion_cliente'];
    $c_cliid = $c_cliid==""?0:$c_cliid;

    $c_cli_ruc = trim($_POST['cotizacion_cliruc']);
    $c_clinom = trim($_POST['cotizacion_valcliente']);
    $c_clidirecc = trim($_POST['cotizacion_clidirecc']);
    $c_billing_address_id = filter_var($_POST['cotizacion_direccion_fiscal_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $c_shipping_address_id = filter_var($_POST['cotizacion_direccion_entrega_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $c_clirefer = trim($_POST['cotizacion_clirefer']);

    $c_currency = $_POST['cotizacion_tipmon'];
    $c_warehouse_id = filter_var($_POST['cotizacion_almacen_id'] ?? null, FILTER_VALIDATE_INT);
    $c_warehouse_id = $c_warehouse_id === false || $c_warehouse_id <= 0 ? 1 : (int) $c_warehouse_id;
    $c_clifecentreg = date("Y-m-d", strtotime($_POST['cotizacion_fecentrega']));
    $c_porcdesc = $_POST['cotizacion_porcdesc'] == "" ? 0 : $_POST['cotizacion_porcdesc'];
    $c_valdesc = $_POST['cotizacion_cantdesc'];
    $c_diaspag = $_POST['cotizacion_formpago'];
    
    $c_fecha = date("Y-m-d", strtotime($_POST['cotizacion_fecha']));
    $c_subtotal = $_POST['cotizacion_opergrab'];
    $c_totalneto = $_POST['cotizacion_total'];
    $c_taxigv = $_POST['cotizacion_igv'];
    $c_lst_prods = $_POST['cotiz_prods'];
    $c_lst_prods = json_decode($c_lst_prods, true);
    $c_estado = $_POST["cotizacion_estado"];

    $c_seller_id = $_POST["cotizacion_usuarioid"];
    $c_user_id = $_SESSION['loggedInUser']['USERID'];
    
    $cotizacion_id = $_POST["id_cotizacion"];

    $c_fecreg = date("Y-m-d H:i:s");
    try {
        $calculated=app_validate_legacy_sales_input($pdo,$_POST,$c_lst_prods,'cotizacion',true);
        $c_cliid = app_validate_customer_reference($pdo, $c_cliid);
        $customerStatement=$pdo->prepare('SELECT ruc,business_name FROM tbl_customer WHERE client_id=? AND active_status=1');$customerStatement->execute([$c_cliid]);$customer=$customerStatement->fetch();
        if(!$customer)throw new DomainException('El cliente seleccionado no existe o esta inactivo.');
        $c_cli_ruc=(string)$customer['ruc'];$c_clinom=(string)$customer['business_name'];
        $billingAddress = app_customer_address_snapshot($pdo, (int)$c_cliid, $c_billing_address_id, 'billing');
        $shippingAddress = app_customer_address_snapshot($pdo, (int)$c_cliid, $c_shipping_address_id, 'shipping');
        $c_billing_address_id = $billingAddress['id'] ? (int)$billingAddress['id'] : null;
        $c_shipping_address_id = $shippingAddress['id'] ? (int)$shippingAddress['id'] : null;
        $c_clidirecc = (string)$billingAddress['address_line'];
        $c_shipping_address = (string)$shippingAddress['address_line'];
        app_validate_product_rows($pdo, is_array($c_lst_prods) ? $c_lst_prods : []);
        $calculatedSubtotal=0.0;
        foreach($c_lst_prods as $row){$price=filter_var($row[3]??null,FILTER_VALIDATE_FLOAT);$quantity=filter_var($row[4]??null,FILTER_VALIDATE_FLOAT);if($price===false||$price<0||$quantity===false||$quantity<=0)throw new InvalidArgumentException('La cotizacion contiene precios o cantidades invalidas.');$calculatedSubtotal+=round((float)$price*(float)$quantity,2);}
        $c_porcdesc=filter_var($c_porcdesc,FILTER_VALIDATE_FLOAT);if($c_porcdesc===false||$c_porcdesc<0||$c_porcdesc>100)throw new InvalidArgumentException('El descuento debe estar entre 0 y 100 por ciento.');
        $c_subtotal=round($calculatedSubtotal,2);$c_taxigv=round($c_subtotal*0.18,2);$gross=round($c_subtotal+$c_taxigv,2);$c_valdesc=round($gross*((float)$c_porcdesc/100),2);$c_totalneto=round($gross-$c_valdesc,2);
    } catch (Throwable $exception) {
        app_api_error($exception,'No se pudo validar la cotización.');
        exit;
    }

    // Integridad: una cotizacion debe apuntar a un cliente y productos existentes.
    if ((int) $c_cliid < 1) {
        echo "ERROR";
        exit;
    }
    $customerCheck = $pdo->prepare("SELECT client_id FROM tbl_customer WHERE client_id = ? AND active_status = 1 LIMIT 1");
    $customerCheck->execute([(int) $c_cliid]);
    if (!$customerCheck->fetchColumn()) {
        echo "ERROR";
        exit;
    }
    if (!is_array($c_lst_prods) || count($c_lst_prods) === 0) {
        echo "ERROR";
        exit;
    }
    $productCheck = $pdo->prepare("SELECT id FROM tbl_product WHERE id = ? AND active_status = 1 LIMIT 1");
    foreach ($c_lst_prods as $productRow) {
        $productId = (int) (($productRow[0] ?? 0));
        if ($productId < 1) {
            echo "ERROR";
            exit;
        }
        $productCheck->execute([$productId]);
        if (!$productCheck->fetchColumn()) {
            echo "ERROR";
            exit;
        }
    }

    try {
    $pdo->beginTransaction();
    $warehouseCheck = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id = ? AND active_status = 1');
    $warehouseCheck->execute([$c_warehouse_id]);
    if (!$warehouseCheck->fetchColumn()) {
        throw new DomainException('El almacen seleccionado no existe o esta inactivo.');
    }
    $response = '';
    $previousStatus = null;
    $id_prod = "";
    $nom_prod = "";
    $desc_prod = "";
    $prec_prod = 0;
    $import_prod = 0;
    $cant_prod = 0;

    if ($cotizacion_id == "" || $cotizacion_id == null) {

        $c_cotcod = app_next_document_number($pdo, 'QUOTATION', 'COTZ');
        $c_estado = 1;
        

        $sqlStatement = $pdo->prepare("INSERT INTO tbl_quotation (number,status,customer_id,warehouse_id,billing_address_id,shipping_address_id,ruc,name,address,shipping_address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id,registration_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if ($sqlStatement) {
            $sqlStatement->execute([$c_cotcod, $c_estado, $c_cliid, $c_warehouse_id, $c_billing_address_id, $c_shipping_address_id, $c_cli_ruc, $c_clinom, $c_clidirecc, $c_shipping_address, $c_clirefer, $c_diaspag, $c_fecha, $c_clifecentreg, $c_currency, $c_porcdesc, $c_valdesc, $c_subtotal, $c_taxigv, $c_totalneto, $c_seller_id, $c_user_id, $c_fecreg]);
            $cotizacion_id = (int) $pdo->lastInsertId();
            $response = "OK_INSERT";
        } else {
            throw new RuntimeException('No se pudo preparar la cotizacion.');
        }
    } else {
        $existingStatement = $pdo->prepare('SELECT number, status FROM tbl_quotation WHERE id = ? FOR UPDATE');
        $existingStatement->execute([(int) $cotizacion_id]);
        $existingQuotation = $existingStatement->fetch();
        if (!$existingQuotation) {
            throw new RuntimeException('La cotizacion no existe.');
        }
        $c_cotcod = (string) $existingQuotation['number'];
        $previousStatus = (int) $existingQuotation['status'];
        if ($previousStatus === 2) {
            throw new RuntimeException('La cotizacion anulada no se puede modificar.');
        }
        $derivedStatement=$pdo->prepare("SELECT COUNT(*) FROM tbl_sales_order WHERE quotation_id=? AND status<>'Anulada'");$derivedStatement->execute([(int)$cotizacion_id]);
        if((int)$derivedStatement->fetchColumn()>0)throw new DomainException('La cotizacion tiene una orden de venta activa y ya no se puede modificar.');
        if(app_quotation_has_active_sale($pdo,(int)$cotizacion_id))throw new DomainException('La cotización tiene un documento de venta activo y ya no se puede modificar.');
        $c_estado=$previousStatus;
        $sqlStatement = $pdo->prepare("UPDATE tbl_quotation SET status=?,customer_id=?,warehouse_id=?,billing_address_id=?,shipping_address_id=?,ruc=?,name=?,address=?,shipping_address=?,reference=?,payment_days=?,date=?,delivery_date=?,currency=?,discount_rate=?,discount_value=?,total_sub=?,total_tax=?,total_net=?,seller_id=?,user_id=?,last_update=? WHERE id=?");
        if ($sqlStatement) {
            $sqlStatement->execute([$c_estado, $c_cliid, $c_warehouse_id, $c_billing_address_id, $c_shipping_address_id, $c_cli_ruc, $c_clinom, $c_clidirecc, $c_shipping_address, $c_clirefer, $c_diaspag, $c_fecha, $c_clifecentreg, $c_currency, $c_porcdesc, $c_valdesc, $c_subtotal, $c_taxigv, $c_totalneto, $c_seller_id, $c_user_id, $c_fecreg, $cotizacion_id]);
            $response = "OK_UPDATE";
        } else {
            throw new RuntimeException('No se pudo preparar la actualizacion de cotizacion.');
        }
        $sqlStatement = $pdo->prepare("DELETE FROM tbl_quotation_detail WHERE quotation_id=:IDCOTIZ");
        $sqlStatement->bindParam("IDCOTIZ", $cotizacion_id, PDO::PARAM_INT);
        $sqlStatement->execute();
    }

    if ($c_lst_prods != "") {
        if (count($c_lst_prods) > 0) {
            foreach ($c_lst_prods as $key => $value) {
                foreach ($value as $k => $v) {
                    switch ($k) {
                        case "0":
                            $id_prod = $v;
                            break;
                        case "1":
                            $nom_prod = $v;
                            break;
                        case "2":
                            $desc_prod = $v;
                            break;
                        case "3":
                            $prec_prod = $v;
                            break;
                        case "4":
                            $cant_prod = $v;
                            break;
                        case "5":
                            $import_prod = $v;
                            break;
                    }
                }
                $sqlStatement = $pdo->prepare("INSERT INTO tbl_quotation_detail (item_description,item_id,item_name,item_quantity,item_unit_price,quotation_id) VALUES (?,?,?,?,?,?)");
                $sqlStatement->execute([$desc_prod, $id_prod, $nom_prod, $cant_prod, $prec_prod, $cotizacion_id]);
            }
        }
    }
    $customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];
    app_save_custom_fields($pdo, 'quotation', (int) $cotizacion_id, $customValues);
    app_record_document_status(
        $pdo,
        'quotation',
        (int) $cotizacion_id,
        (string) $c_cotcod,
        $previousStatus === null ? '' : app_sales_status_label($previousStatus),
        app_sales_status_label((int) $c_estado),
        $previousStatus === null ? 'Creacion' : 'Actualizacion',
        $c_user_id
    );
    $pdo->commit();
    echo $response;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('APPU quotation save error: ' . $exception->getMessage());
        app_api_error($exception,'No se pudo guardar la cotización.');
    }
} else {
    echo "ERROR";
}
