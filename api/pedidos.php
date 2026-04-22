<?php
/**
 * API admin — Pedidos (CRUD + distancia)
 *
 * GET    /repo-admin/api/pedidos.php[?estado={estado}&q={texto}]
 *   Lista pedidos con sus ítems. Filtra por estado y/o búsqueda libre
 *   (número, cliente, teléfono). Devuelve stats por estado (cantidad y monto).
 *
 * PUT    /repo-admin/api/pedidos.php
 *   Cambia el estado de un pedido y recalcula distancia/tiempo vía Google Distance Matrix.
 *   Body JSON: { id, estado }
 *   Estados válidos: pendiente | preparando | listo | entregado | cancelado
 *
 * DELETE /repo-admin/api/pedidos.php?id={id}
 *   Elimina un pedido y sus ítems en una transacción.
 *
 * Helpers internos:
 *   calcDistanciaYTiempo() — llama a Google Distance Matrix API para obtener km y minutos
 *   calcularDistanciaPedido() — actualiza distancia_km y tiempo_min en la fila del pedido
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

// Migración inline: agregar repartidor_id si no existe
try {
    $pdo->query("SELECT repartidor_id FROM pedidos LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE pedidos ADD COLUMN repartidor_id INT UNSIGNED DEFAULT NULL AFTER cliente_id");
}

function calcDistanciaYTiempo($lat1, $lng1, $lat2, $lng2) {
    $apiKey = 'AIzaSyDXN7-CpoFdxh_6V-_7UQkPzWFbX6_T1p0';
    $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?'
         . 'origins=' . $lat1 . ',' . $lng1
         . '&destinations=' . $lat2 . ',' . $lng2
         . '&mode=driving&language=es&key=' . $apiKey;
    $resp = @file_get_contents($url);
    if ($resp) {
        $data = json_decode($resp, true);
        $el = isset($data['rows'][0]['elements'][0]) ? $data['rows'][0]['elements'][0] : null;
        if ($el && isset($el['distance']['value']) && isset($el['duration']['value'])) {
            return array(
                'km'  => round($el['distance']['value'] / 1000, 2),
                'min' => round($el['duration']['value'] / 60)
            );
        }
    }
    return array('km' => 0, 'min' => 0);
}

function calcularDistanciaPedido($pdo, $pedidoId) {
    $stmt = $pdo->prepare("SELECT lat, lng FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $ped = $stmt->fetch();
    $distancia = 0;
    $tiempo = 0;
    if ($ped && $ped['lat'] && $ped['lng']) {
        $stmtCfg = $pdo->prepare("SELECT clave, valor FROM configuracion WHERE clave IN ('centro_dist_lat','centro_dist_lng')");
        $stmtCfg->execute();
        $cfgRows = $stmtCfg->fetchAll();
        $cfg = [];
        foreach ($cfgRows as $r) { $cfg[$r['clave']] = $r['valor']; }
        if (!empty($cfg['centro_dist_lat']) && !empty($cfg['centro_dist_lng'])) {
            $result = calcDistanciaYTiempo(
                (float)$cfg['centro_dist_lat'], (float)$cfg['centro_dist_lng'],
                (float)$ped['lat'], (float)$ped['lng']
            );
            $distancia = $result['km'];
            $tiempo = $result['min'];
        }
    }
    $pdo->prepare("UPDATE pedidos SET distancia_km = ?, tiempo_min = ? WHERE id = ?")->execute([$distancia, $tiempo, $pedidoId]);
    return array('km' => $distancia, 'min' => $tiempo);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar pedidos ----
    case 'GET':
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';

        $where = [];
        $params = [];

        if ($estado && $estado !== 'todos') {
            $where[] = 'p.estado = ?';
            $params[] = $estado;
        }
        if ($q) {
            $where[] = '(p.numero LIKE ? OR p.cliente LIKE ? OR p.celular LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT p.id, p.numero, p.cliente, p.correo, p.celular, p.direccion, p.notas, p.total, p.estado,
                       p.lat, p.lng, p.distancia_km, p.tiempo_min, p.created_at as fecha,
                       p.repartidor_id, r.nombre AS repartidor_nombre
                FROM pedidos p
                LEFT JOIN repartidores r ON r.id = p.repartidor_id"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY p.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll();

        foreach ($pedidos as &$ped) {
            $stmtItems = $pdo->prepare("SELECT nombre, precio, cantidad FROM pedido_items WHERE pedido_id = ?");
            $stmtItems->execute([$ped['id']]);
            $ped['items'] = $stmtItems->fetchAll();
            $ped['total'] = (float)$ped['total'];
        }

        // Stats
        $stmtStats = $pdo->query("
            SELECT estado, COUNT(*) as cant, SUM(total) as monto
            FROM pedidos GROUP BY estado
        ");
        $statsRaw = $stmtStats->fetchAll();
        $stats = [];
        foreach ($statsRaw as $s) {
            $stats[$s['estado']] = ['cant' => (int)$s['cant'], 'monto' => (float)$s['monto']];
        }

        echo json_encode(['ok' => true, 'data' => $pedidos, 'stats' => $stats]);
        break;

    // ---- PUT: cambiar estado ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id     = isset($body['id'])     ? (int)$body['id']       : 0;
        $estado = isset($body['estado']) ? trim($body['estado'])  : '';

        $estados_validos = ['pendiente', 'preparando', 'listo', 'entregado', 'cancelado'];

        if (!$id || !in_array($estado, $estados_validos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID y estado válido requeridos']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
            break;
        }

        // Recalcular distancia al guardar
        $distancia = calcularDistanciaPedido($pdo, $id);

        echo json_encode(['ok' => true, 'id' => $id, 'estado' => $estado, 'distancia_km' => $distancia]);
        break;

    // ---- DELETE: eliminar pedido ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM pedido_items WHERE pedido_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
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
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
