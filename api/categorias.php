<?php
/**
 * API admin — Categorías (CRUD) con hasta 3 niveles de jerarquía.
 *
 * Modelo:
 *   - Nivel 0 (raíz):             parent_id IS NULL
 *   - Nivel 1 (subcategoría):     parent_id = id de una raíz
 *   - Nivel 2 (subsubcategoría):  parent_id = id de una subcategoría
 * Profundidad máxima: 2 (tres niveles). No se aceptan descendientes más profundos.
 *
 * GET    /repo-admin/api/categorias.php[?todas=1]
 *   Lista las categorías. Sin ?todas solo devuelve las activas.
 *
 * POST   /repo-admin/api/categorias.php
 *   Crea una nueva categoría. Body JSON: { id, label, emoji?, imagen?, parent_id? }
 *
 * PUT    /repo-admin/api/categorias.php
 *   Actualiza. Body JSON: { id, label?, emoji?, imagen?, orden?, activa?, parent_id? }
 *
 * DELETE /repo-admin/api/categorias.php?id={id}
 *   Elimina. Falla si tiene productos o descendientes.
 */
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
    echo json_encode(['ok' => false, 'error' => 'Error de conexión']);
    exit;
}

// Migración perezosa: parent_id
try { $pdo->query("SELECT parent_id FROM categorias LIMIT 1"); } catch (Exception $e) {
    $pdo->exec("ALTER TABLE categorias ADD COLUMN parent_id VARCHAR(50) NULL DEFAULT NULL, ADD INDEX idx_parent_id (parent_id)");
}

/**
 * Devuelve la profundidad de la categoría:
 *   0 = raíz,  1 = subcategoría,  2 = subsubcategoría.
 * Si no existe devuelve -1.
 */
