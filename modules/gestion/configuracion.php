<?php
require '../../global/connection.php';
require_once '../../global/custom-field-configuration.php';
require_once '../../global/currency.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$json = static function (int $status, array $data): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; };
if ($method === 'GET') {
    app_require_permission('management.view');
    $company = $pdo->query('SELECT * FROM tbl_company_settings WHERE id = 1')->fetch() ?: [];
    $lists = $pdo->query('SELECT * FROM tbl_app_value_list ORDER BY name')->fetchAll();
    foreach ($lists as &$list) {
        $stmt = $pdo->prepare('SELECT * FROM tbl_app_value_option WHERE list_id = ? ORDER BY sort_order, label');
        $stmt->execute([(int) $list['id']]);
        $list['options'] = $stmt->fetchAll();
    }
    $fields = $pdo->query('SELECT * FROM tbl_custom_field_definition ORDER BY entity, label')->fetchAll();
    $roles = $pdo->query('SELECT id, code, name, description FROM tbl_role ORDER BY id')->fetchAll();
    $permissions = $pdo->query('SELECT id, code, name FROM tbl_permission ORDER BY code')->fetchAll();
    $rolePermissionRows = $pdo->query('SELECT role_id, permission_id FROM tbl_role_permission ORDER BY role_id, permission_id')->fetchAll();
    $rolePermissionMap = [];
    foreach ($rolePermissionRows as $rolePermission) {
        $rolePermissionMap[(int) $rolePermission['role_id']][] = (int) $rolePermission['permission_id'];
    }
    foreach ($roles as &$role) {
        $role['permission_ids'] = $rolePermissionMap[(int) $role['id']] ?? [];
    }
    unset($role);
    $financialAccounts = $pdo->query('SELECT id, code, name, account_type, currency, account_number, active_status FROM tbl_financial_account ORDER BY account_type, name')->fetchAll();
    $priceLists = $pdo->query('SELECT id,code,name,currency,factor,active_status FROM tbl_price_list ORDER BY name')->fetchAll();
    $connectedUsers = $pdo->query("SELECT user.id, user.username, COALESCE(NULLIF(TRIM(CONCAT(employee.name, ' ', employee.last_name_1, ' ', employee.last_name_2)), ''), user.username) AS display_name, user.last_seen_at FROM tbl_user user LEFT JOIN tbl_employee employee ON employee.id = user.employee_id WHERE user.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY user.last_seen_at DESC")->fetchAll();
    $json(200, ['company' => $company, 'lists' => $lists, 'fields' => $fields, 'roles' => $roles, 'permissions' => $permissions, 'financial_accounts' => $financialAccounts, 'price_lists' => $priceLists, 'connected_users' => $connectedUsers]);
}
app_require_permission('management.manage');
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
try {
    if ($action === 'company') {
        $taxId = preg_replace('/\D/', '', (string) ($_POST['tax_id'] ?? ''));
        $legalName = trim((string) ($_POST['legal_name'] ?? ''));
        $tradeName = trim((string) ($_POST['trade_name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $currency = strtoupper(trim((string) ($_POST['currency'] ?? 'PEN')));
        if (!preg_match('/^(10|20)\d{9}$/', $taxId) || $legalName === '') { $json(422, ['error' => 'Ingresa un RUC peruano válido y la razón social.']); }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $json(422, ['error' => 'El correo de la empresa no es válido.']); }
        if (!in_array($currency, ['PEN', 'USD', 'EUR'], true)) { $json(422, ['error' => 'La moneda base no es válida.']); }
        $previousCurrency=$pdo->query('SELECT currency FROM tbl_company_settings WHERE id=1')->fetchColumn();
        if($previousCurrency!==false&&app_currency_code((string)$previousCurrency)!==$currency){$valued=(int)$pdo->query('SELECT (SELECT COUNT(*) FROM tbl_warehouse_movement WHERE ABS(inventory_value_delta)>0.000001)+(SELECT COUNT(*) FROM tbl_warehouse_stock WHERE ABS(inventory_value)>0.000001)')->fetchColumn();if($valued>0)throw new DomainException('La empresa ya tiene movimientos de inventario valorizados. No se puede cambiar su moneda base.');}
        foreach (['legal_name'=>[$legalName,200],'trade_name'=>[$tradeName,200],'address'=>[$address,300],'phone'=>[$phone,50],'email'=>[$email,150]] as [$value,$length]) if(mb_strlen($value)>$length) throw new DomainException('Uno de los datos de empresa supera la longitud permitida.');
        $pdo->prepare('INSERT INTO tbl_company_settings (id,tax_id,legal_name,trade_name,address,phone,email,currency) VALUES (1,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tax_id=VALUES(tax_id),legal_name=VALUES(legal_name),trade_name=VALUES(trade_name),address=VALUES(address),phone=VALUES(phone),email=VALUES(email),currency=VALUES(currency)')->execute([
            $taxId, $legalName, $tradeName, $address, $phone, $email, $currency,
        ]);
    } elseif ($action === 'value_list') {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        if (!preg_match('/^[A-Z][A-Z0-9_]{1,59}$/', $code) || $name === '' || mb_strlen($name)>120) {
            $json(422, ['error' => 'El codigo de la lista solo admite letras, numeros y guion bajo.']);
        }
        $pdo->prepare('INSERT INTO tbl_app_value_list (code,name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), active_status=1')->execute([$code, $name]);
    } elseif ($action === 'value_option') {
        $listId = filter_var($_POST['list_id'] ?? null, FILTER_VALIDATE_INT);
        $valueCode = strtoupper(trim((string) ($_POST['value_code'] ?? '')));
        $label = trim((string) ($_POST['label'] ?? ''));
        $optionId=filter_var($_POST['option_id']??null,FILTER_VALIDATE_INT);
        if ($listId === false || $listId < 1 || !preg_match('/^[A-Z][A-Z0-9_]{1,59}$/', $valueCode) || $label === '' || mb_strlen($label)>120) {
            $json(422, ['error' => 'La opcion de lista es invalida.']);
        }
        $listCheck = $pdo->prepare('SELECT id FROM tbl_app_value_list WHERE id = ? AND active_status = 1');
        $listCheck->execute([$listId]);
        if (!$listCheck->fetchColumn()) { $json(422, ['error' => 'La lista seleccionada no existe o esta inactiva.']); }
        if($optionId!==false&&$optionId!==null&&$optionId>0){
            $optionCheck=$pdo->prepare('SELECT list_id,value_code FROM tbl_app_value_option WHERE id=?');$optionCheck->execute([$optionId]);$previous=$optionCheck->fetch();
            if(!$previous)throw new DomainException('La opción no existe.');
            if((int)$previous['list_id']!==(int)$listId||$previous['value_code']!==$valueCode)throw new DomainException('Conserva la lista y el código de la opción. Solo se puede editar su nombre y orden.');
            $pdo->prepare('UPDATE tbl_app_value_option SET label=?,sort_order=? WHERE id=?')->execute([$label,(int)($_POST['sort_order']??0),$optionId]);$json(200,['message'=>'Opción actualizada correctamente.']);
        }
        $duplicateCheck = $pdo->prepare('SELECT id FROM tbl_app_value_option WHERE list_id = ? AND value_code = ?');
        $duplicateCheck->execute([$listId, $valueCode]);
        if ($duplicateCheck->fetchColumn()) { $json(422, ['error' => 'Ya existe una opcion con ese codigo en la lista.']); }
        $pdo->prepare('INSERT INTO tbl_app_value_option (list_id,value_code,label,sort_order) VALUES (?,?,?,?)')->execute([$listId, $valueCode, $label, (int) ($_POST['sort_order'] ?? 0)]);
    } elseif ($action === 'value_option_toggle') {
        $optionId = filter_var($_POST['option_id'] ?? null, FILTER_VALIDATE_INT);
        $active = (int) ($_POST['active_status'] ?? 0) === 1 ? 1 : 0;
        if ($optionId === false || $optionId < 1) { $json(422, ['error' => 'Opcion invalida.']); }
        $exists=$pdo->prepare('SELECT id FROM tbl_app_value_option WHERE id=?');$exists->execute([$optionId]);if(!$exists->fetchColumn())throw new DomainException('La opción no existe.');
        $pdo->prepare('UPDATE tbl_app_value_option SET active_status=? WHERE id=?')->execute([$active, $optionId]);
    } elseif ($action === 'custom_field') {
        app_save_custom_field_definition($pdo, $_POST);
        $json(200, ['message' => 'Campo personalizado guardado.']);
    } elseif ($action === 'custom_field_toggle') {
        $fieldId = filter_var($_POST['field_id'] ?? null, FILTER_VALIDATE_INT);
        $active = (int) ($_POST['active_status'] ?? 0) === 1 ? 1 : 0;
        if ($fieldId === false || $fieldId < 1) { $json(422, ['error' => 'Campo personalizado invalido.']); }
        $exists=$pdo->prepare('SELECT id FROM tbl_custom_field_definition WHERE id=?');$exists->execute([$fieldId]);if(!$exists->fetchColumn())throw new DomainException('El campo personalizado no existe.');
        $pdo->prepare('UPDATE tbl_custom_field_definition SET active_status=? WHERE id=?')->execute([$active, $fieldId]);
    } elseif ($action === 'role_create') {
        $code=strtolower(trim((string)($_POST['code']??'')));$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));
        if(!preg_match('/^[a-z][a-z0-9_]{1,49}$/',$code)||$name===''||mb_strlen($name)>100||mb_strlen($description)>255)throw new DomainException('Ingresa un código, nombre y descripción de perfil válidos.');
        $exists=$pdo->prepare('SELECT id FROM tbl_role WHERE code=?');$exists->execute([$code]);if($exists->fetchColumn()){$json(409,['error'=>'Ya existe un perfil con ese código.']);}
        $pdo->prepare('INSERT INTO tbl_role(code,name,description) VALUES(?,?,?)')->execute([$code,$name,$description]);$json(201,['message'=>'Perfil creado. Asigna sus permisos en la sección de perfiles.','role_id'=>(int)$pdo->lastInsertId()]);
    } elseif ($action === 'role') {
        $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($roleId === false || $roleId < 1 || $name === '' || mb_strlen($name)>100 || mb_strlen($description)>255) { $json(422, ['error' => 'Perfil invalido.']); }
        $roleCheck = $pdo->prepare('SELECT code FROM tbl_role WHERE id = ?');
        $roleCheck->execute([$roleId]);
        $roleCode=$roleCheck->fetchColumn();if (!$roleCode) { $json(422, ['error' => 'Perfil no encontrado.']); }
        $permissionIds = $_POST['permission_ids'] ?? [];
        if (!is_array($permissionIds)) { $permissionIds = [$permissionIds]; }
        foreach($permissionIds as $permissionId)if(filter_var($permissionId,FILTER_VALIDATE_INT)===false||(int)$permissionId<1)throw new DomainException('La selección de permisos no es válida.');
        $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn ($id) => $id > 0)));
        if($roleCode==='admin')$permissionIds=array_map('intval',$pdo->query('SELECT id FROM tbl_permission')->fetchAll(PDO::FETCH_COLUMN));
        elseif($permissionIds){$valid=$pdo->prepare('SELECT COUNT(*) FROM tbl_permission WHERE id IN ('.implode(',',array_fill(0,count($permissionIds),'?')).')');$valid->execute($permissionIds);if((int)$valid->fetchColumn()!==count($permissionIds))throw new DomainException('Uno o más permisos no existen. No se modificó el perfil.');}
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE tbl_role SET name=?, description=? WHERE id=?')->execute([$name, $description, $roleId]);
        $pdo->prepare('DELETE FROM tbl_role_permission WHERE role_id=?')->execute([$roleId]);
        $permissionCheck = $pdo->prepare('SELECT id FROM tbl_permission WHERE id=?');
        $permissionInsert = $pdo->prepare('INSERT INTO tbl_role_permission (role_id, permission_id) VALUES (?, ?)');
        foreach ($permissionIds as $permissionId) {
            $permissionCheck->execute([$permissionId]);
            if ($permissionCheck->fetchColumn()) { $permissionInsert->execute([$roleId, $permissionId]); }
        }
        $pdo->commit();
    } elseif ($action === 'price_list') {
        $listId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'PEN')));
        $factor = filter_var($_POST['factor'] ?? 1, FILTER_VALIDATE_FLOAT);
        $active = (int)($_POST['active_status'] ?? 1) === 1 ? 1 : 0;
        if (!preg_match('/^[A-Z][A-Z0-9_-]{1,29}$/',$code) || $name==='' || mb_strlen($name)>100 || !in_array($currency,['PEN','USD','EUR'],true) || $factor===false || !is_finite((float)$factor) || $factor<0.0001 || $factor>999999.9999 || abs((float)$factor-round((float)$factor,4))>0.0000001) $json(422,['error'=>'Datos de lista de precios inválidos. El factor admite cuatro decimales.']);
        if ($listId) $pdo->prepare('UPDATE tbl_price_list SET code=?,name=?,currency=?,factor=?,active_status=? WHERE id=?')->execute([$code,$name,$currency,$factor,$active,$listId]);
        else $pdo->prepare('INSERT INTO tbl_price_list(code,name,currency,factor,active_status) VALUES(?,?,?,?,?)')->execute([$code,$name,$currency,$factor,$active]);
    } elseif ($action === 'financial_account') {
        $accountId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $type = trim((string)($_POST['account_type'] ?? 'Caja'));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'PEN')));
        $number = trim((string)($_POST['account_number'] ?? ''));
        $active = (int)($_POST['active_status'] ?? 1);
        if (!preg_match('/^[A-Z0-9_-]{1,30}$/',$code) || $name === '' || mb_strlen($name)>120 || mb_strlen($number)>100 || !in_array($type, ['Caja', 'Banco'], true) || !in_array($currency,['PEN','USD','EUR'],true)) $json(422, ['error' => 'Datos de cuenta financiera inválidos.']);
        if ($accountId !== false && $accountId !== null && $accountId > 0) {
            $previous=$pdo->prepare('SELECT currency FROM tbl_financial_account WHERE id=?');$previous->execute([$accountId]);$previousCurrency=$previous->fetchColumn();if($previousCurrency===false)throw new DomainException('La cuenta financiera no existe.');
            if($previousCurrency!==$currency){$used=$pdo->prepare('SELECT (SELECT COUNT(*) FROM tbl_incoming_payment WHERE financial_account_id=?)+(SELECT COUNT(*) FROM tbl_outgoing_payment WHERE financial_account_id=?)');$used->execute([$accountId,$accountId]);if((int)$used->fetchColumn()>0)throw new DomainException('La cuenta financiera ya tiene pagos. Conserva su moneda y crea otra cuenta para una moneda diferente.');}
            $pdo->prepare('UPDATE tbl_financial_account SET code=?,name=?,account_type=?,currency=?,account_number=?,active_status=? WHERE id=?')->execute([$code, $name, $type, $currency, $number, $active ? 1 : 0, $accountId]);
        } else {
            $pdo->prepare('INSERT INTO tbl_financial_account (code,name,account_type,currency,account_number,active_status) VALUES (?,?,?,?,?,?)')->execute([$code, $name, $type, $currency, $number, $active ? 1 : 0]);
        }
    } else { $json(422, ['error' => 'Accion de gestion invalida.']); }
    $json(200, ['message' => 'Configuracion guardada correctamente.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('APPU configuration error: ' . $exception->getMessage());
    app_api_error($exception, 'No se pudo guardar la configuración.');
}
