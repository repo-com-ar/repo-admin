<?php
/**
 * API admin — Categorías (CRUD) con 1 nivel de jerarquía.
 *
 * Modelo: raíces (parent_id NULL) agrupan subcategorías (parent_id = id de raíz).
 * No se permiten sub-subcategorías.
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
 *   Elimina. Falla si tiene productos o subcategorías.
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
 * Valida que $parentId sea un ID de una categoría raíz existente.
 * Devuelve null si es válido o no se setea; devuelve string de error si es inválido.
 */
function validarParentId(PDO $pdo, ?string $parentId, ?string $idPropio = null): ?string {
    if ($parentId === null || $parentId === '') return null;
    if ($idPropio !== null && $parentId === $idPropio) return 'Una categoría no puede ser su propio padre';
    $stmt = $pdo->prepare("SELECT parent_id FROM categorias WHERE id = ?");
    $stmt->execute([$parentId]);
    $row = $stmt->fetch();
    if (!$row) return 'La categoría padre no existe';
    if ($row['parent_id'] !== null) return 'Solo se permite 1 nivel: el padre debe ser una categoría raíz';
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
            // Si esta categoría tiene hijos, no puede convertirse en subcategoría
            if ($parentId !== null) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE parent_id = ?");
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'Esta categoría tiene subcategorías; no puede convertirse en subcategoría']);
                    break;
                }
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

        // Verificar si tiene subcategorías
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE parent_id = ?");
        $stmt->execute([$id]);
        $subs = (int)$stmt->fetchColumn();
        if ($subs > 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => "No se puede eliminar: tiene {$subs} subcategoría(s)"]);
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
