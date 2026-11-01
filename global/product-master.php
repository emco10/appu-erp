<?php
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/document-history.php';

/** Only master attributes participate: a stock posting must not invalidate an open master. */
function app_product_master_fields(): array
{
    return ['producto_code'=>'code','producto_nombre'=>'name','producto_marca'=>'brand',
        'producto_description'=>'description','producto_unitvalue'=>'unit_value','producto_precio'=>'unit_price',
        'producto_barcode'=>'barcode','producto_sunat_code'=>'sunat_item_code','producto_sunat_unit'=>'sunat_unit_code',
        'producto_tax_code'=>'tax_affectation_code','producto_osce_code'=>'osce_catalog_code',
        'producto_manufacturer_code'=>'manufacturer_code','producto_model'=>'model',
        'producto_tracking_type'=>'tracking_type'];
}

function app_product_master_snapshot(PDO $pdo, int $id, bool $lock = false): array
{
    $query=$pdo->prepare('SELECT * FROM tbl_product WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]); $row=$query->fetch();
    if (!$row) throw new DomainException('El artículo seleccionado ya no existe.');
    $values=['producto_id'=>(string)$id,'producto_codigo'=>'PROD-'.$id,'producto_estado'=>(int)$row['active_status']===0];
    foreach (app_product_master_fields() as $name=>$column) $values[$name]=(string)$row[$column];
    $custom=$pdo->prepare('SELECT field_definition_id,field_value FROM tbl_custom_field_value WHERE entity=? AND record_id=? ORDER BY field_definition_id' . ($lock ? ' FOR UPDATE' : ''));
    $custom->execute(['product',$id]); $customValues=[];
    foreach ($custom->fetchAll() as $field) $customValues[(string)$field['field_definition_id']]=$field['field_value'];
    return ['id'=>$id,'values'=>$values,'custom_fields'=>$customValues,
        'version'=>hash('sha256',json_encode([$values,$customValues],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION))];
}

function app_product_master_errors(PDO $pdo, array $input): array
{
    $schema=[];
    foreach (['producto_code'=>['Código',100], 'producto_nombre'=>['Nombre',300], 'producto_marca'=>['Marca',200],
        'producto_unitvalue'=>['Unidad',100], 'producto_description'=>['Descripción',300], 'producto_barcode'=>['Código de barras',50],
        'producto_sunat_code'=>['Código SUNAT',30], 'producto_sunat_unit'=>['Unidad SUNAT',10],
        'producto_tax_code'=>['Afectación tributaria',10], 'producto_osce_code'=>['Código OSCE',50],
        'producto_manufacturer_code'=>['Código fabricante',50], 'producto_model'=>['Modelo',100]] as $name=>$rule) {
        $schema[$name]=['label'=>$rule[0],'maxLength'=>$rule[1],
            'required'=>in_array($name,['producto_code','producto_nombre','producto_marca','producto_unitvalue'],true)];
    }
    $schema['producto_tracking_type']=['label'=>'Gestión de inventario','required'=>true,
        'validValues'=>['NONE','SERIAL','LOT']];
    $schema['producto_precio']=['label'=>'Precio','required'=>true,'min'=>0,'max'=>999999999,
        'messages'=>['min'=>'El precio debe ser un número mayor o igual que cero.','max'=>'El precio excede el máximo permitido.']];
    $schema['producto_tax_code']=['label'=>'Afectacion tributaria','required'=>true,'validValues'=>['10','20','30','40']];
    $schema['producto_estado']=['label'=>'Estado','validValues'=>['on','']];
    $validatedInput=$input;
    if (!array_key_exists('producto_tracking_type',$validatedInput)) $validatedInput['producto_tracking_type']='NONE';
    $errors=app_validate_fields($validatedInput,$schema);
    if (isset($input['producto_id']) && $input['producto_id']!=='' && !(filter_var($input['producto_id'],FILTER_VALIDATE_INT)>0))
        $errors[]=app_field_error('producto_id','INVALID_ID','Identificador de artículo inválido.');
    if (isset($input['custom_field']) && !is_array($input['custom_field']))
        $errors[]=app_field_error('custom_field','INVALID_TYPE','Los campos adicionales tienen un formato inválido.');
    return array_merge($errors,app_custom_field_errors($pdo,'product',is_array($input['custom_field']??null)?$input['custom_field']:[]));
}

function app_product_master_search(PDO $pdo, array $input): array
{
    $where=[]; $params=[];
    $term=trim((string)($input['q']??''));
    if ($term!=='' && $term!=='*') {
        $pattern='%'.strtr($term,['='=>'==','%'=>'=%','_'=>'=_']).'%';
        $where[]="(code LIKE ? ESCAPE '=' OR name LIKE ? ESCAPE '=' OR brand LIKE ? ESCAPE '=' OR barcode LIKE ? ESCAPE '=' OR CONCAT('PROD-',id)=?)";
        array_push($params,$pattern,$pattern,$pattern,$pattern,$term);
    }
    $status=(string)($input['status']??'');
    if ($status!=='') {
        if (!in_array($status,['0','1'],true)) throw new InvalidArgumentException('Estado de artículo inválido.');
        $where[]='active_status=?'; $params[]=(int)$status;
    }
    $fieldId=(int)($input['custom_field_id']??0);
    if ($fieldId>0) {
        $definition=$pdo->prepare("SELECT id FROM tbl_custom_field_definition WHERE id=? AND entity='product' AND active_status=1 AND searchable=1");
        $definition->execute([$fieldId]);
        if (!$definition->fetchColumn()) throw new InvalidArgumentException('El campo seleccionado no admite búsqueda.');
        $where[]="EXISTS(SELECT 1 FROM tbl_custom_field_value cf WHERE cf.entity='product' AND cf.record_id=p.id AND cf.field_definition_id=? AND cf.field_value=?)";
        array_push($params,$fieldId,trim((string)($input['custom_field_value']??'')));
    }
    $sql=$where?' WHERE '.implode(' AND ',$where):'';
    $count=$pdo->prepare('SELECT COUNT(*) FROM tbl_product p'.$sql); $count->execute($params); $total=(int)$count->fetchColumn();
    $size=25; $pages=(int)ceil($total/$size); $page=max(1,min(max(1,$pages),(int)($input['page']??1)));
    $query=$pdo->prepare('SELECT id,code,name,brand,active_status FROM tbl_product p'.$sql.' ORDER BY id DESC LIMIT '.$size.' OFFSET '.(($page-1)*$size));
    $query->execute($params);
    return ['records'=>$query->fetchAll(),'pagination'=>['page'=>$page,'pages'=>$pages,'total'=>$total,'per_page'=>$size]];
}
