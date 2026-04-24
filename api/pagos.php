<?php
/**
 * API admin — Pagos
 *
 * GET /repo-admin/api/pagos[?metodo={efectivo|mercadopago}&estado={aprobado|...}&q={texto}]
 *   Lista todos los pagos con datos del pedido y cliente asociado.
 *   Filtra por método, estado y búsqueda libre (número pedido, cliente, mp_payment_id).
 *   Devuelve stats agrupadas por método (cantidad y monto).
 */
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $metodo = isset($_GET['metodo']) ? trim($_GET['metodo']) : '';
    $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
    $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';

    $metodosValidos = ['efectivo', 'mercadopago'];
    $estadosValidos = ['pendiente', 'aprobado', 'rechazado', 'reembolsado'];

    $where  = [];
    $params = [];

    if ($metodo && in_array($metodo, $metodosValidos)) {
        $where[]  = 'pg.metodo = ?';
        $params[] = $metodo;
    }
    if ($estado && in_array($estado, $estadosValidos)) {
        $where[]  = 'pg.estado = ?';
        $params[] = $estado;
    }
    if ($q !== '') {
        $like     = '%' . $q . '%';
        $where[]  = '(p.numero LIKE ? OR p.cliente LIKE ? OR p.celular LIKE ? OR pg.mp_payment_id LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            pg.id,
            pg.pedido_id,
            pg.metodo,
            pg.monto,
            pg.estado,
            pg.mp_preference_id,
            pg.mp_payment_id,
            pg.mp_status,
            pg.recibido_por,
            pg.notas,
            pg.created_at,
            p.numero  AS numero_pedido,
            p.cliente,
            p.celular
        FROM pagos pg
        LEFT JOIN pedidos p ON p.id = pg.pedido_id
        {$whereSql}
        ORDER BY pg.created_at DESC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats por método (sin filtros de método/estado para mostrar totales reales)
    $statsParams = [];
    $statsWhere  = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $statsWhere[]  = '(p.numero LIKE ? OR p.cliente LIKE ? OR p.celular LIKE ? OR pg.mp_payment_id LIKE ?)';
        $statsParams[] = $like;
        $statsParams[] = $like;
        $statsParams[] = $like;
        $statsParams[] = $like;
    }
    $statsWhereSql = $statsWhere ? 'WHERE ' . implode(' AND ', $statsWhere) : '';

    $stmtStats = $pdo->prepare("
        SELECT pg.metodo, COUNT(*) AS cant, COALESCE(SUM(pg.monto), 0) AS monto
        FROM pagos pg
        LEFT JOIN pedidos p ON p.id = pg.pedido_id
        {$statsWhereSql}
        GROUP BY pg.metodo
    ");
    $stmtStats->execute($statsParams);
    $statsRaw = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    $stats = [];
    foreach ($statsRaw as $row) {
        $stats[$row['metodo']] = [
            'cant'  => (int) $row['cant'],
            'monto' => (float) $row['monto'],
        ];
    }

    echo json_encode(['ok' => true, 'data' => $data, 'stats' => $stats]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
