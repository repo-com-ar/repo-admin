<?php
require_once __DIR__ . '/lib/auth_check.php';
requireAuth();
$authUser = authUser();
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repo Admin</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="favicon/favicon-96x96.png">
  <link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD5WChZRhfb478oxJr7kUBwufoe-G_5SBg&loading=async&libraries=marker"></script>
</head>
<body>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="layout">

  <!-- ===== Sidebar ===== -->
  <aside class="sidebar" id="mainSidebar">
    <div class="sidebar-logo">
      <img class="logo-light" src="assets/img/repo_logo_black.png" alt="Repo" style="height:36px; width:auto; display:block;">
      <img class="logo-dark"  src="assets/img/repo_logo_withe.png" alt="Repo" style="height:36px; width:auto; display:none;">
    </div>
    <nav class="sidebar-nav">
      
      <a class="nav-item active" href="#" onclick="cambiarSeccion('inicio', this)" data-section="inicio">
        <span class="nav-icon">🏠</span> Inicio
      </a>

      <div class="nav-group-wrap open" id="navGroupProductos">
        <a class="nav-item nav-group-toggle" href="#" onclick="toggleNavGroup('navGroupProductos');return false;">
          <span class="nav-icon">📦</span> Productos
          <span class="nav-group-arrow">+</span>
        </a>
        <div class="nav-sub">
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('productos', this)" data-section="productos">
            <span class="nav-icon">📦</span> Productos
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('categorias', this)" data-section="categorias">
            <span class="nav-icon">🏷️</span> Categorías
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('inventarios', this)" data-section="inventarios">
            <span class="nav-icon">📊</span> Inventarios
          </a>
        </div>
      </div>

      <div class="nav-group-wrap open" id="navGroupVentas">
        <a class="nav-item nav-group-toggle" href="#" onclick="toggleNavGroup('navGroupVentas');return false;">
          <span class="nav-icon">💰</span> Ventas
          <span class="nav-group-arrow">+</span>
        </a>
        <div class="nav-sub">
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('pedidos', this)" data-section="pedidos">
            <span class="nav-icon">📋</span> Pedidos
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('clientes', this)" data-section="clientes">
            <span class="nav-icon">👥</span> Clientes
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('carritos', this)" data-section="carritos">
            <span class="nav-icon">🛒</span> Carritos
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('repartidores', this)" data-section="repartidores">
            <span class="nav-icon">🛵</span> Repartidores
          </a>
        </div>
      </div>

      <div class="nav-group-wrap open" id="navGroupCompras">
        <a class="nav-item nav-group-toggle" href="#" onclick="toggleNavGroup('navGroupCompras');return false;">
          <span class="nav-icon">🛍️</span> Compras
          <span class="nav-group-arrow">+</span>
        </a>
        <div class="nav-sub">
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('compras', this)" data-section="compras">
            <span class="nav-icon">🛍️</span> Compras
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('proveedores', this)" data-section="proveedores">
            <span class="nav-icon">🏭</span> Proveedores
          </a>
        </div>
      </div>
      <div class="nav-group-wrap open" id="navGroupAdmin">
        <a class="nav-item nav-group-toggle" href="#" onclick="toggleNavGroup('navGroupAdmin');return false;">
          <span class="nav-icon">⚙️</span> Administración
          <span class="nav-group-arrow">+</span>
        </a>
        <div class="nav-sub">
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('mensajes', this)" data-section="mensajes">
            <span class="nav-icon">💬</span> Mensajes
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('suscriptores', this)" data-section="suscriptores">
            <span class="nav-icon">🔔</span> Suscriptores
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('eventos', this)" data-section="eventos">
            <span class="nav-icon">📝</span> Eventos
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('usuarios', this)" data-section="usuarios">
            <span class="nav-icon">👤</span> Usuarios
          </a>
          <a class="nav-item nav-sub-item" href="#" onclick="cambiarSeccion('config', this)" data-section="config">
            <span class="nav-icon">⚙️</span> Configuración
          </a>
        </div>
      </div>
    </nav>
  </aside>

  <!-- ===== Main ===== -->
  <div class="main">

    <!-- Topbar -->
    <div class="topbar">
      <button class="hamburger" id="menuToggle" onclick="toggleSidebar()" aria-label="Menú">&#9776;</button>
      <div class="topbar-title">Gestión de Productos</div>
