<?php
/**
 * API admin — Usuarios del backoffice
 *
 * GET    /lider-admin/api/usuarios.php[?q={texto}]
 *   Lista usuarios del panel de administración.
 *
 * POST   /lider-admin/api/usuarios.php
 *   Crea un nuevo usuario. Body JSON: { usuario, correo, celular, contrasena }
 *
 * PUT    /lider-admin/api/usuarios.php
 *   Actualiza un usuario. Body JSON: { id, usuario?, correo?, celular?, contrasena? }
 *
 * DELETE /lider-admin/api/usuarios.php?id={id}
 *   Elimina un usuario.
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

// Migración automática
$pdo->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario    VARCHAR(100) NOT NULL,
        correo     VARCHAR(255) NOT NULL DEFAULT '',
        celular    VARCHAR(50)  NOT NULL DEFAULT '',
        contrasena VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar ----
    case 'GET':
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $params = [];
        $where  = '';
        if ($q) {
            $where    = 'WHERE usuario LIKE ? OR correo LIKE ? OR celular LIKE ?';
            $like     = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $stmt = $pdo->prepare("SELECT id, usuario, correo, celular, contrasena, created_at FROM usuarios $where ORDER BY id DESC LIMIT 200");
        $stmt->execute($params);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

        echo json_encode(['ok' => true, 'data' => $usuarios, 'stats' => ['total' => $total]]);
        break;

    // ---- POST: crear ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $usuario   = trim($body['usuario']   ?? '');
        $correo    = trim($body['correo']     ?? '');
        $celular   = trim($body['celular']    ?? '');
        $contrasena = trim($body['contrasena'] ?? '');

        if (!$usuario) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'El nombre de usuario es requerido']);
            break;
        }

        // Verificar unicidad de usuario
        $dup = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $dup->execute([$usuario]);
        if ($dup->fetch()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'El usuario ya existe']);
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, correo, celular, contrasena) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario, $correo, $celular, $contrasena]);
        $id = (int)$pdo->lastInsertId();

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ---- PUT: editar ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = isset($body['id']) ? (int)$body['id'] : 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $campos = [];
        $params = [];

        if (isset($body['usuario']) && trim($body['usuario']) !== '') {
            // Verificar unicidad excluyendo el propio registro
            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $dup->execute([trim($body['usuario']), $id]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'El usuario ya existe']);
                break;
            }
            $campos[] = 'usuario = ?';
            $params[] = trim($body['usuario']);
        }
        if (isset($body['correo'])) {
            $campos[] = 'correo = ?';
            $params[] = trim($body['correo']);
        }
        if (isset($body['celular'])) {
            $campos[] = 'celular = ?';
            $params[] = trim($body['celular']);
        }
        if (isset($body['contrasena']) && trim($body['contrasena']) !== '') {
            $campos[] = 'contrasena = ?';
            $params[] = trim($body['contrasena']);
        }

        if (empty($campos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nada que actualizar']);
            break;
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
            break;
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        break;

    // ---- DELETE: eliminar ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
