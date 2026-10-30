<?php
require '../../global/connection.php';
require_once '../../global/inventory-document.php';
header('Content-Type: application/json; charset=utf-8');

$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
    app_require_permission('inventory.view');
    $action=strtolower(trim((string)($_GET['action']??'list')));
    if($action==='master'){
        $warehouseId=filter_var($_GET['warehouse_id']??null,FILTER_VALIDATE_INT);
        $warehouses=$pdo->query("SELECT id,code,name,address FROM tbl_warehouse WHERE active_status=1 ORDER BY name")->fetchAll();
        $sql="SELECT product.id,product.code,product.name,product.brand,product.unit_value,COALESCE(stock.quantity,0) quantity,COALESCE(stock.reserved_quantity,0) reserved_quantity,GREATEST(COALESCE(stock.quantity,0)-COALESCE(stock.reserved_quantity,0),0) available_quantity,COALESCE(stock.average_unit_cost,0) average_unit_cost,(SELECT COUNT(*) FROM tbl_product_lot lot WHERE lot.product_id=product.id) lot_managed FROM tbl_product product LEFT JOIN tbl_warehouse_stock stock ON stock.product_id=product.id AND stock.warehouse_id=? WHERE product.active_status=1 ORDER BY product.name";
        $products=[];
        if($warehouseId!==false&&$warehouseId!==null&&$warehouseId>0){$statement=$pdo->prepare($sql);$statement->execute([(int)$warehouseId]);$products=$statement->fetchAll();}
        echo json_encode(['warehouses'=>$warehouses,'products'=>$products],JSON_UNESCAPED_UNICODE);exit;
    }
    if($action==='detail'){
        $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if($id===false||$id<1){http_response_code(422);echo json_encode(['error'=>'Documento inválido.'],JSON_UNESCAPED_UNICODE);exit;}
        $header=$pdo->prepare("SELECT document.*,warehouse.code warehouse_code,warehouse.name warehouse_name,user.username created_by_name FROM tbl_inventory_document document JOIN tbl_warehouse warehouse ON warehouse.id=document.warehouse_id LEFT JOIN tbl_user user ON user.id=document.created_by WHERE document.id=?");$header->execute([$id]);$document=$header->fetch();
        if(!$document){http_response_code(404);echo json_encode(['error'=>'Documento no encontrado.'],JSON_UNESCAPED_UNICODE);exit;}
        $lines=$pdo->prepare("SELECT detail.*,product.code product_code,product.name product_name,COALESCE((SELECT GROUP_CONCAT(CONCAT(lot.lot_number,': ',application.quantity) ORDER BY lot.expiration_date SEPARATOR ', ') FROM tbl_inventory_lot_application application JOIN tbl_product_lot lot ON lot.id=application.lot_id WHERE application.source_type='inventory_document' AND application.source_id=detail.inventory_document_id AND application.product_id=detail.product_id),detail.lot_number) lot_summary FROM tbl_inventory_document_detail detail JOIN tbl_product product ON product.id=detail.product_id WHERE detail.inventory_document_id=? ORDER BY detail.line_number");$lines->execute([$id]);
        echo json_encode(['document'=>$document,'lines'=>$lines->fetchAll()],JSON_UNESCAPED_UNICODE);exit;
    }
    $type=strtoupper(trim((string)($_GET['document_type']??'')));$status=trim((string)($_GET['status']??''));$search=trim((string)($_GET['search']??''));
    $where=['1=1'];$params=[];if(in_array($type,['ENTRY','ISSUE'],true)){$where[]='document.document_type=?';$params[]=$type;}if(in_array($status,['Registrado','Anulado'],true)){$where[]='document.status=?';$params[]=$status;}if($search!==''){$where[]='(document.number LIKE ? OR document.external_reference LIKE ? OR document.reason LIKE ? OR warehouse.name LIKE ?)';$like='%'.$search.'%';array_push($params,$like,$like,$like,$like);}
    $statement=$pdo->prepare("SELECT document.id,document.number,document.document_type,document.document_date,document.status,document.external_reference,document.reason,document.registration_date,warehouse.code warehouse_code,warehouse.name warehouse_name,COUNT(detail.id) line_count,COALESCE(SUM(detail.quantity),0) total_quantity,COALESCE(SUM(detail.inventory_value),0) total_value FROM tbl_inventory_document document JOIN tbl_warehouse warehouse ON warehouse.id=document.warehouse_id LEFT JOIN tbl_inventory_document_detail detail ON detail.inventory_document_id=document.id WHERE ".implode(' AND ',$where)." GROUP BY document.id ORDER BY document.id DESC LIMIT 500");$statement->execute($params);
    echo json_encode(['documents'=>$statement->fetchAll()],JSON_UNESCAPED_UNICODE);exit;
}
if($method!=='POST'){http_response_code(405);echo json_encode(['error'=>'Método no permitido.'],JSON_UNESCAPED_UNICODE);exit;}
app_require_permission('inventory.manage');$action=strtolower(trim((string)($_POST['action']??'create')));$userId=(int)($_SESSION['loggedInUser']['USERID']??0);
try{
    if(!in_array($action,['create','cancel'],true))throw new DomainException('Acción de inventario inválida.');
    $pdo->beginTransaction();
    if($action==='cancel'){$id=filter_var($_POST['document_id']??null,FILTER_VALIDATE_INT);if($id===false)throw new DomainException('Documento inválido.');app_inventory_document_cancel($pdo,(int)$id,(string)($_POST['cancellation_reason']??''),$userId);$pdo->commit();echo json_encode(['message'=>'Documento anulado y existencias revertidas correctamente.'],JSON_UNESCAPED_UNICODE);exit;}
    $lines=json_decode((string)($_POST['lines']??''),true);if(!is_array($lines))throw new DomainException('Las líneas del documento no son válidas.');
    $result=app_inventory_document_create($pdo,['document_type'=>$_POST['document_type']??'','warehouse_id'=>$_POST['warehouse_id']??null,'document_date'=>$_POST['document_date']??'','external_reference'=>$_POST['external_reference']??'','reason'=>$_POST['reason']??'','notes'=>$_POST['notes']??'','lines'=>$lines],$userId);
    $pdo->commit();echo json_encode(['message'=>($result['document_type']==='ENTRY'?'Entrada ':'Salida ').$result['number'].' registrada correctamente.','document'=>$result],JSON_UNESCAPED_UNICODE);
}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();error_log('APPU inventory document: '.$exception->getMessage());http_response_code(422);echo json_encode(['error'=>$exception instanceof DomainException?$exception->getMessage():'No se pudo procesar el documento de inventario.'],JSON_UNESCAPED_UNICODE);}
