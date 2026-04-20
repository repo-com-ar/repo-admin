<?php
/**
 * API admin — Mensajes enviados (solo lectura)
 *
 * GET /lider-admin/api/mensajes.php[?q={texto}&canal={email|whatsapp}]
 *   Lista todos los mensajes enviados a usuarios, ordenados del más reciente al más antiguo.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();


$configPath = __DIR__ . '/../../config/db.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'config no encontrado']);
    exit;
}
require_once $configPath;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDB();

    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mensajes (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            canal        ENUM('email','whatsapp') NOT NULL,
            destinatario VARCHAR(255) NOT NULL,
            destino      VARCHAR(255) NOT NULL DEFAULT '',
            asunto       VARCHAR(500) NOT NULL DEFAULT '',
            mensaje      TEXT        NOT NULL,
            estado       VARCHAR(50)  NOT NULL DEFAULT 'enviado',
            created_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
    $canal = isset($_GET['canal']) ? trim($_GET['canal']) : '';

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[]          = '(m.destino LIKE :q OR m.asunto LIKE :q2 OR m.mensaje LIKE :q3)';
        $params[':q']     = "%$q%";
        $params[':q2']    = "%$q%";
        $params[':q3']    = "%$q%";
    }
    if (in_array($canal, ['email', 'whatsapp'], true)) {
        $where[]          = 'm.canal = :canal';
        $params[':canal'] = $canal;
    }

    $sql = "SELECT id, canal, destinatario, destino, asunto, mensaje, estado, created_at FROM mensajes m";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total    = (int)$pdo->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();
    $email    = (int)$pdo->query("SELECT COUNT(*) FROM mensajes WHERE canal='email'")->fetchColumn();
    $whatsapp = (int)$pdo->query("SELECT COUNT(*) FROM mensajes WHERE canal='whatsapp'")->fetchColumn();

    echo json_encode([
        'ok'    => true,
        'data'  => $mensajes,
        'stats' => [
            'total'    => $total,
            'email'    => $email,
            'whatsapp' => $whatsapp,
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al consultar mensajes']);
}
