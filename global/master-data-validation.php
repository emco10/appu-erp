<?php

function app_validate_customer_reference(PDO $pdo, $customerId): int
{
    $id = filter_var($customerId, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Selecciona un cliente registrado.');
    }
    $statement = $pdo->prepare('SELECT client_id FROM tbl_customer WHERE client_id = ? AND active_status = 1 LIMIT 1');
    $statement->execute([$id]);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('El cliente seleccionado ya no existe.');
    }
    return (int) $id;
}

function app_validate_product_reference(PDO $pdo, $productId): int
{
    $id = filter_var($productId, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Selecciona un articulo registrado.');
    }
    $statement = $pdo->prepare('SELECT id FROM tbl_product WHERE id = ? AND active_status = 1 LIMIT 1');
    $statement->execute([$id]);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('Uno de los articulos seleccionados no existe o está inactivo.');
    }
    return (int) $id;
}

function app_validate_product_rows(PDO $pdo, array $rows): void
{
    if (!$rows) {
        throw new InvalidArgumentException('El documento debe contener al menos un articulo.');
    }
    foreach ($rows as $row) {
        app_validate_product_reference($pdo, $row[0] ?? null);
    }
}

function app_validate_provider_reference(PDO $pdo, $providerId): int
{
    $id = filter_var($providerId, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Selecciona un proveedor registrado.');
    }
    $statement = $pdo->prepare('SELECT id FROM tbl_provider WHERE id = ? AND active_status = 1 LIMIT 1');
    $statement->execute([$id]);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('El proveedor seleccionado ya no existe.');
    }
    return (int) $id;
}
