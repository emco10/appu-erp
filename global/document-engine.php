<?php
require_once __DIR__ . '/validation.php';

final class AppDocumentStatus
{
    public const DRAFT = 'DRAFT';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const OPEN = 'OPEN';
    public const PARTIALLY_PROCESSED = 'PARTIALLY_PROCESSED';
    public const CLOSED = 'CLOSED';
    public const CANCELLED = 'CANCELLED';
}

/** Physical tables are retained; these adapters expose one line contract. */
function app_document_adapter(string $type): array
{
    $adapters = [
        'quotation' => ['table'=>'tbl_quotation','lines'=>'tbl_quotation_detail','key'=>'quotation_id','product'=>'item_id','quantity'=>'item_quantity'],
        'sales_order' => ['table'=>'tbl_sales_order','lines'=>'tbl_sales_order_detail','key'=>'sales_order_id','product'=>'item_id','quantity'=>'item_quantity'],
        'remission_guide' => ['table'=>'tbl_remission_guide','lines'=>'tbl_remission_guide_detail','key'=>'remission_guide_id','product'=>'product_id','quantity'=>'quantity'],
        'invoice' => ['table'=>'tbl_invoice','lines'=>'tbl_invoice_detail','key'=>'invoice_id','product'=>'item_id','quantity'=>'item_quantity'],
        'receipt' => ['table'=>'tbl_receipt','lines'=>'tbl_receipt_detail','key'=>'receipt_id','product'=>'item_id','quantity'=>'item_quantity'],
        'order' => ['table'=>'tbl_order','lines'=>'tbl_order_detail','key'=>'order_id','product'=>'item_id','quantity'=>'item_quantity'],
        'purchase' => ['table'=>'tbl_purchase','lines'=>'tbl_purchase_detail','key'=>'purchase_id','product'=>'item_id','quantity'=>'item_quantity'],
        'goods_receipt' => ['table'=>'tbl_goods_receipt','lines'=>'tbl_goods_receipt_detail','key'=>'goods_receipt_id','product'=>'product_id','quantity'=>'quantity'],
    ];
    if (!isset($adapters[$type])) throw new InvalidArgumentException('Tipo documental no compatible.');
    return $adapters[$type];
}

