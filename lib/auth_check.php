<?php
/**
 * Middleware de autenticación.
 * Incluir en cada endpoint protegido después del handler OPTIONS.
 *
 * requireAuth()  → valida el JWT de la cookie; si falla:
 *   - peticiones JSON  → 401 JSON
 *   - peticiones HTML  → redirect a login.php
 *
 * authUser()     → devuelve el payload del token o null
 */

require_once __DIR__ . '/jwt.php';

function authUser(): ?array {
    $token = $_COOKIE['lider_token'] ?? '';
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            $token = $m[1];
        }
    }
    if (!$token) return null;
    return jwt_decode($token);
}

function requireAuth(): void {
    if (authUser()) return;

    $accept      = $_SERVER['HTTP_ACCEPT']    ?? '';
    $contentType = $_SERVER['CONTENT_TYPE']   ?? '';
    $self        = $_SERVER['PHP_SELF']        ?? '';
    $uri         = $_SERVER['REQUEST_URI']     ?? '';

    $isApi = (
        strpos($accept, 'application/json') !== false
        || strpos($contentType, 'application/json') !== false
        || strpos($uri, '/api/') !== false
    );

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No autorizado', 'login' => true]);
        exit;
    }

    // Si no hay cookie, pasar error por query
    $dir = rtrim(dirname($self), '/');
    $hasCookie = isset($_COOKIE['lider_token']);
    $err = $hasCookie ? '2' : '1';
    header('Location: ' . $dir . '/login.php?error=' . $err);
    exit;
}

function setAuthCookie(string $token): void {
    $exp = time() + JWT_TTL;
    // Forzar secure=false en desarrollo para permitir cookies sin HTTPS
    // Usar path global y sin domain para máxima compatibilidad
    // Para desarrollo local (HTTP): SameSite=Lax y secure=false
    setcookie('lider_token', $token, $exp, '/');
}

function clearAuthCookie(): void {
    setcookie('lider_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => true,
    ]);
}
