<?php
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

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id) {
            $stmt = $pdo->prepare("
                SELECT c.*, cl.nombre AS cliente_nombre, cl.correo AS cliente_correo, cl.celular AS cliente_celular
                FROM carritos c
                LEFT JOIN clientes cl ON cl.id = c.usuario_id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $carrito = $stmt->fetch();
            if (!$carrito) {
                echo json_encode(['ok' => false, 'error' => 'Carrito no encontrado']);
                exit;
            }
            $stmtItems = $pdo->prepare("
                SELECT ci.*, p.imagen AS producto_imagen
                FROM carritos_items ci
                LEFT JOIN productos p ON p.id = ci.producto_id
                WHERE ci.carrito_id = ?
                ORDER BY ci.id ASC
            ");
            $stmtItems->execute([$id]);
            $carrito['items'] = $stmtItems->fetchAll();
            echo json_encode(['ok' => true, 'data' => $carrito]);
            exit;
        }

        $estado = $_GET['estado'] ?? 'todos';
        $q      = trim($_GET['q'] ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($estado !== 'todos') {
            $where[] = 'c.estado = ?';
            $params[] = $estado;
        }
        if ($q !== '') {
            $like    = '%' . $q . '%';
            $where[] = '(cl.nombre LIKE ? OR cl.correo LIKE ? OR cl.celular LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "
            SELECT
                c.id, c.usuario_id, c.session_id, c.estado, c.total, c.created_at, c.updated_at,
                cl.nombre  AS cliente_nombre,
                cl.correo  AS cliente_correo,
                cl.celular AS cliente_celular,
                COUNT(ci.id)       AS items_count,
                COALESCE(SUM(ci.cantidad), 0) AS unidades_total,
                TIMESTAMPDIFF(MINUTE, c.updated_at, NOW()) AS minutos_inactivo
            FROM carritos c
            LEFT JOIN clientes cl ON cl.id = c.usuario_id
            LEFT JOIN carritos_items ci ON ci.carrito_id = c.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id
            ORDER BY c.updated_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $carritos = $stmt->fetchAll();

        $stmtStats = $pdo->query("
            SELECT
                COUNT(*)                   AS total,
                SUM(estado = 'activo')     AS activos,
                SUM(estado = 'abandonado') AS abandonados,
                SUM(estado = 'exitoso')    AS exitosos
            FROM carritos
        ");
        $stats = $stmtStats->fetch();

        echo json_encode(['ok' => true, 'data' => $carritos, 'stats' => $stats]);
        break;

    case 'PUT':
        $body   = json_decode(file_get_contents('php://input'), true);
        $id     = (int)($body['id'] ?? 0);
        $estado = $body['estado'] ?? '';

        if (!$id || !in_array($estado, ['activo', 'abandonado', 'exitoso'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE carritos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM carritos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
