<?php
/**
 * API admin — Productos (CRUD)
 *
 * GET    /lider-admin/api/productos.php[?id={id}&categoria={cat}&q={texto}]
 *   Con ?id devuelve un único producto. Sin él lista con filtros opcionales.
 *   Campos: id, nombre, precio, categoria, emoji, imagen, unidad, peso_pieza, stock_actual, stock_comprometido, stock_minimo, stock_recomendado.
 *
 * POST   /lider-admin/api/productos.php
 *   Crea un producto. Body JSON: { nombre, categoria, precio?, emoji?, imagen?, unidad?, peso_pieza?, stock_actual?, stock_comprometido?, stock_minimo?, stock_recomendado? }
 *   El nombre se normaliza a Title Case con mb_convert_case.
 *
 * PUT    /lider-admin/api/productos.php
 *   Actualiza un producto existente. Body JSON con id + campos a modificar.
 *
 * DELETE /lider-admin/api/productos.php?id={id}
 *   Elimina el producto y borra su imagen de lider-media/productos/ si es local.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();


require_once __DIR__ . '/../../config/db.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar (con filtros opcionales) ----
    case 'GET':
        $cat = $_GET['categoria'] ?? 'todos';
        $q   = trim($_GET['q'] ?? '');
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if ($item) {
                $item['precio'] = (float)$item['precio'];
                $item['peso_pieza'] = isset($item['peso_pieza']) ? ($item['peso_pieza'] !== null ? (float)$item['peso_pieza'] : null) : null;
                $item['stock_actual']      = (int)($item['stock_actual'] ?? 1);
                $item['stock_comprometido'] = (int)($item['stock_comprometido'] ?? 0);
                $item['stock_minimo']      = (int)($item['stock_minimo'] ?? 0);
                $item['stock_recomendado'] = (int)($item['stock_recomendado'] ?? 3);
                $item['stock'] = $item['stock_actual'] > 0;
            }
            echo json_encode(['ok' => true, 'data' => $item ?: null]);
            break;
        }

        $sql    = "SELECT * FROM productos WHERE 1=1";
        $params = [];

        if ($cat !== 'todos') {
            $sql .= " AND categoria = ?";
            $params[] = $cat;
        }
        if ($q !== '') {
            $sql .= " AND nombre LIKE ?";
            $params[] = "%{$q}%";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        foreach ($result as &$p) {
            $p['precio'] = (float)$p['precio'];
            $p['peso_pieza'] = isset($p['peso_pieza']) ? ($p['peso_pieza'] !== null ? (float)$p['peso_pieza'] : null) : null;
            $p['stock_actual']      = (int)($p['stock_actual'] ?? 1);
            $p['stock_comprometido'] = (int)($p['stock_comprometido'] ?? 0);
            $p['stock_minimo']      = (int)($p['stock_minimo'] ?? 0);
            $p['stock_recomendado'] = (int)($p['stock_recomendado'] ?? 3);
            $p['stock'] = $p['stock_actual'] > 0;
        }

        echo json_encode(['ok' => true, 'data' => $result, 'total' => count($result)]);
        break;

    // ---- POST: crear ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['nombre']) || empty($body['categoria'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nombre y categoría son obligatorios']);
            break;
        }

        $nombreNorm = mb_convert_case(trim($body['nombre']), MB_CASE_TITLE, 'UTF-8');

        $stock_actual       = (int)($body['stock_actual'] ?? 1);
        $stock_comprometido = (int)($body['stock_comprometido'] ?? 0);
        $stock_minimo       = (int)($body['stock_minimo'] ?? 0);
        $stock_recomendado  = (int)($body['stock_recomendado'] ?? 3);

        $stmt = $pdo->prepare("
            INSERT INTO productos (nombre, precio, categoria, emoji, imagen, unidad, peso_pieza, stock_actual, stock_comprometido, stock_minimo, stock_recomendado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nombreNorm,
            (float)($body['precio'] ?? 0),
            $body['categoria'],
            $body['emoji'] ?? '📦',
            $body['imagen'] ?? '',
            $body['unidad'] ?? 'u',
            ($body['peso_pieza'] ?? null) !== null && $body['peso_pieza'] !== '' ? (float)$body['peso_pieza'] : null,
            $stock_actual,
            $stock_comprometido,
            $stock_minimo,
            $stock_recomendado,
        ]);

        $nuevo = [
            'id'         => (int)$pdo->lastInsertId(),
            'nombre'     => $nombreNorm,
            'precio'     => (float)($body['precio'] ?? 0),
            'categoria'  => $body['categoria'],
            'emoji'      => $body['emoji'] ?? '📦',
            'imagen'     => $body['imagen'] ?? '',
            'unidad'     => $body['unidad'] ?? 'u',
            'stock'      => $stock_actual > 0,
            'peso_pieza' => ($body['peso_pieza'] ?? null) !== null && $body['peso_pieza'] !== '' ? (float)$body['peso_pieza'] : null,
            'stock_actual'       => $stock_actual,
            'stock_comprometido' => $stock_comprometido,
            'stock_minimo'       => $stock_minimo,
            'stock_recomendado'  => $stock_recomendado,
        ];

        echo json_encode(['ok' => true, 'data' => $nuevo]);
        break;

    // ---- PUT: actualizar ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        // Verificar que existe
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $actual = $stmt->fetch();

        if (!$actual) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
            break;
        }

        $nombre    = mb_convert_case(trim($body['nombre'] ?? $actual['nombre']), MB_CASE_TITLE, 'UTF-8');
        $precio    = (float)($body['precio'] ?? $actual['precio']);
        $categoria = $body['categoria']      ?? $actual['categoria'];
        $emoji     = $body['emoji']          ?? $actual['emoji'];
        $imagen    = $body['imagen']         ?? $actual['imagen'];
        $unidad    = $body['unidad']         ?? $actual['unidad'];

        $peso_pieza = array_key_exists('peso_pieza', $body)
            ? ($body['peso_pieza'] !== null && $body['peso_pieza'] !== '' ? (float)$body['peso_pieza'] : null)
            : (isset($actual['peso_pieza']) ? $actual['peso_pieza'] : null);
        $stock_actual       = isset($body['stock_actual'])       ? (int)$body['stock_actual']       : (int)($actual['stock_actual'] ?? 1);
        $stock_comprometido = isset($body['stock_comprometido']) ? (int)$body['stock_comprometido'] : (int)($actual['stock_comprometido'] ?? 0);
        $stock_minimo       = isset($body['stock_minimo'])       ? (int)$body['stock_minimo']       : (int)($actual['stock_minimo'] ?? 0);
        $stock_recomendado  = isset($body['stock_recomendado'])  ? (int)$body['stock_recomendado']  : (int)($actual['stock_recomendado'] ?? 3);

        $stmt = $pdo->prepare("
            UPDATE productos SET nombre=?, precio=?, categoria=?, emoji=?, imagen=?, unidad=?, peso_pieza=?, stock_actual=?, stock_comprometido=?, stock_minimo=?, stock_recomendado=?
            WHERE id=?
        ");
        $stmt->execute([$nombre, $precio, $categoria, $emoji, $imagen, $unidad, $peso_pieza, $stock_actual, $stock_comprometido, $stock_minimo, $stock_recomendado, $id]);

        $actualizado = [
            'id' => $id, 'nombre' => $nombre, 'precio' => $precio,
            'categoria' => $categoria, 'emoji' => $emoji, 'imagen' => $imagen,
            'unidad' => $unidad, 'stock' => $stock_actual > 0, 'peso_pieza' => $peso_pieza,
            'stock_actual'       => $stock_actual,
            'stock_comprometido' => $stock_comprometido,
            'stock_minimo'       => $stock_minimo,
            'stock_recomendado'  => $stock_recomendado,
        ];

        echo json_encode(['ok' => true, 'data' => $actualizado]);
        break;

    // ---- DELETE ----
    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        // Obtener imagen antes de eliminar
        $stmt = $pdo->prepare("SELECT imagen FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch();

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
            break;
        }

        // Eliminar registro
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);

        // Eliminar imagen local si existe en lider-media/productos/
        if (!empty($producto['imagen'])) {
            $mediaDir = __DIR__ . '/../../lider-media/productos/';
            $nombreArchivo = basename($producto['imagen']);
            $rutaArchivo = $mediaDir . $nombreArchivo;
            if (strpos(realpath($rutaArchivo) ?: '', realpath($mediaDir)) === 0 && is_file($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