<div class="topbar-user">
        <div class="user-menu-wrap" id="userMenuWrap">
          <button class="topbar-username" onclick="toggleUserMenu()" id="userMenuBtn">
            👤 <?= htmlspecialchars($authUser['usr'] ?? '') ?> <span class="user-menu-arrow">▾</span>
          </button>
          <div class="user-dropdown" id="userDropdown">
            <button onclick="cerrarSesionAdmin()">🔒 Cerrar sesión</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- ========== SECCIÓN INICIO (Dashboard) ========== -->
      <div class="section" id="seccionInicio">

        <div class="stats-bar" id="dashUsuariosStats">
          <div class="stat-card dash-link" onclick="irSeccion('clientes')"><span class="stat-label">Usuarios en línea ahora</span><span class="stat-value green" id="dashUsrOnline">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('clientes')"><span class="stat-label">Usuarios activos hoy</span><span class="stat-value" style="color:#3b82f6" id="dashUsrActivos">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('clientes')"><span class="stat-label">Nuevos usuarios esta semana</span><span class="stat-value orange" id="dashUsrNuevos">—</span></div>
        </div>

        <div class="stats-bar" id="dashStats">
          <div class="stat-card dash-link" onclick="irSeccion('productos')"><span class="stat-label">Productos</span><span class="stat-value orange" id="dashProd">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('clientes')"><span class="stat-label">Clientes</span><span class="stat-value green" id="dashCli">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('pedidos')"><span class="stat-label">Pedidos hoy</span><span class="stat-value" style="color:#3b82f6" id="dashPedHoy">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('pedidos')"><span class="stat-label">Pedidos totales</span><span class="stat-value orange" id="dashPedTotal">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('pedidos')"><span class="stat-label">Ventas totales</span><span class="stat-value green" id="dashVentas">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('compras')"><span class="stat-label">Compras totales</span><span class="stat-value red" id="dashCompras">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('mensajes')"><span class="stat-label">Mensajes enviados</span><span class="stat-value" style="color:#8b5cf6" id="dashMensajes">—</span></div>
          <div class="stat-card dash-link" onclick="irSeccion('proveedores')"><span class="stat-label">Proveedores</span><span class="stat-value" style="color:#64748b" id="dashProv">—</span></div>
        </div>

        <div class="dash-grid">

          <!-- Últimos pedidos -->
          <div class="table-card">
            <div class="dash-table-header dash-link" onclick="irSeccion('pedidos')">📋 Últimos pedidos <span class="dash-ver-mas">Ver todos →</span></div>
            <table>
              <thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Estado</th></tr></thead>
              <tbody id="dashPedidosBody"><tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr></tbody>
            </table>
          </div>

          <!-- Últimos clientes -->
          <div class="table-card">
            <div class="dash-table-header dash-link" onclick="irSeccion('clientes')">👥 Últimos clientes <span class="dash-ver-mas">Ver todos →</span></div>
            <table>
              <thead><tr><th>Nombre</th><th>Celular</th><th>Pedidos</th></tr></thead>
              <tbody id="dashClientesBody"><tr><td colspan="3" style="text-align:center;padding:20px"><div class="spin"></div></td></tr></tbody>
            </table>
          </div>

          <!-- Últimos mensajes -->
          <div class="table-card">
            <div class="dash-table-header dash-link" onclick="irSeccion('mensajes')">💬 Últimos mensajes <span class="dash-ver-mas">Ver todos →</span></div>
            <table>
              <thead><tr><th>Canal</th><th>Destinatario</th><th>Estado</th></tr></thead>
              <tbody id="dashMensajesBody"><tr><td colspan="3" style="text-align:center;padding:20px"><div class="spin"></div></td></tr></tbody>
            </table>
          </div>

          <!-- Stock crítico -->
          <div class="table-card">
            <div class="dash-table-header dash-link" onclick="irSeccion('productos')">⚠️ Stock crítico <span class="dash-ver-mas">Ver productos →</span></div>
            <table>
              <thead><tr><th>Producto</th><th>Actual</th><th>A comprar</th><th>Proveedor</th></tr></thead>
              <tbody id="dashStockBody"><tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr></tbody>
            </table>
          </div>

        </div>
      </div><!-- /seccionInicio -->

      <!-- ========== SECCIÓN PRODUCTOS ========== -->
      <div class="section" id="seccionProductos" style="display:none">

      <!-- Stats -->
      <div class="stats-bar">
        <div class="stat-card">
          <span class="stat-label">Total productos</span>
          <span class="stat-value orange" id="statTotal">—</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Con stock</span>
          <span class="stat-value green" id="statStock">—</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Sin stock</span>
          <span class="stat-value red" id="statSinStock">—</span>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="toolbar">
        <div class="toolbar-left">
          <input
            class="search-input"
            type="text"
            placeholder="🔍 Buscar producto..."
            oninput="onSearch(this.value)"
          >
          <select id="filterCat" onchange="onFiltroCategoria(this.value)">
            <!-- poblado por JS -->
          </select>
        </div>
        <div class="toolbar-right">
          <button class="btn btn-primary" onclick="abrirNuevo()">
            + Nuevo producto
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Imagen</th>
              <th>Nombre</th>
              <th>P. Compra</th>
              <th>Margen</th>
              <th>P. Venta</th>
              <th>Unidad</th>
              <th>Stock</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr class="spinner-row"><td colspan="9"><div class="spin"></div></td></tr>
          </tbody>
        </table>
      </div>

      </div><!-- /seccionProductos -->

      <!-- ========== SECCIÓN CATEGORÍAS ========== -->
      <div class="section" id="seccionCategorias" style="display:none">

        <div class="toolbar">
          <div class="toolbar-left">
            <h3 style="font-size:1rem;font-weight:600">Categorías</h3>
            <span class="cat-tree-hint">Hasta 3 niveles: categoría → subcategoría → subsubcategoría</span>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost btn-sm" onclick="catTree.expandirTodo()">Expandir todo</button>
            <button class="btn btn-ghost btn-sm" onclick="catTree.colapsarTodo()">Colapsar todo</button>
            <button class="btn btn-primary" onclick="catModal.abrir()">
              + Nueva categoría
            </button>
          </div>
        </div>

        <div class="cat-tree" id="catTree">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionCategorias -->

      <!-- ========== SECCIÓN PEDIDOS ========== -->
      <div class="section" id="seccionPedidos" style="display:none">

        <!-- Stats pedidos -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total pedidos</span>
            <span class="stat-value orange" id="pedStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Pendientes</span>
            <span class="stat-value" style="color:#3b82f6" id="pedStatPendiente">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Preparación</span>
            <span class="stat-value" style="color:var(--warn)" id="pedStatPreparacion">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Entregados</span>
            <span class="stat-value green" id="pedStatEntregado">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Facturación</span>
            <span class="stat-value orange" id="pedStatMonto">—</span>
          </div>
        </div>

        <!-- Toolbar pedidos -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar pedido, cliente..." oninput="onSearchPedido(this.value)">
            <select id="filterEstado" onchange="onFiltroEstado(this.value)">
              <option value="todos">Todos los estados</option>
              <option value="pendiente">⏳ Pendiente</option>
              <option value="preparacion">🔧 Preparación</option>
              <option value="asignacion">📋 Asignación</option>
              <option value="reparto">🛵 Reparto</option>
              <option value="entregado">✅ Entregado</option>
              <option value="cancelado">❌ Cancelado</option>
            </select>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarPedidos()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Lista de pedidos -->
        <div id="pedidosLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionPedidos -->

      <!-- ========== SECCIÓN CONFIGURACIÓN ========== -->
      <div class="section" id="seccionConfig" style="display:none">

        <div class="config-panel">
          <div class="config-card">
            <div class="config-card-header">
              <span class="config-card-icon">💰</span>
              <div>
                <div class="config-card-title">Pedido mínimo</div>
                <div class="config-card-desc">Monto mínimo que debe alcanzar un pedido para poder ser confirmado por el cliente.</div>
              </div>
            </div>
            <div class="config-card-body">
              <div class="config-input-row">
                <span class="config-currency">$</span>
                <input type="number" id="cfgPedidoMinimo" min="0" step="1" placeholder="0" class="config-input">
              </div>
              <div class="config-hint" id="cfgPedidoMinimoHint">Valor actual: $0 — Dejá en 0 para no aplicar mínimo.</div>
            </div>
          </div>

          <div class="config-card">
            <div class="config-card-header">
              <span class="config-card-icon">📍</span>
              <div>
                <div class="config-card-title">Centro de distribución</div>
                <div class="config-card-desc">Ubicación GPS del local o centro desde donde se despachan los pedidos.</div>
              </div>
            </div>
            <div class="config-card-body">
              <div id="cfgCentroInfo" class="config-hint" style="margin-bottom:10px">Sin ubicación configurada.</div>
              <div id="cfgMiniMapa" class="config-minimapa"></div>
              <button type="button" class="btn btn-ghost" onclick="abrirMapaSelector()" style="margin-top:10px">🗺️ Elegir ubicación en el mapa</button>
            </div>
          </div>

          <div class="config-card">
            <div class="config-card-header">
              <span class="config-card-icon">🚚</span>
              <div>
                <div class="config-card-title">Precio por kilómetro</div>
                <div class="config-card-desc">Monto que se cobra por cada kilómetro de distancia entre el centro de distribución y el cliente.</div>
              </div>
            </div>
            <div class="config-card-body">
              <div class="config-input-row">
                <span class="config-currency">$</span>
                <input type="number" id="cfgPrecioKm" min="0" step="0.01" placeholder="0" class="config-input">
                <span style="margin-left:8px;color:var(--text-secondary);font-size:0.9rem">/ km</span>
              </div>
              <div class="config-hint" id="cfgPrecioKmHint">Valor actual: $0 / km — Dejá en 0 para no cobrar envío por distancia.</div>
            </div>
          </div>

          <div class="config-card">
            <div class="config-card-header">
              <span class="config-card-icon">🕐</span>
              <div>
                <div class="config-card-title">Fecha y hora del sistema</div>
                <div class="config-card-desc">Hora actual del servidor PHP y de la base de datos.</div>
              </div>
            </div>
            <div class="config-card-body">
              <div style="display:flex;flex-direction:column;gap:10px">
                <div>
                  <div style="font-size:.75rem;color:var(--muted);margin-bottom:2px">Servidor (PHP)</div>
                  <div id="cfgFechaServidor" style="font-size:.9rem;font-weight:600;font-family:monospace">—</div>
                </div>
                <div>
                  <div style="font-size:.75rem;color:var(--muted);margin-bottom:2px">Base de datos (MySQL)</div>
                  <div id="cfgFechaBD" style="font-size:.9rem;font-weight:600;font-family:monospace">—</div>
                </div>
              </div>
              <button type="button" class="btn btn-ghost" style="margin-top:14px;font-size:.8rem" onclick="cargarFechasSistema()">↻ Actualizar</button>
            </div>
          </div>

          <div class="config-card">
            <div class="config-card-header">
              <span class="config-card-icon">🌙</span>
              <div>
                <div class="config-card-title">Modo oscuro</div>
                <div class="config-card-desc">Cambia la apariencia del panel entre modo claro y oscuro.</div>
              </div>
            </div>
            <div class="config-card-body">
              <label class="toggle-switch">
                <input type="checkbox" id="cfgModoOscuro" onchange="adminTema.toggle(this.checked)">
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label" id="cfgModoOscuroLabel">Modo claro</span>
              </label>
            </div>
          </div>

          <div class="config-actions">
            <button class="btn btn-primary" onclick="guardarConfig()">💾 Guardar configuración</button>
            <span class="config-saved" id="configSaved" style="display:none">✅ Guardado</span>
          </div>
        </div>

      </div><!-- /seccionConfig -->

      <!-- ===== Sección Clientes ===== -->
      <div class="section" id="seccionClientes" style="display:none">

        <!-- Stats clientes -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total clientes</span>
            <span class="stat-value orange" id="cliStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Con pedidos</span>
            <span class="stat-value green" id="cliStatConPedidos">—</span>
          </div>
        </div>

        <!-- Toolbar clientes -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar cliente..." oninput="onSearchCliente(this.value)">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarClientes()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Lista de clientes -->
        <div id="clientesLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionClientes -->

      <!-- ========== SECCIÓN REPARTIDORES ========== -->
      <div class="section" id="seccionRepartidores" style="display:none">

        <!-- Stats repartidores -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total repartidores</span>
            <span class="stat-value orange" id="repStatTotal">—</span>
          </div>
        </div>

        <!-- Toolbar repartidores -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar repartidor..." oninput="onSearchRepartidor(this.value)">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarRepartidores()">🔄 Actualizar</button>
            <button class="btn btn-primary" onclick="abrirNuevoRepartidor()">+ Nuevo</button>
          </div>
        </div>

        <!-- Lista de repartidores -->
        <div id="repartidoresLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionRepartidores -->

      <!-- ========== SECCIÓN SUSCRIPTORES (push notifications) ========== -->
      <div class="section" id="seccionSuscriptores" style="display:none">

        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total suscripciones</span>
            <span class="stat-value orange" id="suscStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Repartidores</span>
            <span class="stat-value" style="color:#3b82f6" id="suscStatRepartidor">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Clientes</span>
            <span class="stat-value green" id="suscStatCliente">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Usuarios</span>
            <span class="stat-value" style="color:#8b5cf6" id="suscStatUsuario">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Con errores</span>
            <span class="stat-value red" id="suscStatError">—</span>
          </div>
        </div>

        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar por nombre, origen, dispositivo..." oninput="onSearchSuscriptor(this.value)">
            <select id="filterActorType" onchange="onFiltroActorType(this.value)">
              <option value="">Todos los tipos</option>
              <option value="repartidor">🛵 Repartidores</option>
              <option value="cliente">🛒 Clientes</option>
              <option value="usuario">👤 Usuarios del sistema</option>
            </select>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarSuscriptores()">🔄 Actualizar</button>
          </div>
        </div>

        <div id="suscriptoresLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionSuscriptores -->

      <!-- ========== SECCIÓN CARRITOS ========== -->
      <div class="section" id="seccionCarritos" style="display:none">

        <!-- Stats carritos -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total carritos</span>
            <span class="stat-value orange" id="cartStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Activos</span>
            <span class="stat-value" style="color:#3b82f6" id="cartStatActivos">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Abandonados</span>
            <span class="stat-value red" id="cartStatAbandonados">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Exitosos</span>
            <span class="stat-value green" id="cartStatExitosos">—</span>
          </div>
        </div>

        <!-- Toolbar carritos -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar cliente..." oninput="onSearchCarrito(this.value)">
            <select id="filterEstadoCarrito" onchange="onFiltroEstadoCarrito(this.value)">
              <option value="todos">Todos los estados</option>
              <option value="activo">🟢 Activo</option>
              <option value="abandonado">🔴 Abandonado</option>
              <option value="exitoso">✅ Exitoso</option>
            </select>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarCarritos()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Tabla de carritos -->
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Sesión</th>
                <th>Productos</th>
                <th>Unidades</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Última actividad</th>
                <th>Inactivo</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="carritosTbody">
              <tr class="spinner-row"><td colspan="10"><div class="spin"></div></td></tr>
            </tbody>
          </table>
        </div>

      </div><!-- /seccionCarritos -->

      <!-- ========== SECCIÓN PROVEEDORES ========== -->
      <div class="section" id="seccionProveedores" style="display:none">

        <!-- Stats proveedores -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total proveedores</span>
            <span class="stat-value orange" id="provStatTotal">—</span>
          </div>
        </div>

        <!-- Toolbar proveedores -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar proveedor..." oninput="onSearchProveedor(this.value)">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-primary" onclick="abrirNuevoProveedor()">+ Nuevo proveedor</button>
            <button class="btn btn-ghost" onclick="cargarProveedores()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Lista de proveedores -->
        <div id="proveedoresLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionProveedores -->

      <!-- ========== SECCIÓN COMPRAS ========== -->
      <div class="section" id="seccionCompras" style="display:none">

        <!-- Stats compras -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total compras</span>
            <span class="stat-value orange" id="compStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Pendientes</span>
            <span class="stat-value" style="color:#3b82f6" id="compStatPendiente">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Confirmadas</span>
            <span class="stat-value" style="color:var(--warn)" id="compStatConfirmada">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Gasto total</span>
            <span class="stat-value orange" id="compStatMonto">—</span>
          </div>
        </div>

        <!-- Toolbar compras -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar compra, proveedor..." oninput="onSearchCompra(this.value)">
            <select id="filterEstadoCompra" onchange="onFiltroEstadoCompra(this.value)">
              <option value="todos">Todos los estados</option>
              <option value="pendiente">⏳ Pendiente</option>
              <option value="confirmada">✅ Confirmada</option>
              <option value="cancelada">❌ Cancelada</option>
            </select>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-primary" onclick="abrirNuevaCompra()">+ Nueva compra</button>
            <button class="btn btn-ghost" onclick="cargarCompras()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Lista de compras -->
        <div id="comprasLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionCompras -->

      <!-- ========== SECCIÓN MENSAJES ========== -->
      <div class="section" id="seccionMensajes" style="display:none">

        <!-- Stats mensajes -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total mensajes</span>
            <span class="stat-value orange" id="msgStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Correo</span>
            <span class="stat-value" style="color:#3b82f6" id="msgStatEmail">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">WhatsApp</span>
            <span class="stat-value green" id="msgStatWhatsapp">—</span>
          </div>
        </div>

        <!-- Toolbar mensajes -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar mensaje, destinatario..." oninput="onSearchMensaje(this.value)">
            <select id="filterCanal" onchange="onFiltroCanal(this.value)">
              <option value="todos">Todos los canales</option>
              <option value="email">📧 Correo</option>
              <option value="whatsapp">💬 WhatsApp</option>
            </select>
          </div>
          <div class="toolbar-right">
            <button class="btn btn-primary" onclick="abrirNuevoMensaje()">✉️ Nuevo mensaje</button>
            <button class="btn btn-ghost" onclick="cargarMensajes()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Tabla mensajes -->
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha y hora</th>
                <th>Canal</th>
                <th>Destinatario</th>
                <th>Asunto / Mensaje</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="mensajesBody">
              <tr class="spinner-row"><td colspan="7"><div class="spin"></div></td></tr>
            </tbody>
          </table>
        </div>

      </div><!-- /seccionMensajes -->

      <!-- ========== SECCIÓN EVENTOS ========== -->
      <div class="section" id="seccionEventos" style="display:none">

        <!-- Stats eventos -->
        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total registros</span>
            <span class="stat-value orange" id="evtStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Hoy</span>
            <span class="stat-value green" id="evtStatHoy">—</span>
          </div>
        </div>

        <!-- Toolbar eventos -->
        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar evento..." oninput="onSearchEvento(this.value)">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-ghost" onclick="cargarEventos()">🔄 Actualizar</button>
          </div>
        </div>

        <!-- Tabla eventos -->
        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha y hora</th>
                <th>Cliente</th>
                <th>Detalle</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="eventosBody">
              <tr class="spinner-row"><td colspan="5"><div class="spin"></div></td></tr>
            </tbody>
          </table>
        </div>

      </div><!-- /seccionEventos -->

      <!-- ========== SECCIÓN USUARIOS ========== -->
      <div class="section" id="seccionUsuarios" style="display:none">

        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total usuarios</span>
            <span class="stat-value orange" id="usrStatTotal">—</span>
          </div>
        </div>

        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar usuario..." oninput="onSearchUsuario(this.value)">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-primary" onclick="abrirNuevoUsuario()">+ Nuevo usuario</button>
            <button class="btn btn-ghost" onclick="cargarUsuarios()">🔄 Actualizar</button>
          </div>
        </div>

        <div id="usuariosLista">
          <div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>
        </div>

      </div><!-- /seccionUsuarios -->

      <!-- ========== SECCIÓN INVENTARIOS ========== -->
      <div class="section" id="seccionInventarios" style="display:none">

        <div class="stats-bar">
          <div class="stat-card">
            <span class="stat-label">Total inventarios</span>
            <span class="stat-value orange" id="invStatTotal">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Último</span>
            <span class="stat-value" id="invStatUltimo">—</span>
          </div>
        </div>

        <div class="toolbar">
          <div class="toolbar-left">
            <input class="search-input" type="text" placeholder="🔍 Buscar inventario..." oninput="onSearchInv(this.value)" id="invSearch">
          </div>
          <div class="toolbar-right">
            <button class="btn btn-primary" onclick="abrirNuevoInventario()">+ Nuevo inventario</button>
          </div>
        </div>

        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Número</th>
                <th>Notas</th>
                <th>Productos</th>
                <th>Estado</th>
                <th>Usuario</th>
                <th>Fecha</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="invTbody">
              <tr class="spinner-row"><td colspan="8"><div class="spin"></div></td></tr>
            </tbody>
          </table>
        </div>

      </div><!-- /seccionInventarios -->

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<!-- ===== Modal Detalle Producto ===== -->
<div class="modal-backdrop" id="prodDetBackdrop" onclick="if(event.target===this)cerrarDetalleProducto()">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title" id="prodDetNombre"></div>
      <button class="btn btn-ghost" onclick="cerrarDetalleProducto()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">

      <div style="text-align:center">
        <img id="prodDetImg" src="" alt="" style="max-width:160px;max-height:160px;border-radius:12px;object-fit:cover">
      </div>

      <!-- Código y EAN eliminados -->

      <div class="ped-detail-section">
        <div class="ped-detail-label">Categoría</div>
        <div id="prodDetCategoria" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Precio / Unidad</div>
        <div id="prodDetPrecio" style="font-weight:700;font-size:1.1rem;color:var(--primary)"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Stock</div>
        <div class="stats-bar" style="margin:6px 0 0">
          <div class="stat-card">
            <span class="stat-label">Actual</span>
            <span class="stat-value green" id="prodDetStockActual">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Comprometido</span>
            <span class="stat-value" style="color:var(--warn)" id="prodDetStockComprometido">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Mínimo</span>
            <span class="stat-value" id="prodDetStockMinimo">—</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Recomendado</span>
            <span class="stat-value" id="prodDetStockRecomendado">—</span>
          </div>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleProducto()">Cerrar</button>
      <button class="btn btn-primary" id="btnProdDetEditar">✏️ Editar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Producto ===== -->
