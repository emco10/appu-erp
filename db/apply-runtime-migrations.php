<?php
/** Additive, repeatable runtime upgrade; preserves operational rows and balances. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/connection.php';
$backup=$argv[1]??'';
if(!str_ends_with(DB,'_qa')&&(!is_file($backup)||filesize($backup)<1024))throw new RuntimeException('Indica un respaldo SQL existente antes de migrar la base operativa.');
$snapshot=static function()use($pdo):array{
 $result=[];foreach(['tbl_customer','tbl_provider','tbl_product','tbl_quotation','tbl_sales_order','tbl_invoice','tbl_receipt','tbl_credit_note','tbl_order','tbl_purchase','tbl_goods_receipt','tbl_warehouse_movement','tbl_accounts_receivable','tbl_accounts_payable','tbl_incoming_payment','tbl_outgoing_payment','tbl_custom_field_value'] as $table)$result[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
 $result['stock']=$pdo->query('SELECT COALESCE(SUM(quantity),0) quantity,COALESCE(SUM(reserved_quantity),0) reserved,COALESCE(SUM(inventory_value),0) value FROM tbl_warehouse_stock')->fetch();
 foreach(['tbl_accounts_receivable','tbl_accounts_payable'] as $table)$result[$table.'_amounts']=$pdo->query('SELECT COALESCE(SUM(total_amount),0) total,COALESCE(SUM(paid_amount),0) paid,COALESCE(SUM(balance_amount),0) balance FROM '.$table)->fetch();
 return $result;
};
$before=$snapshot();
$hasColumn=static function(string $table,string $column)use($pdo):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$q->execute([$table,$column]);return (int)$q->fetchColumn()>0;};
$hasIndex=static function(string $table,string $index)use($pdo):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');$q->execute([$table,$index]);return (int)$q->fetchColumn()>0;};
$columns=['searchable'=>'TINYINT(1) NOT NULL DEFAULT 0','copy_to_target'=>'TINYINT(1) NOT NULL DEFAULT 0','visible'=>'TINYINT(1) NOT NULL DEFAULT 1','display_order'=>'INT NOT NULL DEFAULT 0','default_value'=>'VARCHAR(255) DEFAULT NULL','max_length'=>'INT DEFAULT NULL','min_value'=>'DECIMAL(20,6) DEFAULT NULL','max_value'=>'DECIMAL(20,6) DEFAULT NULL','validation_pattern'=>"VARCHAR(250) NOT NULL DEFAULT ''",'section'=>"VARCHAR(80) NOT NULL DEFAULT ''"];
$changes=0;foreach($columns as $name=>$sql)if(!$hasColumn('tbl_custom_field_definition',$name)){$pdo->exec('ALTER TABLE tbl_custom_field_definition ADD COLUMN '.$name.' '.$sql);$changes++;}
if(!$hasIndex('tbl_custom_field_value','idx_custom_field_search')){$pdo->exec('CREATE INDEX idx_custom_field_search ON tbl_custom_field_value(field_definition_id,entity,field_value(100),record_id)');$changes++;}
foreach([
 ['tbl_invoice','idx_invoice_order_effect','sales_order_id,status,inventory_origin,document_subtype'],
 ['tbl_receipt','idx_receipt_order_effect','sales_order_id,status'],
 ['tbl_remission_guide','idx_guide_source_effect','source_type,source_id,status,inventory_posted'],
] as [$table,$index,$fields])if(!$hasIndex($table,$index)){$pdo->exec("CREATE INDEX {$index} ON {$table}({$fields})");$changes++;}
if(!$hasColumn('tbl_invoice','rounding_adjustment')){$pdo->exec('ALTER TABLE tbl_invoice ADD COLUMN rounding_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0');$changes++;}
foreach([
 ['tbl_goods_receipt','valuation_currency','VARCHAR(10) NULL'],
 ['tbl_goods_receipt','valuation_exchange_rate','DECIMAL(18,8) NULL'],
 ['tbl_goods_receipt_detail','document_unit_cost','DECIMAL(16,6) NULL'],
 ['tbl_goods_receipt_detail','document_inventory_value','DECIMAL(16,4) NULL'],
] as [$table,$column,$definition])if(!$hasColumn($table,$column)){$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");$changes++;}
$pdo->exec("CREATE TABLE IF NOT EXISTS tbl_document_request(user_id INT NOT NULL,operation VARCHAR(40) NOT NULL,request_key VARCHAR(64) NOT NULL,payload_hash CHAR(64) NOT NULL,result_type VARCHAR(30) DEFAULT NULL,result_id INT DEFAULT NULL,result_json TEXT DEFAULT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,operation,request_key),KEY idx_document_request_result(result_type,result_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if($before!==$snapshot())throw new RuntimeException('El control posterior detectó una diferencia de registros o saldos; revisa el respaldo antes de continuar.');
echo 'PASS additive runtime migrations '.DB.' changes='.$changes.'; operational row counts, inventory and financial balances unchanged'.PHP_EOL;
