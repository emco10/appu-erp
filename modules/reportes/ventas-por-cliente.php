<?php

include 'template.php';
require '../../global/connection.php';
require_once '../../global/reporting.php';

if (!isset($_GET['customerid'])){
	echo "Error al obtener reporte. Variable 'customerid' no especificada.";
	return;
}

if (!isset($_GET['datefrom'])){
	echo "Error al obtener reporte. Variable 'datefrom' no especificada.";
	return;
}

if (!isset($_GET['dateto'])){
	echo "Error al obtener reporte. Variable 'dateto' no especificada.";
	return;
}

$customerId = trim($_GET['customerid']);
$dateFrom = $_GET['datefrom'];
$dateTo = $_GET['dateto'];
try{app_report_date_range($dateFrom,$dateTo,true);}catch(InvalidArgumentException $e){app_report_error($e->getMessage());}

if (!preg_match('/^[0-9]{8,11}$/', $customerId)) {
	echo "Error al obtener reporte. Variable 'customerid' no válida.";
	return;
}

$reportTitle = "Ventas por Cliente";
$rptDateInterval = "VENTAS (Sin rango de fecha)";

$productString = "";
$dateString = "";

if ($dateFrom!= "" && $dateTo!= ""){
	$dateString = " AND (th.date BETWEEN ? AND ?)";
	$rptDateInterval = "VENTAS DEL " . date("d/m/Y", strtotime($dateFrom)) . " AL " . date("d/m/Y", strtotime($dateTo));
}

$sqlCustomerInfo = "(SELECT DISTINCT th.ruc, th.name, th.address
 FROM tbl_invoice th
WHERE th.ruc = ?)
UNION
(SELECT DISTINCT th.ruc, th.name, th.address
 FROM tbl_receipt th
WHERE th.ruc = ?)";

$sqlStatement = $pdo->prepare($sqlCustomerInfo);
$sqlStatement->execute([$customerId, $customerId]);
$customerData = $sqlStatement->fetch();

if ($customerData == null){
	echo "Error al obtener reporte. Variable 'customerid' no es válida. No existe el cliente solicitado.";
	return;
}

$sqlString = "(SELECT id, CONCAT(series, '-',number) AS DOC_NUMBER,
date,
delivery_date,
currency,
total_sub,
total_tax,
total_net,
(CASE
 WHEN th.status=1 THEN 'Vigente'
 WHEN th.status=2 THEN 'Anulado'
 WHEN th.status=3 THEN 'Pendiente de Pago'
 WHEN th.status=4 THEN 'Cancelado'
END) AS STATUS
FROM tbl_invoice th
WHERE th.ruc = ? AND th.status NOT IN (2,4) AND th.document_subtype<>'ADVANCE' " . $dateString . ")
UNION ALL
(SELECT id, CONCAT(series, '-',number) AS DOC_NUMBER,
date,
delivery_date,
currency,
total_sub,
total_tax,
total_net,
(CASE
 WHEN th.status=1 THEN 'Vigente'
 WHEN th.status=2 THEN 'Anulado'
 WHEN th.status=3 THEN 'Pendiente de Pago'
 WHEN th.status=4 THEN 'Cancelado'
END) AS STATUS
FROM tbl_receipt th
WHERE th.ruc = ? AND th.status NOT IN (2,4) " . $dateString . ")
UNION ALL
(SELECT id, CONCAT(series, '-',number) AS DOC_NUMBER,
date,
delivery_date,
currency,
total_sub*-1 AS total_sub,
total_tax*-1 AS total_tax,
total_net*-1 AS total_net,
(CASE
 WHEN th.status=1 THEN 'Vigente'
 WHEN th.status=2 THEN 'Anulado'
 WHEN th.status=3 THEN 'Pendiente de Pago'
 WHEN th.status=4 THEN 'Cancelado'
END) AS STATUS
FROM tbl_credit_note th
WHERE th.ruc = ? AND th.status NOT IN (2,4) AND NOT EXISTS(SELECT 1 FROM tbl_invoice advance_invoice WHERE th.referenced_doc_type_id=1 AND advance_invoice.id=th.referenced_doc_id AND advance_invoice.document_subtype='ADVANCE') " . $dateString . ")
ORDER BY date DESC";

