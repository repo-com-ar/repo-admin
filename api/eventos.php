<?php
/**
 * API admin — Eventos (solo lectura)
 *
 * GET /lider-admin/api/eventos.php[?q={texto}]
 *   Lista todos los eventos registrados, ordenados del más reciente al más antiguo.
 *   Opcionalmente filtra por texto en el detalle o nombre del cliente.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();


$configPath = __DIR__ . '/../../repo-api/config/db.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'config no encontrado']);
    exit;
}
require_once $configPath;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDB();

    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS eventos (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id  INT UNSIGNED NOT NULL DEFAULT 0,
            detalle     VARCHAR(500) NOT NULL,
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    $sql = "SELECT e.id, e.cliente_id, e.detalle, e.created_at,
                   COALESCE(c.nombre, 'Sin sesión') AS cliente_nombre
            FROM eventos e
            LEFT JOIN clientes c ON e.cliente_id = c.id AND e.cliente_id > 0";

    $params = [];
    if ($q !== '') {
        $sql .= " WHERE e.detalle LIKE :q OR c.nombre LIKE :q2";
        $params[':q']  = "%$q%";
        $params[':q2'] = "%$q%";
    }

    $sql .= " ORDER BY e.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $total = $pdo->query("SELECT COUNT(*) FROM eventos")->fetchColumn();
    $hoy   = $pdo->query("SELECT COUNT(*) FROM eventos WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    echo json_encode([
        'ok'   => true,
        'data' => $eventos,
        'stats' => [
            'total' => (int)$total,
            'hoy'   => (int)$hoy,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al consultar eventos']);
}
