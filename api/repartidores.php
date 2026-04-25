<?php
/**
 * API admin — Repartidores (CRUD)
 *
 * GET    /repo-admin/api/repartidores.php[?q={texto}]
 * POST   /repo-admin/api/repartidores.php   — crear repartidor
 * PUT    /repo-admin/api/repartidores.php   — editar repartidor
 * DELETE /repo-admin/api/repartidores.php?id={id}
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Crear tabla si no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS repartidores (
        id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre            VARCHAR(120) NOT NULL,
        correo            VARCHAR(150) DEFAULT NULL,
        celular           VARCHAR(40)  DEFAULT '',
        direccion         VARCHAR(255) DEFAULT '',
        contrasena        VARCHAR(100) NOT NULL DEFAULT '',
        lat               DECIMAL(10,7) DEFAULT NULL,
        lng               DECIMAL(10,7) DEFAULT NULL,
        ubicacion_activa  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Migración: quitar columna `clave` si existe (ya no se utiliza)
try {
    $pdo->query("SELECT clave FROM repartidores LIMIT 1");
    $pdo->exec("ALTER TABLE repartidores DROP COLUMN clave");
} catch (Throwable $e) { /* ya no existe, seguir */ }

// Migración: agregar `ubicacion_activa` si falta
try {
    $pdo->query("SELECT ubicacion_activa FROM repartidores LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE repartidores ADD COLUMN ubicacion_activa TINYINT(1) NOT NULL DEFAULT 0 AFTER lng");
    } catch (Throwable $e2) { /* silencioso */ }
}

// Migración: agregar `last_seen` si falta (para el heartbeat "en línea")
try {
    $pdo->query("SELECT last_seen FROM repartidores LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE repartidores ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL, ADD INDEX idx_last_seen (last_seen)");
    } catch (Throwable $e2) { /* silencioso */ }
}

// Migración: agregar `vehiculo` si falta
try {
    $pdo->query("SELECT vehiculo FROM repartidores LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE repartidores ADD COLUMN vehiculo ENUM('bicicleta','moto','auto','furgon','camioneta','camion') DEFAULT NULL AFTER celular");
    } catch (Throwable $e2) { /* silencioso */ }
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar repartidores (o uno solo con ?id=X) ----
    case 'GET':
        // Petición de un único repartidor (para polling de ubicación)
        if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
            $st = $pdo->prepare(
                "SELECT id, nombre, lat, lng, ubicacion_activa, ubicacion_at, last_seen,
                        (last_seen IS NOT NULL AND last_seen >= (NOW() - INTERVAL 60 SECOND)) AS online
                 FROM repartidores WHERE id = ?"
            );
            $st->execute([(int)$_GET['id']]);
            $r = $st->fetch();
            if (!$r) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Repartidor no encontrado']);
            } else {
                echo json_encode(['ok' => true, 'data' => $r]);
            }
            break;
        }

        $q = isset($_GET['q']) ? trim($_GET['q']) : '';

        $where  = [];
        $params = [];

        if ($q) {
            $where[] = '(nombre LIKE ? OR celular LIKE ? OR direccion LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT id, nombre, correo, celular, vehiculo, direccion, contrasena, lat, lng, ubicacion_activa, ubicacion_at, last_seen, created_at,
                       (last_seen IS NOT NULL AND last_seen >= (NOW() - INTERVAL 60 SECOND)) AS online
                FROM repartidores"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $total = $pdo->query("SELECT COUNT(*) FROM repartidores")->fetchColumn();

        echo json_encode([
            'ok'    => true,
            'data'  => $rows,
            'stats' => ['total' => (int)$total],
        ]);
        break;

    // ---- POST: crear repartidor ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($body['nombre'] ?? '');

        if (!$nombre) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
            break;
        }

        $vehiculosValidos = ['bicicleta','moto','auto','furgon','camioneta','camion'];
        $vehiculo = trim($body['vehiculo'] ?? '');
        $vehiculo = in_array($vehiculo, $vehiculosValidos) ? $vehiculo : null;

        $stmt = $pdo->prepare("INSERT INTO repartidores (nombre, correo, celular, vehiculo, direccion, contrasena, lat, lng)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre,
            trim($body['correo']    ?? '') ?: null,
            trim($body['celular']   ?? ''),
            $vehiculo,
            trim($body['direccion'] ?? ''),
            trim($body['contrasena'] ?? ''),
            isset($body['lat']) && $body['lat'] !== null ? (float)$body['lat'] : null,
            isset($body['lng']) && $body['lng'] !== null ? (float)$body['lng'] : null,
        ]);

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    // ---- PUT: editar repartidor ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = isset($body['id']) ? (int)$body['id'] : 0;

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
        if (isset($body['correo'])) {
            $campos[] = 'correo = ?';
            $params[] = trim($body['correo']) ?: null;
        }
        if (isset($body['celular'])) {
            $campos[] = 'celular = ?';
            $params[] = trim($body['celular']);
        }
        if (array_key_exists('vehiculo', $body)) {
            $vehiculosValidos = ['bicicleta','moto','auto','furgon','camioneta','camion'];
            $v = trim($body['vehiculo'] ?? '');
            $campos[] = 'vehiculo = ?';
            $params[] = in_array($v, $vehiculosValidos) ? $v : null;
        }
        if (isset($body['direccion'])) {
            $campos[] = 'direccion = ?';
            $params[] = trim($body['direccion']);
        }
        if (isset($body['contrasena'])) {
            $campos[] = 'contrasena = ?';
            $params[] = trim($body['contrasena']);
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

        $existe = $pdo->prepare("SELECT id FROM repartidores WHERE id = ?");
        $existe->execute([$id]);
        if (!$existe->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Repartidor no encontrado']);
            break;
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE repartidores SET " . implode(', ', $campos) . " WHERE id = ?");
        $stmt->execute($params);

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ---- DELETE: eliminar repartidor ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM repartidores WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Repartidor no encontrado']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
