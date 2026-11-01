<?php
require_once __DIR__.'/../plugins/fpdf/fpdf.php';
require_once __DIR__.'/../plugins/phpqrcode/phpqrcode.php';

final class AppuElectronicDocumentPdf extends FPDF {
 private array $company; private array $doc; private string $label; private string $logo; private array $widths=[12,22,20,77,28,29];
 function __construct(array $company,array $doc,string $label,string $logo){parent::__construct('P','mm','A4');$this->company=$company;$this->doc=$doc;$this->label=$label;$this->logo=$logo;$this->SetMargins(11,12,11);$this->SetAutoPageBreak(true,18);}
 private function pdfText(mixed $value):string{$value=(string)$value;return iconv('UTF-8','windows-1252//TRANSLIT',$value)?:$value;}
 function Header():void{
  if($this->logo&&is_file($this->logo))$this->Image($this->logo,12,12,34);
  $this->SetXY(49,12);$this->SetFont('Arial','B',12);$this->Cell(94,6,$this->pdfText($this->company['legal_name']?:'APPU'),0,1);$this->SetX(49);$this->SetFont('Arial','',8);$this->MultiCell(94,4,$this->pdfText($this->company['address']?:'Domicilio fiscal no configurado'));
  $location=implode(' - ',array_filter([$this->company['district']??'',$this->company['province']??'',$this->company['department']??'']));if($location){$this->SetX(49);$this->Cell(94,4,$this->pdfText($location));}
  $this->SetXY(146,12);$this->SetDrawColor(35,75,120);$this->SetLineWidth(.45);$this->SetFont('Arial','B',10);$this->Cell(52,8,'RUC: '.$this->pdfText($this->company['tax_id']),'LTR',1,'C');$this->SetX(146);$this->Cell(52,8,$this->pdfText($this->label.' ELECTRONICA'),'LR',1,'C');$this->SetX(146);$this->SetFont('Arial','B',12);$this->Cell(52,9,$this->pdfText($this->doc['series'].'-'.$this->doc['number']),'LBR',1,'C');$this->Ln(7);
 }
 function Footer():void{$this->SetY(-15);$this->SetFont('Arial','I',7);$this->SetTextColor(80);$this->Cell(0,4,$this->pdfText('Representación impresa del comprobante electrónico - Página '.$this->PageNo().'/{nb}'),0,1,'C');$this->Cell(0,4,$this->pdfText('La validez tributaria se acredita con el XML firmado y la respuesta de SUNAT.'),0,0,'C');}
 private function field(string $label,string $value,string $label2='',string $value2=''):void{$this->SetFont('Arial','B',8);$this->Cell(30,6,$this->pdfText($label.':'));$this->SetFont('Arial','',8);$this->Cell(78,6,$this->pdfText($value));if($label2){$this->SetFont('Arial','B',8);$this->Cell(25,6,$this->pdfText($label2.':'));$this->SetFont('Arial','',8);$this->Cell(55,6,$this->pdfText($value2));}$this->Ln();}
 private function tableHeader():void{$headers=['Cant.','Unidad','Código','Descripción','V. unitario','Importe'];$this->SetFillColor(35,75,120);$this->SetTextColor(255);$this->SetFont('Arial','B',8);foreach($headers as $i=>$header)$this->Cell($this->widths[$i],7,$this->pdfText($header),1,0,'C',true);$this->Ln();$this->SetTextColor(0);$this->SetFont('Arial','',7.5);}
 private function wrappedLines(float $width,string $text):int{
  $cw=$this->CurrentFont['cw'];$available=($width-2*$this->cMargin)*1000/$this->FontSize;$value=str_replace("\r",'',$this->pdfText($text));$length=strlen($value);if($length>0&&$value[$length-1]==="\n")$length--;$separator=-1;$index=0;$lineStart=0;$lineWidth=0;$lines=1;
  while($index<$length){$character=$value[$index];if($character==="\n"){$index++;$separator=-1;$lineStart=$index;$lineWidth=0;$lines++;continue;}if($character===' ')$separator=$index;$lineWidth+=$cw[$character]??0;if($lineWidth>$available){if($separator===-1){if($index===$lineStart)$index++;}else{$index=$separator+1;}$separator=-1;$lineStart=$index;$lineWidth=0;$lines++;}else{$index++;}}
  return $lines;
 }
 private function row(array $line):void{
  $description=trim((string)(($line['item_description']??'')?:($line['item_name']??'')));$height=max(7,$this->wrappedLines($this->widths[3],$description)*4.2);
  if($this->GetY()+$height>248){$this->AddPage();$this->tableHeader();}
  $x=$this->GetX();$y=$this->GetY();$values=[number_format((float)$line['item_quantity'],2),$line['sunat_unit_code']?:'NIU',$line['item_code'],'',number_format((float)$line['item_unit_price'],2),number_format((float)$line['item_quantity']*(float)$line['item_unit_price'],2)];
  foreach($values as $i=>$value){$this->SetXY($x+array_sum(array_slice($this->widths,0,$i)),$y);if($i===3)$this->MultiCell($this->widths[$i],4.2,$this->pdfText($description),1);else $this->Cell($this->widths[$i],$height,$this->pdfText($value),1,0,$i===0||$i>3?'R':'C');}$this->SetY($y+$height);
 }
 private function qrPayload(array $sunat):string{
  $type=['FACTURA'=>'01','BOLETA DE VENTA'=>'03','NOTA DE CREDITO'=>'07'][$this->label]??'';$customer=(string)($this->doc['ruc']??'');$customerType=preg_match('/^\d{11}$/',$customer)?'6':(preg_match('/^\d{8}$/',$customer)?'1':'0');$number=ltrim((string)$this->doc['number'],'0')?:'0';
  return implode('|',[$this->company['tax_id'],$type,$this->doc['series'],$number,number_format((float)$this->doc['total_tax'],2,'.',''),number_format((float)$this->doc['total_net'],2,'.',''),$this->doc['date'],$customerType,$customer,(string)($sunat['digest_value']??'')]).'|';
 }
 private function drawQr(array $sunat):void{
  if(!in_array($sunat['status']??'',['accepted','observed'],true)||trim((string)($sunat['digest_value']??''))==='')return;
  if(!function_exists('imagepng'))throw new RuntimeException('La extensión PHP GD es necesaria para generar el QR SUNAT.');
  $base=tempnam(sys_get_temp_dir(),'appu_qr_');if($base===false)throw new RuntimeException('No se pudo crear el QR SUNAT.');$png=$base.'.png';@unlink($base);
  try{QRcode::png($this->qrPayload($sunat),$png,QR_ECLEVEL_Q,5,2);$y=$this->GetY();$this->Image($png,12,$y,34,34);$this->SetXY(49,$y+2);$this->SetFont('Arial','B',8);$this->Cell(149,5,$this->pdfText(!empty($this->doc['is_simulation'])?'QR DE DEMOSTRACION - NO VALIDO':'Escanee para consultar los datos del comprobante'),0,1);$this->SetX(49);$this->SetFont('Arial','',7);$this->MultiCell(149,4,$this->pdfText('El QR contiene los datos reglamentarios SUNAT y el resumen digital del XML firmado.'));$this->SetX(49);$this->SetFont('Arial','',6.5);$this->MultiCell(149,3.5,$this->pdfText('Valor resumen: '.$sunat['digest_value']));$this->SetY($y+35);}finally{if(is_file($png))@unlink($png);}
 }
 function render(array $lines,array $taxes,?array $sunat):void{
  $this->AliasNbPages();$this->AddPage();$status=!empty($this->doc['is_simulation'])?'simulation':($sunat['status']??'draft');$names=['accepted'=>'ACEPTADO POR SUNAT','observed'=>'ACEPTADO CON OBSERVACIONES','rejected'=>'RECHAZADO POR SUNAT','processing'=>'EN PROCESO','technical_error'=>'ERROR TECNICO','draft'=>'PENDIENTE DE ENVIO','simulation'=>'SIMULACION QA - NO VALIDO'];
  $this->SetFillColor(232,244,238);$this->SetTextColor(25,80,55);$this->SetFont('Arial','B',8);$this->Cell(0,6,$this->pdfText('Estado SUNAT: '.($names[$status]??strtoupper($status))),1,1,'C',true);$this->SetTextColor(0);$this->Ln(3);
  $this->field('Fecha de emisión',date('d/m/Y',strtotime($this->doc['date'])),'Moneda',$this->doc['currency_label']);$this->field('Cliente',$this->doc['name'],'RUC / DNI',$this->doc['ruc']);
  $this->SetFont('Arial','B',8);$this->Cell(30,5,$this->pdfText('Dirección fiscal:'));$this->SetFont('Arial','',8);$this->MultiCell(158,5,$this->pdfText($this->doc['address']?:'No consignada'));
  $this->field('Forma de pago',$this->doc['payment_label']);
  if(trim((string)($this->doc['reference']??''))!==''){$this->SetFont('Arial','B',8);$this->Cell(30,5,$this->pdfText('Observaciones:'));$this->SetFont('Arial','',8);$this->MultiCell(158,5,$this->pdfText($this->doc['reference']));}
  $this->Ln(2);$this->SetAutoPageBreak(false);$this->tableHeader();foreach($lines as $line)$this->row($line);$this->SetAutoPageBreak(true,18);
  $required=18+count($taxes)*6+38;if($this->GetY()+$required>248)$this->AddPage();else $this->Ln(4);
  $this->SetX(112);$this->SetFont('Arial','',8);foreach($taxes as $label=>$value){$this->Cell(52,6,$this->pdfText($label),1,0,'R');$this->Cell(34,6,$this->pdfText($this->doc['currency_symbol'].' '.number_format($value,2)),1,1,'R');$this->SetX(112);}$this->SetFont('Arial','B',9);$this->Cell(52,7,'IMPORTE TOTAL',1,0,'R');$this->Cell(34,7,$this->pdfText($this->doc['currency_symbol'].' '.number_format($this->doc['total_net'],2)),1,1,'R');
  if($sunat){$this->Ln(3);$this->SetFont('Arial','',7);$this->MultiCell(0,4,$this->pdfText('Respuesta SUNAT: '.trim(($sunat['sunat_code']??'').' '.($sunat['sunat_description']??''))));if($this->GetY()+38>248)$this->AddPage();$this->drawQr($sunat);}
 }
}

