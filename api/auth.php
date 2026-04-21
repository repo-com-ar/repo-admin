<?php
/**
 * API de autenticación
 *
 * POST   /repo-admin/api/auth.php  { correo, contrasena }
 *   Valida credenciales contra tabla usuarios.
 *   Respuesta: { ok, usuario, token } + cookie repo_token
 *
 * DELETE /repo-admin/api/auth.php
 *   Cierra sesión eliminando la cookie.
 */
header('Content-Type: application/json');
$_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . ($_origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/jwt.php';
require_once __DIR__ . '/../lib/auth_check.php';
require_once __DIR__ . '/../../repo-api/config/db.php';

// ---- DELETE: logout ----
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    clearAuthCookie();
    echo json_encode(['ok' => true]);
    exit;
}

// ---- POST: login ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$correo    = trim($body['correo']     ?? '');
$contrasena = trim($body['contrasena'] ?? '');

if (!$correo || !$contrasena) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Correo y contraseña requeridos']);
    exit;
}

try {
    $pdo = getDB();

    // Asegurar que la tabla existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre     VARCHAR(100) NOT NULL,
            correo     VARCHAR(255) NOT NULL DEFAULT '',
            celular    VARCHAR(50)  NOT NULL DEFAULT '',
            contrasena VARCHAR(255) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    try {
        $pdo->exec("ALTER TABLE usuarios CHANGE usuario nombre VARCHAR(100) NOT NULL");
    } catch (Exception $e) { /* columna ya renombrada o no existe */ }

    $stmt = $pdo->prepare("SELECT id, nombre, correo FROM usuarios WHERE correo = ? AND contrasena = ? LIMIT 1");
    $stmt->execute([$correo, $contrasena]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos']);
        exit;
    }

    $token = jwt_encode([
        'uid' => (int)$user['id'],
        'usr' => $user['nombre'],
        'email' => $user['correo'],
        'exp' => time() + JWT_TTL,
        'iat' => time(),
    ]);

    setAuthCookie($token);

    echo json_encode([
        'ok'      => true,
        'usuario' => $user['nombre'],
        'token'   => $token,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
