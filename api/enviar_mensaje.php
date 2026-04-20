<?php
/**
 * API admin — Enviar mensaje vía datarocket
 *
 * POST /lider-admin/api/enviar_mensaje.php
 * Body JSON: {
 *   canal:        "email" | "whatsapp",
 *   destinatario: "Nombre visible",
 *   destino:      "email@ejemplo.com" | "5491112345678",
 *   asunto:       "Asunto (solo email)",
 *   cuerpo:       "Texto del mensaje"
 * }
 *
 * Configuración requerida en tabla `configuracion`:
 *   datarocket_url          — URL base de la API (ej: https://api.databox.net.ar)
 *   datarocket_apikey       — Bearer token para autorizar
 *   datarocket_proyecto     — ID o UUID del proyecto
 *   datarocket_canal_email  — ID o UUID del canal AWS SES configurado
 *   datarocket_canal_wa     — ID o UUID del canal WhatsApp (Evolution) configurado
 *   datarocket_remitente    — Nombre visible del remitente
 *   datarocket_remite       — Email o teléfono del remitente
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();


require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// Leer body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$canal        = trim($body['canal']        ?? '');
$destinatario = trim($body['destinatario'] ?? '');
$destino      = trim($body['destino']      ?? '');
$asunto       = trim($body['asunto']       ?? '');
$cuerpo       = trim($body['cuerpo']       ?? '');

// Validar campos requeridos
if (!in_array($canal, ['email', 'whatsapp'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Canal inválido. Debe ser email o whatsapp']);
    exit;
}
if ($destinatario === '' || $destino === '' || $cuerpo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios']);
    exit;
}
if ($canal === 'email' && $asunto === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El asunto es obligatorio para correo']);
    exit;
}

try {
    $pdo = getDB();

    // Crear tabla mensajes si no existe
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
    // Leer configuración datarocket
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'datarocket_%'");
    $cfg  = [];
    foreach ($stmt->fetchAll() as $row) {
        $cfg[$row['clave']] = $row['valor'];
    }

    $apiUrl     = rtrim($cfg['datarocket_url']          ?? 'https://api.databox.net.ar', '/');
    $apikey     = $cfg['datarocket_apikey']              ?? 'z9SACoW1SiHGiyan6JVMwudC73r7Y0An';
    $proyecto   = $cfg['datarocket_proyecto']            ?? 'vigicom';
    $canalEmail = $cfg['datarocket_canal_email']         ?? 'databox';
    $canalWa    = $cfg['datarocket_canal_wa']            ?? 'repo-hum';
    $remitente  = $cfg['datarocket_remitente']           ?? 'Lider Online';
    $remite     = $cfg['datarocket_remite']              ?? '1169391123';

    $estadoFinal = 'pendiente';
    $errorMsg    = '';

    // Construir payload según canal
    if ($canal === 'email') {
        $payload = [
            'servicio'     => 'awsses',
            'proyecto'     => $proyecto,
            'canal'        => $canalEmail,
            'plantilla'    => 'repo',
            'remitente'    => $remitente,
            'remite'       => $remite,
            'destinatario' => $destinatario,
            'destino'      => $destino,
            'asunto'       => $asunto,
            'cuerpo'       => $cuerpo,
            'formato'      => 'T',
        ];
    } else {
        $payload = [
            'servicio'     => 'evolution',
            'proyecto'     => $proyecto,
            'canal'        => $canalWa,
            'remitente'    => $remitente,
            'remite'       => $remite,
            'destinatario' => $destinatario,
            'destino'      => $destino,
            'asunto'       => $asunto,
            'cuerpo'       => $cuerpo,
            'formato'      => 'T',
        ];
    }

    // Llamar a datarocket
    $ch = curl_init($apiUrl . '/v3/datarocket/mensajes/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $respuesta = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($curlErrno === 0 && $respuesta !== false) {
        $json = json_decode($respuesta, true);
        if (isset($json['meta']['code']) && $json['meta']['code'] == 200) {
            $estadoFinal = 'enviado';
        } elseif (isset($json['meta']['message'])) {
            $estadoFinal = 'error';
            $errorMsg    = $json['meta']['message'];
        } else {
            $estadoFinal = 'enviado';
        }
    } else {
        $estadoFinal = 'error';
        $errorMsg    = 'Error de conexión con datarocket';
    }

    // Guardar en tabla local
    $stmt = $pdo->prepare("
        INSERT INTO mensajes (canal, destinatario, destino, asunto, mensaje, estado)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$canal, $destinatario, $destino, $asunto, $cuerpo, $estadoFinal]);

    if ($estadoFinal === 'error') {
        echo json_encode(['ok' => false, 'error' => $errorMsg ?: 'Error al enviar el mensaje']);
    } else {
        echo json_encode(['ok' => true, 'estado' => $estadoFinal]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
