<?php
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/document-history.php';
    require_once '../../global/master-data-validation.php';
    require_once '../../global/document-numbering.php';
    require_once '../../global/business-partner-details.php';
    require_once '../../global/purchase-request-validation.php';
    require_once '../../global/purchase-document-validation.php';

    $ord_code = "";
    $ord_tipo = $_POST['orden_tipo'];
    $ord_id = $_POST['orden_id'];

    $ord_rcompra = $_POST['orden_tccompra'];
    $ord_rventa = $_POST['orden_tcventa'];

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
    $ord_purchase_request_id = filter_var($_POST['orden_solicitud_compra'] ?? null, FILTER_VALIDATE_INT);
    $ord_purchase_request_id = $ord_purchase_request_id === false || $ord_purchase_request_id < 1 ? null : $ord_purchase_request_id;
    $ord_cotiz = trim($_POST['orden_cotizacion']);
    $ord_request = trim($_POST['orden_solicitante']);
    $ord_estado = 'Pendiente';
    $user_id = (int) $_SESSION['loggedInUser']['USERID'];
    $ord_totneto = $_POST['orden_totneto'];
    $ord_totcompra = $_POST['orden_totcompra'];
    $ord_totigv = $_POST['orden_igv'];

    $ord_lstprods = "";
    $ord_lstservicios = "";

    switch ($ord_tipo) {
        case 'COMPRA':
            $ord_tipo = 1;
            $ord_code = "OC-";
            $ord_lstprods = $_POST['orden_lstprods'];
            $ord_lstprods = json_decode($ord_lstprods, true);
            if (is_array($ord_lstprods)) {
                $ord_lstprods = app_purchase_order_product_rows_from_ui($ord_lstprods);
            }
            break;
        case 'SERVICIO':
            $ord_tipo = 2;
            $ord_code = "OS-";
            $ord_lstservicios = $_POST['orden_lstservicios'];
            $ord_lstservicios = json_decode($ord_lstservicios, true);
            break;
        default:
            http_response_code(422);
            echo 'ERROR';
            exit;
    }

    try {
        app_validate_purchase_input($_POST,$ord_tipo===1?(is_array($ord_lstprods)?$ord_lstprods:[]):(is_array($ord_lstservicios)?$ord_lstservicios:[]),$ord_tipo===2);
        $ord_provider = app_validate_provider_reference($pdo, $ord_provider);
        if ($ord_tipo === 1) {
            app_validate_product_rows($pdo, is_array($ord_lstprods) ? $ord_lstprods : []);
        } elseif ($ord_tipo === 2 && (!is_array($ord_lstservicios) || count($ord_lstservicios) === 0)) {
            throw new InvalidArgumentException('La orden de servicio debe contener al menos un servicio.');
        }
        $pdo->beginTransaction();
        $providerAddress = app_provider_address_snapshot($pdo, (int) $ord_provider, filter_var($_POST['orden_direccion_proveedor_id'] ?? null, FILTER_VALIDATE_INT) ?: null);
        if ($ord_purchase_request_id !== null) {
            if ($ord_tipo !== 1) throw new DomainException('Las solicitudes de compra solo pueden originar ordenes de compra.');
            $purchaseRequest = app_validate_purchase_request_conversion($pdo, (int)$ord_purchase_request_id, is_array($ord_lstprods) ? $ord_lstprods : [], !($ord_id === '' || $ord_id === null));
        }
        $response = '';
        $previous_status = null;

        if ($ord_id !== "" && $ord_id !== null) {
            $statusStatement = $pdo->prepare('SELECT number, status, purchase_request_id FROM tbl_order WHERE id = ? FOR UPDATE');
            $statusStatement->execute([$ord_id]);
            $existingOrder = $statusStatement->fetch();
            $previous_status = $existingOrder['status'] ?? false;
            $ord_code = (string) ($existingOrder['number'] ?? $ord_code);

            if ($previous_status === false || !in_array($previous_status, ['Pendiente', 'Rechazado'], true)) {
                throw new RuntimeException('La orden ya no se puede modificar.');
            }
            $existingRequestId=(int)($existingOrder['purchase_request_id']??0);
            if($existingRequestId>0 && $existingRequestId!==(int)($ord_purchase_request_id??0))throw new DomainException('No se puede cambiar ni retirar la solicitud de origen de una orden existente.');
        }

        if ($ord_id == "" || $ord_id == null) {
            $orderNumber = $ord_tipo === 1
                ? app_next_document_number($pdo, 'ORDER', 'OC')
                : 'OS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $sqlStatement = $pdo->prepare("INSERT INTO tbl_order (type,number,status,currency,issue_date,delivery_date,provider_id,provider_address_id,provider_address,purchase_request_id,payment_days,account_number,quotation,requester,approver,created_by,submitted_at,observation,total_purchase,total_tax,total_net,exchange_rate_sale,exchange_rate_purchase) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?)");
            $sqlStatement->execute([$ord_tipo, $orderNumber, $ord_estado, $ord_tipomon, $ord_emidate, $ord_deldate, $ord_provider, $providerAddress['id'], $providerAddress['address_line'], $ord_purchase_request_id, $ord_daypay, $ord_nrocuenta, $ord_cotiz, $ord_request, '', $user_id, $ord_observ, $ord_totcompra, $ord_totigv, $ord_totneto, $ord_rventa, $ord_rcompra]);

            $ord_id = (int) $pdo->lastInsertId();
            $ord_code = $orderNumber;
            app_record_document_status($pdo, 'order', $ord_id, $ord_code, '', 'Pendiente', 'Creacion', $user_id);
            $response = 'OK_INSERT';
        } else {
        $sqlStatement = $pdo->prepare("UPDATE tbl_order SET type=?,status=?,currency=?,issue_date=?,delivery_date=?,provider_id=?,provider_address_id=?,provider_address=?,purchase_request_id=?,payment_days=?,account_number=?,quotation=?,requester=?,approver='',submitted_at=NOW(),approved_by=NULL,approved_at=NULL,rejected_by=NULL,rejected_at=NULL,approval_comment='',observation=?,total_purchase=?,total_tax=?,total_net=?,exchange_rate_sale=?,exchange_rate_purchase=? WHERE id=?");
            $sqlStatement->execute([$ord_tipo, $ord_estado, $ord_tipomon, $ord_emidate, $ord_deldate, $ord_provider, $providerAddress['id'], $providerAddress['address_line'], $ord_purchase_request_id, $ord_daypay, $ord_nrocuenta, $ord_cotiz, $ord_request, $ord_observ, $ord_totcompra, $ord_totigv, $ord_totneto, $ord_rventa, $ord_rcompra, $ord_id]);
            $response = 'OK_UPDATE';
        $sqlStatement = $pdo->prepare("DELETE FROM tbl_order_detail WHERE order_id=:IDORDER");
        $sqlStatement->bindParam("IDORDER", $ord_id, PDO::PARAM_INT);
        $sqlStatement->execute();

        if ($previous_status === 'Rechazado') {
            $historyStatement = $pdo->prepare("INSERT INTO tbl_approval_history(document_type,document_id,previous_status,new_status,comment,user_id) VALUES('order',?,'Rechazado','Pendiente','Documento corregido y reenviado',?)");
            $historyStatement->execute([$ord_id, $user_id]);
            app_record_document_status($pdo, 'order', (int) $ord_id, $ord_code, 'Rechazado', 'Pendiente', 'Reenvio', $user_id, 'Documento corregido y reenviado');
        }
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

                $sqlStatement = $pdo->prepare("INSERT INTO tbl_order_detail(order_id,item_id,item_code,item_description,item_gloss,item_unit_value,item_unit_price,item_quantity,item_discount_rate,item_discounted_total) VALUES(?,?,?,?,?,?,?,?,?,?)");

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

                $sqlStatement = $pdo->prepare("INSERT INTO tbl_order_detail(order_id,item_description,item_gloss,item_unit_price,item_quantity,item_discount_rate,item_discounted_total) VALUES(?,?,?,?,?,?,?)");

                $sqlStatement->execute([$ord_id, $item_desc, $item_glosa, $item_prec, $item_cant, $item_descpor, $item_descval]);
                
            }
        }
        }

        if ($ord_purchase_request_id !== null) {
            app_copy_custom_fields($pdo, 'purchase_request', (int) $ord_purchase_request_id, 'order', (int) $ord_id);
        }
        app_save_custom_fields($pdo, 'order', (int) $ord_id, is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : []);

        if ($ord_purchase_request_id !== null && isset($purchaseRequest) && $purchaseRequest['status'] !== 'Convertida') {
            $pdo->prepare("UPDATE tbl_purchase_request SET status = 'Convertida', last_update = NOW() WHERE id = ?")->execute([$ord_purchase_request_id]);
            app_record_document_status($pdo, 'purchase_request', (int)$ord_purchase_request_id, (string)$purchaseRequest['number'], (string)$purchaseRequest['status'], 'Convertida', 'Conversion', $user_id, 'Solicitud convertida en orden de compra.');
            app_record_document_flow($pdo, 'purchase_request', (int)$ord_purchase_request_id, 'order', (int)$ord_id, $user_id, 'REQUEST_TO_ORDER');
        }
        $pdo->commit();
        echo $response;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('APPU order save error: ' . $exception->getMessage());
        http_response_code(422);
        echo 'ERROR';
    }

} else {
    echo "ERROR";
}
