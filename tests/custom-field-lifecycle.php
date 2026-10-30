<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/global/config.php';
session_set_save_handler(fn () => true, fn () => true, fn ($id) => '', fn ($id, $data) => true, fn ($id) => true, fn ($max) => 0);
app_start_session();
require $root . '/global/connection.php';
require_once $root . '/global/document-history.php';

$pdo->beginTransaction();
try {
    $suffix = substr(uniqid(), -6);
    $listCode = 'QA_CHANNEL_' . strtoupper($suffix);
    $pdo->prepare('INSERT INTO tbl_app_value_list(code,name) VALUES (?,?)')->execute([$listCode, 'Canal QA']);
    $listId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_app_value_option(list_id,value_code,label,sort_order) VALUES (?,?,?,1)')->execute([$listId, 'PROJECTS', 'Proyectos']);
    $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,value_list_code,required_status) VALUES ('customer',?,'Canal QA','select',?,1)")->execute(['qa_channel_' . strtolower($suffix), $listCode]);
    $selectId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,value_list_code,required_status) VALUES ('customer',?,'Importe QA','number','',0)")->execute(['qa_amount_' . strtolower($suffix)]);
    $numberId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,value_list_code,required_status) VALUES ('customer',?,'Fecha QA','date','',0)")->execute(['qa_date_' . strtolower($suffix)]);
    $dateId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_customer(ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)')->execute(['20999999871','CLIENTE CAMPO QA','CAMPO QA','','','','','','','','','','','']);
    $customerId = (int) $pdo->lastInsertId();
    app_save_custom_fields($pdo, 'customer', $customerId, [(string)$selectId => 'PROJECTS', (string)$numberId => '125.50', (string)$dateId => '2026-10-25']);
    $saved = $pdo->prepare("SELECT field_definition_id,field_value FROM tbl_custom_field_value WHERE entity='customer' AND record_id=? ORDER BY field_definition_id");
    $saved->execute([$customerId]);
    if (count($saved->fetchAll()) !== 3) throw new RuntimeException('No se guardaron todos los campos personalizados.');
    foreach ([[(string)$selectId => 'INVALID'], [(string)$numberId => 'ABC'], [(string)$dateId => '25/10/2026']] as $invalid) {
        try { app_validate_custom_fields($pdo, 'customer', array_merge([(string)$selectId => 'PROJECTS'], $invalid)); throw new RuntimeException('Se aceptó un valor personalizado inválido.'); }
        catch (InvalidArgumentException $expected) {}
    }
    echo 'PASS custom field list number date persistence validation' . PHP_EOL;
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL custom field lifecycle: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
