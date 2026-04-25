<?php
/**
 * API admin — Notificaciones enviadas (solo lectura)
 *
 * GET /repo-admin/api/notificaciones.php[?q={texto}&actor_type={cliente|repartidor|usuario}&estado={enviado|fallido|sin_dispositivo}]
 *   Lista todas las notificaciones registradas, ordenadas del más reciente al más antiguo.
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
require_once __DIR__ . '/../../repo-api/lib/pushservice.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDB();
    push_ensure_schema(); // garantiza tabla notificaciones

    $q          = isset($_GET['q'])          ? trim($_GET['q'])          : '';
    $actorType  = isset($_GET['actor_type']) ? trim($_GET['actor_type']) : '';
    $estado     = isset($_GET['estado'])     ? trim($_GET['estado'])     : '';

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[]      = '(n.titulo LIKE :q OR n.cuerpo LIKE :q2)';
        $params[':q']  = "%$q%";
        $params[':q2'] = "%$q%";
    }
    if (in_array($actorType, ['cliente', 'repartidor', 'usuario'], true)) {
        $where[]              = 'n.actor_type = :at';
        $params[':at']        = $actorType;
    }
    if (in_array($estado, ['enviado', 'fallido', 'sin_dispositivo'], true)) {
        $where[]              = 'n.estado = :est';
        $params[':est']       = $estado;
    }

    // JOIN con la tabla apropiada para mostrar el nombre del destinatario
    $sql = "SELECT
                n.id, n.actor_type, n.actor_id, n.titulo, n.cuerpo, n.data,
                n.estado, n.error, n.leida, n.leida_at, n.created_at,
                CASE n.actor_type
                    WHEN 'cliente'    THEN (SELECT nombre  FROM clientes      WHERE id = n.actor_id)
                    WHEN 'repartidor' THEN (SELECT nombre  FROM repartidores  WHERE id = n.actor_id)
                    WHEN 'usuario'    THEN (SELECT usuario FROM usuarios      WHERE id = n.actor_id)
                END AS destinatario_nombre
            FROM notificaciones n";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY n.created_at DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['data']  = $r['data'] ? json_decode($r['data'], true) : null;
        $r['leida'] = (int) $r['leida'];
    }
    unset($r);

    $total       = (int) $pdo->query("SELECT COUNT(*) FROM notificaciones")->fetchColumn();
    $clientes    = (int) $pdo->query("SELECT COUNT(*) FROM notificaciones WHERE actor_type='cliente'")->fetchColumn();
    $repartidores= (int) $pdo->query("SELECT COUNT(*) FROM notificaciones WHERE actor_type='repartidor'")->fetchColumn();
    $usuarios    = (int) $pdo->query("SELECT COUNT(*) FROM notificaciones WHERE actor_type='usuario'")->fetchColumn();
    $fallidas    = (int) $pdo->query("SELECT COUNT(*) FROM notificaciones WHERE estado='fallido'")->fetchColumn();

    echo json_encode([
        'ok'    => true,
        'data'  => $rows,
        'stats' => [
            'total'        => $total,
            'clientes'     => $clientes,
            'repartidores' => $repartidores,
            'usuarios'     => $usuarios,
            'fallidas'     => $fallidas,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al consultar notificaciones: ' . $e->getMessage()]);
}
