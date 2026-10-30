<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/connection.php';
require_once __DIR__ . '/../global/custom-field-configuration.php';
require_once __DIR__ . '/../global/document-history.php';
require_once __DIR__ . '/../global/sales-order-query.php';
if (!str_ends_with(DB, '_qa')) throw new RuntimeException('Use una base _qa.');
function metadataAssert(bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $key = 'qa_metadata_' . bin2hex(random_bytes(4));
    $definition = ['entity'=>'product','field_key'=>$key,'label'=>'Código QA','field_type'=>'text','default_value'=>'ABC','max_length'=>5,'validation_pattern'=>'/^[A-Z]+$/','searchable'=>1,'copy_to_target'=>0,'visible'=>1];
    app_save_custom_field_definition($pdo, $definition);
    $q = $pdo->prepare('SELECT id FROM tbl_custom_field_definition WHERE entity=? AND field_key=?'); $q->execute(['product',$key]); $source = (int) $q->fetchColumn();
    app_save_custom_fields($pdo, 'product', 999999, []);
    $q = $pdo->prepare("SELECT field_value FROM tbl_custom_field_value WHERE field_definition_id=? AND entity='product' AND record_id=999999"); $q->execute([$source]);
    metadataAssert($q->fetchColumn() === 'ABC', 'No se persistió el valor por defecto.');
    $errors = app_custom_field_errors($pdo, 'product', [$source=>'abcdefg']);
    metadataAssert(count(array_filter($errors, fn ($e) => $e['path'] === 'custom_field[' . $source . ']')) === 2, 'No se acumularon longitud y patrón.');
    $targetDefinition = array_merge($definition, ['entity'=>'customer','default_value'=>null]); app_save_custom_field_definition($pdo, $targetDefinition);
    $q = $pdo->prepare('SELECT id FROM tbl_custom_field_definition WHERE entity=? AND field_key=?'); $q->execute(['customer',$key]); $target = (int) $q->fetchColumn();
    app_copy_custom_fields($pdo,'product',999999,'customer',999999);
    $q = $pdo->prepare('SELECT field_value FROM tbl_custom_field_value WHERE field_definition_id=? AND record_id=999999'); $q->execute([$target]);
    metadataAssert($q->fetchColumn() === false, 'Se copió un campo sin autorización.');
    app_save_custom_field_definition($pdo, array_merge($definition, ['copy_to_target'=>1]));
    app_copy_custom_fields($pdo,'product',999999,'customer',999999); $q->execute([$target]);
    metadataAssert($q->fetchColumn() === 'ABC', 'No se copió el campo compatible autorizado.');
    $pdo->prepare('DELETE FROM tbl_custom_field_value WHERE field_definition_id=?')->execute([$target]);
    app_save_custom_field_definition($pdo, array_merge($targetDefinition, ['field_type'=>'number','validation_pattern'=>'','max_length'=>null]));
    app_copy_custom_fields($pdo,'product',999999,'customer',999999); $q->execute([$target]);
    metadataAssert($q->fetchColumn() === false, 'Se copió un campo de tipo incompatible.');
    try { app_save_custom_field_definition($pdo, array_merge($definition, ['visible'=>0,'required_status'=>1,'default_value'=>''])); throw new RuntimeException('Se permitió un campo obligatorio invisible sin default.'); }
    catch (AppValidationException $expected) { metadataAssert(in_array('visible',array_column($expected->errors,'path')), 'Error de visibilidad sin ruta.'); }
    try { app_save_custom_field_definition($pdo, array_merge($definition, ['field_type'=>'date','default_value'=>'2026-10-30','max_length'=>'','validation_pattern'=>''])); throw new RuntimeException('Se cambió el tipo de un campo utilizado.'); }
    catch (DomainException $expected) {}
    $pdo->rollBack();
    echo "PASS custom metadata defaults constraints opt-in compatible copy visibility and used-type safeguards\n";
} catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR,'FAIL custom metadata: '.$error->getMessage().' '.($error instanceof AppValidationException ? json_encode($error->errors) : '').PHP_EOL); exit(1); }
