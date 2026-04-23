<?php
/**
 * Ticket de pedido — generado server-side
 * URL: /repo-admin/ticket.php?id={pedido_id}
 */
require_once __DIR__ . '/lib/auth_check.php';
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

require_once __DIR__ . '/../repo-api/config/db.php';
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT p.id, p.numero, p.cliente, p.correo, p.celular, p.direccion, p.notas,
           p.total, p.estado, p.created_at AS fecha,
           r.nombre AS repartidor_nombre, r.celular AS repartidor_celular
    FROM pedidos p
    LEFT JOIN repartidores r ON r.id = p.repartidor_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { http_response_code(404); echo 'Pedido no encontrado'; exit; }

$stmtItems = $pdo->prepare("SELECT nombre, cantidad, precio FROM pedido_items WHERE pedido_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$fecha = $p['fecha'] ? date('d/m/Y H:i', strtotime($p['fecha'])) : '';

$itemsHtml = '';
foreach ($items as $it) {
    $sub = number_format($it['precio'] * $it['cantidad'], 0, ',', '.');
    $itemsHtml .= '<tr>'
        . '<td class="prod-nom">'   . h($it['nombre'])  . '</td>'
        . '<td class="prod-cant">×' . (int)$it['cantidad'] . '</td>'
        . '<td class="prod-total">$' . $sub             . '</td>'
        . '</tr>';
}

$total = number_format((float)$p['total'], 0, ',', '.');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ticket <?= h($p['numero']) ?></title>
  <style>
    body { font-family: monospace; font-size: 13px; color: #000; margin: 0; padding: 16px; max-width: 320px; }
    h1 { font-size: 15px; text-align: center; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 1px; }
    .num { text-align: center; font-size: 22px; font-weight: bold; letter-spacing: 3px; margin: 4px 0; }
    .fecha { text-align: center; font-size: 11px; color: #555; margin-bottom: 10px; }
    .sep { border-top: 1px dashed #000; margin: 10px 0; }
    p { margin: 3px 0; font-size: 13px; }
    .lbl { font-weight: bold; }
    table.prod { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 4px; }
    table.prod td { padding: 4px 2px; vertical-align: top; }
    table.prod .prod-nom { width: 60%; }
    table.prod .prod-cant { text-align: center; white-space: nowrap; }
    table.prod .prod-total { text-align: right; font-weight: 600; white-space: nowrap; }
    table.prod tr + tr td { border-top: 1px dotted #bbb; }
    .total-row { display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; margin-top: 8px; border-top: 2px solid #000; padding-top: 8px; }
    .footer { text-align: center; font-size: 10px; color: #888; margin-top: 16px; }
    @media print { body { padding: 0; } }
  </style>
</head>
<body>

<h1>Ticket de Pedido</h1>
<div class="num"><?= h($p['numero']) ?></div>
<div class="fecha"><?= $fecha ?></div>

<div class="sep"></div>

<p><span class="lbl">Cliente:</span> <?= h($p['cliente']) ?></p>
<?php if ($p['direccion']): ?><p><span class="lbl">Domicilio:</span> <?= h($p['direccion']) ?></p><?php endif; ?>
<?php if ($p['celular']):   ?><p><span class="lbl">Celular:</span> <?= h($p['celular']) ?></p><?php endif; ?>
<?php if ($p['correo']):    ?><p><span class="lbl">Correo:</span> <?= h($p['correo']) ?></p><?php endif; ?>
<?php if ($p['notas']):     ?><p><span class="lbl">Notas:</span> <em><?= h($p['notas']) ?></em></p><?php endif; ?>

<?php if ($p['repartidor_nombre']): ?>
<div class="sep"></div>
<p><span class="lbl">Repartidor:</span> <?= h($p['repartidor_nombre']) ?></p>
<?php if ($p['repartidor_celular']): ?><p><span class="lbl">Celular:</span> <?= h($p['repartidor_celular']) ?></p><?php endif; ?>
<?php endif; ?>

<div class="sep"></div>

<table class="prod"><tbody><?= $itemsHtml ?></tbody></table>
<div class="total-row"><span>TOTAL</span><span>$<?= $total ?></span></div>
<div class="footer">Gracias por su compra</div>

<script>window.print();</script>
</body>
</html>
