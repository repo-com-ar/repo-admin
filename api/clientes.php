<?php
/**
 * API admin — Clientes (CRUD)
 *
 * GET    /repo-admin/api/clientes.php[?q={texto}]
 *   Lista clientes con conteo de pedidos y monto total gastado.
 *   Soporta búsqueda libre por nombre, teléfono o dirección.
 *   Incluye stats globales: total de clientes y cuántos tienen al menos un pedido.
 *
 * PUT    /repo-admin/api/clientes.php
 *   Actualiza datos de un cliente. Body JSON: { id, nombre?, celular?, direccion? }
 *
 * DELETE /repo-admin/api/clientes.php?id={id}
 *   Elimina un cliente. Desvincula sus pedidos (cliente_id → NULL) antes de borrar.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Migración: asegurar columna contrasena en clientes
try { $pdo->query("SELECT contrasena FROM clientes LIMIT 1"); } catch (Exception $e) {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN contrasena VARCHAR(100) NOT NULL DEFAULT '' AFTER correo");
}

// Migración: eliminar columna 'clave' de clientes si todavía existe (descontinuada)
try { $pdo->query("SELECT clave FROM clientes LIMIT 1"); $pdo->exec("ALTER TABLE clientes DROP COLUMN clave"); } catch (Exception $e) { /* ya no existe */ }

// Migración: agregar last_seen para tracking de actividad desde repo-app
try { $pdo->query("SELECT last_seen FROM clientes LIMIT 1"); } catch (Exception $e) {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN last_seen DATETIME NULL DEFAULT NULL, ADD INDEX idx_last_seen (last_seen)");
}

// Migración: datos de ubicación geocodificados desde Google Geocoding API
try { $pdo->query("SELECT localidad FROM clientes LIMIT 1"); } catch (Exception $e) {
    $pdo->exec("ALTER TABLE clientes
        ADD COLUMN direccion_geo VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN localidad     VARCHAR(100) NULL DEFAULT NULL,
        ADD COLUMN provincia     VARCHAR(100) NULL DEFAULT NULL,
        ADD COLUMN pais          VARCHAR(100) NULL DEFAULT NULL");
}

// Tabla de direcciones múltiples por cliente
$pdo->exec("
    CREATE TABLE IF NOT EXISTS clientes_direcciones (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cliente_id    INT UNSIGNED NOT NULL,
        etiqueta      VARCHAR(50)  NOT NULL DEFAULT 'Casa',
        direccion     VARCHAR(255) NULL,
        lat           DECIMAL(10,7) NULL,
        lng           DECIMAL(10,7) NULL,
        direccion_geo VARCHAR(255) NULL,
        localidad     VARCHAR(100) NULL,
        provincia     VARCHAR(100) NULL,
        pais          VARCHAR(100) NULL,
        es_principal  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cliente_id (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar clientes ----
    case 'GET':
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';

        $where = [];
        $params = [];

        if ($q) {
            $where[] = '(c.nombre LIKE ? OR c.celular LIKE ? OR c.direccion LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT c.id, c.nombre, c.celular, c.direccion, c.correo, c.contrasena, c.lat, c.lng, c.last_seen, c.created_at,
                       COUNT(p.id) as total_pedidos,
                       COALESCE(SUM(p.total), 0) as total_gastado,
                       MAX(p.created_at) as ultimo_pedido
                FROM clientes c
                LEFT JOIN pedidos p ON p.cliente_id = c.id"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " GROUP BY c.id ORDER BY c.id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();

        foreach ($clientes as &$cli) {
            $cli['total_pedidos'] = (int)$cli['total_pedidos'];
            $cli['total_gastado'] = (float)$cli['total_gastado'];
        }

        // Stats
        $totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
        $conPedidos = $pdo->query("SELECT COUNT(DISTINCT cliente_id) FROM pedidos WHERE cliente_id IS NOT NULL")->fetchColumn();
        $enLinea      = $pdo->query("SELECT COUNT(*) FROM clientes WHERE last_seen >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
        $activosHoy   = $pdo->query("SELECT COUNT(*) FROM clientes WHERE last_seen >= CURDATE()")->fetchColumn();
        $nuevosSemana = $pdo->query("SELECT COUNT(*) FROM clientes WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();

        echo json_encode([
            'ok' => true,
            'data' => $clientes,
            'stats' => [
                'total'         => (int)$totalClientes,
                'con_pedidos'   => (int)$conPedidos,
                'en_linea'      => (int)$enLinea,
                'activos_hoy'   => (int)$activosHoy,
                'nuevos_semana' => (int)$nuevosSemana,
            ]
        ]);
        break;

    // ---- PUT: editar cliente ----
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
        if (isset($body['celular'])) {
            $campos[] = 'celular = ?';
            $params[] = trim($body['celular']);
        }
        if (isset($body['direccion'])) {
            $campos[] = 'direccion = ?';
            $params[] = trim($body['direccion']);
        }
        if (isset($body['correo'])) {
            $campos[] = 'correo = ?';
            $params[] = trim($body['correo']) ?: null;
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

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE clientes SET " . implode(', ', $campos) . " WHERE id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Cliente no encontrado']);
            break;
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ---- DELETE: eliminar cliente ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        // Desvincular pedidos
        $pdo->prepare("UPDATE pedidos SET cliente_id = NULL WHERE cliente_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Cliente no encontrado']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
