<?php
/**
 * API admin — Configuración del sistema
 *
 * GET /lider-admin/api/configuracion.php
 *   Devuelve todos los parámetros de configuración como objeto clave→valor.
 *   Crea la tabla `configuracion` e inserta defaults si aún no existen.
 *
 * PUT /lider-admin/api/configuracion.php
 *   Actualiza uno o más parámetros. Body JSON: { clave: valor, ... }
 *   Solo se aceptan claves permitidas: pedido_minimo, centro_dist_lat,
 *   centro_dist_lng, precio_km.
 *
 * Claves disponibles:
 *   pedido_minimo    — monto mínimo para confirmar un pedido (0 = sin mínimo)
 *   centro_dist_lat  — latitud del centro de distribución
 *   centro_dist_lng  — longitud del centro de distribución
 *   precio_km        — costo de envío por kilómetro (0 = sin costo)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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

// Crear tabla si no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS configuracion (
        clave VARCHAR(100) PRIMARY KEY,
        valor TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Valores por defecto
$defaults = [
    'pedido_minimo'        => '0',
    'centro_dist_lat'      => '',
    'centro_dist_lng'      => '',
    'precio_km'            => '0',
    'datarocket_url'       => 'https://api.databox.net.ar',
    'datarocket_apikey'    => 'z9SACoW1SiHGiyan6JVMwudC73r7Y0An',
    'datarocket_proyecto'  => 'vigicom',
    'datarocket_canal_email' => 'databox',
    'datarocket_canal_wa'  => 'repo-hum',
    'datarocket_remitente' => 'Lider Online',
    'datarocket_remite'    => '1169391123',
];
foreach ($defaults as $clave => $valorDef) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO configuracion (clave, valor) VALUES (?, ?)");
    $stmt->execute([$clave, $valorDef]);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
        $rows = $stmt->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        echo json_encode(['ok' => true, 'data' => $config]);
        break;

    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);

        if (!is_array($body) || empty($body)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Datos requeridos']);
            break;
        }

        $claves_permitidas = ['pedido_minimo', 'centro_dist_lat', 'centro_dist_lng', 'precio_km'];
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

        foreach ($body as $clave => $valor) {
            if (in_array($clave, $claves_permitidas)) {
                $stmt->execute([$clave, $valor]);
            }
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}
