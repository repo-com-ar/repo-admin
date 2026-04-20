<?php
/**
 * API admin — Upload de imágenes de productos
 *
 * POST /lider-admin/api/upload.php (multipart/form-data, campo: imagen)
 *   Sube una imagen al directorio lider-media/productos/.
 *   Valida tipo MIME real (JPG, PNG, WEBP, GIF) y tamaño máximo de 5MB.
 *   Genera un nombre de archivo seguro: {timestamp}_{random8bytes}.{ext}
 *
 * Respuesta:
 *   { ok: true, archivo: "nombre.jpg", url: "../lider-media/productos/nombre.jpg" }
 *
 * Errores posibles:
 *   Tipo no permitido, excede 5MB, carpeta destino inexistente, error de escritura.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../lib/auth_check.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// Carpeta destino con permisos de escritura
$uploadDir = __DIR__ . '/../../lider-media/productos/';

if (!is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Carpeta de destino no existe']);
    exit;
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    $errores = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo del servidor',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo del formulario',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco',
    ];
    $code = $_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'error' => $errores[$code] ?? 'Error al subir archivo']);
    exit;
}

$file = $_FILES['imagen'];

// Validar tipo MIME real
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($file['tmp_name']);
$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($mimeReal, $mimesPermitidos, true)) {
    echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido. Solo JPG, PNG, WEBP, GIF']);
    exit;
}

// Validar tamaño (max 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'error' => 'El archivo excede los 5MB']);
    exit;
}

// Generar nombre seguro: timestamp + random + extensión
$extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext = $extensiones[$mimeReal];
$nombreArchivo = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$rutaDestino = $uploadDir . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
    echo json_encode(['ok' => false, 'error' => 'Error al guardar el archivo']);
    exit;
}

// Construir URL relativa al media
$urlRelativa = '../lider-media/productos/' . $nombreArchivo;

echo json_encode([
    'ok'      => true,
    'archivo' => $nombreArchivo,
    'url'     => $urlRelativa,
]);