function appu_load_electronic_document(PDO $pdo,string $type,int $id):array{
 $defs=['invoice'=>['tbl_invoice','tbl_invoice_detail','invoice_id','FACTURA'],'receipt'=>['tbl_receipt','tbl_receipt_detail','receipt_id','BOLETA DE VENTA'],'credit-note'=>['tbl_credit_note','tbl_credit_note_detail','credit_note_id','NOTA DE CREDITO']];if(!isset($defs[$type])||$id<1)throw new InvalidArgumentException('El documento solicitado no es válido.');[$table,$detail,$foreign,$label]=$defs[$type];
 $q=$pdo->prepare("SELECT * FROM $table WHERE id=?");$q->execute([$id]);$document=$q->fetch();if(!$document)throw new RuntimeException('No se encontró el documento.');$q=$pdo->prepare("SELECT d.*,COALESCE(p.sunat_unit_code,'NIU') sunat_unit_code,COALESCE(p.tax_affectation_code,'10') tax_affectation_code FROM $detail d LEFT JOIN tbl_product p ON p.id=d.item_id WHERE d.$foreign=? ORDER BY d.id");$q->execute([$id]);$lines=$q->fetchAll();$company=$pdo->query('SELECT * FROM tbl_company_settings WHERE id=1')->fetch()?:[];$sunat=null;try{$q=$pdo->prepare('SELECT status,sunat_code,sunat_description,digest_value FROM tbl_sunat_document WHERE source_type=? AND source_id=?');$q->execute([$type,$id]);$sunat=$q->fetch()?:null;}catch(Throwable $ignored){}
 $taxes=['Op. gravadas'=>0.0,'Op. exoneradas'=>0.0,'Op. inafectas'=>0.0];foreach($lines as $line){$value=$line['item_quantity']*$line['item_unit_price'];$code=(string)$line['tax_affectation_code'];$taxes[$code==='10'?'Op. gravadas':($code==='20'?'Op. exoneradas':'Op. inafectas')]+=$value;}$taxes['IGV']=(float)$document['total_tax'];if((float)$document['discount_value'])$taxes['Descuento']=(float)$document['discount_value'];$taxes=array_filter($taxes,fn($value)=>abs($value)>.0001);$usd=in_array(strtoupper($document['currency']),['ME','USD'],true);$document['currency_label']=$usd?'DÓLARES AMERICANOS':'SOLES';$document['currency_symbol']=$usd?'US$':'S/';$days=(int)$document['payment_days'];$document['payment_label']=$days?'Crédito a '.$days.' días':'Contado';return compact('company','document','lines','sunat','taxes','label');
}
