<?php

function app_record_document_status(
    PDO $pdo,
    string $documentType,
    int $documentId,
    string $documentNumber,
    string $previousStatus,
    string $newStatus,
    string $action,
    int $userId,
    string $comment = ''
): void {
    $allowedTypes = ['quotation', 'purchase_request', 'order', 'purchase', 'sales_order', 'invoice', 'receipt', 'credit_note', 'goods_receipt', 'remission_guide', 'incoming_payment', 'outgoing_payment', 'stock_transfer', 'landed_cost', 'inventory_document'];
    if (!in_array($documentType, $allowedTypes, true) || $documentId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('Datos de historial de documento invalidos.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO tbl_document_status_history
            (document_type, document_id, document_number, previous_status, new_status, action, comment, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([
        $documentType,
        $documentId,
        substr($documentNumber, 0, 50),
        substr($previousStatus, 0, 50),
        substr($newStatus, 0, 50),
        substr($action, 0, 50),
        substr($comment, 0, 500),
        $userId,
    ]);
}

function app_sales_status_label(int $status): string
{
    $labels = [
        1 => 'Vigente',
        2 => 'Anulado',
        3 => 'Pendiente de Pago',
        4 => 'Cancelado',
    ];

    return $labels[$status] ?? (string) $status;
}

function app_record_document_flow(PDO $pdo, string $sourceType, int $sourceId, string $targetType, int $targetId, int $userId, string $relationshipType = 'BASE_TO_RESULT', ?float $quantityApplied = null): void
{
    if ($sourceId <= 0 || $targetId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('Datos de flujo documental invalidos.');
    }
    $pdo->prepare('INSERT INTO tbl_document_flow (source_type,source_id,target_type,target_id,relationship_type,quantity_applied,created_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity_applied=VALUES(quantity_applied)')->execute([$sourceType,$sourceId,$targetType,$targetId,$relationshipType,$quantityApplied,$userId]);
}

function app_custom_field_definitions(PDO $pdo, string $entity): array
{
    $query=$pdo->prepare('SELECT * FROM tbl_custom_field_definition WHERE entity=? AND active_status=1 ORDER BY id'); $query->execute([$entity]); $fields=$query->fetchAll();
    usort($fields,fn($a,$b)=>[($a['display_order']??0),$a['id']]<=>[($b['display_order']??0),$b['id']]);
    $options=$pdo->prepare('SELECT opt.value_code,opt.label FROM tbl_app_value_option opt JOIN tbl_app_value_list list ON list.id=opt.list_id WHERE list.code=? AND list.active_status=1 AND opt.active_status=1 ORDER BY opt.sort_order,opt.label');
    foreach ($fields as &$field) { $options->execute([$field['value_list_code']]); $field['options']=$options->fetchAll(); }
    unset($field);return $fields;
}

function app_custom_field_copy_values(PDO $pdo, string $sourceEntity, int $sourceId, string $targetEntity): array
{
    $fields = $pdo->prepare("SELECT source.*, target.id AS target_definition_id, target.field_type AS target_field_type, target.value_list_code AS target_list, value.field_value FROM tbl_custom_field_definition source JOIN tbl_custom_field_value value ON value.field_definition_id=source.id AND value.entity=? AND value.record_id=? JOIN tbl_custom_field_definition target ON target.entity=? AND target.field_key=source.field_key AND target.active_status=1 WHERE source.entity=? AND source.active_status=1");
    $fields->execute([$sourceEntity, $sourceId, $targetEntity, $sourceEntity]);
    $values = [];
    foreach ($fields->fetchAll() as $field) {
        if (isset($field['copy_to_target']) && !(int) $field['copy_to_target']) continue;
        if ($field['field_type'] !== $field['target_field_type']) continue;
        if ($field['field_type'] === 'select' && $field['value_list_code'] !== $field['target_list']) continue;
        $values[(int) $field['target_definition_id']] = (string) $field['field_value'];
    }
    return $values;
}

function app_copy_custom_fields(PDO $pdo, string $sourceEntity, int $sourceId, string $targetEntity, int $targetId, array $overrides = []): void
{
    $values=app_custom_field_copy_values($pdo,$sourceEntity,$sourceId,$targetEntity);
    app_save_custom_fields($pdo, $targetEntity, $targetId, array_replace($values,$overrides));
}

function app_custom_field_defaults(PDO $pdo, string $entity, array $values): array
{
    $query = $pdo->prepare('SELECT * FROM tbl_custom_field_definition WHERE entity=? AND active_status=1');
    $query->execute([$entity]);
    foreach ($query->fetchAll() as $definition) {
        if (!array_key_exists($definition['id'], $values) && isset($definition['default_value'])) $values[$definition['id']] = $definition['default_value'];
    }
    return $values;
}

function app_custom_field_errors(PDO $pdo, string $entity, array $values): array
{
    require_once __DIR__ . '/validation.php';
    if ($entity === '') return [];
    $errors = [];
    $values = app_custom_field_defaults($pdo, $entity, $values);
    $definitions = $pdo->prepare('SELECT * FROM tbl_custom_field_definition WHERE entity = ? AND active_status = 1');
    $definitions->execute([$entity]);
    foreach ($definitions->fetchAll() as $definition) {
        $key = (string) $definition['id'];
        if (isset($values[$key]) && !is_scalar($values[$key])) {
            $errors[] = app_field_error('custom_field[' . $key . ']', 'INVALID_TYPE', $definition['label'] . ': valor inválido.');
            continue;
        }
        $value = array_key_exists($key, $values) ? trim((string) $values[$key]) : '';
        $rules = ['label' => $definition['label']];
        if (isset($definition['max_length'])) $rules['maxLength'] = (int) $definition['max_length'];
        if (isset($definition['min_value'])) $rules['min'] = (float) $definition['min_value'];
        if (isset($definition['max_value'])) $rules['max'] = (float) $definition['max_value'];
        if (!empty($definition['validation_pattern'])) $rules['regex'] = $definition['validation_pattern'];
        $errors = array_merge($errors, app_validate_fields(['custom_field[' . $key . ']' => $value], ['custom_field[' . $key . ']' => $rules]));
        if ((int) $definition['required_status'] === 1 && $value === '') {
            $errors[] = app_field_error('custom_field[' . $key . ']', 'REQUIRED', $definition['label'] . ' es obligatorio.');
        }
        if ($value !== '' && $definition['field_type'] === 'select' && trim((string) $definition['value_list_code']) !== '') {
            $option = $pdo->prepare('SELECT opt.id FROM tbl_app_value_option opt JOIN tbl_app_value_list list ON list.id = opt.list_id WHERE list.code = ? AND list.active_status = 1 AND opt.value_code = ? AND opt.active_status = 1 LIMIT 1');
            $option->execute([trim((string) $definition['value_list_code']), $value]);
            if (!$option->fetchColumn()) {
                $errors[] = app_field_error('custom_field[' . $key . ']', 'VALID_VALUES', 'El valor seleccionado no es válido para: ' . $definition['label']);
            }
        }
        if ($value !== '' && $definition['field_type'] === 'number' && (!is_numeric($value) || !is_finite((float) $value))) {
            $errors[] = app_field_error('custom_field[' . $key . ']', 'NUMBER', 'Ingresa un número válido en: ' . $definition['label']);
        }
        if ($value !== '' && $definition['field_type'] === 'date') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) $errors[] = app_field_error('custom_field[' . $key . ']', 'INVALID_DATE', 'Ingresa una fecha válida en: ' . $definition['label']);
        }
        if ($value !== '' && $definition['field_type'] === 'checkbox' && !in_array($value, ['0', '1'], true)) {
            $errors[] = app_field_error('custom_field[' . $key . ']', 'BOOLEAN', 'El valor Sí/No no es válido para: ' . $definition['label']);
        }
    }
    return $errors;
}

function app_validate_custom_fields(PDO $pdo, string $entity, array $values): void
{
    $errors = app_custom_field_errors($pdo, $entity, $values);
    if ($errors) throw new AppValidationException($errors, $errors[0]['message']);
}

function app_save_custom_fields(PDO $pdo, string $entity, int $recordId, array $values, bool $replaceEmpty = false): void
{
    if ($entity === '' || $recordId <= 0) return;
    $values = app_custom_field_defaults($pdo, $entity, $values);
    app_validate_custom_fields($pdo, $entity, $values);
    if (!$values) return;
    $definitions = $pdo->prepare('SELECT id FROM tbl_custom_field_definition WHERE entity = ? AND active_status = 1');
    $definitions->execute([$entity]);
    $save = $pdo->prepare('INSERT INTO tbl_custom_field_value (field_definition_id,entity,record_id,field_value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE field_value=VALUES(field_value)');
    foreach ($definitions->fetchAll(PDO::FETCH_COLUMN) as $definitionId) {
        if (array_key_exists((string) $definitionId, $values)) {
            $value = trim((string) $values[$definitionId]);
            // Un formulario antiguo puede no traer aún los campos personalizados;
            // no borremos un valor existente por recibir una cadena vacía.
            if ($value === '' && !$replaceEmpty) continue;
            $save->execute([(int) $definitionId, $entity, $recordId, $value]);
        }
    }
}
