<?php
/** Read-only deployment check. Safe for the operational database. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
$required=[
 'tbl_product'=>['tracking_type'],
 'tbl_custom_field_definition'=>['searchable','copy_to_target','visible','display_order','default_value','max_length','min_value','max_value','validation_pattern','section'],
 'tbl_document_line_application'=>['source_type','source_id','source_line_id','target_type','target_id','target_line_id','stage','quantity','status'],
 'tbl_document_request'=>['operation','request_key','payload_hash'],
 'tbl_invoice'=>['document_subtype','inventory_origin','rounding_adjustment','warehouse_id'],
 'tbl_invoice_detail'=>['base_document_type','base_document_id','base_line_id'],
 'tbl_remission_guide'=>['inventory_posted','warehouse_id'],
 'tbl_warehouse_stock'=>['quantity','reserved_quantity','inventory_value','average_unit_cost'],
 'tbl_warehouse_movement'=>['document_line_id','inventory_value_delta'],
 'tbl_goods_receipt'=>['base_document_type','base_document_id','purchase_order_id','provider_code','provider_name','provider_address','currency','supplier_delivery_reference','valuation_currency','valuation_exchange_rate'],
 'tbl_goods_receipt_detail'=>['base_document_type','base_line_id','order_detail_id','item_code','item_description','unit_of_measure','base_unit_cost','base_inventory_value','document_unit_cost','document_inventory_value'],
 'tbl_inventory_document'=>['document_type','status','movement_watermark_id'],
 'tbl_inventory_document_detail'=>['inventory_document_id','line_number','unit_cost','inventory_value'],
 'tbl_incoming_payment'=>['financial_account_id','status'],
 'tbl_incoming_payment_detail'=>['incoming_payment_id','account_receivable_id','applied_amount'],
 'tbl_outgoing_payment'=>['financial_account_id','status'],
 'tbl_outgoing_payment_detail'=>['outgoing_payment_id','account_payable_id','applied_amount'],
 'tbl_user'=>['active_status','last_seen_at'],
 'tbl_employee'=>['active_status'],
];
$query=$pdo->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE()');$actual=[];
foreach($query->fetchAll() as $column)$actual[$column['TABLE_NAME']][$column['COLUMN_NAME']]=true;
$missing=[];foreach($required as $table=>$columns)foreach($columns as $column)if(!isset($actual[$table][$column]))$missing[]=$table.'.'.$column;
if($missing){fwrite(STDERR,'FAIL schema readiness '.DB.': '.implode(', ',$missing).PHP_EOL);exit(1);}
echo 'PASS schema readiness '.DB.' '.count($required).' document/master tables'.PHP_EOL;