function app_document_header(PDO $pdo, string $type, int $id, bool $lock = false): array
{
    if ($lock && !$pdo->inTransaction()) throw new LogicException('El bloqueo documental requiere una transacción.');
    $adapter = app_document_adapter($type);
    $query = $pdo->prepare('SELECT * FROM ' . $adapter['table'] . ' WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]); $header = $query->fetch();
    if (!$header) throw new DomainException('El documento de origen no existe.');
    return $header;
}

function app_document_open_lines(PDO $pdo, string $type, int $id, string $stage): array
{
    app_require_document_engine($pdo);
    if (!in_array($stage, ['CONVERSION','FULFILLMENT','BILLING','CREDIT','RECEPTION'], true)) throw new InvalidArgumentException('Etapa documental inválida.');
    $adapter = app_document_adapter($type);
    $lock = $pdo->inTransaction() ? ' FOR UPDATE' : '';
    $query = $pdo->prepare("SELECT line.*,line.{$adapter['product']} AS product_id,line.{$adapter['quantity']} AS original_quantity FROM {$adapter['lines']} line WHERE line.{$adapter['key']}=? ORDER BY line.id" . $lock);
    $query->execute([$id]); $lines = $query->fetchAll();
    $query = $pdo->prepare("SELECT source_line_id,quantity FROM tbl_document_line_application WHERE source_type=? AND source_id=? AND stage=? AND status='ACTIVE'" . $lock);
    $query->execute([$type,$id,$stage]); $processed = [];
    foreach ($query->fetchAll() as $application) $processed[$application['source_line_id']] = ($processed[$application['source_line_id']] ?? 0) + (float)$application['quantity'];
    return array_map(static function ($line) use ($processed) { $line['processed_quantity'] = $processed[$line['id']] ?? 0; $line['open_quantity'] = max(0, (float)$line['original_quantity'] - $line['processed_quantity']); return $line; }, $lines);
}

function app_require_document_engine(PDO $pdo): void
{
    if (!app_document_engine_available($pdo)) throw new DomainException('Aplica la migración 20261026_document_line_flow.sql para utilizar las relaciones por línea.');
}

function app_document_engine_available(PDO $pdo): bool
{
    static $ready = [];
    $key=spl_object_id($pdo);
    if (!isset($ready[$key])) $ready[$key]=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tbl_document_line_application'")->fetchColumn();
    return $ready[$key];
}

/** Lock the source before inspecting its balance; all copy paths use this rule. */
function app_apply_document_line(PDO $pdo, string $sourceType, int $sourceId, int $sourceLineId, string $targetType, int $targetId, int $targetLineId, string $stage, float $quantity, int $userId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('La copia documental requiere una transacción.');
    if (!is_finite($quantity) || $quantity <= 0 || $userId <= 0) throw new InvalidArgumentException('Cantidad de copia inválida.');
    $policies = ['quotation:sales_order'=>['CONVERSION'],'sales_order:remission_guide'=>['FULFILLMENT'],'sales_order:invoice'=>['BILLING','FULFILLMENT'],'sales_order:receipt'=>['BILLING','FULFILLMENT'],'invoice:remission_guide'=>['FULFILLMENT'],'receipt:remission_guide'=>['FULFILLMENT'],'remission_guide:invoice'=>['BILLING'],'order:goods_receipt'=>['RECEPTION'],'purchase:goods_receipt'=>['RECEPTION']];
    if (!in_array($stage,$policies[$sourceType . ':' . $targetType] ?? [],true)) throw new DomainException('La copia entre estos documentos no está permitida.');
    $header = app_document_header($pdo, $sourceType, $sourceId, true);
    if (in_array((string)$header['status'], ['Anulada','2','4'], true)) throw new DomainException('El documento de origen está cancelado.');
    $lines = array_column(app_document_open_lines($pdo,$sourceType,$sourceId,$stage), null, 'id');
    if (!isset($lines[$sourceLineId])) throw new DomainException('La línea no pertenece al documento de origen.');
    $line = $lines[$sourceLineId];
    $existing = $pdo->prepare('SELECT quantity,status FROM tbl_document_line_application WHERE source_type=? AND source_id=? AND source_line_id=? AND target_type=? AND target_id=? AND target_line_id=? AND stage=?');
    $existing->execute([$sourceType,$sourceId,$sourceLineId,$targetType,$targetId,$targetLineId,$stage]);
    $applied = $existing->fetch();
    if ($applied) {
        if ($applied['status'] === 'ACTIVE' && abs((float)$applied['quantity']-$quantity)<.000001) return;
        throw new DomainException('La aplicación de esta línea ya existe y no puede modificarse.');
    }
    if ($quantity > $line['open_quantity'] + .000001) throw new AppValidationException([app_field_error('lines['.$sourceLineId.'].quantity','OPEN_QUANTITY','Solo quedan ' . rtrim(rtrim(number_format($line['open_quantity'],6,'.',''),'0'),'.') . ' unidades pendientes.')]);
    $target = app_document_adapter($targetType);
    $check = $pdo->prepare("SELECT {$target['product']} AS product_id,{$target['quantity']} AS quantity FROM {$target['lines']} WHERE id=? AND {$target['key']}=? FOR UPDATE");
    $check->execute([$targetLineId,$targetId]); $targetLine = $check->fetch();
    if (!$targetLine || (int)$targetLine['product_id'] !== (int)$line['product_id']) throw new DomainException('La línea destino no coincide con el artículo de origen.');
    $check = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM tbl_document_line_application WHERE target_type=? AND target_id=? AND target_line_id=? AND stage=? AND status='ACTIVE'");
    $check->execute([$targetType,$targetId,$targetLineId,$stage]);
    if ((float)$check->fetchColumn() + $quantity > (float)$targetLine['quantity'] + .000001) throw new DomainException('La cantidad aplicada supera la línea destino.');
    $pdo->prepare('INSERT INTO tbl_document_line_application(source_type,source_id,source_line_id,target_type,target_id,target_line_id,stage,quantity,created_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$sourceType,$sourceId,$sourceLineId,$targetType,$targetId,$targetLineId,$stage,$quantity,$userId]);
}

function app_reverse_document_lines(PDO $pdo, string $targetType, int $targetId, int $userId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('La reversión requiere una transacción.');
    $pdo->prepare("UPDATE tbl_document_line_application SET status='REVERSED',reversed_by=?,reversed_at=NOW() WHERE target_type=? AND target_id=? AND status='ACTIVE'")->execute([$userId,$targetType,$targetId]);
}

function app_order_domain_status(array $order): string
{
    if ($order['status'] === 'Anulada') return AppDocumentStatus::CANCELLED;
    if ($order['status'] === 'Pendiente') return AppDocumentStatus::DRAFT;
    return match ($order['delivery_status'] ?? '') {
        'Completa' => AppDocumentStatus::CLOSED,
        'Parcial' => AppDocumentStatus::PARTIALLY_PROCESSED,
        default => AppDocumentStatus::OPEN,
    };
}

function app_document_request_start(PDO $pdo, string $operation, array $input, int $userId): ?array
{
    if (!$pdo->inTransaction()) throw new LogicException('La operación requiere una transacción.');
    if (!isset($input['request_key']) || $input['request_key']==='') return null;
    $key=$input['request_key'];
    if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$key)) throw new AppValidationException([app_field_error('request_key','INVALID_KEY','La identificación de la operación no es válida. Vuelve a abrir el formulario.')]);
    if (!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tbl_document_request'")->fetchColumn()) throw new DomainException('Aplica la migración 20261027_document_operation_guards.sql antes de registrar la operación.');
    unset($input['request_key']); ksort($input);
    $hash=hash('sha256',json_encode($input,JSON_INVALID_UTF8_SUBSTITUTE));
    $pdo->prepare('INSERT INTO tbl_document_request(user_id,operation,request_key,payload_hash) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE request_key=VALUES(request_key)')->execute([$userId,$operation,$key,$hash]);
    $q=$pdo->prepare('SELECT payload_hash,result_json FROM tbl_document_request WHERE user_id=? AND operation=? AND request_key=? FOR UPDATE');$q->execute([$userId,$operation,$key]);$request=$q->fetch();
    if (!hash_equals($request['payload_hash'],$hash)) throw new DomainException('Esta operación ya se registró con otros datos. Consulta el documento y abre uno nuevo si corresponde.',409);
    return $request['result_json']!==null ? json_decode($request['result_json'],true) : null;
}

function app_document_request_finish(PDO $pdo, string $operation, array $input, int $userId, string $type, int $id, array $result): void
{
    if (empty($input['request_key'])) return;
    $pdo->prepare('UPDATE tbl_document_request SET result_type=?,result_id=?,result_json=? WHERE user_id=? AND operation=? AND request_key=?')->execute([$type,$id,json_encode($result,JSON_UNESCAPED_UNICODE),$userId,$operation,$input['request_key']]);
}
