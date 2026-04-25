<?php
/**
 * API admin — Asientos contables (CRUD)
 *
 * GET    /repo-admin/api/asientos.php                  — listar todos
 * GET    /repo-admin/api/asientos.php?id={id}          — detalle de un asiento (con líneas)
 * POST   /repo-admin/api/asientos.php                  — crear asiento (con detalle)
 * PUT    /repo-admin/api/asientos.php                  — editar asiento (reemplaza detalle)
 * DELETE /repo-admin/api/asientos.php?id={id}          — eliminar
 *
 * Filtros GET: ?q={texto}&desde={YYYY-MM-DD}&hasta={YYYY-MM-DD}
 *
 * Validación: el total del DEBE debe igualar al total del HABER y debe haber
 * al menos 2 líneas. Todas las cuentas deben ser imputables y activas.
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

// Crear tablas si no existen
$pdo->exec("
    CREATE TABLE IF NOT EXISTS asientos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        numero      INT UNSIGNED NOT NULL,
        fecha       DATE         NOT NULL,
        descripcion VARCHAR(255) NOT NULL,
        total       DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_numero (numero),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS asientos_detalle (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        asiento_id  INT UNSIGNED NOT NULL,
        cuenta_id   INT UNSIGNED NOT NULL,
        debe        DECIMAL(14,2) NOT NULL DEFAULT 0,
        haber       DECIMAL(14,2) NOT NULL DEFAULT 0,
        descripcion VARCHAR(255) DEFAULT NULL,
        orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
        INDEX idx_asiento (asiento_id),
        INDEX idx_cuenta (cuenta_id),
        CONSTRAINT fk_asd_asiento FOREIGN KEY (asiento_id) REFERENCES asientos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar / detalle ----
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $st = $pdo->prepare("SELECT id, numero, fecha, descripcion, total, created_at FROM asientos WHERE id = ?");
            $st->execute([$id]);
            $asiento = $st->fetch();
            if (!$asiento) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Asiento no encontrado']);
                break;
            }
            $std = $pdo->prepare("
                SELECT d.id, d.cuenta_id, d.debe, d.haber, d.descripcion, d.orden,
                       c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
                FROM asientos_detalle d
                LEFT JOIN cuentas c ON c.id = d.cuenta_id
                WHERE d.asiento_id = ?
                ORDER BY d.orden ASC, d.id ASC
            ");
            $std->execute([$id]);
            $asiento['detalle'] = $std->fetchAll();
            echo json_encode(['ok' => true, 'data' => $asiento]);
            break;
        }

        $q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
        $desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
        $hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

        $where  = [];
        $params = [];
        if ($q) {
            $where[] = '(descripcion LIKE ? OR numero LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'fecha >= ?';
            $params[] = $desde;
        }
        if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'fecha <= ?';
            $params[] = $hasta;
        }

        $sql = "SELECT id, numero, fecha, descripcion, total, created_at FROM asientos"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY fecha DESC, numero DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Adjuntar detalle de cuentas a cada asiento listado (en una sola query)
        if (!empty($rows)) {
            $ids = array_column($rows, 'id');
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stD = $pdo->prepare("
                SELECT d.asiento_id, d.cuenta_id, d.debe, d.haber, d.descripcion, d.orden,
                       c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
                FROM asientos_detalle d
                LEFT JOIN cuentas c ON c.id = d.cuenta_id
                WHERE d.asiento_id IN ($place)
                ORDER BY d.asiento_id, d.orden ASC, d.id ASC
            ");
            $stD->execute($ids);
            $detalleByAsiento = [];
            foreach ($stD->fetchAll() as $d) {
                $detalleByAsiento[(int)$d['asiento_id']][] = $d;
            }
            foreach ($rows as &$r) {
                $r['detalle'] = $detalleByAsiento[(int)$r['id']] ?? [];
            }
            unset($r);
        }

        $total      = (int) $pdo->query("SELECT COUNT(*) FROM asientos")->fetchColumn();
        $sumTotal   = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM asientos")->fetchColumn();
        $delMes     = (int) $pdo->query("SELECT COUNT(*) FROM asientos WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())")->fetchColumn();

        echo json_encode([
            'ok'    => true,
            'data'  => $rows,
            'stats' => [
                'total'    => $total,
                'monto'    => $sumTotal,
                'del_mes'  => $delMes,
            ],
        ]);
        break;

    // ---- POST: crear asiento ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $resp = guardarAsiento($pdo, $body, null);
        if (!empty($resp['ok'])) {
            echo json_encode($resp);
        } else {
            http_response_code($resp['status'] ?? 400);
            unset($resp['status']);
            echo json_encode($resp);
        }
        break;

    // ---- PUT: editar asiento ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }
        $resp = guardarAsiento($pdo, $body, $id);
        if (!empty($resp['ok'])) {
            echo json_encode($resp);
        } else {
            http_response_code($resp['status'] ?? 400);
            unset($resp['status']);
            echo json_encode($resp);
        }
        break;

    // ---- DELETE: eliminar ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM asientos WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Asiento no encontrado']);
            break;
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}


/**
 * Crea o actualiza un asiento con su detalle. Valida balance debe=haber.
 */
