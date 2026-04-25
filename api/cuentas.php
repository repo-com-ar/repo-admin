<?php
/**
 * API admin — Plan de Cuentas (CRUD)
 *
 * GET    /repo-admin/api/cuentas.php[?q={texto}&tipo={...}]
 * POST   /repo-admin/api/cuentas.php           — crear cuenta
 * PUT    /repo-admin/api/cuentas.php           — editar cuenta
 * DELETE /repo-admin/api/cuentas.php?id={id}   — eliminar (sólo si no tiene hijos)
 *
 * Auto-seed: al primer request, si la tabla está vacía, se carga un plan
 * de cuentas estándar para un supermercado online (2 socios, 3 repartidores,
 * 1 centro de distribución, 2 cuentas bancarias + caja efectivo).
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

// Crear tabla si no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cuentas (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo      VARCHAR(20)  NOT NULL UNIQUE,
        nombre      VARCHAR(160) NOT NULL,
        tipo        ENUM('activo','pasivo','patrimonio','ingreso','egreso') NOT NULL,
        parent_id   INT UNSIGNED DEFAULT NULL,
        nivel       TINYINT UNSIGNED NOT NULL DEFAULT 1,
        imputable   TINYINT(1)   NOT NULL DEFAULT 1,
        naturaleza  ENUM('deudora','acreedora') NOT NULL,
        descripcion TEXT         DEFAULT NULL,
        activa      TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_parent (parent_id),
        INDEX idx_tipo (tipo),
        INDEX idx_codigo (codigo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Auto-seed si tabla vacía
$count = (int) $pdo->query("SELECT COUNT(*) FROM cuentas")->fetchColumn();
if ($count === 0) {
    seedPlanCuentas($pdo);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ---- GET: listar todas las cuentas (jerárquicas) ----
    case 'GET':
        $q    = isset($_GET['q'])    ? trim($_GET['q'])    : '';
        $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

        $where  = [];
        $params = [];

        if ($q) {
            $where[] = '(codigo LIKE ? OR nombre LIKE ?)';
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($tipo && in_array($tipo, ['activo','pasivo','patrimonio','ingreso','egreso'])) {
            $where[] = 'tipo = ?';
            $params[] = $tipo;
        }

        $sql = "SELECT id, codigo, nombre, tipo, parent_id, nivel, imputable, naturaleza, descripcion, activa
                FROM cuentas"
             . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY codigo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // stats por tipo
        $stats = [];
        foreach (['activo','pasivo','patrimonio','ingreso','egreso'] as $t) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE tipo = ?");
            $st->execute([$t]);
            $stats[$t] = (int)$st->fetchColumn();
        }
        $stats['total'] = (int) $pdo->query("SELECT COUNT(*) FROM cuentas")->fetchColumn();

        echo json_encode([
            'ok'    => true,
            'data'  => $rows,
            'stats' => $stats,
        ]);
        break;

    // ---- POST: crear cuenta ----
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);

        $codigo     = trim($body['codigo']     ?? '');
        $nombre     = trim($body['nombre']     ?? '');
        $tipo       = trim($body['tipo']       ?? '');
        $parent_id  = isset($body['parent_id']) && $body['parent_id'] !== '' ? (int)$body['parent_id'] : null;
        $imputable  = !empty($body['imputable']) ? 1 : 0;
        $naturaleza = trim($body['naturaleza'] ?? '');
        $descripcion = trim($body['descripcion'] ?? '');
        $activa     = isset($body['activa']) ? (int)!!$body['activa'] : 1;

        if (!$codigo || !$nombre || !$tipo || !$naturaleza) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Código, nombre, tipo y naturaleza son obligatorios']);
            break;
        }

        $tiposVal = ['activo','pasivo','patrimonio','ingreso','egreso'];
        $natVal   = ['deudora','acreedora'];
        if (!in_array($tipo, $tiposVal) || !in_array($naturaleza, $natVal)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Tipo o naturaleza inválidos']);
            break;
        }

        // Calcular nivel a partir del padre
        $nivel = 1;
        if ($parent_id) {
            $st = $pdo->prepare("SELECT nivel FROM cuentas WHERE id = ?");
            $st->execute([$parent_id]);
            $padre = $st->fetch();
            if (!$padre) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Cuenta padre no encontrada']);
                break;
            }
            $nivel = (int)$padre['nivel'] + 1;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO cuentas (codigo, nombre, tipo, parent_id, nivel, imputable, naturaleza, descripcion, activa)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$codigo, $nombre, $tipo, $parent_id, $nivel, $imputable, $naturaleza, $descripcion ?: null, $activa]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Ya existe una cuenta con ese código']);
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
            }
        }
        break;

    // ---- PUT: editar cuenta ----
    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = isset($body['id']) ? (int)$body['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $existe = $pdo->prepare("SELECT id FROM cuentas WHERE id = ?");
        $existe->execute([$id]);
        if (!$existe->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada']);
            break;
        }

        $campos = [];
        $params = [];

        if (isset($body['codigo']) && trim($body['codigo']) !== '') {
            $campos[] = 'codigo = ?';
            $params[] = trim($body['codigo']);
        }
        if (isset($body['nombre']) && trim($body['nombre']) !== '') {
            $campos[] = 'nombre = ?';
            $params[] = trim($body['nombre']);
        }
        if (isset($body['tipo']) && in_array($body['tipo'], ['activo','pasivo','patrimonio','ingreso','egreso'])) {
            $campos[] = 'tipo = ?';
            $params[] = $body['tipo'];
        }
        if (isset($body['naturaleza']) && in_array($body['naturaleza'], ['deudora','acreedora'])) {
            $campos[] = 'naturaleza = ?';
            $params[] = $body['naturaleza'];
        }
        if (array_key_exists('imputable', $body)) {
            $campos[] = 'imputable = ?';
            $params[] = !empty($body['imputable']) ? 1 : 0;
        }
        if (array_key_exists('descripcion', $body)) {
            $campos[] = 'descripcion = ?';
            $params[] = trim($body['descripcion']) ?: null;
        }
        if (array_key_exists('activa', $body)) {
            $campos[] = 'activa = ?';
            $params[] = !empty($body['activa']) ? 1 : 0;
        }

        if (empty($campos)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nada que actualizar']);
            break;
        }

        $params[] = $id;
        try {
            $stmt = $pdo->prepare("UPDATE cuentas SET " . implode(', ', $campos) . " WHERE id = ?");
            $stmt->execute($params);
            echo json_encode(['ok' => true, 'id' => $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Ya existe una cuenta con ese código']);
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()]);
            }
        }
        break;

    // ---- DELETE: eliminar cuenta (sólo si no tiene hijos) ----
    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID requerido']);
            break;
        }

        $hijos = $pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE parent_id = ?");
        $hijos->execute([$id]);
        if ((int)$hijos->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'No se puede eliminar: la cuenta tiene subcuentas']);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM cuentas WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada']);
            break;
        }

        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
}


/**
 * Carga el plan de cuentas estándar para un supermercado online
 * con 2 socios, 3 repartidores, 1 centro de distribución, 2 bancos + efectivo.
 */
