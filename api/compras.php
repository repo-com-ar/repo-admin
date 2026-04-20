<?php
/**
 * API admin — Compras (CRUD)
 *
 * GET    /lider-admin/api/compras.php[?estado={estado}&q={texto}]
 *   Lista compras con sus ítems. Filtra por estado y/o búsqueda libre.
 *
 * POST   /lider-admin/api/compras.php
 *   Crea una compra. Body JSON: { proveedor_id, proveedor, notas?, items: [{producto_id?, nombre, precio, cantidad}] }
 *
 * PUT    /lider-admin/api/compras.php
 *   Cambia el estado de una compra. Body JSON: { id, estado }
 *
 * DELETE /lider-admin/api/compras.php?id={id}
 *   Elimina una compra y sus ítems.
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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar compras ----
    case 'GET':
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';

        $where  = [];
        $params = [];

        if ($estado && $estado !== 'todos') {
            $where[] = 'c.estado = ?';
            $params[] = $estado;
        }
        if ($q) {
            $where[] = '(c.numero LIKE ? OR c.proveedor LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT c.id, c.numero, c.proveedor_id, c.proveedor, c.telefono, c.direccion, c.notas, c.total, c.estado, c.created_at as fecha
                FROM compras c"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY c.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $compras = $stmt->fetchAll();

        foreach ($compras as &$comp) {
            $stmtItems = $pdo->prepare("SELECT nombre, precio, cantidad FROM compra_items WHERE compra_id = ?");
            $stmtItems->execute([$comp['id']]);
            $comp['items'] = $stmtItems->fetchAll();
            $comp['total'] = (float)$comp['total'];
        }

        // Stats
        $stmtStats = $pdo->query("
            SELECT estado, COUNT(*) as cant, SUM(total) as monto
            FROM compras GROUP BY estado
        ");
        $statsRaw = $stmtStats->fetchAll();
        $stats = [];
        foreach ($statsRaw as $s) {
            $stats[$s['estado']] = ['cant' => (int)$s['cant'], 'monto' => (float)$s['monto']];
        }

        echo json_encode(['ok' => true, 'data' => $compras, 'stats' => $stats]);
        break;

    // ---- POST: crear compra ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);

        if (!$body || empty($body['proveedor']) || empty($body['items'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Proveedor e ítems son obligatorios']);
            break;
        }

        $pdo->beginTransaction();
        try {
            // Generar número único
            $numero = 'C-' . date('ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Calcular total
            $total = 0;
            foreach ($body['items'] as $item) {
                $total += (float)($item['precio'] ?? 0) * (int)($item['cantidad'] ?? 1);
            }

            $stmt = $pdo->prepare("
                INSERT INTO compras (numero, proveedor_id, proveedor, telefono, direccion, notas, total, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([
                $numero,
                $body['proveedor_id'] ? (int)$body['proveedor_id'] : null,
                $body['proveedor'],
                $body['telefono'] ?? '',
                $body['direccion'] ?? '',
                $body['notas'] ?? '',
                $total,
            ]);
            $compraId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO compra_items (compra_id, producto_id, nombre, precio, cantidad)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtStock = $pdo->prepare("
                UPDATE productos SET stock_actual = stock_actual + ?, precio = ? WHERE id = ?
            ");
            foreach ($body['items'] as $item) {
                $productoId = !empty($item['producto_id']) ? (int)$item['producto_id'] : null;
                $precio     = (float)($item['precio'] ?? 0);
                $cantidad   = (int)($item['cantidad'] ?? 1);

                $stmtItem->execute([
                    $compraId,
                    $productoId,
                    $item['nombre'],
                    $precio,
                    $cantidad,
                ]);

                // Actualizar stock y precio del producto
                if ($productoId) {
                    $stmtStock->execute([$cantidad, $precio, $productoId]);
                }
            }

            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $compraId, 'numero' => $numero]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error al crear compra: ' . $e->getMessage()]);
        }
        break;

    // ---- PUT: cambiar estado ----
    case 'PUT':
        $body   = json_decode(file_get_contents('php://input'), true);
        $id     = isset($body['id'])     ? (int)$body['id']      : 0;
        $estado = isset($body['estado']) ? trim($body['estado']) : '';

        $estados_validos = ['pendiente', 'confirmada', 'cancelada'];

        if (!$id || !in_array($estado, $estados_validos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID y estado válido requeridos']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE compras SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Compra no encontrada']);
            break;
        }

        echo json_encode(['ok' => true, 'id' => $id, 'estado' => $estado]);
        break;

    // ---- DELETE: eliminar compra ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM compra_items WHERE compra_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM compras WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Compra no encontrada']);
                break;
            }

            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error al eliminar']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no soportado']);
}
