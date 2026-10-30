<?php
$appuPaymentCustomEntities = [
    'incoming_payment' => 'Campos adicionales del cobro',
    'outgoing_payment' => 'Campos adicionales del pago',
];
foreach ($appuPaymentCustomEntities as $appuPaymentEntity => $appuPaymentLabel):
    $appuPaymentFieldsStatement = $pdo->prepare('SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity=? AND active_status=1 ORDER BY id');
    $appuPaymentFieldsStatement->execute([$appuPaymentEntity]);
    $appuPaymentFields = $appuPaymentFieldsStatement->fetchAll();
    if (!$appuPaymentFields) continue;
?>
<div class="appu-payment-custom-fields" data-payment-entity="<?php echo e($appuPaymentEntity); ?>" style="display:none">
  <div class="border-top pt-2 mt-3"><strong><?php echo e($appuPaymentLabel); ?></strong></div>
  <div class="form-row mt-2">
  <?php foreach ($appuPaymentFields as $appuPaymentField): ?>
    <div class="form-group col-md-6">
      <label><?php echo e($appuPaymentField['label']); ?></label>
      <?php if ($appuPaymentField['field_type'] === 'select'): ?>
        <select class="form-control" name="custom_field[<?php echo (int) $appuPaymentField['id']; ?>]" <?php echo $appuPaymentField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php
          $appuPaymentOptions = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label');
          $appuPaymentOptions->execute([$appuPaymentField['value_list_code']]);
          foreach ($appuPaymentOptions->fetchAll() as $appuPaymentOption): ?><option value="<?php echo e($appuPaymentOption['value_code']); ?>"><?php echo e($appuPaymentOption['label']); ?></option><?php endforeach; ?></select>
      <?php elseif ($appuPaymentField['field_type'] === 'checkbox'): ?>
        <div class="custom-control custom-checkbox"><input class="custom-control-input" id="payment-custom-<?php echo (int) $appuPaymentField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $appuPaymentField['id']; ?>]"><label class="custom-control-label" for="payment-custom-<?php echo (int) $appuPaymentField['id']; ?>">Si / No</label></div>
      <?php else: ?>
        <input class="form-control" type="<?php echo $appuPaymentField['field_type'] === 'number' ? 'number' : ($appuPaymentField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $appuPaymentField['id']; ?>]" <?php echo $appuPaymentField['required_status'] ? 'required' : ''; ?>>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
