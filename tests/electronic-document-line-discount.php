<?php
require_once __DIR__.'/../global/electronic-document-pdf.php';

if(abs(appu_electronic_line_total(['item_quantity'=>2,'item_unit_price'=>100,'item_discount_rate'=>10,'item_discounted_total'=>180])-180)>.0001)throw new RuntimeException('No se respetó el importe descontado persistido.');
if(abs(appu_electronic_line_total(['item_quantity'=>2,'item_unit_price'=>100,'item_discount_rate'=>10])-180)>.0001)throw new RuntimeException('El cálculo compatible del descuento de línea es incorrecto.');
if(abs(appu_electronic_line_total(['item_quantity'=>2,'item_unit_price'=>100])-200)>.0001)throw new RuntimeException('El cálculo compatible sin descuento es incorrecto.');
echo "PASS electronic PDF line discount totals\n";
