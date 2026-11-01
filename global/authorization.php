<?php

function app_can(string $permission): bool
{
    $user = $_SESSION['loggedInUser'] ?? [];

    if (($user['ROLE_CODE'] ?? '') === 'admin') {
        return true;
    }

    $permissions = $user['PERMISSIONS'] ?? [];
    return is_array($permissions) && in_array($permission, $permissions, true);
}

/** Refresh every authenticated HTTP request so role changes revoke access immediately. */
function app_refresh_logged_in_user(PDO $pdo): bool
{
    $id=(int)($_SESSION['loggedInUser']['USERID']??0);
    if($id<1)return false;
    $query=$pdo->prepare("SELECT user.id USERID,user.username USERNAME,user.photo_url PHOTO_URL,user.role_id ROLE_ID,role.code ROLE_CODE,role.name ROLE_NAME,employee.job JOB,CONCAT(employee.name,' ',employee.last_name_1) EMPLOYEE_NAME FROM tbl_user user JOIN tbl_employee employee ON employee.id=user.employee_id AND employee.active_status=1 JOIN tbl_role role ON role.id=user.role_id WHERE user.id=? AND user.active_status=1");
    $query->execute([$id]);$identity=$query->fetch(PDO::FETCH_ASSOC);
    if(!$identity){unset($_SESSION['loggedInUser'],$_SESSION['csrf_token']);return false;}
    $permissions=$pdo->prepare('SELECT permission.code FROM tbl_role_permission assignment JOIN tbl_permission permission ON permission.id=assignment.permission_id WHERE assignment.role_id=? ORDER BY permission.code');$permissions->execute([(int)$identity['ROLE_ID']]);
    $identity['PERMISSIONS']=$permissions->fetchAll(PDO::FETCH_COLUMN);$identity['PHOTO_URL']=basename($identity['PHOTO_URL']?:'default-avatar.png');
    $_SESSION['loggedInUser']=array_replace($_SESSION['loggedInUser'],$identity);
    return true;
}

function app_require_permission(string $permission): void
{
    if (app_can($permission)) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => true,
        'message' => 'No tienes permiso para realizar esta operacion.',
        'required_permission' => $permission,
    ]);
    exit;
}

