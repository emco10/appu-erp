<?php
$transferFields = [];
try {
    $transferFields = $pdo->query("SELECT id, field_key, label, field_type, value_list_code, required_status FROM tbl_custom_field_definition WHERE entity='stock_transfer' AND active_status=1 ORDER BY sort_order, id")->fetchAll();
} catch (Throwable $ignore) {
    $transferFields = [];
}
if ($transferFields):
?>
<div class="card card-outline card-secondary mt-3">
    <div class="card-header py-2"><strong><i class="fas fa-sliders-h mr-1"></i>Datos adicionales</strong></div>
    <div class="card-body pb-1"><div class="row">
        <?php foreach ($transferFields as $field): $key = (string) $field['id']; $required = (int) $field['required_status'] === 1; ?>
            <div class="form-group col-md-12">
                <label><?php echo htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8'); ?><?php echo $required ? ' *' : ''; ?></label>
                <?php if ($field['field_type'] === 'select'): ?>
                    <select name="custom_field[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" class="form-control" <?php echo $required ? 'required' : ''; ?>><option value="">Seleccione...</option><?php
                        $options = [];
                        try { $optionQuery = $pdo->prepare('SELECT value_code, label FROM tbl_app_value_option o JOIN tbl_app_value_list l ON l.id=o.list_id WHERE l.code=? AND l.active_status=1 AND o.active_status=1 ORDER BY o.sort_order, o.label'); $optionQuery->execute([(string) $field['value_list_code']]); $options = $optionQuery->fetchAll(); } catch (Throwable $ignore) {}
                        foreach ($options as $option): ?><option value="<?php echo htmlspecialchars((string) $option['value_code'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
                <?php elseif ($field['field_type'] === 'checkbox'): ?>
                    <div><input type="checkbox" name="custom_field[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="1" <?php echo $required ? 'required' : ''; ?>> <span class="text-muted">Sí</span></div>
                <?php else: ?>
                    <input type="<?php echo in_array($field['field_type'], ['number', 'date'], true) ? $field['field_type'] : 'text'; ?>" name="custom_field[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" class="form-control" <?php echo $required ? 'required' : ''; ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div></div>
</div>
<?php endif; ?>
