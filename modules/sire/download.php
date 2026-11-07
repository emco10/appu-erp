<?php
require __DIR__ . '/_common.php';
app_sire_require_method('GET', 'management.view');
try {
    $parameters = app_sire_parameters('download', $_GET);
    $response = app_sire_forward('GET', 'download', $parameters);
    $file = $response['data'] ?? [];
    $encoded = $file['content_base64'] ?? null;
    $name = $file['filename'] ?? null;
    $hash = $file['sha256'] ?? null;
    if (!is_string($encoded) || strlen($encoded) > 27962032 || !is_string($name) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,240}\.zip$/Di', $name) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Invalid SIRE archive descriptor.');
    $bytes = base64_decode($encoded, true);
    if ($bytes === false || strlen($bytes) > 20971520 || !str_starts_with($bytes, "PK\x03\x04") || !hash_equals($hash, hash('sha256', $bytes))) throw new RuntimeException('Invalid SIRE archive integrity.');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($bytes));
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (Throwable $error) { app_sire_error($error); }
