<?php

function app_partner_assert_exists(PDO $pdo, string $type, int $partnerId): void
{
    if (!in_array($type, ['customer', 'provider'], true) || $partnerId <= 0) throw new InvalidArgumentException('Socio de negocio no válido.');
    $table = $type === 'customer' ? 'tbl_customer' : 'tbl_provider';
    $key = $type === 'customer' ? 'client_id' : 'id';
    $statement = $pdo->prepare("SELECT {$key} FROM {$table} WHERE {$key}=? LIMIT 1");
    $statement->execute([$partnerId]);
    if (!$statement->fetchColumn()) throw new InvalidArgumentException('El socio de negocio ya no existe.');
}

function app_partner_details(PDO $pdo, string $type, int $partnerId): array
{
    app_partner_assert_exists($pdo, $type, $partnerId);
    $addresses = $pdo->prepare('SELECT * FROM tbl_business_partner_address WHERE partner_type=? AND partner_id=? ORDER BY active_status DESC,address_type,is_default DESC,address_name');
    $addresses->execute([$type, $partnerId]);
    $contacts = $pdo->prepare('SELECT * FROM tbl_business_partner_contact WHERE partner_type=? AND partner_id=? ORDER BY active_status DESC,is_default DESC,contact_name');
    $contacts->execute([$type, $partnerId]);
    return ['addresses' => $addresses->fetchAll(), 'contacts' => $contacts->fetchAll()];
}