<div class="modal-backdrop" id="modalBackdrop" onclick="if(event.target===this)cerrarModal()">

  <div class="modal">

    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Nuevo producto</div>
      <button class="btn btn-ghost" onclick="cerrarModal()">✕</button>
    </div>

    <div class="modal-body">

      <div class="form-row">
        <div class="form-group">
          <label>SKU</label>
          <input type="text" id="fCodigo" placeholder="Ej: FRU-001">
        </div>
        <div class="form-group">
          <label>EAN</label>
          <input type="text" id="fEan" placeholder="Ej: 7790001234567">
        </div>
      </div>

      <div class="form-group">
        <label>
          Categoría *
          <span class="pct-warn-badge" id="fCatLegacyWarn" style="display:none">⚠ Nivel no válido</span>
        </label>
        <div class="prod-cat-display">
          <input type="text" id="fCategoriaDisplay" readonly placeholder="Sin categoría — hacé clic para elegir" onclick="prodCatPicker.abrirModal()">
          <button type="button" class="btn btn-ghost btn-sm" onclick="prodCatPicker.abrirModal()">Cambiar</button>
        </div>
        <input type="hidden" id="fCategoria">
      </div>

      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="fNombre" placeholder="Ej: Manzana Roja">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Contenido</label>
          <input type="text" id="fContenido" placeholder="Ej: 500g, 1kg, 2L">
        </div>
        <div class="form-group">
          <label>Unidad *</label>
          <select id="fUnidad"><!-- poblado por JS --></select>
        </div>
      </div>

      <div class="form-row form-row-3">
        <div class="form-group">
          <label>P. Compra</label>
          <input type="number" id="fPrecioCompra" placeholder="0" min="0" step="0.01" oninput="calcularPrecioVenta()">
        </div>
        <div class="form-group">
          <label>Margen %</label>
          <input type="number" id="fMargen" placeholder="0" min="0" step="0.01" oninput="calcularPrecioVenta()">
        </div>
        <div class="form-group">
          <label>P. Venta *</label>
          <input type="number" id="fPrecioVenta" placeholder="0" min="0" step="0.01" oninput="calcularMargen()">
        </div>
      </div>

      <div class="form-row" style="gap:8px">
        <div class="form-group">
          <label>Stock actual</label>
          <input type="number" id="fStockActual" value="1" min="0" step="1">
        </div>
        <div class="form-group">
          <label>Stock comprometido</label>
          <input type="number" id="fStockComprometido" value="0" min="0" step="1">
        </div>
        <div class="form-group">
          <label>Stock mínimo</label>
          <input type="number" id="fStockMinimo" value="0" min="0" step="1">
        </div>
        <div class="form-group">
          <label>Stock recomendado</label>
          <input type="number" id="fStockRecomendado" value="3" min="0" step="1">
        </div>
      </div>

      <div class="form-group">
        <label>Proveedor</label>
        <select id="fProveedor">
          <option value="">— Sin proveedor —</option>
        </select>
      </div>

      <div class="form-group">
        <label>Imagen del producto</label>
        <div class="upload-area" id="uploadArea">
          <div class="upload-preview" id="uploadPreview">
            <img id="imgPreview" class="img-preview" alt="preview">
            <button type="button" class="upload-remove" id="btnRemoveImg" onclick="removerImagen()" title="Quitar imagen">✕</button>
          </div>
          <div class="upload-controls" id="uploadControls">
            <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('fArchivo').click()">
              <span class="upload-icon">📷</span>
              <span class="upload-text">Arrastrá o hacé clic para subir imagen</span>
              <span class="upload-hint">JPG, PNG, WEBP, GIF — máx 5MB</span>
            </div>
            <input type="file" id="fArchivo" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="subirImagen(this.files[0])">
            <div class="upload-separator"><span>o pegá una URL</span></div>
            <input type="url" id="fImagen" placeholder="https://..." oninput="actualizarPreview()">
          </div>
          <div class="upload-loading" id="uploadLoading" style="display:none">
            <div class="spin"></div>
            <span>Subiendo imagen...</span>
          </div>
        </div>
      </div>

    </div>

    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarProducto()">Guardar</button>
    </div>

  </div>
  
