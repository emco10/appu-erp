<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/sales-delivery-billing.php';
$order=['total_net'=>.35,'discount_rate'=>0];
$context=['ordered_quantities'=>(object)[1=>10],'billed_quantities'=>(object)[1=>5],'previous_total'=>.18,'invoice_count'=>1,'line_count'=>1];
$totals=app_delivery_invoice_totals($order,[['order_line_id'=>1,'quantity'=>5,'unit_price'=>.03]],$context);
if($totals['subtotal']!==.15 || $totals['tax']!==.03 || $totals['rounding_adjustment']!==-.01 || $totals['total']!==.17 || round($totals['total']+.18,2)!==.35)throw new RuntimeException('La facturación parcial acumula un centavo adicional.');
$context['ordered_quantities']=(object)[1=>11];
$partial=app_delivery_invoice_totals($order,[['order_line_id'=>1,'quantity'=>5,'unit_price'=>.03]],$context);
if($partial['rounding_adjustment']!==0.0 || $partial['total']!==.18)throw new RuntimeException('Se ajustó el redondeo antes de la última factura.');
$context['ordered_quantities']=(object)[1=>10];$order['total_net']=50;
try{app_delivery_invoice_totals($order,[['order_line_id'=>1,'quantity'=>5,'unit_price'=>.03]],$context);throw new RuntimeException('Se ocultó un importe inconsistente como redondeo.');}catch(DomainException $expected){}
echo "PASS partial billing preserves tax and explains final cent rounding; inconsistent source totals rejected\n";
