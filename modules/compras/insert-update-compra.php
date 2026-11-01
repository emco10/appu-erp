<?php
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/master-data-validation.php';
    require_once '../../global/document-history.php';
    require_once '../../global/document-numbering.php';
    require_once '../../global/purchase-document-validation.php';
    require_once '../../global/business-partner-details.php';
    require_once '../../global/purchase-receipt.php';

    $supplierReference = trim((string) ($_POST['orden_nro'] ?? ''));
    if ($supplierReference === '' || mb_strlen($supplierReference) > 100) {
        http_response_code(422);
        echo 'ERROR';
        exit;
    }
    $ord_tipo = $_POST['orden_tipo'];
    $ord_id = $_POST['orden_id'];
    $purchaseOrderId = filter_var($_POST['orden_compra_id'] ?? null, FILTER_VALIDATE_INT);
    $purchaseOrderId = $purchaseOrderId === false || $purchaseOrderId < 1 ? null : (int) $purchaseOrderId;
    $importReceiptId = filter_var($_POST['orden_import_receipt_id'] ?? null, FILTER_VALIDATE_INT);
    $importReceiptId = $importReceiptId === false || $importReceiptId < 1 ? null : (int) $importReceiptId;

    //$ord_rcompra = $_POST['orden_tccompra'];
    //$ord_rventa = $_POST['orden_tcventa'];
    $ord_rcompra = 0;
    $ord_rventa = 0;

    if($ord_rcompra == "") $ord_rcompra = 0;
    if($ord_rventa == "") $ord_rventa = 0;

    $ord_nrocuenta = trim($_POST['orden_nrocuenta']);
    $ord_approver = trim($_POST['orden_autorizador']);
    $ord_tipomon = $_POST['orden_tipomoneda'];
    $ord_emidate = date("Y-m-d", strtotime($_POST['orden_fecemision']));
    $ord_deldate = date("Y-m-d", strtotime($_POST['orden_fecentrega']));
    $ord_observ = trim($_POST['orden_observ']);
    $ord_daypay = $_POST['orden_tipopago'];
    $ord_provider = $_POST['orden_proveedor'];
    //$ord_cotiz = trim($_POST['orden_cotizacion']);
    $ord_cotiz = "";
    $ord_request = trim($_POST['orden_solicitante']);
    $ord_estado = 'Pendiente';
    $documentSubtype = strtoupper(trim((string)($_POST['orden_tipo_documento'] ?? 'STANDARD')));
    if (!in_array($documentSubtype, ['STANDARD','RESERVE'], true)) { http_response_code(422); echo 'ERROR'; exit; }
    $user_id = (int) $_SESSION['loggedInUser']['USERID'];
    $ord_totneto = $_POST['orden_totneto'];
    $ord_totcompra = $_POST['orden_totcompra'];
    $ord_totigv = $_POST['orden_igv'];

    $ord_lstprods = "";
    $ord_lstservicios = "";

    switch ($ord_tipo) {
        case 'COMPRA':
            $ord_tipo = 1;
            $importReceiptId = null;
            //$ord_code = "CI-";
            $ord_lstprods = $_POST['orden_lstprods'];
            $ord_lstprods = json_decode($ord_lstprods, true);
            break;
        case 'SERVICIO':
            $ord_tipo = 2;
            // Los servicios no tienen recepcion fisica; generan la cuenta por pagar al aprobarse.
            $documentSubtype = 'RESERVE';
            //$ord_code = "CS-";
            $ord_lstservicios = $_POST['orden_lstservicios'];
            $ord_lstservicios = json_decode($ord_lstservicios,true);
            break;
        default:
            http_response_code(422);
            echo 'ERROR';
            exit;
    }

    try {
        app_validate_purchase_input($_POST,$ord_tipo===1?(is_array($ord_lstprods)?$ord_lstprods:[]):(is_array($ord_lstservicios)?$ord_lstservicios:[]),$ord_tipo===2);
        $ord_provider = app_validate_provider_reference($pdo, $ord_provider);
        if (is_array($ord_lstprods)) {
            app_validate_product_rows($pdo, $ord_lstprods);
        }
        $pdo->beginTransaction();
        $providerAddress = app_provider_address_snapshot($pdo, (int) $ord_provider, filter_var($_POST['orden_direccion_proveedor_id'] ?? null, FILTER_VALIDATE_INT) ?: null);
        if ($importReceiptId !== null) {
            $importReceipt = $pdo->prepare("SELECT id FROM tbl_goods_receipt WHERE id=? AND status='Registrado' FOR UPDATE");
            $importReceipt->execute([$importReceiptId]);
            if (!$importReceipt->fetchColumn()) throw new DomainException('La recepcion de importacion seleccionada no existe o fue anulada.');
        }
        $duplicateReference = $pdo->prepare("SELECT id FROM tbl_purchase WHERE provider_id=? AND supplier_reference=? AND id<>? AND status NOT IN ('Anulado','Anulada','Cancelada') LIMIT 1 FOR UPDATE");
        $duplicateReference->execute([(int)$ord_provider,$supplierReference,$ord_id===''?0:(int)$ord_id]);
        if ($duplicateReference->fetchColumn()) throw new DomainException('La referencia del proveedor ya fue registrada para otra compra activa.');
        if ($purchaseOrderId !== null) {
            $purchaseOrder = app_validate_purchase_order_document($pdo,$purchaseOrderId,(int)$ord_provider,(string)$ord_tipomon,is_array($ord_lstprods)?$ord_lstprods:[],(float)$ord_totcompra,(float)$ord_totigv,(float)$ord_totneto,$ord_id===''?0:(int)$ord_id);
            $orderStatement = $pdo->prepare("SELECT id, number, provider_id, provider_address_id, provider_address, status FROM tbl_order WHERE id = ? AND type = 1 FOR UPDATE");
            $orderStatement->execute([$purchaseOrderId]);
            $purchaseOrder = $orderStatement->fetch();
            if (!$purchaseOrder || in_array($purchaseOrder['status'], ['Anulado', 'Anulada', 'Cancelada', 'Rechazado'], true)) {
                throw new RuntimeException('La orden de compra seleccionada no está disponible.');
            }
            if ((int) $purchaseOrder['provider_id'] !== (int) $ord_provider) {
                throw new RuntimeException('El proveedor de la compra debe coincidir con el proveedor de la orden.');
            }
            $providerAddress = ['id'=>$purchaseOrder['provider_address_id'] ?: null,'address_line'=>(string)$purchaseOrder['provider_address']];
            $linkedPurchase = $pdo->prepare("SELECT id FROM tbl_purchase WHERE purchase_order_id = ? AND id <> ? AND status NOT IN ('Anulado','Anulada','Cancelada') LIMIT 1");
            $linkedPurchase->execute([$purchaseOrderId, $ord_id === '' ? 0 : (int) $ord_id]);
            if ($linkedPurchase->fetchColumn()) {
                throw new RuntimeException('La orden de compra ya está vinculada a otra compra.');
            }
        }
        $response = '';
        $previous_status = null;
        $previousPurchaseOrderId = 0;

        if ($ord_id !== "" && $ord_id !== null) {
            $statusStatement = $pdo->prepare('SELECT number, status, purchase_order_id FROM tbl_purchase WHERE id = ? FOR UPDATE');
            $statusStatement->execute([$ord_id]);
            $existingPurchase = $statusStatement->fetch();
            $previous_status = $existingPurchase['status'] ?? false;
            $previousPurchaseOrderId = (int) ($existingPurchase['purchase_order_id'] ?? 0);
            $ord_code = (string) ($existingPurchase['number'] ?? '');

            if ($previous_status === false || !in_array($previous_status, ['Pendiente', 'Rechazado'], true)) {
                throw new RuntimeException('La compra ya no se puede modificar.');
            }
        }

        if ($previousPurchaseOrderId > 0 && $previousPurchaseOrderId !== (int) ($purchaseOrderId ?? 0)) {
            $otherLinkedPurchase = $pdo->prepare("SELECT COUNT(*) FROM tbl_purchase WHERE purchase_order_id = ? AND id <> ? AND status NOT IN ('Anulado', 'Cancelada')");
            $otherLinkedPurchase->execute([$previousPurchaseOrderId, (int) $ord_id]);
            if ((int) $otherLinkedPurchase->fetchColumn() === 0) {
                $oldOrder = $pdo->prepare('SELECT number, status FROM tbl_order WHERE id = ? FOR UPDATE');
                $oldOrder->execute([$previousPurchaseOrderId]);
                $oldOrderData = $oldOrder->fetch();
                if ($oldOrderData && $oldOrderData['status'] === 'Convertida') {
                    $pdo->prepare("UPDATE tbl_order SET status = 'Aprobado', last_update = NOW() WHERE id = ?")->execute([$previousPurchaseOrderId]);
                    app_record_document_status($pdo, 'order', $previousPurchaseOrderId, (string) $oldOrderData['number'], 'Convertida', 'Aprobado', 'Desvinculacion', $user_id, 'Compra desvinculada de la orden.');
                }
            }
        }

        if ($ord_id == "" || $ord_id == null) {
            $ord_code = app_next_document_number($pdo, 'PURCHASE', 'OC');
            $sqlStatement = $pdo->prepare("INSERT INTO tbl_purchase (type,number,supplier_reference,status,document_subtype,currency,issue_date,delivery_date,provider_id,provider_address_id,provider_address,purchase_order_id,import_goods_receipt_id,payment_days,account_number,quotation,requester,approver,created_by,submitted_at,observation,total_purchase,total_tax,total_net,exchange_rate_sale,exchange_rate_purchase) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?)");
            $sqlStatement->execute([$ord_tipo, $ord_code, $supplierReference, $ord_estado, $documentSubtype, $ord_tipomon, $ord_emidate, $ord_deldate, $ord_provider, $providerAddress['id'], $providerAddress['address_line'], $purchaseOrderId, $importReceiptId, $ord_daypay, $ord_nrocuenta, $ord_cotiz, $ord_request, '', $user_id, $ord_observ, $ord_totcompra, $ord_totigv, $ord_totneto, $ord_rventa, $ord_rcompra]);

            $ord_id = (int) $pdo->lastInsertId();
            app_record_document_status($pdo, 'purchase', $ord_id, $ord_code, '', 'Pendiente', 'Creacion', $user_id);
            $response = 'OK_INSERT';
        } else {
            $sqlStatement = $pdo->prepare("UPDATE tbl_purchase SET type=?,supplier_reference=?,status=?,document_subtype=?,currency=?,issue_date=?,delivery_date=?,provider_id=?,provider_address_id=?,provider_address=?,purchase_order_id=?,import_goods_receipt_id=?,payment_days=?,account_number=?,quotation=?,requester=?,approver='',submitted_at=NOW(),approved_by=NULL,approved_at=NULL,rejected_by=NULL,rejected_at=NULL,approval_comment='',observation=?,total_purchase=?,total_tax=?,total_net=?,exchange_rate_sale=?,exchange_rate_purchase=? WHERE id=?");
            $sqlStatement->execute([$ord_tipo, $supplierReference, $ord_estado, $documentSubtype, $ord_tipomon, $ord_emidate, $ord_deldate, $ord_provider, $providerAddress['id'], $providerAddress['address_line'], $purchaseOrderId, $importReceiptId, $ord_daypay, $ord_nrocuenta, $ord_cotiz, $ord_request, $ord_observ, $ord_totcompra, $ord_totigv, $ord_totneto, $ord_rventa, $ord_rcompra, $ord_id]);
            $response = 'OK_UPDATE';
        $sqlStatement = $pdo->prepare("DELETE FROM tbl_purchase_detail WHERE purchase_id=:IDORDER");
        $sqlStatement->bindParam("IDORDER", $ord_id, PDO::PARAM_INT);
        $sqlStatement->execute();

        if ($previous_status === 'Rechazado') {
            $historyStatement = $pdo->prepare("INSERT INTO tbl_approval_history(document_type,document_id,previous_status,new_status,comment,user_id) VALUES('purchase',?,'Rechazado','Pendiente','Documento corregido y reenviado',?)");
            $historyStatement->execute([$ord_id, $user_id]);
            app_record_document_status($pdo, 'purchase', (int) $ord_id, $ord_code, 'Rechazado', 'Pendiente', 'Reenvio', $user_id, 'Documento corregido y reenviado');
        }
        }

        if ($purchaseOrderId !== null) {
            $orderStatus = $pdo->prepare('SELECT number, status FROM tbl_order WHERE id = ? FOR UPDATE');
            $orderStatus->execute([$purchaseOrderId]);
            $orderData = $orderStatus->fetch();
            if ($orderData && $orderData['status'] !== 'Convertida') {
                $pdo->prepare("UPDATE tbl_order SET status = 'Convertida', last_update = NOW() WHERE id = ?")->execute([$purchaseOrderId]);
                app_record_document_status($pdo, 'order', $purchaseOrderId, (string) $orderData['number'], (string) $orderData['status'], 'Convertida', 'Conversion', $user_id, 'Orden vinculada a la compra ' . $ord_code . '.');
            }
            app_record_document_flow($pdo, 'order', $purchaseOrderId, 'purchase', (int) $ord_id, $user_id, 'ORDER_TO_PURCHASE');
            app_copy_custom_fields($pdo, 'order', $purchaseOrderId, 'purchase', (int) $ord_id);
        }

    $item_id = "";
    $item_nom = "";
    $item_code = "";
    $item_desc = "";
    $item_glosa = "";
    $item_unitval = "";
    $item_descpor = 0;
    $item_descval = 0;
    $item_cant = 0;
    $item_prec = 0;
    $item_total = 0;

        if ($ord_lstprods != "") {
        if (count($ord_lstprods) > 0) {
            foreach ($ord_lstprods as $key => $value) {
                foreach ($value as $k => $v) {
                    switch ($k) {
                        case "0":
                            $item_id = $v;
                            break;
                        case "1":
                            $item_code = $v;
                            break;
                        case "2":
                            $item_desc = $v;
                            break;
                        case "3":
                            $item_glosa = $v;
                            break;
                        case "4":
                            $item_unitval = $v;
                            break;
                        case "5":
                            $item_prec = $v;
                            break;
                        case "6":
                            $item_cant = $v;
                            break;
                        case "7":
                            $item_descpor = $v;
                            break;
                        case "8":
                            $item_descval = $v;
                            break;
                    }
                }

                if (filter_var($item_cant, FILTER_VALIDATE_INT) === false || (int) $item_cant <= 0
                    || !is_numeric($item_prec) || (float) $item_prec < 0
                    || !is_numeric($item_descpor) || !is_numeric($item_descval)) {
                    throw new UnexpectedValueException('Detalle de producto invalido.');
                }

                $sqlStatement = $pdo->prepare("INSERT INTO tbl_purchase_detail(purchase_id,item_id,item_code,item_description,item_gloss,item_unit_value,item_unit_price,item_quantity,item_discount_rate,item_discounted_total) VALUES(?,?,?,?,?,?,?,?,?,?)");

                $sqlStatement->execute([$ord_id, $item_id, $item_code, $item_desc, $item_glosa, $item_unitval, $item_prec, $item_cant, $item_descpor, $item_descval]);
                
            }
        }
    }

        if ($ord_lstservicios != "") {
        if (count($ord_lstservicios) > 0) {
            foreach ($ord_lstservicios as $key => $value) {
                foreach ($value as $k => $v) {
                    switch ($k) {
                        case "0":
                            $item_id = $v;
                            break;
                        case "1":
                            $item_desc = $v;
                            break;
                        case "2":
                            $item_glosa = $v;
                            break;
                        case "3":
                            $item_prec = $v;
                            break;
                        case "4":
                            $item_cant = $v;
                            break;
                        case "5":
                            $item_descpor = $v;
                            break;
                        case "6":
                            $item_descval = $v;
                            break;
                    }
                }

                if (filter_var($item_cant, FILTER_VALIDATE_INT) === false || (int) $item_cant <= 0
                    || !is_numeric($item_prec) || (float) $item_prec < 0
                    || !is_numeric($item_descpor) || !is_numeric($item_descval)) {
                    throw new UnexpectedValueException('Detalle de servicio invalido.');
                }

                $sqlStatement = $pdo->prepare("INSERT INTO tbl_purchase_detail(purchase_id,item_description,item_gloss,item_unit_price,item_quantity,item_discount_rate,item_discounted_total) VALUES(?,?,?,?,?,?,?)");

                $sqlStatement->execute([$ord_id, $item_desc, $item_glosa, $item_prec, $item_cant, $item_descpor, $item_descval]);
                
            }
        }
        }

        $customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];
        app_save_custom_fields($pdo, 'purchase', (int) $ord_id, $customValues);
        if($purchaseOrderId!==null)app_sync_purchase_order_receipt_status($pdo,$purchaseOrderId);

        $pdo->commit();
        echo $response;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('APPU purchase save error: ' . $exception->getMessage());
        http_response_code(422);
        echo 'ERROR';
    }

} else {
    echo "ERROR";
}