</div>

<!-- ===== Modal selector de categoría para productos (árbol) ===== -->
<div class="modal-backdrop" id="prodCatModalBackdrop" onclick="if(event.target===this)prodCatPicker.cerrarModal()">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div class="modal-title">Elegir categoría</div>
      <button class="btn btn-ghost" onclick="prodCatPicker.cerrarModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="prod-cat-crumb">
        <span style="color:var(--muted);font-size:.78rem">Selección:</span>
        <span id="prodCatSelectedPath" style="font-size:.88rem;font-weight:600;color:var(--muted)">— Sin seleccionar —</span>
      </div>
      <div class="prod-cat-tree" id="prodCatTree"></div>
      <small id="fCatPickerHint" style="display:block;margin-top:10px;color:var(--muted);font-size:.74rem">Expandí el árbol y elegí una subsubcategoría (nivel 3).</small>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="prodCatPicker.cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" id="prodCatAceptarBtn" onclick="prodCatPicker.aceptar()" disabled>Aceptar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Categoría (unificado: raíz / sub / subsub) ===== -->
<div class="modal-backdrop" id="catModalBackdrop" onclick="if(event.target===this)catModal.cerrar()">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <div class="modal-title" id="catModalTitle">Nueva categoría</div>
      <button class="btn btn-ghost" onclick="catModal.cerrar()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Padre</label>
        <select id="catParent"></select>
        <small style="color:var(--muted);font-size:.72rem">Vacío = categoría raíz. Hasta 3 niveles.</small>
      </div>
      <div class="form-group">
        <label>ID (slug) *</label>
        <input type="text" id="catId" placeholder="ej: fiambres" pattern="[a-z0-9_-]+" title="Solo minúsculas, números, guiones">
        <small style="color:var(--muted);font-size:.72rem">Identificador único, sin espacios ni mayúsculas</small>
      </div>
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="catLabel" placeholder="ej: Fiambres">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Emoji</label>
          <input type="text" id="catEmoji" placeholder="🥓" maxlength="4" style="font-size:1.4rem;text-align:center">
        </div>
        <div class="form-group">
          <label>Orden</label>
          <input type="number" id="catOrden" placeholder="1" min="0">
        </div>
        <div class="form-group" style="justify-content:flex-end">
          <label>Activa</label>
          <div class="toggle-row">
            <label class="toggle">
              <input type="checkbox" id="catActiva" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="catModal.cerrar()">Cancelar</button>
      <button class="btn btn-primary" onclick="catModal.guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Pedido Detalle ===== -->
