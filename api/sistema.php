<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

require_once __DIR__ . '/../../repo-api/config/db.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT NOW() AS fecha_bd");
    $row  = $stmt->fetch();
    $fecha_bd = $row['fecha_bd'];
} catch (Exception $e) {
    $fecha_bd = 'Error: ' . $e->getMessage();
}

echo json_encode([
    'ok'         => true,
    'servidor'   => date('d/m/Y H:i:s'),
    'base_datos' => $fecha_bd,
]);
