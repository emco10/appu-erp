<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../global/connection.php';
require_once __DIR__.'/../global/product-master.php';
require_once __DIR__.'/qa-http-client.php';
if (!str_ends_with(DB,'_qa')) throw new RuntimeException('Esta prueba requiere una base QA.');
function productAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$client=new AppQaHttpClient(getenv('APPU_QA_URL')?:'http://127.0.0.1:8765',DB);
$prefix='QAMASTER-'.bin2hex(random_bytes(4)); $productIds=[]; $fieldIds=[]; $keys=[];
$path='/modules/productos/consultar-maestro.php'; $save='/modules/productos/insert-update-producto.php';
try {
    $definition=$pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,required_status,active_status,searchable,visible,default_value) VALUES('product',?,?, 'text',?,1,1,?,?)");
    $definition->execute([$prefix.'-required','Canal QA',1,1,null]); $requiredId=(int)$pdo->lastInsertId(); $fieldIds[]=$requiredId;
    $definition->execute([$prefix.'-hidden','Dato interno QA',0,0,'DEFAULT']); $hiddenId=(int)$pdo->lastInsertId(); $fieldIds[]=$hiddenId;
    $definition->execute([$prefix.'-optional','Referencia QA',0,1,'INITIAL']); $optionalId=(int)$pdo->lastInsertId(); $fieldIds[]=$optionalId;
    [$status,$catalog]=$client->request($path.'?action=catalog');
    productAssert($status===200 && in_array($requiredId,array_column($catalog['custom_fields'],'id')),'No se devolvieron los metadatos.');
    [$status,$error]=$client->request($save,['response_format'=>'json','producto_precio'=>'-1']);
    productAssert($status===422 && count($error['errors']??[])>=6,'No se agregaron todos los errores del maestro.');
    productAssert(in_array('custom_field['.$requiredId.']',array_column($error['errors'],'path')),'Falta el error de UDF obligatorio.');
    $custom=[];
    foreach ($catalog['custom_fields'] as $field) {
        if (!(int)$field['required_status'] || (int)$field['id']===$requiredId) continue;
        $custom[$field['id']]=$field['default_value']??(['text'=>'QA','number'=>'1','date'=>'2026-10-27','checkbox'=>'1','select'=>$field['options'][0]['value_code']??''][$field['field_type']]);
    }
    $custom[$requiredId]='CORPORATIVO'; $custom[$optionalId]='BORRAR';
    $key='master-'.bin2hex(random_bytes(12)); $keys[]=$key;
    $input=['response_format'=>'json','request_key'=>$key,'producto_code'=>$prefix,'producto_nombre'=>$prefix.' artículo',
        'producto_marca'=>'APPU','producto_unitvalue'=>'und','producto_precio'=>'19.9','producto_tax_code'=>'10','custom_field'=>$custom];
    [$status,$created]=$client->request($save,$input);
    productAssert($status===200 && ($created['success']??false),'No se creó el artículo: '.json_encode($created));
    $id=(int)$created['id']; $productIds[]=$id;
    [$status,$replay]=$client->request($save,$input);
    productAssert($status===200 && $replay===$created,'El reintento no devolvió exactamente el mismo resultado.');
    $count=$pdo->prepare('SELECT COUNT(*) FROM tbl_product WHERE code=?'); $count->execute([$prefix]); productAssert((int)$count->fetchColumn()===1,'El reintento duplicó el artículo.');
    [$status,$snapshot]=$client->request($path.'?action=get&id='.$id);
    productAssert($status===200 && $snapshot['custom_fields'][$requiredId]==='CORPORATIVO' && $snapshot['custom_fields'][$hiddenId]==='DEFAULT','Los UDF no permanecen al consultar.');
    $pdo->prepare("UPDATE tbl_custom_field_value SET field_value='PRIVATE' WHERE entity='product' AND record_id=? AND field_definition_id=?")->execute([$id,$hiddenId]);
    [$status,$snapshot]=$client->request($path.'?action=get&id='.$id);
    $version=$snapshot['version'];
    // A movement changes stock but does not change the version of editable master attributes.
    $pdo->prepare('UPDATE tbl_product SET stock_quantity=7 WHERE id=?')->execute([$id]);
    $update=array_replace($input,['producto_id'=>$id,'version'=>$version,'producto_nombre'=>$prefix.' editado','producto_precio'=>'25.5','request_key'=>'master-'.bin2hex(random_bytes(12))]);
    $keys[]=$update['request_key']; $update['custom_field'][$optionalId]='';
    [$status,$updated]=$client->request($save,$update);
    productAssert($status===200 && $updated['snapshot']['custom_fields'][$optionalId]==='' && $updated['snapshot']['custom_fields'][$hiddenId]==='PRIVATE','La edición no limpia el UDF o altera el valor oculto: '.json_encode($updated));
    $stock=$pdo->prepare('SELECT stock_quantity FROM tbl_product WHERE id=?'); $stock->execute([$id]); productAssert((int)$stock->fetchColumn()===7,'Editar el maestro modificó el stock.');
    $stale=array_replace($update,['producto_nombre'=>$prefix.' sobrescrito','request_key'=>'master-'.bin2hex(random_bytes(12))]); $keys[]=$stale['request_key'];
    [$status,$error]=$client->request($save,$stale); productAssert($status===409 && $error['code']==='CONFLICT','Se aceptó una edición con versión obsoleta.');
    $changed=array_replace($input,['producto_nombre'=>$prefix.' reintento distinto']);
    [$status,$error]=$client->request($save,$changed); productAssert($status===409,'Se reutilizó una clave confirmada con otros datos.');
    [$status,$current]=$client->request($path.'?action=get&id='.$id);
    productAssert($status===200 && $current['values']['producto_nombre']===$prefix.' editado','El conflicto sobrescribió la ficha.');
    $missingVersion=array_replace($update,['request_key'=>'master-'.bin2hex(random_bytes(12))]); unset($missingVersion['version']);
    [$status,$error]=$client->request($save,$missingVersion); productAssert($status===422 && $error['errors'][0]['path']==='version','La API JSON permite editar sin versión.');
    [$status,$duplicate]=$client->request($save,array_replace($input,['request_key'=>'master-'.bin2hex(random_bytes(12)),'producto_nombre'=>$current['values']['producto_nombre']]));
    productAssert($status===422 && count($duplicate['errors'])===2 && in_array('producto_code',array_column($duplicate['errors'],'path')),'No se devolvieron los campos duplicados.');
    $query=http_build_query(['action'=>'search','q'=>$prefix,'custom_field_id'=>$requiredId,'custom_field_value'=>'CORPORATIVO','status'=>'1']);
    [$status,$found]=$client->request($path.'?'.$query);
    productAssert($status===200 && count($found['records'])===1 && (int)$found['records'][0]['id']===$id,'La búsqueda por atributos y UDF no recuperó el artículo.');
    [$status,$none]=$client->request($path.'?'.http_build_query(['q'=>$prefix.'%'])); productAssert($status===200 && $none['pagination']['total']===0,'La búsqueda interpreta comodines SQL sin escapar.');
    [$status]=$client->request($path.'?custom_field_id=2147483647'); productAssert($status===422,'Se aceptó un campo ajeno al catálogo.');
    $deactivate=array_replace($update,['version'=>$current['version'],'producto_estado'=>'on','request_key'=>'master-'.bin2hex(random_bytes(12))]); $keys[]=$deactivate['request_key'];
    [$status,$inactive]=$client->request($save,$deactivate); productAssert($status===200 && $inactive['snapshot']['values']['producto_estado']===true,'No se persistió la desactivación.');
    [$status,$htmlData,$html]=$client->request('/views/productos/editar-producto?id='.$id);
    productAssert($status===200 && str_contains($html,'data-master-action="edit"') && str_contains($html,'/ajax/registro-producto.js?'),'La URL anterior no usa la ficha común.');
    echo 'PASS HTTP product master: validation, metadata, read/edit, retry, concurrency, search, status, legacy route; no stock effects'.PHP_EOL;
    $fixture=$pdo->prepare("INSERT INTO tbl_product(code,brand,name,description,stock_quantity,unit_price,unit_value,active_status) VALUES(?,'QA',?,'QA',0,1,'und',1)");
    for ($i=0;$i<26;$i++) { $fixture->execute([$prefix.'-'.$i,$prefix.' paginado '.$i]); $productIds[]=(int)$pdo->lastInsertId(); }
    [$status,$page1]=$client->request($path.'?'.http_build_query(['q'=>$prefix,'page'=>1]));
    [$status2,$page2]=$client->request($path.'?'.http_build_query(['q'=>$prefix,'page'=>2]));
    productAssert($status===200 && $status2===200 && count($page1['records'])===25 && count($page2['records'])===2 && $page1['pagination']['total']===27,'La búsqueda no pagina 27 registros en 25 y 2.');
    productAssert(count(array_intersect(array_column($page1['records'],'id'),array_column($page2['records'],'id')))===0,'Las páginas repiten artículos.');
    echo 'PASS HTTP product master pagination 27 records on separate pages of 25 and 2'.PHP_EOL;
} finally {
    $fixtures=$pdo->prepare('SELECT id FROM tbl_product WHERE code=? OR code LIKE ?'); $fixtures->execute([$prefix,$prefix.'-%']);
    $productIds=array_unique(array_merge($productIds,$fixtures->fetchAll(PDO::FETCH_COLUMN)));
    foreach ($productIds as $id) {
        $pdo->prepare("DELETE FROM tbl_document_request WHERE result_type='product' AND result_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tbl_custom_field_value WHERE entity='product' AND record_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM tbl_product WHERE id=?')->execute([$id]);
    }
    foreach ($keys as $key) $pdo->prepare("DELETE FROM tbl_document_request WHERE operation='product_master_save' AND request_key=?")->execute([$key]);
    foreach ($fieldIds as $id) $pdo->prepare('DELETE FROM tbl_custom_field_definition WHERE id=?')->execute([$id]);
}