<div class="modal-backdrop" id="pedModalBackdrop" onclick="if(event.target===this)cerrarPedModal()">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="pedModalTitle">Pedido</div>
        <div style="font-size:.78rem;color:var(--muted)" id="pedModalFecha"></div>
      </div>
      <button class="btn btn-ghost" onclick="cerrarPedModal()">✕</button>
    </div>
    <div class="modal-body">
      <!-- Info cliente -->
      <div class="ped-detail-section">
        <div class="ped-detail-label">Cliente</div>
        <div id="pedDetCliente" style="font-weight:600"></div>
        <div id="pedDetCorreo" style="font-size:.85rem;color:var(--muted)"></div>
        <div id="pedDetCelular" style="font-size:.85rem;color:var(--muted)"></div>
        <div id="pedDetDireccion" style="font-size:.85rem;color:var(--muted)"></div>
        <div id="pedDetUbicacion" style="font-size:.85rem;display:none"><a id="pedDetMapLink" href="#" target="_blank" style="color:var(--primary);font-weight:600;text-decoration:none">📍 Ver en Google Maps</a></div>
        <div id="pedDetNotas" style="font-size:.85rem;color:var(--muted);font-style:italic;display:none"></div>
      </div>
      <!-- Repartidor -->
      <div class="ped-detail-section" id="pedDetRepSection">
        <div class="ped-detail-label">Repartidor</div>
        <div id="pedDetRepartidor" style="font-size:.9rem;display:flex;align-items:center;gap:6px">
          <span style="font-size:1rem">🛵</span>
          <span id="pedDetRepNombre" style="font-weight:600"></span>
        </div>
      </div>
      <!-- Items -->
      <div class="ped-detail-section">
        <div class="ped-detail-label">Productos</div>
        <div id="pedDetItems"></div>
      </div>
      <!-- Total -->
      <div class="ped-detail-total">
        <span>Total</span>
        <span id="pedDetTotal" style="font-weight:700;font-size:1.1rem"></span>
      </div>
      <!-- Cambiar estado -->
      <div class="ped-detail-section">
        <div class="ped-detail-label">Estado</div>
        <div class="ped-estado-btns" id="pedEstadoBtns"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger" onclick="eliminarPedido()" style="margin-right:auto">🗑️ Eliminar</button>
      <button class="btn btn-secondary" onclick="imprimirTicket()">🖨️ Imprimir ticket</button>
      <button class="btn btn-ghost" onclick="cerrarPedModal()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Mapa Selector ===== -->