function app_endpoint_permission(): ?string
{
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (preg_match('#/modules/home\.php$#i', $scriptPath)) {
        return 'dashboard.view';
    }

    if (!preg_match('#/modules/([^/]+)/([^/]+\.php)$#i', $scriptPath, $matches)) {
        return null;
    }

    $directory = strtolower($matches[1]);
    $fileName = strtolower($matches[2]);

    if ($directory === 'aprobaciones') {
        return 'purchases.view';
    }

    if ($directory === 'trazabilidad') {
        if($fileName==='flujo.php'){
            $flowType=strtolower(trim((string)($_GET['type']??'')));
            if(in_array($flowType,['quotation','sales_order','invoice','receipt','credit_note'],true))return 'sales.view';
            if($flowType==='remission_guide')return 'logistics.view';
            if(in_array($flowType,['incoming_payment','outgoing_payment'],true))return 'finance.view';
            return 'purchases.view';
        }
        $documentType = strtolower(trim((string) ($_GET['document_type'] ?? $_POST['document_type'] ?? '')));
        if (in_array($documentType, ['quotation', 'sales_order', 'invoice', 'receipt', 'credit_note'], true)) {
            return 'sales.view';
        }
        if(in_array($documentType,['incoming_payment','outgoing_payment'],true))return 'finance.view';
        if($documentType==='remission_guide')return 'logistics.view';
        if (in_array($documentType,['goods_receipt','inventory_document','stock_transfer'],true)) {
            return 'inventory.view';
        }
        return 'purchases.view';
    }

    if ($directory === 'ventas') {
        return 'sales.view';
    }

    if ($directory === 'compras' && in_array($fileName, ['recepciones.php', 'goods-receipts.php'], true)) {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'purchases.view' : 'inventory.manage';
    }
    if ($directory === 'compras' && $fileName === 'costos-entrega.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'purchases.view' : 'purchases.manage';
    }
    if ($directory === 'compras' && $fileName === 'solicitudes.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'purchases.view' : 'purchases.manage';
    }

    if ($directory === 'logistica' && $fileName === 'guias.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'logistics.view' : 'logistics.manage';
    }

    if ($directory === 'gestion' && $fileName === 'configuracion.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'management.view' : 'management.manage';
    }
    if ($directory === 'gestion' && $fileName === 'series.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'management.view' : 'management.manage';
    }
    if ($directory === 'gestion' && $fileName === 'integraciones.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'management.view' : 'management.manage';
    }
    if ($directory === 'inventory' && $fileName === 'warehouses.php') {
        // La consulta de almacenes alimenta los formularios operativos; la
        // modificación, en cambio, solo corresponde a Gestión.
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? null : 'management.manage';
    }
    if ($directory === 'inventory' && $fileName === 'transfers.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'inventory.view' : 'inventory.manage';
    }
    if ($directory === 'inventory' && $fileName === 'documents.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'inventory.view' : 'inventory.manage';
    }
    if ($directory === 'inventory' && $fileName === 'stock-query.php') {
        return 'inventory.view';
    }
    if ($directory === 'finanzas' && $fileName === 'cuentas.php') {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'finance.view' : 'finance.manage';
    }
    if ($directory === 'facturacion' && $fileName === 'series.php') {
        return 'sales.view';
    }

    if ($directory === 'usuarios' && in_array($fileName, ['obtener-usuarios.php', 'listar-usuarios-xtipo.php'], true)) {
        return 'sales.view';
    }

    $resources = [
        'clientes' => 'customers',
        'proveedores' => 'providers',
        'empleados' => 'employees',
        'usuarios' => 'users',
        'productos' => 'inventory',
        'cotizaciones' => 'sales',
        'facturacion' => 'sales',
        'ordenes' => 'purchases',
        'compras' => 'purchases',
        'logistica' => 'logistics',
        'reportes' => 'reports',
    ];

    if (!isset($resources[$directory])) {
        return null;
    }

    $resource = $resources[$directory];
    if ($resource === 'reports') {
        return 'reports.view';
    }

    $readOnlyPrefixes = [
        'obtener-',
        'listar-',
        'consultar-',
        'filtrar-',
    ];
    $isReadOnly = false;

    foreach ($readOnlyPrefixes as $prefix) {
        if (str_starts_with($fileName, $prefix)) {
            $isReadOnly = true;
            break;
        }
    }

    if ($directory === 'logistica' && $fileName === 'documentos.php') {
        $isReadOnly = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    return $resource . ($isReadOnly ? '.view' : '.manage');
}

function app_enforce_endpoint_permission(): void
{
    $permission = app_endpoint_permission();
    if ($permission !== null) {
        app_require_permission($permission);
    }
}

function app_view_permission(string $route): ?string
{
    $parts = explode('/', trim($route, '/'));
    $section = strtolower($parts[1] ?? 'home');

    if($section==='trazabilidad'){
        $flowType=strtolower(trim((string)($_GET['type']??'')));
        if($flowType===''&&app_can('reports.view'))return 'reports.view';
        if(in_array($flowType,['quotation','sales_order','invoice','receipt','credit_note'],true))return 'sales.view';
        if($flowType==='remission_guide')return 'logistics.view';
        if(in_array($flowType,['incoming_payment','outgoing_payment'],true))return 'finance.view';
        return 'purchases.view';
    }

    // Los almacenes son un maestro/configuracion central de Gestión; las
    // operaciones de stock permanecen en Inventario.
    if ($section === 'productos' && strtolower($parts[2] ?? '') === 'almacenes') {
        return 'management.view';
    }

    $permissions = [
        'home' => 'dashboard.view',
        'clientes' => 'customers.view',
        'proveedores' => 'providers.view',
        'empleados' => 'employees.view',
        'usuarios' => 'users.view',
        'productos' => 'inventory.view',
        'cotizacion' => 'sales.view',
        'ventas' => 'sales.view',
        'facturacion' => 'sales.view',
        'ordenes' => 'purchases.view',
        'compras' => 'purchases.view',
        'logistica' => 'logistics.view',
        'reportes' => 'reports.view',
        'gestion' => 'management.view',
        'finanzas' => 'finance.view',
    ];

    return $permissions[$section] ?? null;
}
