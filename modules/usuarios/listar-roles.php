<?php

require '../../global/connection.php';

$statement = $pdo->query(
    'SELECT id, name, description
     FROM tbl_role
     ORDER BY id'
);

$roles = [];
foreach ($statement as $role) {
    $roles[] = [
        'id' => (int) $role['id'],
        'text' => $role['name'],
        'description' => $role['description'],
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($roles, JSON_UNESCAPED_UNICODE);