function depthOf(PDO $pdo, string $id): int {
    $stmt = $pdo->prepare("SELECT parent_id FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return -1;
    if ($row['parent_id'] === null) return 0;
    $parent = $row['parent_id'];
    $stmt->execute([$parent]);
    $row2 = $stmt->fetch();
    if (!$row2 || $row2['parent_id'] === null) return 1;
    return 2;
}

/**
 * Profundidad máxima del subárbol de $id relativa a $id (0 si no tiene hijos,
 * 1 si tiene hijos directos, 2 si tiene nietos). No evalúa más allá porque
 * el modelo sólo admite 3 niveles.
 */
function maxSubtreeDepth(PDO $pdo, string $id): int {
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE parent_id = ?");
    $stmt->execute([$id]);
    $hijos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$hijos) return 0;
    $ph = implode(',', array_fill(0, count($hijos), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE parent_id IN ($ph)");
    $stmt->execute($hijos);
    return ((int)$stmt->fetchColumn() > 0) ? 2 : 1;
}

/**
 * Valida que $parentId sea un padre admisible para la categoría $idPropio.
 * - Permite hasta 3 niveles (parent puede ser raíz o subcategoría).
 * - Si $idPropio tiene descendientes, los niveles resultantes no deben pasar de 2.
 * Devuelve null si es válido o string de error.
 */
function validarParentId(PDO $pdo, ?string $parentId, ?string $idPropio = null): ?string {
    if ($parentId === null || $parentId === '') return null;
    if ($idPropio !== null && $parentId === $idPropio) return 'Una categoría no puede ser su propio padre';

    $dp = depthOf($pdo, $parentId);
    if ($dp === -1) return 'La categoría padre no existe';
    if ($dp >= 2)  return 'Solo se permiten 3 niveles: el padre no puede ser una subsubcategoría';

    if ($idPropio !== null) {
        // Evitar ciclos: el padre no puede ser descendiente actual de $idPropio.
        $cursor = $parentId;
        for ($i = 0; $i < 3 && $cursor !== null; $i++) {
            $stmt = $pdo->prepare("SELECT parent_id FROM categorias WHERE id = ?");
            $stmt->execute([$cursor]);
            $row = $stmt->fetch();
            if (!$row) break;
            if ($row['parent_id'] === $idPropio) return 'No se puede mover: crearía un ciclo';
            $cursor = $row['parent_id'];
        }
        // Verificar que el subárbol propio entre en el margen restante (2 - ($dp+1)).
        $sub = maxSubtreeDepth($pdo, $idPropio);
        $margen = 2 - ($dp + 1);
        if ($sub > $margen) {
            return 'No se puede mover: esta categoría tiene descendientes que excederían los 3 niveles bajo ese padre';
        }
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar categorías ----
    case 'GET':
        $todas = isset($_GET['todas']);
        $sql = "SELECT id, label, emoji, imagen, orden, activa, productos, parent_id FROM categorias";
        if (!$todas) $sql .= " WHERE activa = 1";
        $sql .= " ORDER BY orden ASC";
        $stmt = $pdo->query($sql);
        $categorias = $stmt->fetchAll();
        foreach ($categorias as &$c) {
            $c['orden']     = (int)$c['orden'];
            $c['activa']    = (bool)$c['activa'];
            $c['productos'] = (int)$c['productos'];
            $c['parent_id'] = $c['parent_id'] ?: null;
        }
        echo json_encode(['ok' => true, 'data' => $categorias]);
        break;

    // ---- POST: crear categoría ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['id']) || empty($body['label'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID y nombre son obligatorios']);
            break;
        }

        $id = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($body['id'])));
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido (solo letras minúsculas, números, guiones)']);
            break;
        }

        // Validar parent_id si se envió
        $parentId = !empty($body['parent_id']) ? (string)$body['parent_id'] : null;
        $errParent = validarParentId($pdo, $parentId, $id);
        if ($errParent) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $errParent]);
            break;
        }

        // Obtener siguiente orden
        $maxOrden = $pdo->query("SELECT COALESCE(MAX(orden), 0) FROM categorias")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO categorias (id, label, emoji, imagen, orden, activa, parent_id)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ");

        try {
            $stmt->execute([
                $id,
                trim($body['label']),
                $body['emoji'] ?? '📦',
                $body['imagen'] ?? '',
                $maxOrden + 1,
                $parentId,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Ya existe una categoría con ese ID']);
                break;
            }
            throw $e;
        }

        echo json_encode(['ok' => true, 'data' => [
            'id' => $id, 'label' => trim($body['label']),
            'emoji' => $body['emoji'] ?? '📦', 'imagen' => $body['imagen'] ?? '',
            'orden' => $maxOrden + 1, 'activa' => true, 'parent_id' => $parentId,
        ]]);
        break;

    // ---- PUT: actualizar categoría ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id = $body['id'] ?? '';

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
        $stmt->execute([$id]);
        $actual = $stmt->fetch();

        if (!$actual) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Categoría no encontrada']);
            break;
        }

        $label  = trim($body['label']  ?? $actual['label']);
        $emoji  = $body['emoji']       ?? $actual['emoji'];
        $imagen = $body['imagen']      ?? $actual['imagen'];
        $orden  = isset($body['orden']) ? (int)$body['orden'] : (int)$actual['orden'];
        $activa = isset($body['activa']) ? (int)(bool)$body['activa'] : (int)$actual['activa'];

        // parent_id: explícito null vacía, explícito string setea, ausente mantiene
        if (array_key_exists('parent_id', $body)) {
            $parentId = !empty($body['parent_id']) ? (string)$body['parent_id'] : null;
            $errParent = validarParentId($pdo, $parentId, $id);
            if ($errParent) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $errParent]);
                break;
            }
        } else {
            $parentId = $actual['parent_id'];
        }

        $stmt = $pdo->prepare("UPDATE categorias SET label=?, emoji=?, imagen=?, orden=?, activa=?, parent_id=? WHERE id=?");
        $stmt->execute([$label, $emoji, $imagen, $orden, $activa, $parentId, $id]);

        echo json_encode(['ok' => true, 'data' => [
            'id' => $id, 'label' => $label, 'emoji' => $emoji,
            'imagen' => $imagen, 'orden' => $orden, 'activa' => (bool)$activa,
            'parent_id' => $parentId,
        ]]);
        break;

    // ---- DELETE: eliminar categoría ----
    case 'DELETE':
        $id = $_GET['id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        // Verificar si tiene productos asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => "No se puede eliminar: tiene {$count} producto(s) asociado(s)"]);
            break;
        }

        // Verificar si tiene descendientes directos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE parent_id = ?");
        $stmt->execute([$id]);
        $subs = (int)$stmt->fetchColumn();
        if ($subs > 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => "No se puede eliminar: tiene {$subs} subcategoría(s) directa(s)"]);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Categoría no encontrada']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
