<?php
/**
 * Setup inicial — crea el usuario administrador.
 * Eliminar este archivo luego de usarlo.
 */
require_once __DIR__ . '/../repo-api/config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = trim($_POST['nombre']     ?? '');
    $correo     = trim($_POST['correo']     ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (!$nombre || !$correo || !$contrasena) {
        $error = 'Todos los campos son obligatorios.';
    } else {
        try {
            $pdo = getDB();

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

            $count = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            if ($count > 0) {
                $error = 'Ya existe al menos un usuario. Eliminá este archivo por seguridad.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, contrasena) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $correo, $contrasena]);
                $success = "Usuario <strong>$nombre</strong> creado correctamente. <strong>Eliminá este archivo (setup.php) ahora.</strong>";
            }
        } catch (Exception $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Setup — Repo Admin</title>
  <style>
    body { font-family: sans-serif; background:#f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { background:#fff; border-radius:12px; padding:36px; width:360px; box-shadow:0 4px 20px rgba(0,0,0,.1); }
    h2 { margin-bottom:20px; color:#1e293b; }
    label { display:block; font-size:.82rem; font-weight:600; color:#475569; margin-bottom:5px; }
    input { width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.95rem; margin-bottom:14px; box-sizing:border-box; }
    button { width:100%; padding:12px; background:#f97316; color:#fff; border:none; border-radius:50px; font-size:1rem; font-weight:700; cursor:pointer; }
    .error { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:.85rem; }
    .success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:.85rem; }
    .warn { font-size:.78rem; color:#94a3b8; margin-top:14px; text-align:center; }
  </style>
</head>
<body>
<div class="card">
  <h2>Setup inicial</h2>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
  <?php if (!$success): ?>
  <form method="POST">
    <label>Nombre de usuario</label>
    <input type="text" name="nombre" required>
    <label>Correo electrónico</label>
    <input type="email" name="correo" required>
    <label>Contraseña</label>
    <input type="password" name="contrasena" required>
    <button type="submit">Crear usuario admin</button>
  </form>
  <?php endif; ?>
  <p class="warn">⚠️ Eliminá este archivo después de usarlo.</p>
</div>
</body>
</html>
