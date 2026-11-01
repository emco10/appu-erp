<?php

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/master-data-validation.php';
    require_once '../inventory/stock.php';
    require_once '../../global/document-history.php';
    require_once '../../global/sales-document-validation.php';
    require_once '../../global/sales-delivery.php';
    require_once '../../global/business-partner-details.php';
    require_once '../finanzas/sync.php';

    $f_faccod = "";
    $f_series = $_POST['facturacion_series'];

    $f_cliid = $_POST['facturacion_cliente'];
    $f_cliid = $f_cliid == "" ? 0 : $f_cliid;

    $f_cli_ruc = trim($_POST['facturacion_cliruc']);
    $f_clinom = trim($_POST['facturacion_valcliente']);
    $f_clidirecc = trim($_POST['facturacion_clidirecc']);
    $f_clirefer = trim($_POST['facturacion_clirefer']);

    $f_currency = $_POST['facturacion_tipmon'];
    $f_clifecentreg = date("Y-m-d", strtotime($_POST['facturacion_fecentrega']));
    $f_porcdesc = $_POST['facturacion_porcdesc'] == "" ? 0 : $_POST['facturacion_porcdesc'];
    $f_valdesc = $_POST['facturacion_cantdesc'];
    $f_diaspag = $_POST['facturacion_formpago'];

    $f_fecha = date("Y-m-d", strtotime($_POST['facturacion_fecha']));
    $f_subtotal = $_POST['facturacion_opergrab'];
    $f_totalneto = $_POST['facturacion_total'];
    $f_taxigv = $_POST['facturacion_igv'];
    $f_lst_prods = json_decode($_POST['facturacion_prods'] ?? '', true);
    $f_estado = $_POST["facturacion_estado"];
    $f_selectcotizid = $_POST["facturacion_listadocotiz"];
    $f_selectcotizid = $f_selectcotizid == "" ? 0 : $f_selectcotizid;
    $f_sales_order_id = filter_var($_POST['facturacion_orden_venta'] ?? null, FILTER_VALIDATE_INT);
    $f_sales_order_id = $f_sales_order_id === false || $f_sales_order_id <= 0 ? null : $f_sales_order_id;
    $f_warehouse_id = filter_var($_POST['facturacion_almacen_id'] ?? null, FILTER_VALIDATE_INT);
    $f_warehouse_id = $f_warehouse_id === false || $f_warehouse_id <= 0 ? 1 : (int) $f_warehouse_id;

    $f_seller_id = $_POST["facturacion_usuarioid"];
    $f_user_id = $_SESSION['loggedInUser']['USERID'];
    $id_facturacion = "";

    $f_faccod = $_POST["facturacion_nro"];

    $f_fecreg = date("Y-m-d H:i:s");

    $id_prod = "";
    $cod_prod = "";
    $nom_prod = "";
    $desc_prod = "";
    $prec_prod = 0;
    $import_prod = 0;
    $cant_prod = 0;
    
    try {
        $totals=app_validate_legacy_sales_input($pdo,$_POST,$f_lst_prods);
        $f_subtotal=$totals['subtotal'];$f_taxigv=$totals['tax'];$f_porcdesc=$totals['discount_rate'];$f_valdesc=$totals['discount'];$f_totalneto=$totals['total'];
        if(!in_array((int)$f_estado,[1,3],true))throw new InvalidArgumentException('La nueva boleta debe estar vigente o pendiente de pago.');
        if (!is_array($f_lst_prods) || count($f_lst_prods) === 0) {
            throw new InvalidArgumentException('La boleta debe contener al menos un producto.');
        }

        $f_cliid = app_validate_customer_reference($pdo, $f_cliid);
        app_validate_product_rows($pdo, $f_lst_prods);
        $pdo->beginTransaction();
        $f_faccod=app_validate_legacy_sales_number($pdo,'RECEIPT',(string)$f_series,(string)$f_faccod);
        $customerStatement=$pdo->prepare('SELECT ruc,business_name FROM tbl_customer WHERE client_id=? AND active_status=1');$customerStatement->execute([$f_cliid]);$customer=$customerStatement->fetch();
        $f_cli_ruc=(string)$customer['ruc'];$f_clinom=(string)$customer['business_name'];
        $buyerDocument=preg_replace('/\D+/', '', $f_cli_ruc);
        if ((float)$f_totalneto > 700 && (!in_array(strlen($buyerDocument), [8,11], true) || trim($f_clinom)==='')) {
            throw new DomainException('Para una boleta mayor a S/ 700 debes identificar al cliente con DNI o RUC y nombre o razon social.');
        }
        $salesOrder = null;
        $salesOrderWarehouseId = $f_warehouse_id;
        $billingAddress = app_customer_address_snapshot($pdo, (int) $f_cliid, null, 'billing');
        $shippingAddress = app_customer_address_snapshot($pdo, (int) $f_cliid, null, 'shipping');
        if ($f_selectcotizid > 0 && $f_sales_order_id === null) {
            app_validate_quotation_sale($pdo,(int)$f_selectcotizid,(string)$f_currency,$f_lst_prods,(float)$f_subtotal,(float)$f_taxigv,(float)$f_totalneto);
            $quotationStatement = $pdo->prepare('SELECT status, customer_id, billing_address_id, shipping_address_id, address, shipping_address FROM tbl_quotation WHERE id = ? FOR UPDATE');
            $quotationStatement->execute([$f_selectcotizid]);
            $quotation = $quotationStatement->fetch();
            if (!$quotation || (int) $quotation['status'] !== 1) {
                throw new DomainException('La cotizacion debe estar vigente.');
            }
            if ((int) $quotation['customer_id'] !== (int) $f_cliid) {
                throw new DomainException('El cliente no coincide con la cotizacion.');
            }
            $billingAddress = ['id'=>$quotation['billing_address_id'] ?: null, 'address_line'=>(string)$quotation['address']];
            $shippingAddress = ['id'=>$quotation['shipping_address_id'] ?: null, 'address_line'=>(string)($quotation['shipping_address'] ?: $quotation['address'])];
            if (app_quotation_has_active_sale($pdo,(int)$f_selectcotizid)) {
                throw new DomainException('La cotizacion ya fue convertida directamente en un documento de venta.');
            }
        }
        if ($f_sales_order_id !== null) {
            $salesOrder = app_validate_sales_order_document($pdo, $f_sales_order_id, (int)$f_cliid, $f_warehouse_id, (string)$f_currency, $f_lst_prods, (float)$f_subtotal, (float)$f_taxigv, (float)$f_totalneto);
            $salesOrderWarehouseId = (int) ($salesOrder['warehouse_id'] ?? $f_warehouse_id) > 0 ? (int) $salesOrder['warehouse_id'] : $f_warehouse_id;
            $f_selectcotizid = (int) ($salesOrder['quotation_id'] ?? 0);
            $f_cli_ruc = (string) $salesOrder['ruc'];
            $f_clinom = (string) $salesOrder['customer_name'];
            $f_clidirecc = (string) $salesOrder['address'];
            $billingAddress = ['id'=>$salesOrder['billing_address_id'] ?: null, 'address_line'=>(string)$salesOrder['address']];
            $shippingAddress = ['id'=>$salesOrder['shipping_address_id'] ?: null, 'address_line'=>(string)($salesOrder['shipping_address'] ?: $salesOrder['address'])];
        }
        $f_clidirecc = (string) $billingAddress['address_line'];
        $warehouseStatement = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id = ? AND active_status = 1');
        $warehouseStatement->execute([$f_warehouse_id]);
        if (!$warehouseStatement->fetchColumn()) {
            throw new DomainException('El almacen seleccionado no existe o esta inactivo.');
        }
        $sqlStatement = $pdo->prepare("INSERT INTO tbl_receipt (series,number,status,quotation_id,sales_order_id,customer_id,warehouse_id,billing_address_id,shipping_address_id,ruc,name,address,shipping_address,reference,payment_days,date,delivery_date,currency,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id,registration_date,last_update) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sqlStatement->execute([$f_series,$f_faccod, $f_estado, $f_selectcotizid, $f_sales_order_id, $f_cliid, $f_warehouse_id, $billingAddress['id'], $shippingAddress['id'], $f_cli_ruc, $f_clinom, $f_clidirecc, $shippingAddress['address_line'], $f_clirefer, $f_diaspag, $f_fecha, $f_clifecentreg, $f_currency, $f_porcdesc, $f_valdesc, $f_subtotal, $f_taxigv, $f_totalneto, $f_seller_id, $f_user_id, $f_fecreg, $f_fecreg]);
        $id_facturacion = (int) $pdo->lastInsertId();

        $insertDetail = $pdo->prepare('INSERT INTO tbl_receipt_detail (item_description,item_id,item_code,item_name,item_quantity,item_unit_price,receipt_id) VALUES (?,?,?,?,?,?,?)');
        $affectsStock = (int) $f_estado !== 2;
        if($affectsStock && $salesOrder!==null)app_release_order_for_billing($pdo,(int)$f_sales_order_id,$salesOrderWarehouseId);
        $documentReference = $f_series . '-' . $f_faccod;

        foreach ($f_lst_prods as $product) {
            $id_prod = filter_var($product[0] ?? null, FILTER_VALIDATE_INT);
            $cod_prod = trim((string) ($product[1] ?? ''));
            $nom_prod = trim((string) ($product[2] ?? ''));
            $desc_prod = trim((string) ($product[3] ?? ''));
            $prec_prod = filter_var($product[4] ?? null, FILTER_VALIDATE_FLOAT);
            $cant_prod = filter_var($product[5] ?? null, FILTER_VALIDATE_FLOAT);

            if ($id_prod === false || $prec_prod === false || $prec_prod < 0 || $cant_prod === false || $cant_prod <= 0) {
                throw new InvalidArgumentException('La boleta contiene un producto inválido.');
            }

            $insertDetail->execute([$desc_prod, $id_prod, $cod_prod, $nom_prod, $cant_prod, $prec_prod, $id_facturacion]);
            $receiptLineId=(int)$pdo->lastInsertId();
            if($salesOrder!==null)app_record_order_billing_line($pdo,(int)$f_sales_order_id,(int)$id_prod,'receipt',$id_facturacion,$receiptLineId,(float)$cant_prod,(int)$f_user_id);

            if ($affectsStock) {
                warehouse_apply_stock_delta(
                    $pdo,
                    (int) $id_prod,
                    -$cant_prod,
                    STOCK_MOVEMENT_SALE,
                    (int) $f_user_id,
                    'Salida por boleta',
                    'receipt',
                    $id_facturacion,
                    $documentReference,
                    null,
                    $f_warehouse_id,
                    null,
                    app_document_engine_available($pdo)?$receiptLineId:null
                );
            }
        }

        app_record_document_status($pdo, 'receipt', $id_facturacion, $documentReference, '', app_sales_status_label((int) $f_estado), 'Creacion', (int) $f_user_id);
        if ($f_selectcotizid > 0) { app_record_document_flow($pdo, 'quotation', (int) $f_selectcotizid, 'receipt', $id_facturacion, (int) $f_user_id, 'DIRECT_CONVERSION'); }
        if ($f_sales_order_id !== null) { app_record_document_flow($pdo, 'sales_order', (int) $f_sales_order_id, 'receipt', $id_facturacion, (int) $f_user_id, 'FULFILLMENT'); }
        if ($f_sales_order_id !== null) { app_copy_custom_fields($pdo, 'sales_order', (int) $f_sales_order_id, 'receipt', $id_facturacion); } elseif ($f_selectcotizid > 0) { app_copy_custom_fields($pdo, 'quotation', (int) $f_selectcotizid, 'receipt', $id_facturacion); }
        app_save_custom_fields($pdo, 'receipt', $id_facturacion, is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : []);

        if ($salesOrder !== null) {
            $pdo->prepare("UPDATE tbl_sales_order SET status = 'Atendida', last_update = NOW() WHERE id = ? AND status = 'Confirmada'")->execute([$f_sales_order_id]);
            app_record_document_status($pdo, 'sales_order', (int) $f_sales_order_id, (string) $salesOrder['number'], 'Confirmada', 'Atendida', 'Atencion', (int) $f_user_id, 'Documento de venta ' . $documentReference);
            if(app_document_engine_available($pdo))app_sync_sales_order_delivery_status($pdo,(int)$f_sales_order_id);
        }

        finance_sync_receipt($pdo, $id_facturacion);
        $pdo->commit();
        echo 'OK_INSERT';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('APPU receipt error: ' . $exception->getMessage());
        app_api_error($exception,'No se pudo registrar la boleta.');
    }
} else {
    echo "ERROR";
}
