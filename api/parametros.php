<?php
/**
 * API admin — Parámetros del sistema (tabla configuracion)
 *
 * GET /repo-admin/api/parametros
 *   Devuelve todas las filas de la tabla configuracion ordenadas por clave.
 *
 * PUT /repo-admin/api/parametros
 *   Inserta o actualiza un parámetro.
 *   Body JSON: { clave: "nombre_clave", valor: "valor" }
 */
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

require_once __DIR__ . '/../../repo-api/config/db.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT clave, valor, updated_at FROM configuracion ORDER BY clave ASC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!isset($body['clave']) || !isset($body['valor'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Se requieren clave y valor']);
        exit;
    }

    $clave = trim($body['clave']);
    $valor = $body['valor'];

    if ($clave === '' || !preg_match('/^[a-z0-9_]+$/', $clave)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'La clave solo puede contener letras minúsculas, números y guión bajo']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute([$clave, $valor]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $clave = isset($_GET['clave']) ? trim($_GET['clave']) : '';
    if ($clave === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Se requiere clave']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM configuracion WHERE clave = ?");
    $stmt->execute([$clave]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Parámetro no encontrado']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
