<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/peru-tax-profile.php';
$db=getenv('APPU_DB_NAME')?:'db_appu_qa';
if($db!=='db_appu_qa')throw new RuntimeException('Only db_appu_qa is permitted.');
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=db_appu_qa;charset=utf8mb4',getenv('APPU_DB_USER')?:'root',getenv('APPU_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=file_get_contents(__DIR__.'/../db/migrations/20261106_create_peru_integrations.sql');
$pdo->exec($sql);$pdo->exec($sql); // The additive migration must be safe to apply again.
foreach(['tbl_partner_tax_profile','tbl_peru_integration_settings','tbl_peru_tax_evaluation'] as $table)if(!app_peru_table_exists($pdo,$table))throw new RuntimeException('Missing integration table.');
$columns=$pdo->query('SHOW COLUMNS FROM tbl_remission_guide')->fetchAll(PDO::FETCH_COLUMN);
foreach(['gross_weight','weight_unit','driver_last_name'] as $column)if(!in_array($column,$columns,true))throw new RuntimeException('Missing GRE column.');
$pdo->beginTransaction();
try{
    $settings=app_peru_settings($pdo);
    foreach(['identity_enabled','gre_enabled','sire_enabled','retention_enabled','detraction_enabled'] as $flag)if((int)$settings[$flag]!==0)throw new RuntimeException('QA integrations must start disabled.');
    $profile=json_encode(['document_number'=>'20999999991','tax_profile'=>['retention_agent'=>false,'perception_agent'=>null,'good_taxpayer'=>true],'source'=>'manual']);
    app_partner_tax_profile_save($pdo,'customer',2147483000,'20999999991',$profile);
    $record=json_decode(app_partner_tax_profile_json($pdo,'customer',2147483000),true,16,JSON_THROW_ON_ERROR);
    if($record['tax_profile']['perception_agent']!==null||$record['tax_profile']['retention_agent']!==false)throw new RuntimeException('Tri-state was corrupted.');
    if(app_partner_tax_profile_json($pdo,'supplier',2147483000)!=='')throw new RuntimeException('Partner types must be isolated.');
    echo "PASS additive migration twice, defaults off, private partner profiles isolated and tri-state preserved; fixture rolled back\n";
}finally{$pdo->rollBack();}
