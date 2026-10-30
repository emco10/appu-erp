<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); require $root.'/global/config.php';
session_set_save_handler(fn()=>true,fn()=>true,fn($id)=>'',fn($id,$data)=>true,fn($id)=>true,fn($max)=>0);
app_start_session(); require $root.'/global/connection.php'; require_once $root.'/global/business-partner-details.php';
$suffix=strtoupper(substr(uniqid(),-7)); $customerId=0; $providerId=0;
try {
    $pdo->prepare('INSERT INTO tbl_customer(ruc,business_name,trade_name,email,phone,cellphone,address,department_id,province_id,district_id,contact1_name,contact1_phone,contact2_name,contact2_phone,commission,active_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,1)')->execute(['20'.substr(preg_replace('/\D/','',uniqid()),-9),'CLIENTE DETALLE '.$suffix,'','','','','Dirección principal','15','1501','150131','','','','']);
    $customerId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_provider(code,business_name,address,country,city,district,contact1_name,contact1_phone,contact2_name,contact2_phone,bank1_name,bank1_currency,bank1_account_number,bank1_account_holder,bank2_name,bank2_currency,bank2_account_number,bank2_account_holder,active_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)')->execute(['QA-'.$suffix,'PROVEEDOR DETALLE '.$suffix,'Dirección','Perú','Lima','','','','','','','','','','','','','']);
    $providerId=(int)$pdo->lastInsertId();

    $first=app_partner_save_address($pdo,'customer',$customerId,['address_type'=>'shipping','address_code'=>'ENT-01','address_name'=>'Almacén norte','address_line'=>'Av. Uno 100','country'=>'Perú','is_default'=>1]);
    $second=app_partner_save_address($pdo,'customer',$customerId,['address_type'=>'shipping','address_code'=>'ENT-02','address_name'=>'Almacén sur','address_line'=>'Av. Dos 200','country'=>'Perú','is_default'=>1]);
    $details=app_partner_details($pdo,'customer',$customerId);
    $defaults=array_values(array_filter($details['addresses'],fn($row)=>(int)$row['is_default']===1&&$row['address_type']==='shipping'));
    if(count($defaults)!==1||(int)$defaults[0]['id']!==$second) throw new RuntimeException('No se mantuvo una sola dirección de entrega predeterminada.');
    app_partner_save_address($pdo,'customer',$customerId,['address_type'=>'billing','address_code'=>'FISCAL','address_name'=>'Domicilio fiscal','address_line'=>'Av. Fiscal 300','country'=>'Perú','department_id'=>'15','province_id'=>'1501','district_id'=>'150131','is_default'=>1]);
    $masterAddress=$pdo->prepare('SELECT address FROM tbl_customer WHERE client_id=?');$masterAddress->execute([$customerId]);
    if($masterAddress->fetchColumn()!=='Av. Fiscal 300') throw new RuntimeException('La dirección fiscal predeterminada no actualizó el maestro usado por los documentos.');
    app_partner_save_address($pdo,'customer',$customerId,['id'=>$first,'address_type'=>'shipping','address_code'=>'ENT-01','address_name'=>'Almacén norte editado','address_line'=>'Av. Uno 101','country'=>'Perú','active_status'=>0]);

    $contact1=app_partner_save_contact($pdo,'provider',$providerId,['contact_name'=>'Contacto uno','email'=>'uno@example.test','is_default'=>1]);
    $contact2=app_partner_save_contact($pdo,'provider',$providerId,['contact_name'=>'Contacto dos','position_name'=>'Compras','mobile'=>'999999999','email'=>'dos@example.test','is_default'=>1]);
    $providerDetails=app_partner_details($pdo,'provider',$providerId);
    $contactDefaults=array_values(array_filter($providerDetails['contacts'],fn($row)=>(int)$row['is_default']===1));
    if(count($contactDefaults)!==1||(int)$contactDefaults[0]['id']!==$contact2) throw new RuntimeException('No se mantuvo un solo contacto predeterminado.');
    try { app_partner_save_contact($pdo,'provider',$providerId,['id'=>$contact1,'contact_name'=>'Contacto inválido','email'=>'correo-invalido']); throw new RuntimeException('Se aceptó un correo inválido.'); } catch(InvalidArgumentException $expected) {}
    echo 'PASS business partner multiple addresses contacts defaults lifecycle'.PHP_EOL;
} finally {
    if(isset($pdo)){
        if($customerId){$pdo->prepare("DELETE FROM tbl_business_partner_address WHERE partner_type='customer' AND partner_id=?")->execute([$customerId]);$pdo->prepare("DELETE FROM tbl_business_partner_contact WHERE partner_type='customer' AND partner_id=?")->execute([$customerId]);$pdo->prepare('DELETE FROM tbl_customer WHERE client_id=?')->execute([$customerId]);}
        if($providerId){$pdo->prepare("DELETE FROM tbl_business_partner_address WHERE partner_type='provider' AND partner_id=?")->execute([$providerId]);$pdo->prepare("DELETE FROM tbl_business_partner_contact WHERE partner_type='provider' AND partner_id=?")->execute([$providerId]);$pdo->prepare('DELETE FROM tbl_provider WHERE id=?')->execute([$providerId]);}
    }
}