$sqlStatement = $pdo->prepare($sqlString);

$queryParams = [];
for ($index = 0; $index < 3; $index++) {
	$queryParams[] = $customerId;
	if ($dateFrom !== '' && $dateTo !== '') {
		$queryParams[] = $dateFrom;
		$queryParams[] = $dateTo;
	}
}
$sqlStatement->execute($queryParams);
$rowsNumber = $sqlStatement->rowCount();

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->SetHeaderTitle(utf8_decode($reportTitle));
$pdf->AddPage("L","A4",0);
$pdf->SetTitle($reportTitle,true);
$pdf->SetSubject($reportTitle,true);
$pdf->SetAuthor("APPU",true);
$pdf->SetCreator("fpdf v1.82",true);

$pdf->SetFillColor(232,232,232);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,6,utf8_decode('DATOS DEL CLIENTE'),1,0,'L',1);
$pdf->Ln();
$pdf->SetFont('Arial','B',9);
$pdf->Cell(20,6,utf8_decode('RUC / DNI'),1,0,'R',1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,6,utf8_decode($customerData['ruc']),1,0,'L',0);
$pdf->Ln();
$pdf->SetFont('Arial','B',9);
$pdf->Cell(20,6,utf8_decode('Nombre'),1,0,'R',1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,6,utf8_decode($customerData['name']),1,0,'L',0);
$pdf->Ln();
$pdf->SetFont('Arial','B',9);
$pdf->Cell(20,6,utf8_decode('Dirección'),1,0,'R',1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,6,utf8_decode($customerData['address']),1,0,'L',0);

$pdf->Ln(10);

$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,6,utf8_decode($rptDateInterval),1,0,'C',1);
$pdf->Ln();
$pdf->Cell(16,6,utf8_decode('TBLID'),1,0,'C',1);
$pdf->Cell(28,6,utf8_decode('Nro. Doc.'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('Fecha Emisión'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('Fecha Entrega'),1,0,'C',1);
$pdf->Cell(59,6,utf8_decode('Estado'),1,0,'C',1);
$pdf->Cell(24,6,utf8_decode('Tipo Moneda'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('Subtotal'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('IGV'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('Total Neto'),1,0,'C',1);

$pdf->Ln();

$pdf->SetFont('Arial','',9);

if ($rowsNumber > 0) {

	$totalSales = [];

    foreach ($sqlStatement as $row) {
        $row['currency']=app_currency_code((string)$row['currency']);
		$pdf->Cell(16,6,utf8_decode($row['id']),1,0,'C');
		$pdf->Cell(28,6,utf8_decode($row['DOC_NUMBER']),1,0,'C');
		$pdf->Cell(30,6,utf8_decode(date("d/m/Y", strtotime($row["date"]))),1,0,'C');
		$pdf->Cell(30,6,utf8_decode(date("d/m/Y", strtotime($row["delivery_date"]))),1,0,'C');
		$pdf->Cell(59,6,utf8_decode($row['STATUS']),1,0,'C');
		$pdf->Cell(24,6,utf8_decode($row['currency']),1,0,'C');
		$pdf->Cell(30,6,utf8_decode(number_format($row['total_sub'], 2, '.','')),1,0,'R');
		$pdf->Cell(30,6,utf8_decode(number_format($row['total_tax'], 2, '.','')),1,0,'R');

		$total_net = $row['total_net'];

		$pdf->Cell(30,6,utf8_decode(number_format($row['total_net'], 2, '.','')),1,0,'R');
			
		$pdf->Ln();

		$totalSales[$row['currency']]=($totalSales[$row['currency']]??0)+$total_net;
	}

    foreach($totalSales as $currency=>$total){$pdf->Cell(217);$pdf->SetFont('Arial','B',9);$pdf->Cell(30,6,utf8_decode('TOTAL '.$currency),1,0,'C',1);$pdf->Cell(0,6,utf8_decode(number_format($total,2,'.','')),1,0,'R');$pdf->Ln();}

}else{
	$pdf->Cell(276,6,utf8_decode("No existen datos para el cliente especificado"),1,0,'C');
}


$pdf->Output("I", $reportTitle . ".pdf", true);
