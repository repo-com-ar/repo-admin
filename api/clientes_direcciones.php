<?php
/**
 * API admin — Direcciones de un cliente.
 *
 * Auth por cookie (repo_token). El admin puede operar sobre cualquier cliente.
 *
 *   GET    /repo-admin/api/clientes_direcciones.php?cliente_id={id}
 *     → { ok, data: [ ... ] }
 *
 *   POST   /repo-admin/api/clientes_direcciones.php
 *     Body: { cliente_id, etiqueta?, direccion?, lat?, lng?, es_principal? }
 *     → { ok, id }
 *
 *   PATCH  /repo-admin/api/clientes_direcciones.php
 *     Body: { id, cliente_id?, ...campos }
 *     → { ok }
 *
 *   DELETE /repo-admin/api/clientes_direcciones.php?id={id}
 *     → { ok }
 *
 * Reutiliza reverseGeocode() y ensure_direcciones_table() de repo-app/api/lib/geocoding.php.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

require_once __DIR__ . '/../../repo-api/config/db.php';
require_once __DIR__ . '/../../repo-app/api/lib/geocoding.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

ensure_direcciones_table($pdo);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET': {
        $clienteId = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
        if ($clienteId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'cliente_id requerido']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT id, cliente_id, etiqueta, direccion, lat, lng,
                   direccion_geo, localidad, provincia, pais, es_principal,
                   created_at, updated_at
            FROM clientes_direcciones
            WHERE cliente_id = ?
            ORDER BY es_principal DESC, id ASC
        ");
        $stmt->execute([$clienteId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id']           = (int)$r['id'];
            $r['cliente_id']   = (int)$r['cliente_id'];
            $r['lat']          = $r['lat'] !== null ? (float)$r['lat'] : null;
            $r['lng']          = $r['lng'] !== null ? (float)$r['lng'] : null;
            $r['es_principal'] = (int)$r['es_principal'] === 1;
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;
    }

    case 'POST': {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $clienteId = (int)($body['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'cliente_id requerido']);
            exit;
        }
        $etiqueta  = trim($body['etiqueta']  ?? '') ?: 'Casa';
        $direccion = trim($body['direccion'] ?? '');
        $lat = isset($body['lat']) && $body['lat'] !== '' && $body['lat'] !== null ? (float)$body['lat'] : null;
        $lng = isset($body['lng']) && $body['lng'] !== '' && $body['lng'] !== null ? (float)$body['lng'] : null;

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM clientes_direcciones WHERE cliente_id = ?");
        $cnt->execute([$clienteId]);
        $esPrimera = (int)$cnt->fetchColumn() === 0;
        $esPrincipal = $esPrimera || !empty($body['es_principal']);

        if ($esPrincipal) {
            $pdo->prepare("UPDATE clientes_direcciones SET es_principal = 0 WHERE cliente_id = ?")
                ->execute([$clienteId]);
        }

        $geo = ['direccion_geo' => null, 'localidad' => null, 'provincia' => null, 'pais' => null];
        if ($lat !== null && $lng !== null) {
            $geo = reverseGeocode($lat, $lng);
        }

        $stmt = $pdo->prepare("
            INSERT INTO clientes_direcciones
                (cliente_id, etiqueta, direccion, lat, lng, direccion_geo, localidad, provincia, pais, es_principal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $clienteId,
            $etiqueta,
            $direccion ?: null,
            $lat, $lng,
            $geo['direccion_geo'], $geo['localidad'], $geo['provincia'], $geo['pais'],
            $esPrincipal ? 1 : 0,
        ]);

        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;
    }

    case 'PATCH': {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'id requerido']);
            exit;
        }

        // Necesitamos cliente_id para operaciones de "principal" — lo leemos si no viene
        $clienteId = (int)($body['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            $stChk = $pdo->prepare("SELECT cliente_id FROM clientes_direcciones WHERE id = ?");
            $stChk->execute([$id]);
            $row = $stChk->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Dirección no encontrada']);
                exit;
            }
            $clienteId = (int)$row['cliente_id'];
        }

        $campos = [];
        $params = [];

        if (array_key_exists('etiqueta', $body)) {
            $campos[] = 'etiqueta = ?';
            $params[] = trim($body['etiqueta']) ?: 'Casa';
        }
        if (array_key_exists('direccion', $body)) {
            $campos[] = 'direccion = ?';
            $params[] = trim($body['direccion']) ?: null;
        }

        $cambiaLat = array_key_exists('lat', $body);
        $cambiaLng = array_key_exists('lng', $body);
        if ($cambiaLat || $cambiaLng) {
            $lat = ($cambiaLat && $body['lat'] !== null && $body['lat'] !== '') ? (float)$body['lat'] : null;
            $lng = ($cambiaLng && $body['lng'] !== null && $body['lng'] !== '') ? (float)$body['lng'] : null;
            $campos[] = 'lat = ?'; $params[] = $lat;
            $campos[] = 'lng = ?'; $params[] = $lng;
            if ($lat !== null && $lng !== null) {
                $geo = reverseGeocode($lat, $lng);
                $campos[] = 'direccion_geo = ?'; $params[] = $geo['direccion_geo'];
                $campos[] = 'localidad = ?';     $params[] = $geo['localidad'];
                $campos[] = 'provincia = ?';     $params[] = $geo['provincia'];
                $campos[] = 'pais = ?';          $params[] = $geo['pais'];
            } else {
                $campos[] = 'direccion_geo = ?'; $params[] = null;
                $campos[] = 'localidad = ?';     $params[] = null;
                $campos[] = 'provincia = ?';     $params[] = null;
                $campos[] = 'pais = ?';          $params[] = null;
            }
        }

        if (!empty($body['es_principal'])) {
            $pdo->prepare("UPDATE clientes_direcciones SET es_principal = 0 WHERE cliente_id = ?")
                ->execute([$clienteId]);
            $campos[] = 'es_principal = ?';
            $params[] = 1;
        }

        if (!$campos) {
            echo json_encode(['ok' => true]);
            exit;
        }

        $params[] = $id;
        $pdo->prepare("UPDATE clientes_direcciones SET " . implode(', ', $campos) . " WHERE id = ?")
            ->execute($params);

        echo json_encode(['ok' => true]);
        break;
    }

    case 'DELETE': {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'id requerido']);
            exit;
        }

        $chk = $pdo->prepare("SELECT cliente_id, es_principal FROM clientes_direcciones WHERE id = ?");
        $chk->execute([$id]);
        $row = $chk->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Dirección no encontrada']);
            exit;
        }

        $pdo->prepare("DELETE FROM clientes_direcciones WHERE id = ?")->execute([$id]);

        if ((int)$row['es_principal'] === 1) {
            $next = $pdo->prepare("SELECT id FROM clientes_direcciones WHERE cliente_id = ? ORDER BY id ASC LIMIT 1");
            $next->execute([(int)$row['cliente_id']]);
            $nextId = $next->fetchColumn();
            if ($nextId) {
                $pdo->prepare("UPDATE clientes_direcciones SET es_principal = 1 WHERE id = ?")->execute([(int)$nextId]);
            }
        }

        echo json_encode(['ok' => true]);
        break;
    }

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