<div class="modal-backdrop" id="mapaBackdrop" onclick="if(event.target===this)cerrarMapaSelector()">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title">📍 Elegir ubicación</div>
      <button class="btn btn-ghost" onclick="cerrarMapaSelector()">✕</button>
    </div>
    <div class="modal-body" style="padding:0">
      <div id="mapaSelector" style="height:420px;width:100%;border-radius:0 0 14px 14px"></div>
    </div>
    <div class="modal-footer" style="gap:8px">
      <button class="btn btn-ghost" onclick="cerrarMapaSelector()">Cancelar</button>
      <button class="btn btn-primary" id="btnAceptarMapa" onclick="aceptarUbicacion()">Aceptar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Cliente ===== -->
<div class="modal-backdrop" id="cliDetBackdrop" onclick="if(event.target===this)cerrarDetalleCliente()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title" id="cliDetNombre"></div>
      <button class="btn btn-ghost" onclick="cerrarDetalleCliente()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div class="ped-detail-label" style="margin-bottom:2px">Correo</div>
          <div id="cliDetCorreo" style="font-weight:600;font-size:.9rem;word-break:break-all"></div>
        </div>
        <div>
          <div class="ped-detail-label" style="margin-bottom:2px">Celular</div>
          <div id="cliDetCelular" style="font-weight:600;font-size:.9rem"></div>
        </div>
        <div>
          <div class="ped-detail-label" style="margin-bottom:2px">Ubicación</div>
          <div id="cliDetUbicacion" style="font-size:.9rem">—</div>
        </div>
        <div>
          <div class="ped-detail-label" style="margin-bottom:2px">Última vez visto</div>
          <div id="cliDetLastSeen" style="font-size:.9rem">—</div>
        </div>
      </div>

      <div class="ped-detail-section" style="margin-top:20px">
        <div class="ped-detail-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Direcciones</span>
          <button class="btn btn-ghost" style="padding:2px 10px;font-size:.8rem" onclick="abrirDirModalAdmin(null)">+ Agregar</button>
        </div>
        <div id="cliDetDireccionesLista">
          <div class="spinner-row" style="text-align:center;padding:12px"><div class="spin"></div></div>
        </div>
      </div>

      <div class="stats-bar" style="margin:0">
        <div class="stat-card">
          <span class="stat-label">Pedidos</span>
          <span class="stat-value orange" id="cliDetPedidos">—</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Total gastado</span>
          <span class="stat-value green" id="cliDetGastado">—</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Último pedido</span>
          <span class="stat-value" id="cliDetUltimo">—</span>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleCliente()">Cerrar</button>
      <button class="btn btn-primary" id="btnCliDetEditar">✏️ Editar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Editar/Crear Dirección ===== -->
<div class="modal-backdrop" id="dirModalBackdrop" onclick="if(event.target===this)cerrarDirModalAdmin()">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title" id="dirModalTitulo">Nueva dirección</div>
      <button class="btn btn-ghost" onclick="cerrarDirModalAdmin()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="dirEditId">
      <div class="form-group">
        <label>Etiqueta *</label>
        <input type="text" id="dirEditEtiqueta" placeholder="Casa, Trabajo, ..." maxlength="50" value="Casa">
      </div>
      <div class="form-group">
        <label>Dirección *</label>
        <input type="text" id="dirEditDireccion" placeholder="Calle, número, piso/depto">
      </div>
      <div class="form-group">
        <label>Ubicación en el mapa</label>
        <div id="dirEditMapInfo" class="config-hint" style="margin-bottom:8px">Sin ubicación seleccionada.</div>
        <button type="button" class="btn btn-ghost" onclick="abrirMapaSelector('direccion')">🗺️ Seleccionar en el mapa</button>
      </div>
      <div class="form-group" id="dirEditPrincipalWrap" style="display:none">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="dirEditPrincipal">
          <span>Marcar como dirección principal</span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDirModalAdmin()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarDirAdmin()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Editar Cliente ===== -->
<div class="modal-backdrop" id="cliModalBackdrop" onclick="if(event.target===this)cerrarModalCliente()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">Editar cliente</div>
      <button class="btn btn-ghost" onclick="cerrarModalCliente()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="cliNombre" placeholder="Nombre completo">
      </div>
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" id="cliCorreo" placeholder="email@ejemplo.com">
      </div>
      <div class="form-group">
        <label>Celular</label>
        <input type="tel" id="cliCelular" placeholder="Ej: 11 2345-6789">
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input type="text" id="cliContrasena" placeholder="Contraseña de acceso" autocomplete="off">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalCliente()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCliente()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Repartidor ===== -->
<div class="modal-backdrop" id="repDetBackdrop" onclick="if(event.target===this)cerrarDetalleRepartidor()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title" id="repDetNombre"></div>
      <button class="btn btn-ghost" onclick="cerrarDetalleRepartidor()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

      <div class="ped-detail-section">
        <div class="ped-detail-label">Correo electrónico</div>
        <div id="repDetCorreo" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Celular</div>
        <div id="repDetCelular" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Dirección</div>
        <div id="repDetDireccion"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Estado</div>
        <div id="repDetEstado"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Ubicación GPS</div>
        <div id="repDetUbicacion"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Seguimiento en tiempo real</div>
        <div id="repDetSeguimiento"></div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleRepartidor()">Cerrar</button>
      <button class="btn btn-primary" id="btnRepDetEditar">✏️ Editar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Editar/Nuevo Repartidor ===== -->
