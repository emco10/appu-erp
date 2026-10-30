<?php

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400); echo 'ERROR'; exit;
}

require '../../global/connection.php';
require_once '../../global/document-history.php';

$providerId = filter_var($_POST['proveedor_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$code = strtoupper(trim((string) ($_POST['proveedor_numero'] ?? '')));
$businessName = trim((string) ($_POST['proveedor_razsoc'] ?? ''));
$country = trim((string) ($_POST['proveedor_pais'] ?? ''));
$city = trim((string) ($_POST['proveedor_ciudad'] ?? ''));
$district = trim((string) ($_POST['proveedor_distrito'] ?? ''));
$address = trim((string) ($_POST['proveedor_direccion'] ?? ''));
$contact1Name = trim((string) ($_POST['proveedor_contnom_1'] ?? ''));
$contact1Phone = trim((string) ($_POST['proveedor_conttelef_1'] ?? ''));
$contact2Name = trim((string) ($_POST['proveedor_contnom_2'] ?? ''));
$contact2Phone = trim((string) ($_POST['proveedor_conttelef_2'] ?? ''));
$bank1 = trim((string) ($_POST['proveedor_banco_1'] ?? ''));
$currency1 = trim((string) ($_POST['proveedor_tipmoneda_1'] ?? ''));
$account1 = trim((string) ($_POST['proveedor_ctacorriente_1'] ?? ''));
$holder1 = trim((string) ($_POST['proveedor_titularcta_1'] ?? ''));
$bank2 = trim((string) ($_POST['proveedor_banco_2'] ?? ''));
$currency2 = trim((string) ($_POST['proveedor_tipmoneda_2'] ?? ''));
$account2 = trim((string) ($_POST['proveedor_ctacorriente_2'] ?? ''));
$holder2 = trim((string) ($_POST['proveedor_titularcta_2'] ?? ''));
$defaultCurrency = strtoupper(trim((string) ($_POST['proveedor_moneda'] ?? 'PEN')));
$paymentDays = filter_var($_POST['proveedor_dias_pago'] ?? 0, FILTER_VALIDATE_INT);
$creditLimit = filter_var($_POST['proveedor_limite_credito'] ?? 0, FILTER_VALIDATE_FLOAT);
$buyerEmployeeId = filter_var($_POST['proveedor_comprador_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$active = (int) ($_POST['proveedor_active_status'] ?? 1) === 0 ? 0 : 1;
$customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];

try {
    if ($code === '' || $businessName === '' || $country === '' || $city === '' || $address === '') throw new InvalidArgumentException('Completa los datos obligatorios del proveedor.');
    if (strlen($code) > 50) throw new InvalidArgumentException('El código del proveedor es demasiado largo.');
    if (!in_array($defaultCurrency, ['PEN','USD','EUR'], true)) throw new InvalidArgumentException('Moneda predeterminada no valida.');
    if ($paymentDays === false || $paymentDays < 0 || $paymentDays > 3650) throw new InvalidArgumentException('Dias de pago no validos.');
    if ($creditLimit === false || $creditLimit < 0) throw new InvalidArgumentException('Limite de credito no valido.');
    if ($buyerEmployeeId) { $reference=$pdo->prepare('SELECT id FROM tbl_employee WHERE id=? AND active_status=1');$reference->execute([$buyerEmployeeId]);if(!$reference->fetchColumn())throw new InvalidArgumentException('Comprador no disponible.'); }
    foreach ([$contact1Phone, $contact2Phone] as $phone) {
        if ($phone !== '' && !preg_match('/^[0-9+() -]{6,20}$/', $phone)) throw new InvalidArgumentException('Revisa los teléfonos del proveedor.');
    }
    foreach ([[$bank1,$currency1,$account1,$holder1],[$bank2,$currency2,$account2,$holder2]] as $bankData) {
        $filled = array_filter($bankData, static fn ($value) => $value !== '');
        if ($filled && count($filled) !== 4) throw new InvalidArgumentException('Completa todos los datos de cada cuenta bancaria utilizada.');
    }
    app_validate_custom_fields($pdo, 'provider', $customValues);

    $pdo->beginTransaction();
    $duplicate = $pdo->prepare('SELECT id FROM tbl_provider WHERE (code=? OR business_name=?) AND (? IS NULL OR id<>?) LIMIT 1');
    $duplicate->execute([$code, $businessName, $providerId, $providerId]);
    if ($duplicate->fetchColumn()) { $pdo->rollBack(); echo 'EXISTE'; exit; }

    $values = [$code,$businessName,$address,$country,$city,$district,$contact1Name,$contact1Phone,$contact2Name,$contact2Phone,$bank1,$currency1,$account1,$holder1,$bank2,$currency2,$account2,$holder2];
    if (!$providerId) {
        $statement = $pdo->prepare('INSERT INTO tbl_provider (code,business_name,address,country,city,district,contact1_name,contact1_phone,contact2_name,contact2_phone,bank1_name,bank1_currency,bank1_account_number,bank1_account_holder,bank2_name,bank2_currency,bank2_account_number,bank2_account_holder,default_currency,payment_days,credit_limit,buyer_employee_id,registration_date,active_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),1)');
        $statement->execute(array_merge($values,[$defaultCurrency,$paymentDays,$creditLimit,$buyerEmployeeId]));
        $providerId = (int) $pdo->lastInsertId();
        $result = 'OK_INSERT';
    } else {
        $exists = $pdo->prepare('SELECT id FROM tbl_provider WHERE id=? LIMIT 1');
        $exists->execute([$providerId]);
        if (!$exists->fetchColumn()) throw new InvalidArgumentException('El proveedor que intentas editar ya no existe.');
        $statement = $pdo->prepare('UPDATE tbl_provider SET code=?,business_name=?,address=?,country=?,city=?,district=?,contact1_name=?,contact1_phone=?,contact2_name=?,contact2_phone=?,bank1_name=?,bank1_currency=?,bank1_account_number=?,bank1_account_holder=?,bank2_name=?,bank2_currency=?,bank2_account_number=?,bank2_account_holder=?,default_currency=?,payment_days=?,credit_limit=?,buyer_employee_id=?,active_status=? WHERE id=?');
        $statement->execute(array_merge($values, [$defaultCurrency,$paymentDays,$creditLimit,$buyerEmployeeId,$active,$providerId]));
        $result = 'OK_UPDATE';
    }
    app_save_custom_fields($pdo, 'provider', $providerId, $customValues);
    $pdo->commit();
    echo $result;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU provider save error: ' . $exception->getMessage());
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo 'ERROR';
}
