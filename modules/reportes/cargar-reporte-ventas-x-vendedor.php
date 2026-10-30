<?php
require '../../global/connection.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'error' => 'Este reporte fue integrado en el análisis de ventas por período.',
    'url' => app_base_path() . '/views/reportes/ventas-periodo',
], JSON_UNESCAPED_UNICODE);
