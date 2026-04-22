<?php
/**
 * API admin — Inventarios
 *
 * GET    /api/inventarios             Lista todos los inventarios con stats
 * GET    /api/inventarios?id={id}     Detalle de un inventario con sus ítems
 * POST   /api/inventarios             Crea inventario, guarda ítems y aplica stock
 * DELETE /api/inventarios?id={id}     Elimina inventario y sus ítems
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();
$authPayload = authUser();

require_once __DIR__ . '/../../repo-api/config/db.php';
try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// Auto-migración
$pdo->exec("
    CREATE TABLE IF NOT EXISTS inventarios (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        numero      VARCHAR(30)  NOT NULL,
        notas       TEXT         NOT NULL DEFAULT '',
        estado      ENUM('borrador','cerrado') NOT NULL DEFAULT 'cerrado',
        productos   INT UNSIGNED NOT NULL DEFAULT 0,
        usuario_id  INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Migraciones para tablas ya existentes
try { $pdo->exec("ALTER TABLE inventarios ADD COLUMN usuario_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER productos"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE inventarios DROP COLUMN usuario"); } catch (Exception $e) {}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS inventario_items (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inventario_id    INT UNSIGNED NOT NULL,
        producto_id      INT UNSIGNED NOT NULL,
        nombre           VARCHAR(255) NOT NULL,
        stock_anterior   INT          NOT NULL DEFAULT 0,
        cantidad_contada INT          NOT NULL DEFAULT 0,
        diferencia       INT          NOT NULL DEFAULT 0,
        created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inv (inventario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ── GET ─────────────────────────────────────────────────────────────────
    case 'GET':
        // Detalle de un inventario específico
        if (!empty($_GET['id'])) {
            $id   = (int)$_GET['id'];
            $stmt = $pdo->prepare("
                SELECT i.*, u.nombre AS usuario_nombre
                FROM inventarios i
                LEFT JOIN usuarios u ON u.id = i.usuario_id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $inv  = $stmt->fetch();
            if (!$inv) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Inventario no encontrado']);
                break;
            }
            $si = $pdo->prepare("SELECT * FROM inventario_items WHERE inventario_id = ? ORDER BY nombre");
            $si->execute([$id]);
            $inv['items'] = $si->fetchAll();
            echo json_encode(['ok' => true, 'data' => $inv]);
            break;
        }

        // Listado
        $q      = isset($_GET['q']) ? trim($_GET['q']) : '';
        $where  = $q ? "WHERE i.numero LIKE ? OR i.notas LIKE ?" : '';
        $params = $q ? ["%$q%", "%$q%"] : [];
        $stmt   = $pdo->prepare("
            SELECT i.id, i.numero, i.notas, i.estado, i.productos, i.usuario_id,
                   u.nombre AS usuario_nombre, i.created_at
            FROM inventarios i
            LEFT JOIN usuarios u ON u.id = i.usuario_id
            $where
            ORDER BY i.id DESC LIMIT 100
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $stats = [
            'total'  => (int)$pdo->query("SELECT COUNT(*) FROM inventarios")->fetchColumn(),
            'ultimo' => $pdo->query("SELECT MAX(created_at) FROM inventarios")->fetchColumn() ?: null,
        ];

        echo json_encode(['ok' => true, 'data' => $rows, 'stats' => $stats]);
        break;

    // ── POST ────────────────────────────────────────────────────────────────
    case 'POST':
        $body  = json_decode(file_get_contents('php://input'), true);
        $items = $body['items'] ?? [];

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El inventario debe tener al menos un ítem']);
            break;
        }

        $numero     = 'INV-' . date('ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $notas      = trim($body['notas'] ?? '');
        $usuario_id = (int)($authPayload['uid'] ?? 0);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO inventarios (numero, notas, estado, productos, usuario_id) VALUES (?, ?, 'cerrado', ?, ?)")
                ->execute([$numero, $notas, count($items), $usuario_id]);
            $invId = (int)$pdo->lastInsertId();

            $stmtItem  = $pdo->prepare("
                INSERT INTO inventario_items (inventario_id, producto_id, nombre, stock_anterior, cantidad_contada, diferencia)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtStock = $pdo->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");

            foreach ($items as $item) {
                $prodId     = (int)$item['producto_id'];
                $contado    = (int)$item['cantidad_contada'];
                $anterior   = (int)$item['stock_anterior'];
                $diferencia = $contado - $anterior;

                $stmtItem->execute([$invId, $prodId, $item['nombre'], $anterior, $contado, $diferencia]);
                $stmtStock->execute([$contado, $prodId]);
            }

            $pdo->commit();
            echo json_encode(['ok' => true, 'id' => $invId, 'numero' => $numero]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    // ── DELETE ──────────────────────────────────────────────────────────────
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM inventario_items WHERE inventario_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM inventarios WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Inventario no encontrado']);
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
