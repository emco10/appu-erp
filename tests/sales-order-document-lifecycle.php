<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../global/connection.php';
require_once __DIR__ . '/../global/sales-order-document.php';
require_once __DIR__ . '/../global/document-numbering.php';
require_once __DIR__ . '/../global/business-partner-details.php';

if (!str_ends_with(DB, '_qa')) throw new RuntimeException('Esta prueba requiere una base _qa.');
function orderAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $user = (int) $pdo->query('SELECT id FROM tbl_user ORDER BY id LIMIT 1')->fetchColumn();
    $warehouse = (int) $pdo->query('SELECT id FROM tbl_warehouse WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    $customer = (int) $pdo->query('SELECT client_id FROM tbl_customer WHERE active_status=1 ORDER BY client_id LIMIT 1')->fetchColumn();
    $product = (int) $pdo->query('SELECT id FROM tbl_product WHERE active_status=1 ORDER BY id LIMIT 1')->fetchColumn();
    $key = 'qa_order_' . uniqid();
    $pdo->prepare("INSERT INTO tbl_custom_field_definition(entity,field_key,label,field_type,required_status) VALUES ('sales_order',?,'Canal obligatorio QA','text',1)")->execute([$key]);
    $field = (int) $pdo->lastInsertId();
    try {
        app_validate_sales_order_input($pdo, ['customer_id' => '', 'warehouse_id' => '', 'delivery_date' => '2026-02-30', 'currency' => 'BAD', 'details' => [['product_id' => 0, 'quantity' => '1.5', 'unit_price' => -1]]]);
        throw new RuntimeException('Se aceptó un documento inválido.');
    } catch (AppValidationException $expected) {
        $paths = array_column($expected->errors, 'path');
        foreach (['customer_id','warehouse_id','delivery_date','currency','details[0].product_id','details[0].quantity','details[0].unit_price',"custom_field[$field]"] as $path) orderAssert(in_array($path, $paths), 'Falta error: ' . $path);
    }
    $input = ['customer_id' => $customer, 'warehouse_id' => $warehouse, 'delivery_date' => '2026-10-30', 'currency' => 'PEN', 'reference' => 'QA original', 'details' => [['product_id' => $product, 'quantity' => 3, 'unit_price' => '10.25']], 'custom_field' => [$field => 'Marketplace']];
    // Supply any pre-existing required metadata as well.
    $defs = $pdo->query("SELECT * FROM tbl_custom_field_definition WHERE entity='sales_order' AND active_status=1 AND required_status=1")->fetchAll();
    foreach ($defs as $def) {
        if ((int) $def['id'] === $field) continue;
        $value = ['text' => 'QA', 'number' => '1', 'date' => '2026-10-30', 'checkbox' => '1'][$def['field_type']] ?? '';
        if ($def['field_type'] === 'select') {
            $q = $pdo->prepare('SELECT opt.value_code FROM tbl_app_value_option opt JOIN tbl_app_value_list list ON list.id=opt.list_id WHERE list.code=? AND list.active_status=1 AND opt.active_status=1 LIMIT 1'); $q->execute([$def['value_list_code']]); $value = $q->fetchColumn();
        }
        $input['custom_field'][$def['id']] = $value;
    }
    $created = app_save_sales_order_document($pdo, $input, $user);
    $id = $created['order_id']; $before = app_sales_order_snapshot($pdo, $id, true);
    orderAssert(abs((float) $before['order']['total_net'] - 36.29) < .001, 'Total autoritativo incorrecto.');
    orderAssert($before['order']['status'] === 'Pendiente', 'La creación debe ser pendiente sin efectos.');
    orderAssert($before['custom_fields']->$field === 'Marketplace', 'El UDF no permanece al consultar.');
    $input['version'] = $before['version']; $input['reference'] = 'QA editada';
    $input['details'][0]['quantity'] = 4;
    app_save_sales_order_document($pdo, $input, $user, $id);
    $after = app_sales_order_snapshot($pdo, $id, true);
    orderAssert($after['version'] !== $before['version'] && (int) $after['details'][0]['item_quantity'] === 4, 'La edición no se persistió.');
    try { app_save_sales_order_document($pdo, $input, $user, $id); throw new RuntimeException('Se aceptó una edición obsoleta.'); }
    catch (DomainException $expected) { orderAssert($expected->getCode() === 409, 'La concurrencia requiere conflicto 409.'); }
    $pdo->prepare("UPDATE tbl_sales_order SET status='Confirmada' WHERE id=?")->execute([$id]);
    $input['version'] = app_sales_order_snapshot($pdo, $id, true)['version'];
    try { app_save_sales_order_document($pdo, $input, $user, $id); throw new RuntimeException('Se editó una orden confirmada.'); }
    catch (DomainException $expected) { orderAssert(str_contains($expected->getMessage(), 'pendientes'), 'Falta restricción de estado.'); }
    $pdo->rollBack();
    echo "PASS sales order aggregate validation create view edit UDF totals optimistic concurrency and posted guard\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL sales order document: ' . $error->getMessage() . PHP_EOL); exit(1);
}