<div class="modal-backdrop" id="repModalBackdrop" onclick="if(event.target===this)cerrarModalRepartidor()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title" id="repModalTitulo">Editar repartidor</div>
      <button class="btn btn-ghost" onclick="cerrarModalRepartidor()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="repNombre" placeholder="Nombre completo">
      </div>
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" id="repCorreo" placeholder="email@ejemplo.com">
      </div>
      <div class="form-group">
        <label>Celular</label>
        <input type="tel" id="repCelular" placeholder="Ej: 11 2345-6789">
      </div>
      <div class="form-group">
        <label>Dirección</label>
        <input type="text" id="repDireccion" placeholder="Calle, número, piso/depto">
      </div>
      <div class="form-group">
        <label>Ubicación en el mapa</label>
        <div id="repMapInfo" class="config-hint" style="margin-bottom:8px">Sin ubicación seleccionada.</div>
        <button type="button" class="btn btn-ghost" onclick="abrirMapaSelector('repartidor')">🗺️ Seleccionar en el mapa</button>
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input type="text" id="repContrasena" placeholder="Contraseña de acceso" autocomplete="off">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalRepartidor()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarRepartidor()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Proveedor ===== -->
<div class="modal-backdrop" id="provDetBackdrop" onclick="if(event.target===this)cerrarDetalleProveedor()">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title" id="provDetNombre"></div>
      <button class="btn btn-ghost" onclick="cerrarDetalleProveedor()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

      <div class="ped-detail-section">
        <div class="ped-detail-label">Domicilio</div>
        <div id="provDetDomicilio"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Correo electrónico</div>
        <div id="provDetCorreo" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Ubicación GPS</div>
        <div id="provDetUbicacion"></div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleProveedor()">Cerrar</button>
      <button class="btn btn-primary" id="btnProvDetEditar">✏️ Editar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Proveedor ===== -->
<div class="modal-backdrop" id="provModalBackdrop" onclick="if(event.target===this)cerrarModalProveedor()">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title" id="provModalTitle">Nuevo proveedor</div>
      <button class="btn btn-ghost" onclick="cerrarModalProveedor()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="provNombre" placeholder="Nombre del proveedor">
      </div>
      <div class="form-group">
        <label>Domicilio</label>
        <input type="text" id="provDomicilio" placeholder="Calle, número, localidad">
      </div>
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" id="provCorreo" placeholder="email@ejemplo.com">
      </div>
      <div class="form-group">
        <label>Ubicación en el mapa</label>
        <div id="provMapInfo" class="config-hint" style="margin-bottom:8px">Sin ubicación seleccionada.</div>
        <button type="button" class="btn btn-ghost" onclick="abrirMapaSelector('proveedor')">🗺️ Seleccionar en el mapa</button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalProveedor()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarProveedor()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Nueva Compra ===== -->
<div class="modal-backdrop" id="compModalBackdrop" onclick="if(event.target===this)cerrarCompModal()">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <div class="modal-title">Nueva compra</div>
      <button class="btn btn-ghost" onclick="cerrarCompModal()">✕</button>
    </div>
    <div class="modal-body">
      <!-- Proveedor -->
      <div class="form-group">
        <label>Proveedor *</label>
        <select id="compProveedor" onchange="onCompProveedorChange()">
          <option value="">— Seleccionar proveedor —</option>
        </select>
      </div>
      <div class="form-group">
        <label>Notas</label>
        <input type="text" id="compNotas" placeholder="Observaciones (opcional)">
      </div>
      <!-- Items -->
      <div class="form-group">
        <label>Productos</label>
        <div id="compItemsWrap">
          <!-- items dinámicos -->
        </div>
        <button type="button" class="btn btn-ghost" onclick="agregarItemCompra()" style="margin-top:8px">+ Agregar producto</button>
      </div>
      <!-- Total -->
      <div style="text-align:right;font-weight:700;font-size:1.1rem;margin-top:10px">
        Total: $<span id="compTotal">0</span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarCompModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCompra()">Crear compra</button>
    </div>
  </div>
</div>

<!-- ===== Modal Compra Detalle ===== -->
<div class="modal-backdrop" id="compDetModalBackdrop" onclick="if(event.target===this)cerrarCompDetModal()">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="compDetTitle">Compra</div>
        <div style="font-size:.78rem;color:var(--muted)" id="compDetFecha"></div>
      </div>
      <button class="btn btn-ghost" onclick="cerrarCompDetModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="ped-detail-section">
        <div class="ped-detail-label">Proveedor</div>
        <div id="compDetProveedor" style="font-weight:600"></div>
        <div id="compDetNotas" style="font-size:.85rem;color:var(--muted);font-style:italic;display:none"></div>
      </div>
      <div class="ped-detail-section">
        <div class="ped-detail-label">Productos</div>
        <div id="compDetItems"></div>
      </div>
      <div class="ped-detail-total">
        <span>Total</span>
        <span id="compDetTotal" style="font-weight:700;font-size:1.1rem"></span>
      </div>
      <div class="ped-detail-section">
        <div class="ped-detail-label">Estado</div>
        <div class="ped-estado-btns" id="compEstadoBtns"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger" onclick="eliminarCompra()" style="margin-right:auto">🗑️ Eliminar</button>
      <button class="btn btn-ghost" onclick="cerrarCompDetModal()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Mensaje ===== -->
<div class="modal-backdrop" id="msgDetBackdrop" onclick="if(event.target===this)cerrarDetalleMensaje()">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="msgDetId">#—</div>
        <div style="font-size:.78rem;color:var(--text-secondary)" id="msgDetFecha"></div>
      </div>
      <button class="btn btn-ghost" onclick="cerrarDetalleMensaje()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

      <div class="ped-detail-section">
        <div class="ped-detail-label">Canal</div>
        <div id="msgDetCanal"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Destinatario</div>
        <div id="msgDetDestinatario" style="font-weight:600"></div>
        <div id="msgDetDestino" style="font-size:.85rem;color:var(--text-secondary)"></div>
      </div>

      <div class="ped-detail-section" id="msgDetAsuntoRow">
        <div class="ped-detail-label">Asunto</div>
        <div id="msgDetAsunto" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Mensaje</div>
        <div id="msgDetCuerpo" style="white-space:pre-wrap;line-height:1.6;font-size:.92rem"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Estado</div>
        <div id="msgDetEstado"></div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleMensaje()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Nuevo Mensaje ===== -->
<div class="modal-backdrop" id="msgModalBackdrop" onclick="if(event.target===this)cerrarMsgModal()">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <div class="modal-title">Nuevo mensaje</div>
      <button class="btn btn-ghost" onclick="cerrarMsgModal()">✕</button>
    </div>
    <div class="modal-body">

      <!-- Canal -->
      <div class="form-group">
        <label>Canal *</label>
        <div style="display:flex;gap:12px;margin-top:4px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500">
            <input type="radio" name="msgCanal" value="email" onchange="onMsgCanalChange(this.value)" checked>
            📧 Correo electrónico
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500">
            <input type="radio" name="msgCanal" value="whatsapp" onchange="onMsgCanalChange(this.value)">
            💬 WhatsApp
          </label>
        </div>
      </div>

      <!-- Cliente (selector rápido) -->
      <div class="form-group">
        <label>Cliente (opcional)</label>
        <select id="msgClienteSelect" onchange="onMsgClienteChange(this.value)">
          <option value="">— Completar manualmente —</option>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Nombre del destinatario *</label>
          <input type="text" id="msgDestinatario" placeholder="Ej: María González">
        </div>
        <div class="form-group">
          <label id="msgDestinoLabel">Email del destinatario *</label>
          <input type="text" id="msgDestino" placeholder="email@ejemplo.com">
        </div>
      </div>

      <!-- Asunto (solo email) -->
      <div class="form-group" id="msgAsuntoGroup">
        <label>Asunto *</label>
        <input type="text" id="msgAsunto" placeholder="Asunto del correo">
      </div>

      <!-- Mensaje -->
      <div class="form-group">
        <label>Mensaje *</label>
        <textarea id="msgCuerpo" rows="5" placeholder="Escribí el mensaje aquí..." style="resize:vertical;min-height:100px"></textarea>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarMsgModal()">Cancelar</button>
      <button class="btn btn-primary" id="btnEnviarMensaje" onclick="enviarMensaje()">Enviar mensaje</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Evento ===== -->
