<?php
require_once __DIR__ . '/validation.php';

function app_save_custom_field_definition(PDO $pdo, array $input): void
{
    $entities = ['quotation','sales_order','invoice','receipt','credit_note','purchase_request','order','purchase','goods_receipt','remission_guide','stock_transfer','incoming_payment','outgoing_payment','customer','provider','product','employee','user'];
    $entity = is_string($input['entity'] ?? null) ? $input['entity'] : '';
    $key = strtolower(trim(is_string($input['field_key'] ?? null) ? $input['field_key'] : ''));
    $label = trim(is_string($input['label'] ?? null) ? $input['label'] : '');
    $type = $input['field_type'] ?? 'text';
    $list = strtoupper(trim(is_string($input['value_list_code'] ?? null) ? $input['value_list_code'] : ''));
    $errors = app_validate_fields(['entity' => $entity, 'field_key' => $key, 'label' => $label, 'field_type' => $type], [
        'entity' => ['label' => 'Objeto', 'required' => true, 'validValues' => $entities],
        'field_key' => ['label' => 'Código', 'required' => true, 'regex' => '/^[a-z][a-z0-9_]{1,59}$/'],
        'label' => ['label' => 'Etiqueta', 'required' => true, 'maxLength' => 120],
        'field_type' => ['label' => 'Tipo', 'validValues' => ['text','number','date','select','checkbox']],
    ]);
    if ($type === 'select') {
        $query = $pdo->prepare('SELECT id FROM tbl_app_value_list WHERE code=? AND active_status=1'); $query->execute([$list]);
        if (!$query->fetchColumn()) $errors[] = app_field_error('value_list_code', 'INVALID_REFERENCE', 'Seleccione una lista de valores activa.');
    } else $list = '';
    $query = $pdo->prepare('SELECT * FROM tbl_custom_field_definition WHERE entity=? AND field_key=?'); $query->execute([$entity, $key]);
    $previous = $query->fetch() ?: [];
    $metadata = [];
    foreach (['searchable' => 0, 'copy_to_target' => 0, 'visible' => 1, 'display_order' => 0, 'default_value' => null, 'max_length' => null, 'min_value' => null, 'max_value' => null, 'validation_pattern' => '', 'section' => ''] as $name => $default) {
        $value = $input[$name] ?? $previous[$name] ?? $default;
        if (in_array($name, ['searchable','copy_to_target','visible'], true)) $value = (int) $value === 1 ? 1 : 0;
        elseif (in_array($name, ['display_order','max_length'], true)) {
            $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_INT);
            if ($value === false || ($name === 'max_length' && $value !== null && ($value < 1 || $value > 4096))) $errors[] = app_field_error($name, 'RANGE', $name . ': valor entero fuera de rango.');
            if ($name === 'display_order' && $value === null) $value = 0;
        } elseif (in_array($name, ['min_value','max_value'], true)) {
            $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($value === false || ($value !== null && (!is_finite((float) $value) || abs($value) > 99999999999999))) $errors[] = app_field_error($name, 'NUMBER', 'Ingrese un límite numérico válido.');
        } else {
            if (!is_scalar($value) && $value !== null) $errors[] = app_field_error($name, 'INVALID_TYPE', 'Valor de configuración inválido.');
            $value = is_scalar($value) ? trim((string) $value) : $default;
        }
        $metadata[$name] = $value;
    }
    if ($metadata['min_value'] !== null && $metadata['max_value'] !== null && $metadata['min_value'] > $metadata['max_value']) $errors[] = app_field_error('max_value', 'RANGE', 'El máximo debe ser mayor o igual al mínimo.');
    if (mb_strlen($metadata['section']) > 80) $errors[] = app_field_error('section', 'MAX_LENGTH', 'Sección: máximo 80 caracteres.');
    if (mb_strlen($metadata['validation_pattern']) > 250 || ($metadata['validation_pattern'] !== '' && @preg_match($metadata['validation_pattern'], '') === false)) $errors[] = app_field_error('validation_pattern', 'REGEX', 'El patrón debe ser una expresión regular válida con delimitadores.');
    $default = $metadata['default_value'];
    if ($default !== null && $default !== '') {
        $rules = ['label' => 'Valor por defecto', 'maxLength' => min(255, $metadata['max_length'] ?: 255)];
        if ($metadata['min_value'] !== null) $rules['min'] = $metadata['min_value'];
        if ($metadata['max_value'] !== null) $rules['max'] = $metadata['max_value'];
        if ($metadata['validation_pattern'] !== '' && @preg_match($metadata['validation_pattern'], '') !== false) $rules['regex'] = $metadata['validation_pattern'];
        $rules['customValidator'] = static function ($value) use ($pdo, $type, $list) {
            if ($type === 'number') return is_numeric($value) && is_finite((float) $value);
            if ($type === 'date') return app_valid_date($value);
            if ($type === 'checkbox') return in_array($value, ['0','1'], true);
            if ($type === 'select') { $q = $pdo->prepare('SELECT opt.id FROM tbl_app_value_option opt JOIN tbl_app_value_list list ON list.id=opt.list_id WHERE list.code=? AND list.active_status=1 AND opt.value_code=? AND opt.active_status=1'); $q->execute([$list,$value]); return (bool) $q->fetchColumn(); }
            return true;
        };
        $errors = array_merge($errors, app_validate_fields(['default_value' => $default], ['default_value' => $rules]));
    }
    if (!(int) $metadata['visible'] && (int) ($input['required_status'] ?? 0) && ($default === null || $default === '')) $errors[] = app_field_error('visible', 'BUSINESS_RULE', 'Un campo obligatorio oculto necesita un valor por defecto.');
    if ($errors) throw new AppValidationException($errors);
    $columns = $pdo->query('SHOW COLUMNS FROM tbl_custom_field_definition')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('searchable', $columns, true)) throw new DomainException('Aplica la migración 20261026_custom_field_metadata.sql antes de configurar estos campos.');
    if ($previous && ($previous['field_type'] !== $type || $previous['value_list_code'] !== $list)) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM tbl_custom_field_value WHERE field_definition_id=?'); $q->execute([$previous['id']]);
        if ($q->fetchColumn()) throw new DomainException('El campo ya tiene valores. Conserva su tipo/lista y crea otro campo para cambiar su significado.');
    }
    $names = array_keys($metadata);
    $sql = 'INSERT INTO tbl_custom_field_definition (entity,field_key,label,field_type,value_list_code,required_status,' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, 6 + count($names), '?')) . ') ON DUPLICATE KEY UPDATE label=VALUES(label),field_type=VALUES(field_type),value_list_code=VALUES(value_list_code),required_status=VALUES(required_status),active_status=1,' . implode(',', array_map(fn ($name) => $name . '=VALUES(' . $name . ')', $names));
    $pdo->prepare($sql)->execute(array_merge([$entity,$key,$label,$type,$list,(int) ($input['required_status'] ?? 0) === 1 ? 1 : 0], array_values($metadata)));
}
