<?php

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

    require '../../global/connection.php';
    require_once '../../global/master-data-validation.php';
    require_once '../inventory/stock.php';
    require_once '../../global/document-history.php';
    require_once '../finanzas/sync.php';
    require_once '../../global/sunat-api.php';
    require_once '../../global/sales-document-validation.php';
    require_once '../../global/sales-document-save.php';
    $asJson = ($_POST['response_format'] ?? '') === 'json';

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
    $f_reason = $_POST['facturacion_motivo_nc'];

    $f_fecha = date("Y-m-d", strtotime($_POST['facturacion_fecha']));
    $f_subtotal = $_POST['facturacion_opergrab'];
    $f_totalneto = $_POST['facturacion_total'];
    $f_taxigv = $_POST['facturacion_igv'];
    $f_lst_prods = json_decode($_POST['facturacion_prods'] ?? '', true);
    $f_estado = $_POST["facturacion_estado"];

    $documentKey = $_POST['facturacion_listadofact'] ?? '';
    if (!is_string($documentKey) || !preg_match('/^([FB]):([1-9][0-9]*)$/D', trim($documentKey), $documentMatch)) {
        if ($asJson) {
            app_api_error(new AppValidationException([app_field_error('facturacion_listadofact', 'INVALID_REFERENCE', 'Selecciona una factura o boleta válida como documento de origen.')]), 'No se pudo registrar la nota de crédito.');
        } else {
            http_response_code(422);
            echo 'ERROR';
        }
        return;
    }
    $f_selectcotizid = (int) $documentMatch[2];
    $f_warehouse_id = filter_var($_POST['facturacion_almacen_id'] ?? null, FILTER_VALIDATE_INT);
    $f_warehouse_id = $f_warehouse_id === false || $f_warehouse_id <= 0 ? 1 : (int) $f_warehouse_id;

    $f_referenced_doc_type = $documentMatch[1] === 'B' ? 2 : 1;

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
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $pdo->beginTransaction();
        $requestResult = app_document_request_start($pdo, 'credit_note_create', $_POST, (int) $f_user_id);
        if ($requestResult !== null) {
            $pdo->commit();
            app_send_sales_creation_result($requestResult, $asJson);
            return;
        }
        if(!in_array((int)$f_estado,[1,3],true))throw new DomainException('La nueva nota de crédito debe estar vigente o pendiente.');
        $totals=app_validate_legacy_sales_input($pdo,$_POST,$f_lst_prods);
        $f_subtotal=$totals['subtotal'];$f_taxigv=$totals['tax'];$f_porcdesc=$totals['discount_rate'];$f_valdesc=$totals['discount'];$f_totalneto=$totals['total'];
        if (!is_array($f_lst_prods) || count($f_lst_prods) === 0) {
            throw new InvalidArgumentException('La nota de crédito debe contener al menos un producto.');
        }

        $f_cliid = app_validate_customer_reference($pdo, $f_cliid);
        app_validate_product_rows($pdo, $f_lst_prods);
        $f_faccod=app_validate_legacy_sales_number($pdo,'CREDIT_NOTE',(string)$f_series,(string)$f_faccod);
        $sourceTable = $f_referenced_doc_type === 2 ? 'tbl_receipt' : 'tbl_invoice';
        $sourceDetailTable = $f_referenced_doc_type === 2 ? 'tbl_receipt_detail' : 'tbl_invoice_detail';
        $sourceDetailKey = $f_referenced_doc_type === 2 ? 'receipt_id' : 'invoice_id';
        $sourceStatement = $pdo->prepare("SELECT * FROM {$sourceTable} WHERE id=? FOR UPDATE");
        $sourceStatement->execute([$f_selectcotizid]);
        $sourceDocument = $sourceStatement->fetch();
        if (!$sourceDocument || in_array((int)$sourceDocument['status'], [2,4], true)) throw new DomainException('El documento de origen no existe o esta anulado.');
        if($f_referenced_doc_type===1&&($sourceDocument['document_subtype']??'')==='ADVANCE'){
            if(warehouse_credit_note_returns_stock((string)$f_reason))throw new DomainException('Un anticipo no descargó mercancías y no admite una devolución física.');
            $advance=$pdo->prepare("SELECT id FROM tbl_sales_advance_application WHERE advance_invoice_id=? AND status='ACTIVE' FOR UPDATE");$advance->execute([$f_selectcotizid]);if($advance->fetchColumn())throw new DomainException('El anticipo está aplicado a una factura final; anula primero la factura final.');
        }
        if($f_referenced_doc_type===1&&($sourceDocument['document_subtype']??'')==='RESERVE'&&warehouse_credit_note_returns_stock((string)$f_reason)){
            $physical=$pdo->prepare("SELECT detail.product_id,SUM(detail.quantity) quantity FROM tbl_remission_guide_detail detail JOIN tbl_remission_guide guide ON guide.id=detail.remission_guide_id WHERE guide.source_type='invoice' AND guide.source_id=? AND guide.inventory_posted=1 AND guide.status<>'Anulada' GROUP BY detail.product_id");$physical->execute([$f_selectcotizid]);$delivered=[];foreach($physical->fetchAll() as $physicalLine)$delivered[(int)$physicalLine['product_id']]=(float)$physicalLine['quantity'];
            $returned=$pdo->prepare("SELECT detail.item_id,SUM(detail.item_quantity) quantity FROM tbl_credit_note_detail detail JOIN tbl_credit_note note ON note.id=detail.credit_note_id WHERE note.referenced_doc_type_id=1 AND note.referenced_doc_id=? AND note.status NOT IN(2,4) AND note.reason IN('DEVOLUCION_TOTAL','DEVOLUCION_ITEM') GROUP BY detail.item_id");$returned->execute([$f_selectcotizid]);foreach($returned->fetchAll() as $returnedLine)$delivered[(int)$returnedLine['item_id']]=($delivered[(int)$returnedLine['item_id']]??0)-(float)$returnedLine['quantity'];
            foreach($f_lst_prods as $returnLine)if((float)$returnLine[5]>($delivered[(int)$returnLine[0]]??0)+.000001)throw new DomainException('La devolución física de la reserva supera la cantidad efectivamente entregada.');
        }
        if ((int)$sourceDocument['customer_id'] !== (int)$f_cliid) throw new DomainException('El cliente no coincide con el documento de origen.');
        if ((int)$sourceDocument['warehouse_id'] !== $f_warehouse_id) throw new DomainException('El almacen no coincide con el documento de origen.');
        if (strcasecmp((string)$sourceDocument['currency'], (string)$f_currency) !== 0) throw new DomainException('La moneda no coincide con el documento de origen.');
        $f_cli_ruc=(string)$sourceDocument['ruc']; $f_clinom=(string)$sourceDocument['name']; $f_clidirecc=(string)$sourceDocument['address'];

        $creditedTotalStatement=$pdo->prepare('SELECT COALESCE(SUM(total_net),0) FROM tbl_credit_note WHERE referenced_doc_id=? AND referenced_doc_type_id=? AND status NOT IN (2,4) FOR UPDATE');
        $creditedTotalStatement->execute([$f_selectcotizid,$f_referenced_doc_type]);
        $alreadyCredited=(float)$creditedTotalStatement->fetchColumn();
        if ($f_totalneto <= 0 || $alreadyCredited + (float)$f_totalneto > (float)$sourceDocument['total_net'] + 0.01) throw new DomainException('El importe de la nota supera el saldo disponible del documento de origen.');
        $accountStatement=$pdo->prepare('SELECT paid_amount FROM tbl_accounts_receivable WHERE document_type=? AND document_id=? FOR UPDATE');
        $accountStatement->execute([$f_referenced_doc_type===2?'receipt':'invoice',$f_selectcotizid]);$paidAmount=(float)($accountStatement->fetchColumn()?:0);
        if ($alreadyCredited + (float)$f_totalneto > (float)$sourceDocument['total_net'] - $paidAmount + 0.01) throw new DomainException('La nota supera el saldo no cobrado; primero reversa los cobros relacionados.');

        $sourceLines=$pdo->prepare("SELECT item_id,item_quantity quantity,item_unit_price unit_price,item_discount_rate,project_code,cost_center_code,lot_number,serial_number,expiration_date FROM {$sourceDetailTable} WHERE {$sourceDetailKey}=? ORDER BY id");
        $sourceLines->execute([$f_selectcotizid]); $available=[]; foreach($sourceLines->fetchAll() as $line){$productId=(int)$line['item_id'];if(isset($available[$productId]))throw new DomainException('El documento de origen contiene líneas duplicadas que deben regularizarse antes de emitir la nota.');$available[$productId]=['quantity'=>(float)$line['quantity'],'price'=>(float)$line['unit_price'],'discount'=>(float)$line['item_discount_rate'],'project'=>(string)$line['project_code'],'cost_center'=>(string)$line['cost_center_code'],'lot'=>(string)$line['lot_number'],'serial'=>(string)$line['serial_number'],'expiration'=>(string)($line['expiration_date']??'')];}
        $creditedLines=$pdo->prepare('SELECT detail.item_id,SUM(detail.item_quantity) quantity FROM tbl_credit_note_detail detail INNER JOIN tbl_credit_note note ON note.id=detail.credit_note_id WHERE note.referenced_doc_id=? AND note.referenced_doc_type_id=? AND note.status NOT IN (2,4) GROUP BY detail.item_id');
        $creditedLines->execute([$f_selectcotizid,$f_referenced_doc_type]); foreach($creditedLines->fetchAll() as $line){if(isset($available[(int)$line['item_id']]))$available[(int)$line['item_id']]['quantity']-=(float)$line['quantity'];}
        foreach($f_lst_prods as $product){$productId=(int)($product[0]??0);$price=(float)($product[4]??-1);$quantity=(float)($product[5]??0);$source=$available[$productId]??null;if(!$source||$quantity<=0||$quantity>$source['quantity']+0.000001||abs($price-$source['price'])>0.01||abs((float)($product[8]??0)-$source['discount'])>.0001||(string)($product[9]??'')!==$source['project']||(string)($product[10]??'')!==$source['cost_center']||(string)($product[11]??'')!==$source['lot']||(string)($product[12]??'')!==$source['serial']||(string)($product[13]??'')!==$source['expiration'])throw new DomainException('Los artículos, cantidades, precios, descuentos o dimensiones no coinciden con el documento de origen.');$available[$productId]['quantity']-=$quantity;}
        $warehouseStatement = $pdo->prepare('SELECT id FROM tbl_warehouse WHERE id = ? AND active_status = 1');
        $warehouseStatement->execute([$f_warehouse_id]);
        if (!$warehouseStatement->fetchColumn()) {
            throw new DomainException('El almacen seleccionado no existe o esta inactivo.');
        }
        $sqlStatement = $pdo->prepare("INSERT INTO tbl_credit_note (series,number,status,referenced_doc_id,referenced_doc_type_id,customer_id,warehouse_id,ruc,name,address,reference,payment_days,date,delivery_date,currency,reason,discount_rate,discount_value,total_sub,total_tax,total_net,seller_id,user_id,registration_date,last_update) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $sqlStatement->execute([$f_series,$f_faccod, $f_estado, $f_selectcotizid, $f_referenced_doc_type, $f_cliid, $f_warehouse_id, $f_cli_ruc, $f_clinom, $f_clidirecc, $f_clirefer, $f_diaspag, $f_fecha, $f_clifecentreg, $f_currency, $f_reason, $f_porcdesc, $f_valdesc, $f_subtotal, $f_taxigv, $f_totalneto, $f_seller_id, $f_user_id, $f_fecreg, $f_fecreg]);
        $id_facturacion = (int) $pdo->lastInsertId();

        $insertDetail = $pdo->prepare('INSERT INTO tbl_credit_note_detail (item_description,item_id,item_code,item_name,item_quantity,item_unit_price,item_discount_rate,item_discounted_total,project_code,cost_center_code,lot_number,serial_number,expiration_date,credit_note_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $returnsStock = (int) $f_estado !== 2 && warehouse_credit_note_returns_stock($f_reason);
        $documentReference = $f_series . '-' . $f_faccod;

        foreach ($f_lst_prods as $product) {
            $id_prod = filter_var($product[0] ?? null, FILTER_VALIDATE_INT);
            $cod_prod = trim((string) ($product[1] ?? ''));
            $nom_prod = trim((string) ($product[2] ?? ''));
            $desc_prod = trim((string) ($product[3] ?? ''));
            $prec_prod = filter_var($product[4] ?? null, FILTER_VALIDATE_FLOAT);
            $cant_prod = filter_var($product[5] ?? null, FILTER_VALIDATE_FLOAT);

            if ($id_prod === false || $prec_prod === false || $prec_prod < 0 || $cant_prod === false || $cant_prod <= 0) {
                throw new InvalidArgumentException('La nota de crédito contiene un producto inválido.');
            }

            $insertDetail->execute([$desc_prod, $id_prod, $cod_prod, $nom_prod, $cant_prod, $prec_prod, (float)($product[8]??0), (float)($product[6]??0), (string)($product[9]??''), (string)($product[10]??''), (string)($product[11]??''), (string)($product[12]??''), ($product[13]??'') ?: null, $id_facturacion]);

            if ($returnsStock) {
                warehouse_apply_stock_delta(
                    $pdo,
                    (int) $id_prod,
                    $cant_prod,
                    STOCK_MOVEMENT_CUSTOMER_RETURN,
                    (int) $f_user_id,
                    'Devolucion por nota de credito',
                    'credit_note',
                    $id_facturacion,
                    $documentReference,
                    null,
                    $f_warehouse_id
                );
            }
        }

        app_save_custom_fields($pdo, 'credit_note', $id_facturacion, is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : []);
        app_record_document_status($pdo, 'credit_note', $id_facturacion, $documentReference, '', app_sales_status_label((int) $f_estado), 'Creacion', (int) $f_user_id);
        if ($f_selectcotizid > 0) { app_record_document_flow($pdo, $f_referenced_doc_type === 2 ? 'receipt' : 'invoice', (int) $f_selectcotizid, 'credit_note', $id_facturacion, (int) $f_user_id, 'CREDIT_NOTE'); }

        finance_recalculate_credit_notes($pdo, (int) $f_selectcotizid, (int) $f_referenced_doc_type);
        $result = ['error' => false, 'document_id' => $id_facturacion, 'number' => $documentReference];
        app_document_request_finish($pdo, 'credit_note_create', $_POST, (int) $f_user_id, 'credit_note', $id_facturacion, $result);
        $pdo->commit();
        try { app_sunat_submit_document($pdo, 'credit-note', (int) $id_facturacion); }
        catch (Throwable $sunatException) { error_log('APPU automatic SUNAT credit note submission: ' . $sunatException->getMessage()); }
        app_send_sales_creation_result($result, $asJson);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('APPU credit note error: ' . $exception->getMessage());
        app_api_error($exception,'No se pudo registrar la nota de crédito.');
    }
} else {
    echo "ERROR";
}
