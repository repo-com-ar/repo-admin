<?php
/**
 * API admin — Suscriptores (push notifications)
 *
 * GET    /repo-admin/api/suscriptores.php[?actor_type=&q=]
 *   Lista suscripciones con el nombre del actor resuelto.
 *
 * DELETE /repo-admin/api/suscriptores.php?id={id}
 *   Elimina una suscripción (fuerza al usuario a resuscribirse).
 *
 * POST   /repo-admin/api/suscriptores.php?id={id}&accion=probar
 *   Envía un push de prueba a esa suscripción.
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

require_once __DIR__ . '/../../repo-api/config/db.php';
require_once __DIR__ . '/../../repo-api/lib/pushservice.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar suscripciones ----
    case 'GET':
        $actorType = isset($_GET['actor_type']) ? trim($_GET['actor_type']) : '';
        $q         = isset($_GET['q'])          ? trim($_GET['q'])          : '';

        $where  = [];
        $params = [];

        if ($actorType && in_array($actorType, ['repartidor', 'cliente', 'usuario'], true)) {
            $where[] = 'ps.actor_type = ?';
            $params[] = $actorType;
        }
        if ($q) {
            $where[] = '(r.nombre LIKE ? OR c.nombre LIKE ? OR u.nombre LIKE ? OR ps.origin LIKE ? OR ps.user_agent LIKE ?)';
            $like = "%$q%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT ps.id, ps.actor_type, ps.actor_id, ps.origin, ps.endpoint, ps.user_agent,
                       ps.created_at, ps.last_used_at, ps.last_error,
                       COALESCE(r.nombre, c.nombre, u.nombre, '') AS actor_nombre
                FROM push_subscriptions ps
                LEFT JOIN repartidores r ON ps.actor_type = 'repartidor' AND r.id = ps.actor_id
                LEFT JOIN clientes     c ON ps.actor_type = 'cliente'    AND c.id = ps.actor_id
                LEFT JOIN usuarios     u ON ps.actor_type = 'usuario'    AND u.id = ps.actor_id"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY ps.id DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Acortar endpoint para UI
        foreach ($rows as &$r) {
            $r['endpoint_preview'] = substr($r['endpoint'], 0, 60) . (strlen($r['endpoint']) > 60 ? '…' : '');
            $parts = parse_url($r['endpoint']);
            $r['proveedor'] = $parts['host'] ?? '';
        }
        unset($r);

        $stats = [];
        $q = $pdo->query("
            SELECT actor_type, COUNT(*) AS cant
            FROM push_subscriptions
            GROUP BY actor_type
        ");
        foreach ($q as $row) { $stats[$row['actor_type']] = (int)$row['cant']; }
        $total    = $pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
        $conError = $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE last_error <> ''")->fetchColumn();

        echo json_encode([
            'ok'    => true,
            'data'  => $rows,
            'stats' => [
                'total'       => (int)$total,
                'repartidor'  => (int)($stats['repartidor'] ?? 0),
                'cliente'     => (int)($stats['cliente']    ?? 0),
                'usuario'     => (int)($stats['usuario']    ?? 0),
                'con_error'   => (int)$conError,
            ],
        ]);
        break;

    // ---- POST: enviar push de prueba ----
    case 'POST':
        $accion = isset($_GET['accion']) ? trim($_GET['accion']) : '';
        $id     = isset($_GET['id'])     ? (int)$_GET['id']      : 0;
        if ($accion !== 'probar' || !$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'accion=probar y id requeridos']);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE id = ?");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Suscripción no encontrada']);
            break;
        }
        try {
            // Enviar directamente a esta suscripción específica (no a todo el actor_id,
            // que puede tener múltiples dispositivos)
            require_once __DIR__ . '/../../repo-api/lib/webpush.php';
            $vapid = push_vapid_config();
            if (empty($vapid['public']) || empty($vapid['private'])) {
                throw new Exception('VAPID keys no configuradas');
            }
            $payload = json_encode([
                'title' => '🔔 Notificación de prueba',
                'body'  => 'Este es un envío de prueba desde el panel.',
                'data'  => ['url' => '/repo-' . ($s['actor_type'] === 'repartidor' ? 'delivery' : 'app') . '/'],
            ], JSON_UNESCAPED_UNICODE);

            $res = webpush_send([
                'endpoint' => $s['endpoint'],
                'p256dh'   => $s['p256dh'],
                'auth'     => $s['auth_key'],
            ], $payload, $vapid);

            // Actualizar last_used_at / last_error / limpiar si murió
            if ($res['status'] >= 200 && $res['status'] < 300) {
                $pdo->prepare("UPDATE push_subscriptions SET last_used_at = NOW(), last_error = '' WHERE id = ?")
                    ->execute([$id]);
                echo json_encode(['ok' => true, 'stats' => ['enviados' => 1]]);
            } elseif ($res['status'] === 404 || $res['status'] === 410) {
                $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true, 'stats' => ['muertos' => 1]]);
            } else {
                $err = $res['error'] ?: ('HTTP ' . $res['status'] . ' ' . substr($res['body'], 0, 120));
                $pdo->prepare("UPDATE push_subscriptions SET last_error = ? WHERE id = ?")
                    ->execute([substr($err, 0, 250), $id]);
                echo json_encode([
                    'ok'     => false,
                    'error'  => $err,
                    'status' => $res['status'],
                    'body'   => substr($res['body'], 0, 500),
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok'    => false,
                'error' => $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        break;

    // ---- DELETE: eliminar suscripción ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Suscripción no encontrada']);
            break;
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
