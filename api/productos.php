<?php
/**
 * API admin — Productos (CRUD)
 *
 * GET    /repo-admin/api/productos.php[?id={id}&categoria={cat}&q={texto}]
 * POST   /repo-admin/api/productos.php  — { nombre, categoria, precio?, sku?, ean?, contenido?, imagen?, unidad?, stock_* }
 * PUT    /repo-admin/api/productos.php  — { id, ...campos }
 * DELETE /repo-admin/api/productos.php?id={id}
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

/**
 * Devuelve true si $catId existe y es una subsubcategoría (nivel 2: su padre
 * tiene padre). Todos los productos deben asignarse a una categoría de este nivel.
 */
function categoriaEsNivel2(PDO $pdo, string $catId): bool {
    $stmt = $pdo->prepare("SELECT parent_id FROM categorias WHERE id = ?");
    $stmt->execute([$catId]);
    $row = $stmt->fetch();
    if (!$row || $row['parent_id'] === null) return false;
    $stmt->execute([$row['parent_id']]);
    $row2 = $stmt->fetch();
    return $row2 && $row2['parent_id'] !== null;
}

function normalizarProducto(array $p): array {
    $p['sku']          = (int)($p['sku'] ?? 0);
    $p['precio_compra'] = (float)($p['precio_compra'] ?? 0);
    $p['margen']        = (float)($p['margen'] ?? 0);
    $p['precio_venta']  = (float)($p['precio_venta'] ?? 0);
    $p['contenido']     = $p['contenido'] ?? null;
    $p['stock_actual']       = (int)($p['stock_actual'] ?? 1);
    $p['stock_comprometido'] = (int)($p['stock_comprometido'] ?? 0);
    $p['stock_minimo']       = (int)($p['stock_minimo'] ?? 0);
    $p['stock_recomendado']  = (int)($p['stock_recomendado'] ?? 3);
    $p['proveedor_id']       = isset($p['proveedor_id']) && $p['proveedor_id'] !== null ? (int)$p['proveedor_id'] : null;
    $p['stock'] = $p['stock_actual'] > 0;
    if (!empty($p['imagen']) && strpos($p['imagen'], 'http') !== 0) {
        $p['imagen'] = 'https://media.repo.com.ar/productos/' . basename($p['imagen']);
    }
    return $p;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET ----
    case 'GET':
        $cat = $_GET['categoria'] ?? 'todos';
        $q   = trim($_GET['q'] ?? '');
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            echo json_encode(['ok' => true, 'data' => $item ? normalizarProducto($item) : null]);
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
        $result = array_map('normalizarProducto', $stmt->fetchAll());

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
        if (!categoriaEsNivel2($pdo, (string)$body['categoria'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El producto debe asociarse a una subsubcategoría (nivel 3)']);
            break;
        }

        $nombreNorm   = mb_convert_case(trim($body['nombre']), MB_CASE_TITLE, 'UTF-8');
        $ean          = trim($body['ean'] ?? '');
        $contenido    = trim($body['contenido'] ?? '');
        $precio_compra = (float)($body['precio_compra'] ?? 0);
        $margen        = (float)($body['margen'] ?? 0);

        // SKU: usar el del formulario si se proporcionó, si no generar el siguiente
        if (!empty($body['sku'])) {
            $sku = (int)$body['sku'];
        } else {
            $sku = (int)$pdo->query("SELECT COALESCE(MAX(sku), 999) + 1 FROM productos")->fetchColumn();
        }

        $stock_actual       = (int)($body['stock_actual'] ?? 1);
        $stock_comprometido = (int)($body['stock_comprometido'] ?? 0);
        $stock_minimo       = (int)($body['stock_minimo'] ?? 0);
        $stock_recomendado  = (int)($body['stock_recomendado'] ?? 3);
        $proveedor_id       = !empty($body['proveedor_id']) ? (int)$body['proveedor_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO productos (sku, ean, nombre, precio_compra, margen, precio_venta, categoria, imagen, contenido, unidad, stock_actual, stock_comprometido, stock_minimo, stock_recomendado, proveedor_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sku,
            $ean,
            $nombreNorm,
            $precio_compra,
            $margen,
            (float)($body['precio_venta'] ?? 0),
            $body['categoria'],
            $body['imagen'] ?? '',
            $contenido ?: null,
            $body['unidad'] ?? 'u',
            $stock_actual,
            $stock_comprometido,
            $stock_minimo,
            $stock_recomendado,
            $proveedor_id,
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        $nuevo = [
            'id'           => $nuevoId,
            'sku'          => $sku,
            'ean'          => $ean,
            'nombre'       => $nombreNorm,
            'precio_compra' => $precio_compra,
            'margen'        => $margen,
            'precio_venta'  => (float)($body['precio_venta'] ?? 0),
            'categoria'    => $body['categoria'],
            'imagen'       => $body['imagen'] ?? '',
            'contenido'    => $contenido ?: null,
            'unidad'       => $body['unidad'] ?? 'u',
            'stock'        => $stock_actual > 0,
            'stock_actual'       => $stock_actual,
            'stock_comprometido' => $stock_comprometido,
            'stock_minimo'       => $stock_minimo,
            'stock_recomendado'  => $stock_recomendado,
            'proveedor_id'       => $proveedor_id,
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

        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $actual = $stmt->fetch();

        if (!$actual) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
            break;
        }

        $nombre    = mb_convert_case(trim($body['nombre'] ?? $actual['nombre']), MB_CASE_TITLE, 'UTF-8');
        $precio_venta = (float)($body['precio_venta'] ?? $actual['precio_venta']);
        $categoria = $body['categoria'] ?? $actual['categoria'];
        // Solo validamos el nivel si la categoría cambia en este PUT (datos
        // heredados pueden estar en niveles 0/1 y no queremos bloquear ediciones
        // que no tocan la categoría).
        if (array_key_exists('categoria', $body) && $categoria !== $actual['categoria']) {
            if (!$categoria || !categoriaEsNivel2($pdo, (string)$categoria)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'El producto debe asociarse a una subsubcategoría (nivel 3)']);
                break;
            }
        }
        $sku           = isset($body['sku']) && $body['sku'] !== '' ? (int)$body['sku'] : (int)($actual['sku'] ?? 0);
        $ean           = isset($body['ean'])       ? trim($body['ean'])       : ($actual['ean'] ?? '');
        $precio_compra = isset($body['precio_compra']) ? (float)$body['precio_compra'] : (float)($actual['precio_compra'] ?? 0);
        $margen        = isset($body['margen'])    ? (float)$body['margen']    : (float)($actual['margen'] ?? 0);
        $contenido     = isset($body['contenido']) ? (trim($body['contenido']) ?: null) : ($actual['contenido'] ?? null);
        $imagen        = $body['imagen']  ?? $actual['imagen'];
        $unidad        = $body['unidad']  ?? $actual['unidad'];

        $stock_actual       = isset($body['stock_actual'])       ? (int)$body['stock_actual']       : (int)($actual['stock_actual'] ?? 1);
        $stock_comprometido = isset($body['stock_comprometido']) ? (int)$body['stock_comprometido'] : (int)($actual['stock_comprometido'] ?? 0);
        $stock_minimo       = isset($body['stock_minimo'])       ? (int)$body['stock_minimo']       : (int)($actual['stock_minimo'] ?? 0);
        $stock_recomendado  = isset($body['stock_recomendado'])  ? (int)$body['stock_recomendado']  : (int)($actual['stock_recomendado'] ?? 3);
        $proveedor_id       = array_key_exists('proveedor_id', $body)
            ? (!empty($body['proveedor_id']) ? (int)$body['proveedor_id'] : null)
            : (isset($actual['proveedor_id']) ? (int)$actual['proveedor_id'] : null);

        $stmt = $pdo->prepare("
            UPDATE productos
            SET nombre=?, precio_compra=?, margen=?, precio_venta=?, categoria=?, sku=?, ean=?, contenido=?, imagen=?, unidad=?,
                stock_actual=?, stock_comprometido=?, stock_minimo=?, stock_recomendado=?, proveedor_id=?
            WHERE id=?
        ");
        $stmt->execute([
            $nombre, $precio_compra, $margen, $precio_venta, $categoria, $sku, $ean, $contenido, $imagen, $unidad,
            $stock_actual, $stock_comprometido, $stock_minimo, $stock_recomendado, $proveedor_id,
            $id,
        ]);

        $actualizado = [
            'id' => $id, 'sku' => $sku, 'ean' => $ean,
            'nombre' => $nombre, 'precio_compra' => $precio_compra, 'margen' => $margen, 'precio_venta' => $precio_venta,
            'categoria' => $categoria, 'imagen' => $imagen,
            'contenido' => $contenido, 'unidad' => $unidad,
            'stock' => $stock_actual > 0,
            'stock_actual'       => $stock_actual,
            'stock_comprometido' => $stock_comprometido,
            'stock_minimo'       => $stock_minimo,
            'stock_recomendado'  => $stock_recomendado,
            'proveedor_id'       => $proveedor_id,
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

        $stmt = $pdo->prepare("SELECT imagen FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch();

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Producto no encontrado']);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);

        if (!empty($producto['imagen'])) {
            $mediaDir = __DIR__ . '/../../repo-media/productos/';
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
