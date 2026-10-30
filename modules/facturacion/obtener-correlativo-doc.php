<?php
    require '../../global/connection.php';

    $tipoDoc = $_POST["TIPO_DOC"];
    $serie = $_POST["SERIE"];

    if ($serie == "DEFAULT"){
        $defaultSeries = $pdo->prepare('SELECT series FROM tbl_document_series WHERE document_type = ? AND active_status = 1 ORDER BY id LIMIT 1');
        $defaultSeries->execute([$tipoDoc]);
        $serie = (string) ($defaultSeries->fetchColumn() ?: '');
        if ($serie === '') {
            if ($tipoDoc == "INVOICE") $serie="F001";
            if ($tipoDoc == "RECEIPT") $serie="B001";
            if ($tipoDoc == "CREDIT_NOTE") $serie="FNC1";
        }
    }

    $table = "";

    if ($tipoDoc == "INVOICE"){
        $table = "tbl_invoice";
    }else if($tipoDoc == "RECEIPT"){
        $table = "tbl_receipt";
    }else if($tipoDoc == "CREDIT_NOTE"){
        $table = "tbl_credit_note";
    }else{
        echo false;
        return;
    }

    $seriesConfig = $pdo->prepare('SELECT next_number, number_padding FROM tbl_document_series WHERE document_type = ? AND series = ? AND active_status = 1 LIMIT 1');
    $seriesConfig->execute([$tipoDoc, $serie]);
    $configured = $seriesConfig->fetch();
    if ($configured) {
        $pdo->beginTransaction();
        $lockedSeries = $pdo->prepare('SELECT next_number, number_padding FROM tbl_document_series WHERE document_type = ? AND series = ? AND active_status = 1 FOR UPDATE');
        $lockedSeries->execute([$tipoDoc, $serie]);
        $locked = $lockedSeries->fetch();
        $maxExisting = $pdo->prepare("SELECT COALESCE(MAX(CONVERT(number,UNSIGNED)),0) FROM {$table} WHERE series=?");
        $maxExisting->execute([$serie]);
        $nextNumber = max((int)$locked['next_number'], ((int)$maxExisting->fetchColumn()) + 1);
        $formatted = str_pad((string)$nextNumber, (int)$locked['number_padding'], '0', STR_PAD_LEFT);
        // Solo se presenta una previsión; el correlativo se reserva al guardar.
        $pdo->commit();
        echo $formatted;
        return;
    }
    $LSTMAXID = $pdo->prepare("SELECT CONVERT(MAX(number),UNSIGNED) AS MAXID FROM " . $table . " WHERE series=:SERIE ORDER BY id DESC");
    $LSTMAXID->bindParam(":SERIE", $serie, PDO::PARAM_STR);
    $LSTMAXID->execute();
    
    $NEXT_ID = 0;
    $MAX_ID = 0;
    
    while ($LMI = $LSTMAXID->fetch()) {
        $MAX_ID = $LMI["MAXID"];
    }
    
    if ($MAX_ID == "" || $MAX_ID == 0) {
        $NEXT_ID = 1;
    } else {
        $NEXT_ID = $MAX_ID + 1;
    }

    switch ($NEXT_ID) {
        case ($NEXT_ID < 10):
            $NEXT_ID = "000000" . $NEXT_ID;
            break;
        case ($NEXT_ID < 100):
            $NEXT_ID = "00000" . $NEXT_ID;
            break;
        case ($NEXT_ID < 1000):
            $NEXT_ID = "0000" . $NEXT_ID;
            break;
        case ($NEXT_ID < 10000):
            $NEXT_ID = "000" . $NEXT_ID;
            break;
        case ($NEXT_ID < 100000):
            $NEXT_ID = "00" . $NEXT_ID;
            break;
        case ($NEXT_ID < 1000000):
            $NEXT_ID = "0" . $NEXT_ID;
            break;
        case ($NEXT_ID < 10000000):
            $NEXT_ID = $NEXT_ID;
            break;
    }

    echo $NEXT_ID;
?>
