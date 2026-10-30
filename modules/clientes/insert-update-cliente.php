<?php

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400); echo 'ERROR'; exit;
}

require '../../global/connection.php';
require_once '../../global/document-history.php';

$customerId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
$ruc = preg_replace('/\D+/', '', (string) ($_POST['cliente_ruc'] ?? ''));
$businessName = trim((string) ($_POST['cliente_razsoc'] ?? ''));
$tradeName = trim((string) ($_POST['cliente_nomcom'] ?? ''));
$email = trim((string) ($_POST['cliente_correo'] ?? ''));
$phone = trim((string) ($_POST['cliente_telfij'] ?? ''));
$cellphone = trim((string) ($_POST['cliente_telcel'] ?? ''));
$address = trim((string) ($_POST['cliente_direccion'] ?? ''));
$department = trim((string) ($_POST['cliente_departamento'] ?? ''));
$province = trim((string) ($_POST['cliente_provincia'] ?? ''));
$district = trim((string) ($_POST['cliente_distrito'] ?? ''));
$contact1Name = trim((string) ($_POST['cliente_nomcont_1'] ?? ''));
$contact1Phone = trim((string) ($_POST['cliente_celcont_1'] ?? ''));
$contact2Name = trim((string) ($_POST['cliente_nomcont_2'] ?? ''));
$contact2Phone = trim((string) ($_POST['cliente_celcont_2'] ?? ''));
$commission = filter_var($_POST['cliente_pagocomision'] ?? 0, FILTER_VALIDATE_FLOAT);
$defaultCurrency = strtoupper(trim((string) ($_POST['cliente_moneda'] ?? 'PEN')));
$paymentDays = filter_var($_POST['cliente_dias_pago'] ?? 0, FILTER_VALIDATE_INT);
$creditLimit = filter_var($_POST['cliente_limite_credito'] ?? 0, FILTER_VALIDATE_FLOAT);
$priceListId = filter_var($_POST['cliente_lista_precio_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$salesEmployeeId = filter_var($_POST['cliente_vendedor_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$priceListId = $priceListId ?: ((int) $pdo->query("SELECT id FROM tbl_price_list WHERE code='GENERAL' AND active_status=1 LIMIT 1")->fetchColumn() ?: null);
$registrationDate = trim((string) ($_POST['cliente_fecreg'] ?? date('Y-m-d')));
$active = (int) ($_POST['cliente_active_status'] ?? 1) === 0 ? 0 : 1;
$customValues = is_array($_POST['custom_field'] ?? null) ? $_POST['custom_field'] : [];

try {
    if (!preg_match('/^\d{11}$/', $ruc)) throw new InvalidArgumentException('El RUC debe contener 11 dígitos.');
    if ($businessName === '' || $address === '' || $department === '' || $province === '' || $district === '') throw new InvalidArgumentException('Completa los datos obligatorios del cliente.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('El correo electrónico no es válido.');
    if ($commission === false || $commission < 0) throw new InvalidArgumentException('La comisión no puede ser negativa.');
    if (!in_array($defaultCurrency, ['PEN','USD','EUR'], true)) throw new InvalidArgumentException('Moneda predeterminada no valida.');
    if ($paymentDays === false || $paymentDays < 0 || $paymentDays > 3650) throw new InvalidArgumentException('Dias de pago no validos.');
    if ($creditLimit === false || $creditLimit < 0) throw new InvalidArgumentException('Limite de credito no valido.');
    if ($priceListId) { $reference=$pdo->prepare('SELECT id FROM tbl_price_list WHERE id=? AND active_status=1');$reference->execute([$priceListId]);if(!$reference->fetchColumn())throw new InvalidArgumentException('Lista de precios no disponible.'); }
    if ($salesEmployeeId) { $reference=$pdo->prepare('SELECT id FROM tbl_employee WHERE id=? AND active_status=1');$reference->execute([$salesEmployeeId]);if(!$reference->fetchColumn())throw new InvalidArgumentException('Vendedor no disponible.'); }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $registrationDate);
    if (!$date || $date->format('Y-m-d') !== $registrationDate) throw new InvalidArgumentException('La fecha de registro no es válida.');
    $location = $pdo->prepare('SELECT district.id FROM ubigeo_peru_districts district JOIN ubigeo_peru_provinces province ON province.id=district.province_id JOIN ubigeo_peru_departments department ON department.id=province.department_id WHERE department.id=? AND province.id=? AND district.id=? LIMIT 1');
    $location->execute([$department, $province, $district]);
    if (!$location->fetchColumn()) throw new InvalidArgumentException('La ubicación seleccionada no es válida.');
    app_validate_custom_fields($pdo, 'customer', $customValues);

    $pdo->beginTransaction();
    $duplicate = $pdo->prepare('SELECT client_id FROM tbl_customer WHERE (ruc=? OR business_name=?) AND (? IS NULL OR client_id<>?) LIMIT 1');
    $normalizedId = $customerId ?: null;
    $duplicate->execute([$ruc, $businessName, $normalizedId, $normalizedId]);
    if ($duplicate->fetchColumn()) { $pdo->rollBack(); echo 'EXISTE'; exit; }

    if (!$normalizedId) {
        $statement = $pdo->prepare('INSERT INTO tbl_customer (ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission,default_currency,payment_days,credit_limit,price_list_id,sales_employee_id,registration_date,active_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $statement->execute([$ruc,$businessName,$tradeName,$email,$phone,$cellphone,$address,$department,$province,$district,$contact1Name,$contact1Phone,$contact2Name,$contact2Phone,$commission,$defaultCurrency,$paymentDays,$creditLimit,$priceListId,$salesEmployeeId,$registrationDate,1]);
        $normalizedId = (int) $pdo->lastInsertId();
        $result = 'OK_INSERT';
    } else {
        $exists = $pdo->prepare('SELECT client_id FROM tbl_customer WHERE client_id=? LIMIT 1');
        $exists->execute([$normalizedId]);
        if (!$exists->fetchColumn()) throw new InvalidArgumentException('El cliente que intentas editar ya no existe.');
        $statement = $pdo->prepare('UPDATE tbl_customer SET ruc=?,business_name=?,trade_name=?,email=?,phone=?,cellphone=?,address=?,department_id=?,province_id=?,district_id=?,contact1_name=?,contact1_phone=?,contact2_name=?,contact2_phone=?,commission=?,default_currency=?,payment_days=?,credit_limit=?,price_list_id=?,sales_employee_id=?,registration_date=?,active_status=? WHERE client_id=?');
        $statement->execute([$ruc,$businessName,$tradeName,$email,$phone,$cellphone,$address,$department,$province,$district,$contact1Name,$contact1Phone,$contact2Name,$contact2Phone,$commission,$defaultCurrency,$paymentDays,$creditLimit,$priceListId,$salesEmployeeId,$registrationDate,$active,$normalizedId]);
        $result = 'OK_UPDATE';
    }
    app_save_custom_fields($pdo, 'customer', $normalizedId, $customValues);
    $pdo->commit();
    echo $result;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU customer save error: ' . $exception->getMessage());
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo 'ERROR';
}
