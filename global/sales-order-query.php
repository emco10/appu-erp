<?php
require_once __DIR__ . '/validation.php';

function app_query_sales_orders(PDO $pdo, array $filters): array
{
    $where = []; $params = [];
    $errors = app_validate_fields($filters, [
        'q' => ['label' => 'Búsqueda', 'maxLength' => 120],
        'page' => ['label' => 'Página', 'min' => 1, 'max' => 1000000, 'customValidator' => fn ($value) => filter_var($value, FILTER_VALIDATE_INT) !== false],
        'per_page' => ['label' => 'Registros por página', 'min' => 1, 'max' => 200, 'customValidator' => fn ($value) => filter_var($value, FILTER_VALIDATE_INT) !== false],
        'sort' => ['label' => 'Orden', 'validValues' => ['id','number','date','customer','total']],
        'direction' => ['label' => 'Dirección', 'validValues' => ['ASC','DESC']],
    ]);
    $search = isset($filters['search']);
    foreach (['customer_id','warehouse_id','user_id'] as $name) {
        if (($filters[$name] ?? '') === '') continue;
        $id = filter_var($filters[$name], FILTER_VALIDATE_INT);
        if (!$id || $id < 1) $errors[] = app_field_error($name, 'INVALID_REFERENCE', 'El filtro ' . $name . ' debe ser un identificador válido.');
        else { $where[] = 'sales_order.' . $name . '=?'; $params[] = $id; }
    }
    foreach (['date_from' => '>=', 'date_to' => '<='] as $name => $operator) {
        if (($filters[$name] ?? '') === '') continue;
        if (!app_valid_date($filters[$name])) $errors[] = app_field_error($name, 'INVALID_DATE', 'El filtro de fecha no es válido.');
        else { $where[] = 'sales_order.issue_date ' . $operator . ' ?'; $params[] = $filters[$name]; }
    }
    if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_from'] > $filters['date_to']) $errors[] = app_field_error('date_to', 'RANGE', 'La fecha final debe ser mayor o igual a la inicial.');
    if (($filters['status'] ?? '') !== '') {
        if (!in_array($filters['status'], ['Pendiente','Confirmada','Atendida','Anulada'], true)) $errors[] = app_field_error('status', 'VALID_VALUES', 'El estado seleccionado no es válido.');
        else { $where[] = 'sales_order.status=?'; $params[] = $filters['status']; }
    }
    if (($filters['q'] ?? '') !== '') {
        $text = is_string($filters['q']) ? trim($filters['q']) : '';
        $where[] = '(sales_order.number LIKE ? OR sales_order.customer_name LIKE ? OR sales_order.reference LIKE ? OR sales_order.ruc LIKE ?)';
        $params = array_merge($params, array_fill(0, 4, '%' . $text . '%'));
    }
    if (($filters['custom_field_id'] ?? '') !== '') {
        $id = filter_var($filters['custom_field_id'], FILTER_VALIDATE_INT);
        $query = $pdo->prepare("SELECT * FROM tbl_custom_field_definition WHERE id=? AND entity='sales_order' AND active_status=1"); $query->execute([$id ?: 0]);
        $definition = $query->fetch();
        if (!$definition || !(int) ($definition['searchable'] ?? 0)) $errors[] = app_field_error('custom_field_id', 'NOT_SEARCHABLE', 'Seleccione un campo de orden habilitado para búsquedas.');
        elseif (!is_scalar($filters['custom_field_value'] ?? null)) $errors[] = app_field_error('custom_field_value', 'INVALID_TYPE', 'Ingrese el valor del campo a buscar.');
        else {
            $where[] = "EXISTS (SELECT 1 FROM tbl_custom_field_value udf WHERE udf.field_definition_id=? AND udf.entity='sales_order' AND udf.record_id=sales_order.id AND udf.field_value=?)";
            $params[] = $id; $params[] = trim((string) $filters['custom_field_value']);
        }
    }
    $page = max(1, min(1000000, (int) ($filters['page'] ?? 1)));
    $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 25)));
    $sorts = ['id' => 'sales_order.id','number' => 'sales_order.number','date' => 'sales_order.issue_date','customer' => 'sales_order.customer_name','total' => 'sales_order.total_net'];
    $sortKey = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'id';
    $sort = $sorts[$sortKey] ?? $sorts['id'];
    $direction = ($filters['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    if ($errors) throw new AppValidationException($errors, 'Corrige los filtros indicados.');
    $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $from = ' FROM tbl_sales_order sales_order LEFT JOIN tbl_quotation quotation ON quotation.id=sales_order.quotation_id';
    $count = $pdo->prepare('SELECT COUNT(*)' . $from . $condition); $count->execute($params); $total = (int) $count->fetchColumn();
    if ($search) $page = min($page, max(1, (int) ceil($total / $perPage)));
    $sql = 'SELECT sales_order.id,sales_order.number,sales_order.status,sales_order.delivery_status,sales_order.quotation_id,sales_order.customer_name,sales_order.issue_date,sales_order.delivery_date,sales_order.currency,sales_order.total_net,quotation.number AS quotation_number' . $from . $condition . ' ORDER BY ' . $sort . ' ' . $direction . ',sales_order.id ' . $direction;
    if ($search) $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    $query = $pdo->prepare($sql); $query->execute($params);
    return ['error' => false, 'orders' => $query->fetchAll(), 'pagination' => ['page' => $page, 'per_page' => $search ? $perPage : $total, 'total' => $total, 'pages' => $search ? (int) ceil($total / $perPage) : 1]];
}
