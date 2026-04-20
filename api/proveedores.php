<?php
/**
 * API admin — Proveedores (CRUD)
 *
 * GET    /lider-admin/api/proveedores.php[?q={texto}]
 * POST   /lider-admin/api/proveedores.php  — crea proveedor
 * PUT    /lider-admin/api/proveedores.php   — actualiza proveedor
 * DELETE /lider-admin/api/proveedores.php?id={id}
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();


require_once __DIR__ . '/../../repo-api/config/db.php';

try {
    $pdo = getDB();

    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS proveedores (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre      VARCHAR(120) NOT NULL,
            domicilio   VARCHAR(255) DEFAULT '',
            correo      VARCHAR(150) DEFAULT NULL,
            lat         DECIMAL(10,7) DEFAULT NULL,
            lng         DECIMAL(10,7) DEFAULT NULL,
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar proveedores ----
    case 'GET':
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';

        $where = [];
        $params = [];

        if ($q) {
            $where[] = '(nombre LIKE ? OR domicilio LIKE ? OR correo LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM proveedores"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = $pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn();

        echo json_encode([
            'ok'   => true,
            'data' => $proveedores,
            'stats' => ['total' => (int)$total],
        ]);
        break;

    // ---- POST: crear proveedor ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $nombre = isset($body['nombre']) ? trim($body['nombre']) : '';

        if ($nombre === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nombre requerido']);
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, domicilio, correo, lat, lng) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre,
            isset($body['domicilio']) ? trim($body['domicilio']) : '',
            isset($body['correo']) && trim($body['correo']) !== '' ? trim($body['correo']) : null,
            isset($body['lat']) ? (float)$body['lat'] : null,
            isset($body['lng']) ? (float)$body['lng'] : null,
        ]);

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    // ---- PUT: editar proveedor ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id = isset($body['id']) ? (int)$body['id'] : 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $campos = [];
        $params = [];

        if (isset($body['nombre']) && trim($body['nombre']) !== '') {
            $campos[] = 'nombre = ?';
            $params[] = trim($body['nombre']);
        }
        if (isset($body['domicilio'])) {
            $campos[] = 'domicilio = ?';
            $params[] = trim($body['domicilio']);
        }
        if (isset($body['correo'])) {
            $campos[] = 'correo = ?';
            $params[] = trim($body['correo']) ?: null;
        }
        if (array_key_exists('lat', $body)) {
            $campos[] = 'lat = ?';
            $params[] = $body['lat'] !== null ? (float)$body['lat'] : null;
        }
        if (array_key_exists('lng', $body)) {
            $campos[] = 'lng = ?';
            $params[] = $body['lng'] !== null ? (float)$body['lng'] : null;
        }

        if (empty($campos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nada que actualizar']);
            break;
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE proveedores SET " . implode(', ', $campos) . " WHERE id = ?");
        $stmt->execute($params);

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ---- DELETE: eliminar proveedor ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Proveedor no encontrado']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