<div class="modal-backdrop" id="evtDetBackdrop" onclick="if(event.target===this)cerrarDetalleEvento()">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="evtDetId"></div>
        <div style="font-size:.78rem;color:var(--text-secondary)" id="evtDetFecha"></div>
      </div>
      <button class="btn btn-ghost" onclick="cerrarDetalleEvento()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

      <div class="ped-detail-section">
        <div class="ped-detail-label">Cliente</div>
        <div id="evtDetCliente" style="font-weight:600"></div>
      </div>

      <div class="ped-detail-section">
        <div class="ped-detail-label">Detalle</div>
        <div id="evtDetDetalle" style="white-space:pre-wrap;line-height:1.6"></div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarDetalleEvento()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Usuario ===== -->
<div class="modal-backdrop" id="usrModalBackdrop" onclick="if(event.target===this)cerrarModalUsuario()">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <div class="modal-title" id="usrModalTitulo">Nuevo usuario</div>
      <button class="btn btn-ghost" onclick="cerrarModalUsuario()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" id="usrNombre" placeholder="Nombre de usuario" autocomplete="off">
      </div>
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" id="usrCorreo" placeholder="correo@ejemplo.com">
      </div>
      <div class="form-group">
        <label>Celular</label>
        <input type="tel" id="usrCelular" placeholder="Ej: 11 2345-6789">
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input type="text" id="usrContrasena" placeholder="Contraseña de acceso" autocomplete="off">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModalUsuario()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarUsuario()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Carrito ===== -->
<div class="modal-backdrop" id="cartDetalleBackdrop" onclick="if(event.target===this)cerrarDetalleCarrito()">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="cartDetalleTitle">Carrito #—</div>
        <div style="font-size:.78rem;color:var(--muted)" id="cartDetalleFecha"></div>
      </div>
      <button class="btn btn-ghost" onclick="cerrarDetalleCarrito()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
      <!-- Info cliente -->
      <div id="cartDetalleCliente" style="display:flex;gap:12px;flex-wrap:wrap"></div>
      <!-- Estado -->
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:.85rem;color:var(--muted)">Estado:</span>
        <span id="cartDetalleEstadoBadge"></span>
        <select id="cartDetalleEstadoSel" onchange="cambiarEstadoCarrito()" style="margin-left:8px;padding:4px 8px;border-radius:6px;border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:.85rem">
          <option value="activo">🟢 Activo</option>
          <option value="abandonado">🔴 Abandonado</option>
          <option value="exitoso">✅ Exitoso</option>
        </select>
      </div>
      <!-- Items -->
      <div class="table-card" style="margin:0;max-height:320px;overflow-y:auto">
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th style="text-align:center">Precio</th>
              <th style="text-align:center">Cant.</th>
              <th style="text-align:right">Subtotal</th>
            </tr>
          </thead>
          <tbody id="cartDetalleItems"></tbody>
        </table>
      </div>
      <!-- Total -->
      <div style="text-align:right;font-weight:700;font-size:1rem" id="cartDetalleTotal"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="cartRecordatorioBtn" onclick="enviarRecordatorioCarrito()" style="margin-right:auto">📩 Enviar recordatorio</button>
      <button class="btn btn-danger" onclick="confirmarEliminarCarrito(cartDetalleActual?.id)">🗑️ Eliminar</button>
      <button class="btn btn-ghost" onclick="cerrarDetalleCarrito()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ===== Confirm dialog ===== -->
<div class="confirm-backdrop" id="confirmBackdrop">
  <div class="confirm-box">
    <div class="confirm-title">Confirmar eliminación</div>
    <div class="confirm-msg" id="confirmMsg"></div>
    <div class="confirm-actions">
      <button class="btn btn-ghost" onclick="cerrarConfirm(false)">Cancelar</button>
      <button class="btn btn-danger" onclick="cerrarConfirm(true)">Eliminar</button>
    </div>
  </div>
</div>

<!-- ===== Toast ===== -->
<div class="toast" id="toast"></div>

<!-- ===== Modal Nuevo Inventario ===== -->
<div class="modal-backdrop" id="invNuevoBackdrop" onclick="if(event.target===this)cerrarNuevoInventario()">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title">Nuevo inventario</div>
      <button class="btn btn-ghost" onclick="cerrarNuevoInventario()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
      <div style="font-weight:600;font-size:.9rem;color:var(--muted)">Cantidades contadas por producto</div>
      <div class="table-card" style="margin:0;max-height:400px;overflow-y:auto">
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th style="text-align:center">Stock actual</th>
              <th style="text-align:center">Cantidad contada</th>
              <th style="text-align:center">Diferencia</th>
            </tr>
          </thead>
          <tbody id="invNuevoItems">
            <tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr>
          </tbody>
        </table>
      </div>
      <div class="form-group">
        <label class="form-label">Notas</label>
        <input type="text" id="invNuevoNotas" class="form-input" placeholder="Descripción opcional del inventario">
      </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;padding:16px">
      <button class="btn btn-ghost" onclick="cerrarNuevoInventario()">Cancelar</button>
      <button class="btn btn-primary" id="invGuardarBtn" onclick="guardarInventario()">Guardar inventario</button>
    </div>
  </div>
</div>

<!-- ===== Modal Detalle Inventario ===== -->
<div class="modal-backdrop" id="invDetalleBackdrop" onclick="if(event.target===this)cerrarDetalleInventario()">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title" id="invDetalleNumero">Detalle de inventario</div>
      <button class="btn btn-ghost" onclick="cerrarDetalleInventario()">✕</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:16px">
      <div style="display:flex;gap:12px;flex-wrap:wrap" id="invDetalleInfo"></div>
      <div class="table-card" style="margin:0;max-height:450px;overflow-y:auto">
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th style="text-align:center">Stock anterior</th>
              <th style="text-align:center">Contado</th>
              <th style="text-align:center">Diferencia</th>
            </tr>
          </thead>
          <tbody id="invDetalleItems">
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;padding:16px">
      <button class="btn btn-ghost" onclick="cerrarDetalleInventario()">Cerrar</button>
    </div>
  </div>
</div>

<script src="assets/js/admin.js?v=<?= time() ?>"></script>
</body>
</html>