function guardarAsiento(PDO $pdo, $body, ?int $id): array {
    $fecha       = trim($body['fecha']       ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $detalle     = $body['detalle'] ?? [];

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return ['ok' => false, 'error' => 'Fecha inválida (YYYY-MM-DD)', 'status' => 400];
    }
    if (!$descripcion) {
        return ['ok' => false, 'error' => 'La descripción es obligatoria', 'status' => 400];
    }
    if (!is_array($detalle) || count($detalle) < 2) {
        return ['ok' => false, 'error' => 'Se requieren al menos 2 líneas', 'status' => 400];
    }

    // Normalizar y validar cada línea
    $lineas = [];
    $totDebe  = 0.0;
    $totHaber = 0.0;
    $cuentasIds = [];
    foreach ($detalle as $i => $d) {
        $cuenta_id = isset($d['cuenta_id']) ? (int)$d['cuenta_id'] : 0;
        $debe      = isset($d['debe'])  ? round((float)$d['debe'],  2) : 0.0;
        $haber     = isset($d['haber']) ? round((float)$d['haber'], 2) : 0.0;
        $desc      = isset($d['descripcion']) ? trim($d['descripcion']) : '';

        if (!$cuenta_id) {
            return ['ok' => false, 'error' => "Línea " . ($i+1) . ": cuenta requerida", 'status' => 400];
        }
        if (($debe > 0 && $haber > 0) || ($debe == 0 && $haber == 0)) {
            return ['ok' => false, 'error' => "Línea " . ($i+1) . ": debe ingresar debe O haber (no ambos)", 'status' => 400];
        }
        if ($debe < 0 || $haber < 0) {
            return ['ok' => false, 'error' => "Línea " . ($i+1) . ": importes no pueden ser negativos", 'status' => 400];
        }
        $cuentasIds[] = $cuenta_id;
        $totDebe  += $debe;
        $totHaber += $haber;
        $lineas[] = [
            'cuenta_id' => $cuenta_id,
            'debe'      => $debe,
            'haber'     => $haber,
            'descripcion' => $desc ?: null,
            'orden'     => $i,
        ];
    }

    // Validar balance (con tolerancia 0.01)
    if (abs($totDebe - $totHaber) > 0.01) {
        return ['ok' => false, 'error' => 'El asiento no balancea: Debe ' . number_format($totDebe,2,'.','') . ' ≠ Haber ' . number_format($totHaber,2,'.',''), 'status' => 400];
    }

    // Validar que las cuentas existan, sean imputables y activas
    $idsUnicos = array_unique($cuentasIds);
    $place = implode(',', array_fill(0, count($idsUnicos), '?'));
    $stCu = $pdo->prepare("SELECT id, imputable, activa FROM cuentas WHERE id IN ($place)");
    $stCu->execute($idsUnicos);
    $cuentas = $stCu->fetchAll();
    if (count($cuentas) !== count($idsUnicos)) {
        return ['ok' => false, 'error' => 'Una o más cuentas no existen', 'status' => 400];
    }
    foreach ($cuentas as $c) {
        if ((int)$c['imputable'] !== 1) {
            return ['ok' => false, 'error' => 'Hay cuentas no imputables (de agrupación) seleccionadas', 'status' => 400];
        }
        if ((int)$c['activa'] !== 1) {
            return ['ok' => false, 'error' => 'Hay cuentas inactivas seleccionadas', 'status' => 400];
        }
    }

    $total = $totDebe; // = totHaber

    $pdo->beginTransaction();
    try {
        if ($id) {
            // Update
            $exists = $pdo->prepare("SELECT id FROM asientos WHERE id = ?");
            $exists->execute([$id]);
            if (!$exists->fetch()) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Asiento no encontrado', 'status' => 404];
            }
            $upd = $pdo->prepare("UPDATE asientos SET fecha = ?, descripcion = ?, total = ? WHERE id = ?");
            $upd->execute([$fecha, $descripcion, $total, $id]);
            $pdo->prepare("DELETE FROM asientos_detalle WHERE asiento_id = ?")->execute([$id]);
            $asientoId = $id;
        } else {
            // Insert con número siguiente
            $next = (int) $pdo->query("SELECT COALESCE(MAX(numero),0) + 1 FROM asientos")->fetchColumn();
            $ins = $pdo->prepare("INSERT INTO asientos (numero, fecha, descripcion, total) VALUES (?, ?, ?, ?)");
            $ins->execute([$next, $fecha, $descripcion, $total]);
            $asientoId = (int)$pdo->lastInsertId();
        }

        $insD = $pdo->prepare("INSERT INTO asientos_detalle (asiento_id, cuenta_id, debe, haber, descripcion, orden)
                               VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($lineas as $l) {
            $insD->execute([$asientoId, $l['cuenta_id'], $l['debe'], $l['haber'], $l['descripcion'], $l['orden']]);
        }

        $pdo->commit();
        return ['ok' => true, 'id' => $asientoId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Error: ' . $e->getMessage(), 'status' => 500];
    }
}