function seedPlanCuentas(PDO $pdo): void {
    // Estructura: [codigo, nombre, tipo, naturaleza, imputable]
    // imputable=0 = cuenta de agrupación; imputable=1 = recibe asientos
    $plan = [
        // ===== ACTIVO =====
        ['1',         'ACTIVO',                                 'activo',     'deudora',   0],
        ['1.1',       'ACTIVO CORRIENTE',                       'activo',     'deudora',   0],
        ['1.1.01',    'Caja y Bancos',                          'activo',     'deudora',   0],
        ['1.1.01.01', 'Caja Efectivo',                          'activo',     'deudora',   1],
        ['1.1.01.02', 'Banco Cuenta Corriente 1',               'activo',     'deudora',   1],
        ['1.1.01.03', 'Banco Cuenta Corriente 2',               'activo',     'deudora',   1],
        ['1.1.02',    'Créditos por Ventas',                    'activo',     'deudora',   0],
        ['1.1.02.01', 'Deudores por Ventas',                    'activo',     'deudora',   1],
        ['1.1.02.02', 'Tarjetas de Crédito a Cobrar',           'activo',     'deudora',   1],
        ['1.1.02.03', 'Mercado Pago a Cobrar',                  'activo',     'deudora',   1],
        ['1.1.03',    'Otros Créditos',                         'activo',     'deudora',   0],
        ['1.1.03.01', 'Anticipos a Proveedores',                'activo',     'deudora',   1],
        ['1.1.03.02', 'Adelantos a Repartidores',               'activo',     'deudora',   1],
        ['1.1.03.03', 'IVA Crédito Fiscal',                     'activo',     'deudora',   1],
        ['1.1.04',    'Bienes de Cambio',                       'activo',     'deudora',   0],
        ['1.1.04.01', 'Mercaderías - Almacén',                  'activo',     'deudora',   1],
        ['1.1.04.02', 'Mercaderías - Bebidas',                  'activo',     'deudora',   1],
        ['1.1.04.03', 'Mercaderías - Frescos',                  'activo',     'deudora',   1],
        ['1.1.04.04', 'Mercaderías - Limpieza',                 'activo',     'deudora',   1],

        ['1.2',       'ACTIVO NO CORRIENTE',                    'activo',     'deudora',   0],
        ['1.2.01',    'Bienes de Uso',                          'activo',     'deudora',   0],
        ['1.2.01.01', 'Inmueble - Centro de Distribución',      'activo',     'deudora',   1],
        ['1.2.01.02', 'Rodados - Vehículo Repartidor 1',        'activo',     'deudora',   1],
        ['1.2.01.03', 'Rodados - Vehículo Repartidor 2',        'activo',     'deudora',   1],
        ['1.2.01.04', 'Rodados - Vehículo Repartidor 3',        'activo',     'deudora',   1],
        ['1.2.01.05', 'Muebles y Útiles',                       'activo',     'deudora',   1],
        ['1.2.01.06', 'Equipos de Computación',                 'activo',     'deudora',   1],
        ['1.2.01.07', 'Instalaciones',                          'activo',     'deudora',   1],
        ['1.2.02',    'Amortizaciones Acumuladas',              'activo',     'acreedora', 0],
        ['1.2.02.01', 'Amort. Acum. Inmuebles',                 'activo',     'acreedora', 1],
        ['1.2.02.02', 'Amort. Acum. Rodados',                   'activo',     'acreedora', 1],
        ['1.2.02.03', 'Amort. Acum. Muebles y Útiles',          'activo',     'acreedora', 1],
        ['1.2.02.04', 'Amort. Acum. Equipos de Computación',    'activo',     'acreedora', 1],

        // ===== PASIVO =====
        ['2',         'PASIVO',                                 'pasivo',     'acreedora', 0],
        ['2.1',       'PASIVO CORRIENTE',                       'pasivo',     'acreedora', 0],
        ['2.1.01',    'Deudas Comerciales',                     'pasivo',     'acreedora', 0],
        ['2.1.01.01', 'Proveedores',                            'pasivo',     'acreedora', 1],
        ['2.1.01.02', 'Documentos a Pagar',                     'pasivo',     'acreedora', 1],
        ['2.1.02',    'Deudas Fiscales',                        'pasivo',     'acreedora', 0],
        ['2.1.02.01', 'IVA Débito Fiscal',                      'pasivo',     'acreedora', 1],
        ['2.1.02.02', 'IVA a Pagar',                            'pasivo',     'acreedora', 1],
        ['2.1.02.03', 'Ingresos Brutos a Pagar',                'pasivo',     'acreedora', 1],
        ['2.1.02.04', 'Impuesto a las Ganancias',               'pasivo',     'acreedora', 1],
        ['2.1.03',    'Deudas Sociales',                        'pasivo',     'acreedora', 0],
        ['2.1.03.01', 'Sueldos a Pagar',                        'pasivo',     'acreedora', 1],
        ['2.1.03.02', 'Cargas Sociales a Pagar',                'pasivo',     'acreedora', 1],
        ['2.1.03.03', 'Provisión Aguinaldo',                    'pasivo',     'acreedora', 1],
        ['2.1.03.04', 'Provisión Vacaciones',                   'pasivo',     'acreedora', 1],
        ['2.1.04',    'Otras Deudas',                           'pasivo',     'acreedora', 0],
        ['2.1.04.01', 'Servicios a Pagar',                      'pasivo',     'acreedora', 1],
        ['2.1.04.02', 'Alquileres a Pagar',                     'pasivo',     'acreedora', 1],

        // ===== PATRIMONIO NETO =====
        ['3',         'PATRIMONIO NETO',                        'patrimonio', 'acreedora', 0],
        ['3.1',       'Capital',                                'patrimonio', 'acreedora', 0],
        ['3.1.01',    'Capital Social',                         'patrimonio', 'acreedora', 0],
        ['3.1.01.01', 'Aporte Socio 1',                         'patrimonio', 'acreedora', 1],
        ['3.1.01.02', 'Aporte Socio 2',                         'patrimonio', 'acreedora', 1],
        ['3.2',       'Resultados',                             'patrimonio', 'acreedora', 0],
        ['3.2.01',    'Resultados Acumulados',                  'patrimonio', 'acreedora', 1],
        ['3.2.02',    'Resultado del Ejercicio',                'patrimonio', 'acreedora', 1],

        // ===== INGRESOS =====
        ['4',         'INGRESOS',                               'ingreso',    'acreedora', 0],
        ['4.1',       'Ventas',                                 'ingreso',    'acreedora', 0],
        ['4.1.01',    'Ventas Almacén',                         'ingreso',    'acreedora', 1],
        ['4.1.02',    'Ventas Bebidas',                         'ingreso',    'acreedora', 1],
        ['4.1.03',    'Ventas Frescos',                         'ingreso',    'acreedora', 1],
        ['4.1.04',    'Ventas Limpieza',                        'ingreso',    'acreedora', 1],
        ['4.2',       'Otros Ingresos',                         'ingreso',    'acreedora', 0],
        ['4.2.01',    'Cargo por Envío',                        'ingreso',    'acreedora', 1],
        ['4.2.02',    'Descuentos Obtenidos',                   'ingreso',    'acreedora', 1],
        ['4.2.03',    'Intereses Ganados',                      'ingreso',    'acreedora', 1],

        // ===== EGRESOS =====
        ['5',         'EGRESOS',                                'egreso',     'deudora',   0],
        ['5.1',       'Costo de Mercadería Vendida',            'egreso',     'deudora',   0],
        ['5.1.01',    'CMV Almacén',                            'egreso',     'deudora',   1],
        ['5.1.02',    'CMV Bebidas',                            'egreso',     'deudora',   1],
        ['5.1.03',    'CMV Frescos',                            'egreso',     'deudora',   1],
        ['5.1.04',    'CMV Limpieza',                           'egreso',     'deudora',   1],

        ['5.2',       'Gastos de Comercialización',             'egreso',     'deudora',   0],
        ['5.2.01',    'Sueldos Repartidores',                   'egreso',     'deudora',   0],
        ['5.2.01.01', 'Sueldo Repartidor 1',                    'egreso',     'deudora',   1],
        ['5.2.01.02', 'Sueldo Repartidor 2',                    'egreso',     'deudora',   1],
        ['5.2.01.03', 'Sueldo Repartidor 3',                    'egreso',     'deudora',   1],
        ['5.2.02',    'Combustible y Mantenimiento Vehículos',  'egreso',     'deudora',   1],
        ['5.2.03',    'Comisiones MercadoPago / Tarjetas',      'egreso',     'deudora',   1],
        ['5.2.04',    'Publicidad y Marketing',                 'egreso',     'deudora',   1],
        ['5.2.05',    'Packaging y Bolsas',                     'egreso',     'deudora',   1],

        ['5.3',       'Gastos de Administración',               'egreso',     'deudora',   0],
        ['5.3.01',    'Sueldos Administración',                 'egreso',     'deudora',   1],
        ['5.3.02',    'Honorarios Profesionales',               'egreso',     'deudora',   1],
        ['5.3.03',    'Gastos de Oficina',                      'egreso',     'deudora',   1],
        ['5.3.04',    'Servicios (Luz, Agua, Internet)',        'egreso',     'deudora',   1],
        ['5.3.05',    'Hosting y Software',                     'egreso',     'deudora',   1],
        ['5.3.06',    'Alquiler Centro de Distribución',        'egreso',     'deudora',   1],

        ['5.4',       'Impuestos y Tasas',                      'egreso',     'deudora',   0],
        ['5.4.01',    'Impuesto a los Débitos y Créditos',      'egreso',     'deudora',   1],
        ['5.4.02',    'Tasa Municipal',                         'egreso',     'deudora',   1],
        ['5.4.03',    'Ingresos Brutos',                        'egreso',     'deudora',   1],

        ['5.5',       'Gastos Financieros',                     'egreso',     'deudora',   0],
        ['5.5.01',    'Intereses Bancarios',                    'egreso',     'deudora',   1],
        ['5.5.02',    'Comisiones Bancarias',                   'egreso',     'deudora',   1],
    ];

    $pdo->beginTransaction();
    try {
        $idsByCodigo = [];
        $ins = $pdo->prepare("INSERT INTO cuentas (codigo, nombre, tipo, parent_id, nivel, imputable, naturaleza, activa)
                              VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        foreach ($plan as $row) {
            [$codigo, $nombre, $tipo, $naturaleza, $imputable] = $row;
            $partes = explode('.', $codigo);
            $nivel  = count($partes);
            $padreCod = $nivel > 1 ? implode('.', array_slice($partes, 0, $nivel - 1)) : null;
            $parentId = $padreCod && isset($idsByCodigo[$padreCod]) ? $idsByCodigo[$padreCod] : null;
            $ins->execute([$codigo, $nombre, $tipo, $parentId, $nivel, $imputable, $naturaleza]);
            $idsByCodigo[$codigo] = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