function app_partner_save_address(PDO $pdo, string $type, int $partnerId, array $data): int
{
    app_partner_assert_exists($pdo, $type, $partnerId);
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $addressType = (string) ($data['address_type'] ?? 'billing');
    if (!in_array($addressType, ['billing', 'shipping', 'other'], true)) throw new InvalidArgumentException('Tipo de dirección no válido.');
    $code = strtoupper(trim((string) ($data['address_code'] ?? '')));
    $name = trim((string) ($data['address_name'] ?? ''));
    $line = trim((string) ($data['address_line'] ?? ''));
    if ($code === '' || $name === '' || $line === '') throw new InvalidArgumentException('Código, nombre y dirección son obligatorios.');
    $isDefault = (int) ($data['is_default'] ?? 0) === 1 ? 1 : 0;
    $pdo->beginTransaction();
    try {
        if ($isDefault) {
            $clear = $pdo->prepare('UPDATE tbl_business_partner_address SET is_default=0 WHERE partner_type=? AND partner_id=? AND address_type=?');
            $clear->execute([$type, $partnerId, $addressType]);
        }
        $values = [$addressType,$code,$name,$line,trim((string)($data['country']??'Perú')),trim((string)($data['department_id']??'')),trim((string)($data['province_id']??'')),trim((string)($data['district_id']??'')),trim((string)($data['city']??'')),trim((string)($data['reference']??'')),$isDefault,(int)($data['active_status']??1)===0?0:1];
        if ($id) {
            $statement=$pdo->prepare('UPDATE tbl_business_partner_address SET address_type=?,address_code=?,address_name=?,address_line=?,country=?,department_id=?,province_id=?,district_id=?,city=?,reference=?,is_default=?,active_status=? WHERE id=? AND partner_type=? AND partner_id=?');
            $statement->execute(array_merge($values,[$id,$type,$partnerId]));
            if (!$statement->rowCount()) { $check=$pdo->prepare('SELECT id FROM tbl_business_partner_address WHERE id=? AND partner_type=? AND partner_id=?'); $check->execute([$id,$type,$partnerId]); if(!$check->fetchColumn()) throw new InvalidArgumentException('La dirección no existe.'); }
        } else {
            $statement=$pdo->prepare('INSERT INTO tbl_business_partner_address(partner_type,partner_id,address_type,address_code,address_name,address_line,country,department_id,province_id,district_id,city,reference,is_default,active_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $statement->execute(array_merge([$type,$partnerId],$values));
            $id=(int)$pdo->lastInsertId();
        }
        if ($isDefault === 1 && $addressType === 'billing' && (int) ($data['active_status'] ?? 1) !== 0) {
            if ($type === 'customer') {
                $master = $pdo->prepare('UPDATE tbl_customer SET address=?,department_id=?,province_id=?,district_id=? WHERE client_id=?');
                $master->execute([$line, trim((string)($data['department_id']??'')), trim((string)($data['province_id']??'')), trim((string)($data['district_id']??'')), $partnerId]);
            } else {
                $master = $pdo->prepare('UPDATE tbl_provider SET address=?,country=?,city=?,district=? WHERE id=?');
                $master->execute([$line, trim((string)($data['country']??'Perú')), trim((string)($data['city']??'')), trim((string)($data['district_id']??'')), $partnerId]);
            }
        }
        $pdo->commit(); return (int)$id;
    } catch(Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack(); throw $exception; }
}

function app_partner_save_contact(PDO $pdo, string $type, int $partnerId, array $data): int
{
    app_partner_assert_exists($pdo, $type, $partnerId);
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT)?:null;
    $name=trim((string)($data['contact_name']??'')); $email=trim((string)($data['email']??''));
    if($name==='') throw new InvalidArgumentException('El nombre del contacto es obligatorio.');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('El correo del contacto no es válido.');
    $isDefault=(int)($data['is_default']??0)===1?1:0;
    $pdo->beginTransaction();
    try {
        if($isDefault){$clear=$pdo->prepare('UPDATE tbl_business_partner_contact SET is_default=0 WHERE partner_type=? AND partner_id=?');$clear->execute([$type,$partnerId]);}
        $values=[$name,trim((string)($data['position_name']??'')),trim((string)($data['phone']??'')),trim((string)($data['mobile']??'')),$email,$isDefault,(int)($data['active_status']??1)===0?0:1];
        if($id){$statement=$pdo->prepare('UPDATE tbl_business_partner_contact SET contact_name=?,position_name=?,phone=?,mobile=?,email=?,is_default=?,active_status=? WHERE id=? AND partner_type=? AND partner_id=?');$statement->execute(array_merge($values,[$id,$type,$partnerId]));if(!$statement->rowCount()){$check=$pdo->prepare('SELECT id FROM tbl_business_partner_contact WHERE id=? AND partner_type=? AND partner_id=?');$check->execute([$id,$type,$partnerId]);if(!$check->fetchColumn())throw new InvalidArgumentException('El contacto no existe.');}}
        else{$statement=$pdo->prepare('INSERT INTO tbl_business_partner_contact(partner_type,partner_id,contact_name,position_name,phone,mobile,email,is_default,active_status) VALUES(?,?,?,?,?,?,?,?,?)');$statement->execute(array_merge([$type,$partnerId],$values));$id=(int)$pdo->lastInsertId();}
        $pdo->commit(); return (int)$id;
    } catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

function app_customer_address_snapshot(PDO $pdo, int $customerId, ?int $addressId, string $preferredType): array
{
    app_partner_assert_exists($pdo, 'customer', $customerId);
    if ($addressId) {
        $statement=$pdo->prepare("SELECT id,address_type,address_line,department_id,province_id,district_id FROM tbl_business_partner_address WHERE id=? AND partner_type='customer' AND partner_id=? AND active_status=1");
        $statement->execute([$addressId,$customerId]);
        $address=$statement->fetch();
        if(!$address) throw new InvalidArgumentException('La dirección seleccionada no pertenece al cliente o está inactiva.');
        return $address;
    }
    $statement=$pdo->prepare("SELECT id,address_type,address_line,department_id,province_id,district_id FROM tbl_business_partner_address WHERE partner_type='customer' AND partner_id=? AND active_status=1 ORDER BY (address_type=?) DESC,is_default DESC,id LIMIT 1");
    $statement->execute([$customerId,$preferredType]);
    $address=$statement->fetch();
    if($address)return $address;
    $statement=$pdo->prepare('SELECT address,department_id,province_id,district_id FROM tbl_customer WHERE client_id=?');$statement->execute([$customerId]);$master=$statement->fetch();
    return ['id'=>null,'address_type'=>$preferredType,'address_line'=>(string)($master['address']??''),'department_id'=>(string)($master['department_id']??''),'province_id'=>(string)($master['province_id']??''),'district_id'=>(string)($master['district_id']??'')];
}

function app_provider_address_snapshot(PDO $pdo, int $providerId, ?int $addressId = null): array
{
    app_partner_assert_exists($pdo, 'provider', $providerId);
    if ($addressId) {
        $statement=$pdo->prepare("SELECT id,address_type,address_line,department_id,province_id,district_id FROM tbl_business_partner_address WHERE id=? AND partner_type='provider' AND partner_id=? AND active_status=1");
        $statement->execute([$addressId,$providerId]);
        $address=$statement->fetch();
        if(!$address) throw new InvalidArgumentException('La direccion seleccionada no pertenece al proveedor o esta inactiva.');
        return $address;
    }
    $statement=$pdo->prepare("SELECT id,address_type,address_line,department_id,province_id,district_id FROM tbl_business_partner_address WHERE partner_type='provider' AND partner_id=? AND active_status=1 ORDER BY is_default DESC,(address_type='billing') DESC,id LIMIT 1");
    $statement->execute([$providerId]);
    $address=$statement->fetch();
    if($address)return $address;
    $statement=$pdo->prepare('SELECT address FROM tbl_provider WHERE id=?');$statement->execute([$providerId]);
    return ['id'=>null,'address_type'=>'billing','address_line'=>(string)$statement->fetchColumn(),'department_id'=>'','province_id'=>'','district_id'=>''];
}
