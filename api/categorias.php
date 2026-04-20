<?php
/**
 * API admin — Categorías (CRUD)
 *
 * GET    /lider-admin/api/categorias.php[?todas=1]
 *   Lista las categorías. Sin ?todas solo devuelve las activas.
 *
 * POST   /lider-admin/api/categorias.php
 *   Crea una nueva categoría. Body JSON: { id, label, emoji?, imagen? }
 *   El `id` se normaliza a minúsculas/sin espacios. Falla con 409 si ya existe.
 *
 * PUT    /lider-admin/api/categorias.php
 *   Actualiza una categoría existente. Body JSON: { id, label?, emoji?, imagen?, orden?, activa? }
 *
 * DELETE /lider-admin/api/categorias.php?id={id}
 *   Elimina una categoría. Falla con 409 si tiene productos asociados.
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

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar categorías ----
    case 'GET':
        $todas = isset($_GET['todas']);
        $sql = "SELECT id, label, emoji, imagen, orden, activa FROM categorias";
        if (!$todas) $sql .= " WHERE activa = 1";
        $sql .= " ORDER BY orden ASC";
        $stmt = $pdo->query($sql);
        $categorias = $stmt->fetchAll();
        foreach ($categorias as &$c) {
            $c['orden']  = (int)$c['orden'];
            $c['activa'] = (bool)$c['activa'];
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

        // Obtener siguiente orden
        $maxOrden = $pdo->query("SELECT COALESCE(MAX(orden), 0) FROM categorias")->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO categorias (id, label, emoji, imagen, orden, activa)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        try {
            $stmt->execute([
                $id,
                trim($body['label']),
                $body['emoji'] ?? '📦',
                $body['imagen'] ?? '',
                $maxOrden + 1,
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
            'orden' => $maxOrden + 1, 'activa' => true,
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

        $stmt = $pdo->prepare("UPDATE categorias SET label=?, emoji=?, imagen=?, orden=?, activa=? WHERE id=?");
        $stmt->execute([$label, $emoji, $imagen, $orden, $activa, $id]);

        echo json_encode(['ok' => true, 'data' => [
            'id' => $id, 'label' => $label, 'emoji' => $emoji,
            'imagen' => $imagen, 'orden' => $orden, 'activa' => (bool)$activa,
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
