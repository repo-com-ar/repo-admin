/* ===== Auth: redirigir al login si el token expira ===== */
(function() {
  var _fetch = window.fetch;
  window.fetch = function() {
    return _fetch.apply(this, arguments).then(function(res) {
      if (res.status === 401) {
        res.clone().json().then(function(d) {
          if (d && d.login) window.location.href = 'login';
        }).catch(function(){});
      }
      return res;
    });
  };
})();

/* ===== Config ===== */
const API = 'api/productos';
const UPLOAD_API = 'api/upload';
const CAT_API = 'api/categorias';
const PED_API = 'api/pedidos';
const CFG_API = 'api/configuracion';
const CLI_API = 'api/clientes';
const REP_API = 'api/repartidores';
const PROV_API = 'api/proveedores';
const COMP_API = 'api/compras';
const CART_API = 'api/carritos';

let CATEGORIAS = [];
let PROVEEDORES = [];

const UNIDADES = ['kg', 'u', 'lt', 'g', 'docena', 'pack'];

/* ===== State ===== */
let productos = [];
let filtroCategoria = 'todos';
let filtroBusqueda  = '';
let editandoId      = null;
let confirmCallback = null;

/* ===== API calls ===== */
async function cargarCategorias() {
  try {
    const res = await fetch(CAT_API + '?todas=1');
    const data = await res.json();
    if (data.ok) CATEGORIAS = data.data;
  } catch (e) { console.error('Error cargando categorías', e); }
}
async function cargarProveedoresLista() {
  try {
    const res = await fetch(PROV_API);
    const data = await res.json();
    if (data.ok) PROVEEDORES = data.data || [];
  } catch (e) { console.error('Error cargando proveedores', e); }
}
async function apiGet(params = {}) {
  const qs = new URLSearchParams(params).toString();
  const res = await fetch(`${API}${qs ? '?' + qs : ''}`);
  return res.json();
}
async function apiPost(body) {
  const res = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return res.json();
}
async function apiPut(body) {
  const res = await fetch(API, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return res.json();
}
async function apiDelete(id) {
  const res = await fetch(`${API}?id=${id}`, { method: 'DELETE' });
  return res.json();
}

/* ===== Load ===== */
async function cargarProductos() {
  showTableLoading();
  const data = await apiGet({ categoria: filtroCategoria, q: filtroBusqueda });
  productos = data.data || [];
  renderTabla();
  renderStats();
}

/* ===== Stats ===== */
async function renderStats() {
  const all = await apiGet({});
  const lista = all.data || [];
  const total   = lista.length;
  const conStock = lista.filter(p => p.stock_actual > 0).length;
  const sinStock = lista.filter(p => p.stock_actual <= 0).length;
  document.getElementById('statTotal').textContent   = total;
  document.getElementById('statStock').textContent   = conStock;
  document.getElementById('statSinStock').textContent = sinStock;
}

/* ===== Table ===== */
function showTableLoading() {
  document.getElementById('tbody').innerHTML = `<tr class="spinner-row"><td colspan="10"><div class="spin"></div></td></tr>`;
}

function renderTabla() {
  const tbody = document.getElementById('tbody');
  if (!productos.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="table-empty">Sin productos para los filtros aplicados</td></tr>`;
    return;
  }
  tbody.innerHTML = productos.map(p => `
    <tr data-id="${p.id}">
      <td class="td-id">#${p.id}</td>
      <td><img class="td-img" src="${p.imagen || ''}" alt="${p.nombre}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22><rect width=%2244%22 height=%2244%22 fill=%22%23e2e8f0%22/><text x=%2250%25%22 y=%2254%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2220%22>📦</text></svg>'"></td>
      <td class="td-nombre">${esc(p.nombre)}</td>
      <td><span class="badge badge-cat">${esc(p.categoria)}</span></td>
      <td>$${Number(p.precio_compra ?? 0).toLocaleString('es-AR')}</td>
      <td>${p.margen != null ? Number(p.margen).toLocaleString('es-AR', {minimumFractionDigits:1, maximumFractionDigits:1}) + '%' : '—'}</td>
      <td>$${Number(p.precio_venta ?? 0).toLocaleString('es-AR')}</td>
      <td>${esc(p.unidad)}</td>
      <td>
        ${p.stock_actual > 0
          ? '<span class="badge badge-stock">Stock: ' + p.stock_actual + '</span>'
          : '<span class="badge badge-nostock">Sin stock</span>'
        }
        ${p.stock_comprometido > 0
          ? '<span class="badge badge-comprometido">Comp: ' + p.stock_comprometido + '</span>'
          : ''
        }
      </td>
      <td>
        <div class="actions">
          <button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleProducto(${p.id})">🔍</button>
          <button class="btn-icon-sm" title="Editar" onclick="abrirEditar(${p.id})">✏️</button>
          <button class="btn-icon-sm" title="Eliminar" onclick="confirmarEliminar(${p.id}, '${esc(p.nombre)}')">🗑️</button>
        </div>
      </td>
    </tr>`).join('');
  resolverPendingDetail('productos');
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ===== Modal detalle producto ===== */
function abrirDetalleProducto(id) {
  const p = productos.find(x => x.id === id);
  if (!p) return;

  const cat = CATEGORIAS.find(c => c.id === p.categoria);

  document.getElementById('prodDetNombre').textContent    = p.nombre;
  document.getElementById('prodDetCategoria').textContent = cat ? cat.emoji + ' ' + cat.label : p.categoria;
  document.getElementById('prodDetPrecio').textContent    = '$' + Number(p.precio_venta ?? 0).toLocaleString('es-AR') + ' / ' + p.unidad;
  document.getElementById('prodDetStockActual').textContent      = p.stock_actual      ?? 0;
  document.getElementById('prodDetStockComprometido').textContent = p.stock_comprometido ?? 0;
  document.getElementById('prodDetStockMinimo').textContent      = p.stock_minimo      ?? 0;
  document.getElementById('prodDetStockRecomendado').textContent  = p.stock_recomendado  ?? 0;

  const imgEl = document.getElementById('prodDetImg');
  if (p.imagen) {
    imgEl.src = p.imagen;
    imgEl.style.display = '';
  } else {
    imgEl.style.display = 'none';
  }

  document.getElementById('btnProdDetEditar').onclick = function() {
    cerrarDetalleProducto();
    abrirEditar(id);
  };

  document.getElementById('prodDetBackdrop').classList.add('open');
}

function cerrarDetalleProducto() {
  document.getElementById('prodDetBackdrop').classList.remove('open');
}

/* ===== Modal: poblar selects ===== */
function poblarSelects() {
  const selCat = document.getElementById('fCategoria');
  selCat.innerHTML = CATEGORIAS.map(c => `<option value="${c.id}">${c.emoji} ${c.label}</option>`).join('');

  const selUn = document.getElementById('fUnidad');
  selUn.innerHTML = UNIDADES.map(u => `<option value="${u}">${u}</option>`).join('');

  // filtro categoría
  const selFil = document.getElementById('filterCat');
  selFil.innerHTML = `<option value="todos">Todas las categorías</option>` +
    CATEGORIAS.map(c => `<option value="${c.id}">${c.emoji} ${c.label}</option>`).join('');

  // proveedor
  const selProv = document.getElementById('fProveedor');
  selProv.innerHTML = '<option value="">— Sin proveedor —</option>' +
    PROVEEDORES.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
}

/* ===== Modal: abrir / cerrar ===== */
function abrirNuevo() {
  editandoId = null;
  document.getElementById('modalTitle').textContent = 'Nuevo producto';
  limpiarForm();
  document.getElementById('modalBackdrop').classList.add('open');
}

function abrirEditar(id) {
  const p = productos.find(x => x.id === id);
  if (!p) return;
  editandoId = id;
  document.getElementById('modalTitle').textContent = 'Editar producto';
  document.getElementById('fCodigo').value    = p.sku || '';
  document.getElementById('fEan').value       = p.ean || '';
  document.getElementById('fNombre').value       = p.nombre;
  document.getElementById('fPrecioCompra').value = p.precio_compra ?? '';
  document.getElementById('fMargen').value       = p.margen ?? '';
  document.getElementById('fPrecioVenta').value  = p.precio_venta ?? '';
  document.getElementById('fCategoria').value = p.categoria;
  document.getElementById('fContenido').value  = p.contenido || '';
  document.getElementById('fUnidad').value    = p.unidad;
  document.getElementById('fImagen').value    = p.imagen || '';
  document.getElementById('fStockActual').value       = p.stock_actual ?? 1;
  document.getElementById('fStockComprometido').value = p.stock_comprometido ?? 0;
  document.getElementById('fStockMinimo').value       = p.stock_minimo ?? 0;
  document.getElementById('fStockRecomendado').value  = p.stock_recomendado ?? 3;
  document.getElementById('fProveedor').value  = p.proveedor_id ?? '';
  actualizarPreview();
  document.getElementById('modalBackdrop').classList.add('open');
}

function cerrarModal() {
  document.getElementById('modalBackdrop').classList.remove('open');
}

function limpiarForm() {
  ['fCodigo','fEan','fNombre','fPrecioCompra','fMargen','fPrecioVenta','fContenido','fImagen'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fCategoria').value = 'frutas';
  document.getElementById('fUnidad').value    = 'kg';
  document.getElementById('fStockActual').value       = 1;
  document.getElementById('fStockComprometido').value = 0;
  document.getElementById('fStockMinimo').value       = 0;
  document.getElementById('fStockRecomendado').value  = 3;
  document.getElementById('fProveedor').value  = '';
  document.getElementById('fArchivo').value   = '';
  actualizarPreview();
}

/* ===== Cálculo de precios ===== */
function calcularPrecioVenta() {
  const compra = parseFloat(document.getElementById('fPrecioCompra').value) || 0;
  const margen = parseFloat(document.getElementById('fMargen').value) || 0;
  if (compra > 0) {
    document.getElementById('fPrecioVenta').value = (compra * (1 + margen / 100)).toFixed(2);
  }
}

function calcularMargen() {
  const compra = parseFloat(document.getElementById('fPrecioCompra').value) || 0;
  const venta  = parseFloat(document.getElementById('fPrecioVenta').value) || 0;
  if (compra > 0) {
    document.getElementById('fMargen').value = ((venta - compra) / compra * 100).toFixed(2);
  }
}

/* ===== Preview imagen ===== */
function actualizarPreview() {
  const url = document.getElementById('fImagen').value.trim();
  const img = document.getElementById('imgPreview');
  const preview = document.getElementById('uploadPreview');
  const controls = document.getElementById('uploadControls');
  if (url) {
    img.onload = () => {
      preview.classList.add('visible');
      controls.classList.add('has-image');
    };
    img.onerror = () => {
      preview.classList.remove('visible');
      controls.classList.remove('has-image');
    };
    img.src = url;
  } else {
    preview.classList.remove('visible');
    controls.classList.remove('has-image');
  }
}

/* ===== Upload imagen ===== */
async function subirImagen(file) {
  if (!file) return;

  // Validar tipo
  const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  if (!tiposPermitidos.includes(file.type)) {
    showToast('Solo se permiten imágenes JPG, PNG, WEBP o GIF', true);
    return;
  }

  // Validar tamaño (5MB)
  if (file.size > 5 * 1024 * 1024) {
    showToast('La imagen no puede superar los 5MB', true);
    return;
  }

  const loading = document.getElementById('uploadLoading');
  const controls = document.getElementById('uploadControls');
  controls.style.display = 'none';
  loading.style.display = 'flex';

  try {
    const formData = new FormData();
    formData.append('imagen', file);

    const res = await fetch(UPLOAD_API, { method: 'POST', body: formData });
    const data = await res.json();

    if (data.ok) {
      document.getElementById('fImagen').value = data.url;
      actualizarPreview();
      showToast('Imagen subida correctamente');
    } else {
      showToast(data.error || 'Error al subir imagen', true);
    }
  } catch (e) {
    showToast('Error de conexión al subir imagen', true);
  } finally {
    loading.style.display = 'none';
    controls.style.display = '';
  }

  // Resetear input file para permitir subir el mismo archivo de nuevo
  document.getElementById('fArchivo').value = '';
}

function removerImagen() {
  document.getElementById('fImagen').value = '';
  document.getElementById('fArchivo').value = '';
  actualizarPreview();
}

/* ===== Drag & Drop ===== */
function initDragDrop() {
  const dropzone = document.getElementById('dropzone');
  if (!dropzone) return;

  ['dragenter', 'dragover'].forEach(ev => {
    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragover'); });
  });
  ['dragleave', 'drop'].forEach(ev => {
    dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragover'); });
  });
  dropzone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) subirImagen(files[0]);
  });
}

/* ===== Guardar ===== */
async function guardarProducto() {
  const nombre         = document.getElementById('fNombre').value.trim();
  const precio_compra  = parseFloat(document.getElementById('fPrecioCompra').value) || 0;
  const margen         = parseFloat(document.getElementById('fMargen').value) || 0;
  const precio_venta   = parseFloat(document.getElementById('fPrecioVenta').value);
  const categoria = document.getElementById('fCategoria').value;
  const sku       = document.getElementById('fCodigo').value.trim();
  const ean       = document.getElementById('fEan').value.trim();
  const contenido = document.getElementById('fContenido').value.trim();
  const unidad    = document.getElementById('fUnidad').value;
  const imagen    = document.getElementById('fImagen').value.trim();
  const stock_actual       = parseInt(document.getElementById('fStockActual').value) || 0;
  const stock_comprometido = parseInt(document.getElementById('fStockComprometido').value) || 0;
  const stock_minimo       = parseInt(document.getElementById('fStockMinimo').value) || 0;
  const stock_recomendado  = parseInt(document.getElementById('fStockRecomendado').value) || 3;
  const proveedor_id       = parseInt(document.getElementById('fProveedor').value) || null;

  if (!nombre) { showToast('El nombre es obligatorio', true); return; }
  if (isNaN(precio_venta) || precio_venta < 0) { showToast('Precio de venta inválido', true); return; }

  const body = { nombre, precio_compra, margen, precio_venta, categoria, sku, ean, contenido, unidad, imagen, stock_actual, stock_comprometido, stock_minimo, stock_recomendado, proveedor_id };
  let res;
  if (editandoId) {
    body.id = editandoId;
    res = await apiPut(body);
  } else {
    res = await apiPost(body);
  }

  if (res.ok) {
    cerrarModal();
    showToast(editandoId ? 'Producto actualizado' : 'Producto creado');
    await cargarProductos();
  } else {
    showToast(res.error || 'Error al guardar', true);
  }
}

/* ===== Eliminar ===== */
function confirmarEliminar(id, nombre) {
  document.getElementById('confirmMsg').textContent = `¿Eliminás "${nombre}"? Esta acción no se puede deshacer.`;
  confirmCallback = async () => {
    const res = await apiDelete(id);
    if (res.ok) {
      showToast('Producto eliminado');
      await cargarProductos();
    } else {
      showToast(res.error || 'Error al eliminar', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

function cerrarConfirm(ejecutar) {
  document.getElementById('confirmBackdrop').classList.remove('open');
  if (ejecutar && confirmCallback) confirmCallback();
  confirmCallback = null;
}

/* ===== Filtros ===== */
let searchTimer;
function onSearch(val) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { filtroBusqueda = val; cargarProductos(); }, 300);
}

function onFiltroCategoria(val) {
  filtroCategoria = val;
  cargarProductos();
}

/* ===== Toast ===== */
let toastTimer;
function showToast(msg, error = false) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast show' + (error ? ' error' : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 2500);
}

/* ===== User menu ===== */
function toggleUserMenu() {
  document.getElementById('userDropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  const wrap = document.getElementById('userMenuWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('userDropdown').classList.remove('open');
  }
});

/* ===== Admin tema ===== */
const adminTema = {
  init() {
    const saved = localStorage.getItem('adminTema') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const chk = document.getElementById('cfgModoOscuro');
    if (chk) { chk.checked = saved === 'dark'; this._updateLabel(saved); }
  },
  toggle(isDark) {
    const t = isDark ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
    localStorage.setItem('adminTema', t);
    this._updateLabel(t);
  },
  _updateLabel(t) {
    const lbl = document.getElementById('cfgModoOscuroLabel');
    if (lbl) lbl.textContent = t === 'dark' ? 'Modo oscuro' : 'Modo claro';
  }
};

/* ===== Navegación de secciones ===== */
async function cerrarSesionAdmin() {
  try {
    await fetch('api/auth', { method: 'DELETE', credentials: 'include' });
  } catch (e) { /* continuar aunque falle el servidor */ }
  document.cookie = 'repo_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
  window.location.href = 'login';
}

function toggleSidebar() {
  document.getElementById('mainSidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('active');
}
function closeSidebar() {
  document.getElementById('mainSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

function toggleNavGroup(wrapId) {
  document.getElementById(wrapId).classList.toggle('open');
}

// Secciones que pertenecen a cada grupo colapsable
const NAV_GROUPS = {
  navGroupProductos: ['productos', 'categorias', 'inventarios'],
  navGroupVentas:    ['pedidos', 'clientes', 'carritos', 'repartidores'],
  navGroupCompras:   ['compras', 'proveedores'],
  navGroupAdmin:     ['eventos', 'usuarios', 'config'],
};

function cambiarSeccion(seccion, navEl) {
  closeSidebar();
  // Actualizar sidebar
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  if (navEl) navEl.classList.add('active');

  // Auto-expandir el grupo que contiene esta sección
  Object.entries(NAV_GROUPS).forEach(([wrapId, secciones]) => {
    if (secciones.includes(seccion)) {
      document.getElementById(wrapId).classList.add('open');
    }
  });

  // Mostrar/ocultar secciones
  document.querySelectorAll('.section').forEach(s => s.style.display = 'none');

  const topbar = document.querySelector('.topbar-title');

  if (seccion === 'inicio') {
    document.getElementById('seccionInicio').style.display = '';
    topbar.textContent = 'Inicio';
    cargarDashboard();
  } else if (seccion === 'productos') {
    document.getElementById('seccionProductos').style.display = '';
    topbar.textContent = 'Gestión de Productos';
    cargarProductos();
  } else if (seccion === 'categorias') {
    document.getElementById('seccionCategorias').style.display = '';
    topbar.textContent = 'Gestión de Categorías';
    renderCatGrid();
  } else if (seccion === 'pedidos') {
    document.getElementById('seccionPedidos').style.display = '';
    topbar.textContent = 'Gestión de Pedidos';
    cargarPedidos();
  } else if (seccion === 'config') {
    document.getElementById('seccionConfig').style.display = '';
    topbar.textContent = 'Configuración';
    cargarConfiguracion();
    cargarFechasSistema();
    const chk = document.getElementById('cfgModoOscuro');
    if (chk) { const t = localStorage.getItem('adminTema') || 'light'; chk.checked = t === 'dark'; adminTema._updateLabel(t); }
  } else if (seccion === 'clientes') {
    document.getElementById('seccionClientes').style.display = '';
    topbar.textContent = 'Gestión de Clientes';
    cargarClientes();
  } else if (seccion === 'proveedores') {
    document.getElementById('seccionProveedores').style.display = '';
    topbar.textContent = 'Gestión de Proveedores';
    cargarProveedores();
  } else if (seccion === 'compras') {
    document.getElementById('seccionCompras').style.display = '';
    topbar.textContent = 'Gestión de Compras';
    cargarCompras();
  } else if (seccion === 'mensajes') {
    document.getElementById('seccionMensajes').style.display = '';
    topbar.textContent = 'Mensajes Enviados';
    cargarMensajes();
  } else if (seccion === 'eventos') {
    document.getElementById('seccionEventos').style.display = '';
    topbar.textContent = 'Registros de Eventos';
    cargarEventos();
  } else if (seccion === 'usuarios') {
    document.getElementById('seccionUsuarios').style.display = '';
    topbar.textContent = 'Usuarios del sistema';
    cargarUsuarios();
  } else if (seccion === 'inventarios') {
    document.getElementById('seccionInventarios').style.display = '';
    topbar.textContent = 'Gestión de Inventarios';
    cargarInventarios();
  } else if (seccion === 'carritos') {
    document.getElementById('seccionCarritos').style.display = '';
    topbar.textContent = 'Gestión de Carritos';
    cargarCarritos();
  } else if (seccion === 'repartidores') {
    document.getElementById('seccionRepartidores').style.display = '';
    topbar.textContent = 'Gestión de Repartidores';
    cargarRepartidores();
  }
}

/* ===== Usuarios ===== */
const USR_API = 'api/usuarios';
let usuarios = [];
let usrBusqueda = '';
let usrEditandoId = null;
let usrSearchTimer = null;

function onSearchUsuario(val) {
  clearTimeout(usrSearchTimer);
  usrSearchTimer = setTimeout(function() { usrBusqueda = val.trim(); cargarUsuarios(); }, 300);
}

async function cargarUsuarios() {
  const lista = document.getElementById('usuariosLista');
  lista.innerHTML = '<div class="spinner-row" style="text-align:center;padding:40px"><div class="spin"></div></div>';
  try {
    const url = USR_API + (usrBusqueda ? '?q=' + encodeURIComponent(usrBusqueda) : '');
    const res  = await fetch(url);
    const data = await res.json();
    if (data.ok) {
      usuarios = data.data || [];
      renderUsuarios();
      document.getElementById('usrStatTotal').textContent = data.stats?.total ?? usuarios.length;
    } else {
      lista.innerHTML = '<div class="table-empty">Error al cargar usuarios</div>';
    }
  } catch (e) {
    lista.innerHTML = '<div class="table-empty">Error de conexión</div>';
  }
}

function renderUsuarios() {
  const lista = document.getElementById('usuariosLista');
  if (!usuarios.length) {
    lista.innerHTML = '<div class="table-empty">No hay usuarios registrados</div>';
    return;
  }
  lista.innerHTML = '<div class="table-card"><table class="table"><thead><tr>'
    + '<th>#</th><th>Nombre</th><th>Correo</th><th>Celular</th><th>Contraseña</th><th>Alta</th><th></th>'
    + '</tr></thead><tbody>'
    + usuarios.map(function(u) { return renderFilaUsuario(u); }).join('')
    + '</tbody></table></div>';
}

function renderFilaUsuario(u) {
  var fecha = u.created_at ? new Date(u.created_at).toLocaleDateString('es-AR') : '—';
  return '<tr>'
    + '<td class="td-id">#' + u.id + '</td>'
    + '<td><strong>' + esc(u.nombre) + '</strong></td>'
    + '<td>' + esc(u.correo || '—') + '</td>'
    + '<td>' + esc(u.celular || '—') + '</td>'
    + '<td style="font-family:monospace;font-size:.82rem">' + esc(u.contrasena || '—') + '</td>'
    + '<td>' + fecha + '</td>'
    + '<td><div class="actions">'
    + '<button class="btn-icon-sm" title="Editar" onclick="abrirEditarUsuario(' + u.id + ')">✏️</button>'
    + '<button class="btn-icon-sm btn-danger-sm" title="Eliminar" onclick="confirmarEliminarUsuario(' + u.id + ',\'' + esc(u.nombre) + '\')">🗑️</button>'
    + '</div></td>'
    + '</tr>';
}

function abrirNuevoUsuario() {
  usrEditandoId = null;
  document.getElementById('usrModalTitulo').textContent = 'Nuevo usuario';
  document.getElementById('usrNombre').value      = '';
  document.getElementById('usrCorreo').value      = '';
  document.getElementById('usrCelular').value     = '';
  document.getElementById('usrContrasena').value  = '';
  document.getElementById('usrModalBackdrop').classList.add('open');
  document.getElementById('usrNombre').focus();
}

function abrirEditarUsuario(id) {
  var u = usuarios.find(function(x) { return x.id === id; });
  if (!u) return;
  usrEditandoId = id;
  document.getElementById('usrModalTitulo').textContent = 'Editar usuario';
  document.getElementById('usrNombre').value      = u.nombre     || '';
  document.getElementById('usrCorreo').value      = u.correo     || '';
  document.getElementById('usrCelular').value     = u.celular    || '';
  document.getElementById('usrContrasena').value  = u.contrasena || '';
  document.getElementById('usrModalBackdrop').classList.add('open');
  document.getElementById('usrNombre').focus();
}

function cerrarModalUsuario() {
  document.getElementById('usrModalBackdrop').classList.remove('open');
}

async function guardarUsuario() {
  var nombre     = document.getElementById('usrNombre').value.trim();
  var correo     = document.getElementById('usrCorreo').value.trim();
  var celular    = document.getElementById('usrCelular').value.trim();
  var contrasena = document.getElementById('usrContrasena').value.trim();

  if (!nombre) { showToast('El nombre es requerido'); return; }

  var body   = { nombre, correo, celular, contrasena };
  var method = usrEditandoId ? 'PUT' : 'POST';
  if (usrEditandoId) body.id = usrEditandoId;

  try {
    var res  = await fetch(USR_API, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    var data = await res.json();
    if (data.ok) {
      cerrarModalUsuario();
      cargarUsuarios();
      showToast(usrEditandoId ? 'Usuario actualizado' : 'Usuario creado');
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function confirmarEliminarUsuario(id, nombre) {
  document.getElementById('confirmMsg').textContent = '¿Eliminar el usuario "' + nombre + '"? Esta acción no se puede deshacer.';
  abrirConfirm(function(ok) {
    if (!ok) return;
    fetch(USR_API + '?id=' + id, { method: 'DELETE' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) { cargarUsuarios(); showToast('Usuario eliminado'); }
        else showToast(data.error || 'Error al eliminar', true);
      });
  });
}

/* ===== Eventos ===== */
let eventosData = [];
let eventoBusqueda = '';

async function cargarEventos() {
  const tbody = document.getElementById('eventosBody');
  tbody.innerHTML = '<tr class="spinner-row"><td colspan="5"><div class="spin"></div></td></tr>';

  try {
    const params = eventoBusqueda ? '?q=' + encodeURIComponent(eventoBusqueda) : '';
    const res  = await fetch('api/eventos' + params);
    const data = await res.json();
    if (data.ok) {
      eventosData = data.data || [];
      document.getElementById('evtStatTotal').textContent = data.stats.total;
      document.getElementById('evtStatHoy').textContent   = data.stats.hoy;
      renderEventos();
    } else {
      tbody.innerHTML = '<tr><td colspan="5" class="table-empty">Error al cargar eventos</td></tr>';
    }
  } catch {
    tbody.innerHTML = '<tr><td colspan="5" class="table-empty">Sin conexión</td></tr>';
  }
}

function renderEventos() {
  const tbody = document.getElementById('eventosBody');
  if (!eventosData.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No hay eventos registrados</td></tr>';
    return;
  }
  tbody.innerHTML = eventosData.map(ev => {
    const fecha = new Date(ev.created_at).toLocaleString('es-AR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
    const cliente = ev.cliente_id > 0
      ? esc(ev.cliente_nombre) + ' <span style="color:var(--muted);font-size:.78rem">(#' + ev.cliente_id + ')</span>'
      : '<span style="color:var(--muted)">Sin sesión</span>';
    return '<tr style="cursor:pointer" onclick="abrirDetalleEvento(' + ev.id + ')">'
      + '<td>' + ev.id + '</td>'
      + '<td>' + fecha + '</td>'
      + '<td>' + cliente + '</td>'
      + '<td>' + esc(ev.detalle) + '</td>'
      + '<td onclick="event.stopPropagation()"><button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleEvento(' + ev.id + ')">🔍</button></td>'
      + '</tr>';
  }).join('');
}

/* ===== Modal detalle evento ===== */
function abrirDetalleEvento(id) {
  const ev = eventosData.find(x => x.id == id);
  if (!ev) return;

  const fecha = new Date(ev.created_at).toLocaleString('es-AR', {
    weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });

  document.getElementById('evtDetId').textContent      = '#' + ev.id;
  document.getElementById('evtDetFecha').textContent   = fecha;
  document.getElementById('evtDetDetalle').textContent = ev.detalle || '—';

  const cliEl = document.getElementById('evtDetCliente');
  if (ev.cliente_id > 0) {
    cliEl.textContent = ev.cliente_nombre + ' (#' + ev.cliente_id + ')';
  } else {
    cliEl.innerHTML = '<span style="color:var(--text-secondary)">Sin sesión</span>';
  }

  document.getElementById('evtDetBackdrop').classList.add('open');
}

function cerrarDetalleEvento() {
  document.getElementById('evtDetBackdrop').classList.remove('open');
}

function onSearchEvento(val) {
  eventoBusqueda = val.trim();
  cargarEventos();
}

/* ===== Mensajes ===== */
let mensajesData = [];
let mensajeBusqueda = '';
let mensajeFiltroCanal = 'todos';

async function cargarMensajes() {
  const tbody = document.getElementById('mensajesBody');
  tbody.innerHTML = '<tr class="spinner-row"><td colspan="7"><div class="spin"></div></td></tr>';

  try {
    const params = new URLSearchParams();
    if (mensajeBusqueda) params.set('q', mensajeBusqueda);
    if (mensajeFiltroCanal !== 'todos') params.set('canal', mensajeFiltroCanal);
    const qs = params.toString() ? '?' + params.toString() : '';
    const res  = await fetch('api/mensajes' + qs);
    const data = await res.json();
    if (data.ok) {
      mensajesData = data.data || [];
      document.getElementById('msgStatTotal').textContent    = data.stats.total;
      document.getElementById('msgStatEmail').textContent    = data.stats.email;
      document.getElementById('msgStatWhatsapp').textContent = data.stats.whatsapp;
      renderMensajes();
    } else {
      tbody.innerHTML = '<tr><td colspan="7" class="table-empty">Error al cargar mensajes</td></tr>';
    }
  } catch {
    tbody.innerHTML = '<tr><td colspan="7" class="table-empty">Sin conexión</td></tr>';
  }
}

function renderMensajes() {
  const tbody = document.getElementById('mensajesBody');
  if (!mensajesData.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="table-empty">No hay mensajes registrados</td></tr>';
    return;
  }
  tbody.innerHTML = mensajesData.map(m => {
    const fecha = new Date(m.created_at).toLocaleString('es-AR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
    const canal = m.canal === 'email'
      ? '<span style="color:#3b82f6;font-weight:600">📧 Correo</span>'
      : '<span style="color:#16a34a;font-weight:600">💬 WhatsApp</span>';
    const estadoColor = m.estado === 'enviado' ? 'green'
      : m.estado === 'error' ? 'red' : 'var(--warn)';
    const estadoLabel = m.estado === 'enviado' ? '✅ Enviado'
      : m.estado === 'error' ? '❌ Error' : '⏳ ' + esc(m.estado);
    return '<tr style="cursor:pointer" onclick="abrirDetalleMensaje(' + m.id + ')">'
      + '<td>' + m.id + '</td>'
      + '<td>' + fecha + '</td>'
      + '<td>' + canal + '</td>'
      + '<td>' + esc(m.destinatario) + '</td>'
      + '<td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(m.mensaje) + '">'
        + (m.asunto ? '<strong>' + esc(m.asunto) + '</strong> — ' : '') + esc(m.mensaje)
        + '</td>'
      + '<td style="color:' + estadoColor + ';font-weight:600">' + estadoLabel + '</td>'
      + '<td onclick="event.stopPropagation()"><button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleMensaje(' + m.id + ')">🔍</button></td>'
      + '</tr>';
  }).join('');
  resolverPendingDetail('mensajes');
}

function onSearchMensaje(val) {
  mensajeBusqueda = val.trim();
  cargarMensajes();
}

function onFiltroCanal(val) {
  mensajeFiltroCanal = val;
  cargarMensajes();
}

function abrirDetalleMensaje(id) {
  const m = mensajesData.find(x => x.id == id);
  if (!m) return;

  const fecha = new Date(m.created_at).toLocaleString('es-AR', {
    weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });

  const canalLabel = m.canal === 'email'
    ? '<span style="color:#3b82f6;font-weight:600">📧 Correo electrónico</span>'
    : '<span style="color:#16a34a;font-weight:600">💬 WhatsApp</span>';

  const estadoColor = m.estado === 'enviado' ? 'var(--success)'
    : m.estado === 'error' ? 'var(--danger)' : 'var(--warn)';
  const estadoLabel = m.estado === 'enviado' ? '✅ Enviado'
    : m.estado === 'error' ? '❌ Error' : '⏳ ' + esc(m.estado);

  document.getElementById('msgDetId').textContent      = '#' + m.id;
  document.getElementById('msgDetFecha').textContent   = fecha;
  document.getElementById('msgDetCanal').innerHTML     = canalLabel;
  document.getElementById('msgDetDestinatario').textContent = m.destinatario || '—';
  document.getElementById('msgDetDestino').textContent = m.destino || (m.canal === 'email' ? '(sin email registrado)' : '(sin teléfono registrado)');
  document.getElementById('msgDetEstado').innerHTML    = '<span style="color:' + estadoColor + ';font-weight:700">' + estadoLabel + '</span>';

  const asuntoRow = document.getElementById('msgDetAsuntoRow');
  if (m.asunto) {
    asuntoRow.style.display = '';
    document.getElementById('msgDetAsunto').textContent = m.asunto;
  } else {
    asuntoRow.style.display = 'none';
  }

  document.getElementById('msgDetCuerpo').textContent = m.mensaje || '';

  document.getElementById('msgDetBackdrop').classList.add('open');
}

function cerrarDetalleMensaje() {
  document.getElementById('msgDetBackdrop').classList.remove('open');
}

/* ===== Categorías: Grid ===== */
function renderCatCard(c, esSub) {
  const emoji = esSub ? '' : `<div class="cat-card-emoji">${esc(c.emoji)}</div>`;
  return `
    <div class="cat-card ${c.activa === false ? 'cat-inactiva' : ''} ${esSub ? 'cat-card-sub' : ''}">
      ${emoji}
      <div class="cat-card-info">
        <div class="cat-card-label">${esc(c.label)}</div>
        <div class="cat-card-orden">orden: #${c.orden}</div>
        <div class="cat-card-productos">productos: ${Number(c.productos || 0)}</div>
      </div>
      <div class="cat-card-actions">
        <button class="btn-icon-sm" title="Editar" onclick="catModal.editar('${esc(c.id)}')">✏️</button>
        <button class="btn-icon-sm" title="Eliminar" onclick="catModal.eliminar('${esc(c.id)}', '${esc(c.label)}')">🗑️</button>
      </div>
    </div>`;
}

function renderCatGrid() {
  const grid = document.getElementById('catGrid');
  if (!CATEGORIAS.length) {
    grid.innerHTML = '<div class="table-empty">No hay categorías cargadas</div>';
    return;
  }
  const raices = CATEGORIAS.filter(c => !c.parent_id);
  const hijosPor = {};
  CATEGORIAS.filter(c => c.parent_id).forEach(c => {
    (hijosPor[c.parent_id] = hijosPor[c.parent_id] || []).push(c);
  });

  grid.innerHTML = raices.map(r => {
    const hijos = hijosPor[r.id] || [];
    const subs = hijos.map(h => renderCatCard(h, true)).join('');
    return `
      <div class="cat-group">
        ${renderCatCard(r, false)}
        ${subs ? `<div class="cat-subgroup">${subs}</div>` : ''}
      </div>
    `;
  }).join('');
}

/* ===== Categorías: Modal CRUD (solo raíces) ===== */
const catModal = {
  editandoId: null,

  abrir() {
    this.editandoId = null;
    document.getElementById('catModalTitle').textContent = 'Nueva categoría';
    document.getElementById('catId').value = '';
    document.getElementById('catId').disabled = false;
    document.getElementById('catLabel').value = '';
    document.getElementById('catEmoji').value = '';
    document.getElementById('catActiva').checked = true;
    document.getElementById('catOrden').value = CATEGORIAS.filter(c => !c.parent_id).length + 1;
    document.getElementById('catModalBackdrop').classList.add('open');
  },

  // Dispatcher: si es subcategoría, delega al modal correcto
  editar(id) {
    const cat = CATEGORIAS.find(c => c.id === id);
    if (!cat) return;
    if (cat.parent_id) return subcatModal.editar(id);

    this.editandoId = id;
    document.getElementById('catModalTitle').textContent = 'Editar categoría';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catId').disabled = true;
    document.getElementById('catLabel').value = cat.label;
    document.getElementById('catEmoji').value = cat.emoji;
    document.getElementById('catActiva').checked = cat.activa !== false;
    document.getElementById('catOrden').value = cat.orden || 0;
    document.getElementById('catModalBackdrop').classList.add('open');
  },

  cerrar() {
    document.getElementById('catModalBackdrop').classList.remove('open');
  },

  async guardar() {
    const id     = document.getElementById('catId').value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
    const label  = document.getElementById('catLabel').value.trim();
    const emoji  = document.getElementById('catEmoji').value.trim();
    const activa = document.getElementById('catActiva').checked;
    const orden  = parseInt(document.getElementById('catOrden').value) || 0;

    if (!id)    { showToast('El ID es obligatorio', true); return; }
    if (!label) { showToast('El nombre es obligatorio', true); return; }
    if (!emoji) { showToast('El emoji es obligatorio', true); return; }

    // Siempre raíz. En edición mandamos parent_id=null por si venía con padre previamente.
    const body = { id, label, emoji, activa, orden, parent_id: null };
    let res;

    try {
      if (this.editandoId) {
        res = await fetch(CAT_API, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      } else {
        res = await fetch(CAT_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      }
      const data = await res.json();

      if (data.ok) {
        this.cerrar();
        showToast(this.editandoId ? 'Categoría actualizada' : 'Categoría creada');
        await cargarCategorias();
        poblarSelects();
        renderCatGrid();
      } else {
        showToast(data.error || 'Error al guardar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  },

  eliminar(id, label) {
    document.getElementById('confirmMsg').textContent = `¿Eliminás la categoría "${label}"? Solo se puede eliminar si no tiene productos asociados.`;
    confirmCallback = async () => {
      try {
        const res = await fetch(`${CAT_API}?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.ok) {
          showToast('Categoría eliminada');
          await cargarCategorias();
          poblarSelects();
          renderCatGrid();
        } else {
          showToast(data.error || 'Error al eliminar', true);
        }
      } catch (e) {
        showToast('Error de conexión', true);
      }
    };
    document.getElementById('confirmBackdrop').classList.add('open');
  },
};

/* ===== Subcategorías: Modal CRUD (siempre tienen padre, nunca emoji) ===== */
const subcatModal = {
  editandoId: null,

  poblarParentSelect(preseleccionado) {
    const sel = document.getElementById('subcatParent');
    const raices = CATEGORIAS.filter(c => !c.parent_id);
    if (!raices.length) {
      sel.innerHTML = '<option value="">— Primero creá una categoría raíz —</option>';
      return;
    }
    sel.innerHTML = raices.map(c => `<option value="${esc(c.id)}">${esc(c.emoji)} ${esc(c.label)}</option>`).join('');
    if (preseleccionado) sel.value = preseleccionado;
  },

  abrir() {
    const raices = CATEGORIAS.filter(c => !c.parent_id);
    if (!raices.length) { showToast('Primero creá una categoría raíz', true); return; }
    this.editandoId = null;
    document.getElementById('subcatModalTitle').textContent = 'Nueva subcategoría';
    document.getElementById('subcatId').value = '';
    document.getElementById('subcatId').disabled = false;
    document.getElementById('subcatLabel').value = '';
    document.getElementById('subcatActiva').checked = true;
    document.getElementById('subcatOrden').value = CATEGORIAS.filter(c => c.parent_id).length + 1;
    this.poblarParentSelect(null);
    document.getElementById('subcatModalBackdrop').classList.add('open');
  },

  editar(id) {
    const cat = CATEGORIAS.find(c => c.id === id);
    if (!cat) return;
    this.editandoId = id;
    document.getElementById('subcatModalTitle').textContent = 'Editar subcategoría';
    document.getElementById('subcatId').value = cat.id;
    document.getElementById('subcatId').disabled = true;
    document.getElementById('subcatLabel').value = cat.label;
    document.getElementById('subcatActiva').checked = cat.activa !== false;
    document.getElementById('subcatOrden').value = cat.orden || 0;
    this.poblarParentSelect(cat.parent_id);
    document.getElementById('subcatModalBackdrop').classList.add('open');
  },

  cerrar() {
    document.getElementById('subcatModalBackdrop').classList.remove('open');
  },

  async guardar() {
    const id        = document.getElementById('subcatId').value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
    const label     = document.getElementById('subcatLabel').value.trim();
    const parent_id = document.getElementById('subcatParent').value;
    const activa    = document.getElementById('subcatActiva').checked;
    const orden     = parseInt(document.getElementById('subcatOrden').value) || 0;

    if (!id)        { showToast('El ID es obligatorio', true); return; }
    if (!label)     { showToast('El nombre es obligatorio', true); return; }
    if (!parent_id) { showToast('Tenés que elegir una categoría padre', true); return; }

    const body = { id, label, emoji: '', activa, orden, parent_id };
    try {
      const method = this.editandoId ? 'PUT' : 'POST';
      const res = await fetch(CAT_API, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const data = await res.json();
      if (data.ok) {
        this.cerrar();
        showToast(this.editandoId ? 'Subcategoría actualizada' : 'Subcategoría creada');
        await cargarCategorias();
        poblarSelects();
        renderCatGrid();
      } else {
        showToast(data.error || 'Error al guardar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  },
};

/* ===== Pedidos ===== */
const ESTADOS = {
  pendiente:   { label: 'Pendiente',   emoji: '⏳', color: '#3b82f6' },
  preparacion: { label: 'Preparación', emoji: '🔧', color: '#f59e0b' },
  asignacion:  { label: 'Asignación',  emoji: '📋', color: '#8b5cf6' },
  reparto:     { label: 'Reparto',     emoji: '🛵', color: '#06b6d4' },
  entregado:   { label: 'Entregado',   emoji: '✅', color: '#16a34a' },
  cancelado:   { label: 'Cancelado',   emoji: '❌', color: '#ef4444' },
};

let pedidos = [];
let filtroEstado = 'todos';
let filtroBusqPedido = '';
let pedidoActual = null;
let pedSearchTimer;

function onSearchPedido(val) {
  clearTimeout(pedSearchTimer);
  pedSearchTimer = setTimeout(function() { filtroBusqPedido = val; cargarPedidos(); }, 300);
}
function onFiltroEstado(val) {
  filtroEstado = val;
  cargarPedidos();
}

async function cargarPedidos() {
  var params = [];
  if (filtroEstado && filtroEstado !== 'todos') params.push('estado=' + encodeURIComponent(filtroEstado));
  if (filtroBusqPedido) params.push('q=' + encodeURIComponent(filtroBusqPedido));
  var qs = params.length ? '?' + params.join('&') : '';

  try {
    var res = await fetch(PED_API + qs);
    var data = await res.json();
    if (data.ok) {
      pedidos = data.data || [];
      renderPedStats(data.stats || {});
      renderPedidos();
    } else {
      showToast(data.error || 'Error al cargar pedidos', true);
    }
  } catch (e) {
    showToast('Error de conexión al cargar pedidos', true);
  }
}

function renderPedStats(stats) {
  var total = 0, monto = 0;
  for (var key in stats) {
    total += stats[key].cant;
    monto += stats[key].monto;
  }
  document.getElementById('pedStatTotal').textContent = total;
  document.getElementById('pedStatPendiente').textContent = (stats.pendiente ? stats.pendiente.cant : 0);
  document.getElementById('pedStatPreparacion').textContent = (stats.preparacion ? stats.preparacion.cant : 0);
  document.getElementById('pedStatEntregado').textContent = (stats.entregado ? stats.entregado.cant : 0);
  document.getElementById('pedStatMonto').textContent = '$' + monto.toLocaleString('es-AR');
}

function renderPedidos() {
  var lista = document.getElementById('pedidosLista');
  if (!pedidos.length) {
    lista.innerHTML = '<div class="table-empty">No hay pedidos para los filtros aplicados</div>';
    return;
  }
  lista.innerHTML = pedidos.map(function(p) {
    var est = ESTADOS[p.estado] || ESTADOS.pendiente;
    var itemCount = 0;
    for (var i = 0; i < (p.items || []).length; i++) {
      itemCount += (p.items[i].cantidad || 1);
    }
    var fecha = p.fecha ? new Date(p.fecha).toLocaleString('es-AR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : '';
    return '<div class="ped-card" onclick="abrirPedido(' + p.id + ')" data-id="' + p.id + '">' +
      '<div class="ped-card-head">' +
        '<span class="ped-card-num">' + esc(p.numero) + '</span>' +
        '<span class="ped-card-badge" style="background:' + est.color + '15;color:' + est.color + '">' + est.emoji + ' ' + est.label + '</span>' +
      '</div>' +
      '<div class="ped-card-body">' +
        '<div class="ped-card-cliente">' + esc(p.cliente) + '</div>' +
        '<div class="ped-card-meta">' +
          '<span>📞 ' + esc(p.celular || '—') + '</span>' +
          '<span>🏠 ' + esc(p.direccion ? (p.direccion.length > 30 ? p.direccion.substring(0, 30) + '...' : p.direccion) : '—') + '</span>' +
          '<span>📍 ' + parseFloat(p.distancia_km || 0).toFixed(1) + ' km • ' + parseInt(p.tiempo_min || 0) + ' min' + (cfgPrecioKm > 0 ? ' • $' + (parseFloat(p.distancia_km || 0) * cfgPrecioKm).toLocaleString('es-AR', {minimumFractionDigits:0, maximumFractionDigits:0}) : '') + '</span>' +
        '</div>' +
        '<div class="ped-card-footer">' +
          '<span class="ped-card-items">' + itemCount + ' producto' + (itemCount !== 1 ? 's' : '') + '</span>' +
          '<span class="ped-card-total">$' + Number(p.total).toLocaleString('es-AR') + '</span>' +
          '<span class="ped-card-fecha">' + fecha + '</span>' +
        '</div>' +
        '<div class="ped-card-rep' + (p.repartidor_nombre ? '' : ' ped-card-rep--none') + '">🛵 ' + (p.repartidor_nombre ? esc(p.repartidor_nombre) : 'Sin asignar') + '</div>' +
      '</div>' +
    '</div>';
  }).join('');
  resolverPendingDetail('pedidos');
}

function abrirPedido(id) {
  pedidoActual = null;
  for (var i = 0; i < pedidos.length; i++) {
    if (pedidos[i].id === id || pedidos[i].id == id) { pedidoActual = pedidos[i]; break; }
  }
  if (!pedidoActual) return;

  var est = ESTADOS[pedidoActual.estado] || ESTADOS.pendiente;
  document.getElementById('pedModalTitle').textContent = pedidoActual.numero;
  document.getElementById('pedModalFecha').textContent = pedidoActual.fecha ? new Date(pedidoActual.fecha).toLocaleString('es-AR') : '';

  document.getElementById('pedDetCliente').textContent = pedidoActual.cliente;
  document.getElementById('pedDetCorreo').textContent  = pedidoActual.correo ? '✉️ ' + pedidoActual.correo : '';
  document.getElementById('pedDetCelular').textContent = '📞 ' + (pedidoActual.celular || '—');
  document.getElementById('pedDetDireccion').textContent = '🏠 ' + (pedidoActual.direccion || '—');

  var ubiEl = document.getElementById('pedDetUbicacion');
  if (pedidoActual.lat && pedidoActual.lng) {
    var mapUrl = 'https://www.google.com/maps?q=' + pedidoActual.lat + ',' + pedidoActual.lng;
    document.getElementById('pedDetMapLink').href = mapUrl;
    var distKm = parseFloat(pedidoActual.distancia_km || 0);
    var costoEnvio = cfgPrecioKm > 0 ? ' • $' + (distKm * cfgPrecioKm).toLocaleString('es-AR', {minimumFractionDigits:0, maximumFractionDigits:0}) : '';
    document.getElementById('pedDetMapLink').textContent = '📍 ' + distKm.toFixed(1) + ' km • ' + parseInt(pedidoActual.tiempo_min || 0) + ' min' + costoEnvio + ' — Ver en mapa';
    ubiEl.style.display = '';
  } else {
    ubiEl.style.display = 'none';
  }

  var notasEl = document.getElementById('pedDetNotas');
  if (pedidoActual.notas) {
    notasEl.textContent = '📝 ' + pedidoActual.notas;
    notasEl.style.display = '';
  } else {
    notasEl.style.display = 'none';
  }

  var repSection = document.getElementById('pedDetRepSection');
  var repNombre  = document.getElementById('pedDetRepNombre');
  repSection.style.display = '';
  if (pedidoActual.repartidor_nombre) {
    repNombre.textContent    = pedidoActual.repartidor_nombre;
    repNombre.style.color    = '';
    repNombre.style.fontStyle = '';
  } else {
    repNombre.textContent    = 'Sin asignar';
    repNombre.style.color    = 'var(--muted)';
    repNombre.style.fontStyle = 'italic';
  }

  // Items
  var itemsHtml = '';
  var items = pedidoActual.items || [];
  for (var i = 0; i < items.length; i++) {
    var it = items[i];
    var subtotal = (it.precio * (it.cantidad || 1));
    itemsHtml += '<div class="ped-item-row">' +
      '<span class="ped-item-name">' + esc(it.nombre) + ' ×' + (it.cantidad || 1) + '</span>' +
      '<span class="ped-item-price">$' + Number(subtotal).toLocaleString('es-AR') + '</span>' +
    '</div>';
  }
  document.getElementById('pedDetItems').innerHTML = itemsHtml;
  document.getElementById('pedDetTotal').textContent = '$' + Number(pedidoActual.total).toLocaleString('es-AR');

  // Estado buttons
  var btnsHtml = '';
  var keys = ['pendiente', 'preparacion', 'asignacion', 'reparto', 'entregado', 'cancelado'];
  for (var k = 0; k < keys.length; k++) {
    var key = keys[k];
    var e = ESTADOS[key];
    var active = pedidoActual.estado === key;
    btnsHtml += '<button class="ped-estado-btn' + (active ? ' active' : '') + '" ' +
      'style="--est-color:' + e.color + '" ' +
      'data-estado="' + key + '" ' +
      'onclick="cambiarEstado(\'' + key + '\')">' +
      e.emoji + ' ' + e.label + '</button>';
  }
  document.getElementById('pedEstadoBtns').innerHTML = btnsHtml;

  document.getElementById('pedModalBackdrop').classList.add('open');
}

function cerrarPedModal() {
  document.getElementById('pedModalBackdrop').classList.remove('open');
  pedidoActual = null;
}

function imprimirTicket() {
  if (!pedidoActual) return;
  window.open('ticket.php?id=' + pedidoActual.id, '_blank', 'width=400,height=650');
}

async function cambiarEstado(nuevoEstado) {
  if (!pedidoActual) return;
  try {
    var res = await fetch(PED_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: pedidoActual.id, estado: nuevoEstado })
    });
    var data = await res.json();
    if (data.ok) {
      pedidoActual.estado = nuevoEstado;
      // Actualizar botones en el DOM sin llamar abrirPedido (que hace pedidoActual = null)
      document.querySelectorAll('#pedEstadoBtns .ped-estado-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.estado === nuevoEstado);
      });
      showToast('Estado actualizado a: ' + ESTADOS[nuevoEstado].label);
      cargarPedidos();
    } else {
      showToast(data.error || 'Error al cambiar estado', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

async function eliminarPedido() {
  if (!pedidoActual) return;
  var num = pedidoActual.numero;
  document.getElementById('confirmMsg').textContent = '¿Eliminás el pedido "' + num + '"? Esta acción no se puede deshacer.';
  confirmCallback = async function() {
    try {
      var res = await fetch(PED_API + '?id=' + pedidoActual.id, { method: 'DELETE' });
      var data = await res.json();
      if (data.ok) {
        cerrarPedModal();
        showToast('Pedido ' + num + ' eliminado');
        cargarPedidos();
      } else {
        showToast(data.error || 'Error al eliminar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

/* ===== Clientes ===== */
var clientes = [];
var cliSearchTimer = null;
var filtroBusqCliente = '';

function onSearchCliente(val) {
  clearTimeout(cliSearchTimer);
  cliSearchTimer = setTimeout(function() { filtroBusqCliente = val; cargarClientes(); }, 300);
}

async function cargarClientes() {
  try {
    var url = CLI_API + '?q=' + encodeURIComponent(filtroBusqCliente);
    var res = await fetch(url);
    var data = await res.json();
    if (data.ok) {
      clientes = data.data || [];
      renderClientes();
      if (data.stats) {
        document.getElementById('cliStatTotal').textContent = data.stats.total;
        document.getElementById('cliStatConPedidos').textContent = data.stats.con_pedidos;
      }
    } else {
      showToast(data.error || 'Error al cargar clientes', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function renderClientes() {
  var lista = document.getElementById('clientesLista');
  if (!clientes.length) {
    lista.innerHTML = '<div class="table-empty">No hay clientes registrados</div>';
    return;
  }
  lista.innerHTML = '<div class="table-card"><table class="table"><thead><tr>' +
    '<th>Nombre / Correo</th><th>Celular</th><th>Dirección / Ubicación</th><th>Pedidos</th><th>Total gastado</th><th>Último pedido</th><th></th>' +
    '</tr></thead><tbody>' +
    clientes.map(function(c) { return renderFilaCliente(c); }).join('') +
    '</tbody></table></div>';
  resolverPendingDetail('clientes');
}

function renderFilaCliente(c) {
  var ultimo  = c.ultimo_pedido ? new Date(c.ultimo_pedido).toLocaleDateString('es-AR') : '—';
  var correo  = c.correo ? '<br><a class="cli-email" href="mailto:' + esc(c.correo) + '">' + esc(c.correo) + '</a>' : '';
  var dir     = c.direccion ? esc(c.direccion.length > 40 ? c.direccion.substring(0,40) + '…' : c.direccion) : '—';
  var mapa    = (c.lat && c.lng)
    ? '<br><a class="cli-mapa" href="https://www.google.com/maps?q=' + c.lat + ',' + c.lng + '" target="_blank" rel="noopener">🗺️ Ver ubicación</a>'
    : '';
  return '<tr id="cli-row-' + c.id + '" style="cursor:pointer" onclick="abrirDetalleCliente(' + c.id + ')">' +
    '<td><strong>' + esc(c.nombre) + '</strong>' + correo + '</td>' +
    '<td>' + esc(c.celular || '—') + '</td>' +
    '<td>' + dir + mapa + '</td>' +
    '<td style="text-align:center">' + c.total_pedidos + '</td>' +
    '<td>$' + Number(c.total_gastado).toLocaleString('es-AR') + '</td>' +
    '<td>' + ultimo + '</td>' +
    '<td><div class="actions" onclick="event.stopPropagation()">' +
      '<button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleCliente(' + c.id + ')">🔍</button>' +
      '<button class="btn-icon-sm" title="Editar" onclick="abrirEditarCliente(' + c.id + ')">✏️</button>' +
      '<button class="btn-icon-sm" title="Eliminar" onclick="eliminarCliente(' + c.id + ',\'' + esc(c.nombre).replace(/'/g, "\\'") + '\')">🗑️</button>' +
    '</div></td>' +
    '</tr>';
}

/* ===== Modal detalle cliente ===== */
var cliDetActualId    = null;
var cliDetDirecciones = [];

function abrirDetalleCliente(id) {
  var c = clientes.find(function(x) { return x.id === id; });
  if (!c) return;
  cliDetActualId = id;

  document.getElementById('cliDetNombre').textContent   = c.nombre || '—';
  document.getElementById('cliDetCelular').textContent  = c.celular || '—';
  document.getElementById('cliDetCorreo').textContent   = c.correo || '—';

  var ubicEl = document.getElementById('cliDetUbicacion');
  ubicEl.style.color = '';
  if (c.lat && c.lng) {
    var mapsUrl = 'https://www.google.com/maps?q=' + c.lat + ',' + c.lng;
    ubicEl.innerHTML =
      '<a href="' + mapsUrl + '" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600">📍 Ver última ubicación</a>';
  } else {
    ubicEl.textContent = 'Sin ubicación detectada';
    ubicEl.style.color = 'var(--muted)';
  }

  var lastSeenEl = document.getElementById('cliDetLastSeen');
  var lsRaw = (c.last_seen != null && String(c.last_seen).trim() !== '') ? String(c.last_seen).trim() : '';
  if (lsRaw) {
    var d = new Date(lsRaw.replace(' ', 'T'));
    var formatted = isNaN(d.getTime())
      ? lsRaw
      : d.toLocaleString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', hour12: false });
    lastSeenEl.textContent = formatted;
    lastSeenEl.style.color = '';
    lastSeenEl.style.fontWeight = '600';
  } else {
    lastSeenEl.textContent = '—';
    lastSeenEl.style.color = 'var(--muted)';
    lastSeenEl.style.fontWeight = '';
  }

  document.getElementById('cliDetPedidos').textContent    = c.total_pedidos || 0;
  document.getElementById('cliDetGastado').textContent    = '$' + Number(c.total_gastado || 0).toLocaleString('es-AR');
  document.getElementById('cliDetUltimo').textContent     = c.ultimo_pedido
    ? new Date(c.ultimo_pedido).toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' })
    : '—';

  document.getElementById('btnCliDetEditar').onclick = function() {
    cerrarDetalleCliente();
    abrirEditarCliente(id);
  };

  document.getElementById('cliDetBackdrop').classList.add('open');
  cargarDireccionesAdmin(id);
}

function cerrarDetalleCliente() {
  document.getElementById('cliDetBackdrop').classList.remove('open');
  cliDetActualId = null;
  cliDetDirecciones = [];
}

/* ===== Direcciones del cliente (dentro del detalle) ===== */
function iconoEtiquetaAdmin(etiqueta) {
  var e = (etiqueta || '').toLowerCase();
  if (e.indexOf('trabajo') >= 0 || e.indexOf('oficina') >= 0 || e.indexOf('lab') >= 0) return '💼';
  if (e.indexOf('casa')    >= 0 || e.indexOf('hogar')   >= 0 || e.indexOf('depto') >= 0) return '🏠';
  return '📍';
}

async function cargarDireccionesAdmin(clienteId) {
  var cont = document.getElementById('cliDetDireccionesLista');
  if (!cont) return;
  cont.innerHTML = '<div class="spinner-row" style="text-align:center;padding:12px"><div class="spin"></div></div>';
  try {
    var r = await fetch('api/clientes_direcciones.php?cliente_id=' + clienteId);
    var data = await r.json();
    if (!data.ok) { cont.innerHTML = '<div style="color:var(--text-secondary)">Error al cargar direcciones</div>'; return; }
    cliDetDirecciones = data.data || [];
    renderDireccionesAdmin();
  } catch (e) {
    cont.innerHTML = '<div style="color:var(--text-secondary)">Error de conexión</div>';
  }
}

function renderDireccionesAdmin() {
  var cont = document.getElementById('cliDetDireccionesLista');
  if (!cont) return;
  if (!cliDetDirecciones.length) {
    cont.innerHTML = '<div style="color:var(--text-secondary);padding:8px 0">Sin direcciones</div>';
    return;
  }
  cont.innerHTML = cliDetDirecciones.map(function(d) {
    var icon = iconoEtiquetaAdmin(d.etiqueta);
    var badge = d.es_principal ? '<span class="dir-principal-badge-admin">Principal</span>' : '';
    var lugar = [d.localidad, d.provincia].filter(Boolean).join(', ');
    var mapsLink = (d.lat && d.lng)
      ? '<a href="https://www.google.com/maps?q=' + d.lat + ',' + d.lng + '" target="_blank" rel="noopener" style="color:var(--primary);font-size:.8rem">📍 Ver en Maps</a>'
      : '<span style="color:var(--text-secondary);font-size:.8rem">Sin ubicación</span>';
    var btnPrin = !d.es_principal
      ? '<button class="btn-dir-text" onclick="setPrincipalDirAdmin(' + d.id + ')">Marcar principal</button>'
      : '';
    return '<div class="dir-card-admin">' +
      '<div class="dir-card-admin-head"><span>' + icon + '</span><strong>' + esc(d.etiqueta) + '</strong>' + badge + '</div>' +
      '<div class="dir-card-admin-dir">' + esc(d.direccion || 'Sin dirección') + '</div>' +
      (lugar ? '<div class="dir-card-admin-lugar">' + esc(lugar) + '</div>' : '') +
      '<div class="dir-card-admin-map">' + mapsLink + '</div>' +
      '<div class="dir-card-admin-actions">' +
        btnPrin +
        '<button class="btn-dir-text" onclick="abrirDirModalAdmin(' + d.id + ')">Editar</button>' +
        '<button class="btn-dir-text" style="color:#dc2626" onclick="eliminarDirAdmin(' + d.id + ')">Eliminar</button>' +
      '</div>' +
    '</div>';
  }).join('');
}

/* ===== Modal editar/crear dirección (admin) ===== */
var dirEditandoId = null;
var dirEditMapLat = null;
var dirEditMapLng = null;

function abrirDirModalAdmin(id) {
  if (!cliDetActualId) { showToast('Abrí primero un cliente', true); return; }
  dirEditandoId = id || null;
  var d = id ? cliDetDirecciones.find(function(x) { return x.id === id; }) : null;

  document.getElementById('dirModalTitulo').textContent = d ? 'Editar dirección' : 'Nueva dirección';
  document.getElementById('dirEditId').value        = d ? d.id : '';
  document.getElementById('dirEditEtiqueta').value  = d ? (d.etiqueta || 'Casa') : 'Casa';
  document.getElementById('dirEditDireccion').value = d ? (d.direccion || '') : '';

  dirEditMapLat = d && d.lat != null ? parseFloat(d.lat) : null;
  dirEditMapLng = d && d.lng != null ? parseFloat(d.lng) : null;
  document.getElementById('dirEditMapInfo').innerHTML = (dirEditMapLat && dirEditMapLng)
    ? '📍 <strong>' + dirEditMapLat.toFixed(6) + ', ' + dirEditMapLng.toFixed(6) + '</strong>'
    : 'Sin ubicación seleccionada.';

  var wrap = document.getElementById('dirEditPrincipalWrap');
  var chk  = document.getElementById('dirEditPrincipal');
  var otras = cliDetDirecciones.filter(function(x) { return x.id !== id; });
  if (otras.length > 0) {
    wrap.style.display = '';
    chk.checked = d ? !!d.es_principal : false;
  } else {
    wrap.style.display = 'none';
    chk.checked = false;
  }

  document.getElementById('dirModalBackdrop').classList.add('open');
  document.getElementById('dirEditEtiqueta').focus();
}

function cerrarDirModalAdmin() {
  document.getElementById('dirModalBackdrop').classList.remove('open');
  dirEditandoId = null;
  dirEditMapLat = null;
  dirEditMapLng = null;
}

async function guardarDirAdmin() {
  if (!cliDetActualId) return;
  var etiqueta  = document.getElementById('dirEditEtiqueta').value.trim() || 'Casa';
  var direccion = document.getElementById('dirEditDireccion').value.trim();
  var principal = document.getElementById('dirEditPrincipal').checked;
  if (!direccion) { showToast('Ingresá la dirección', true); return; }

  var payload = {
    etiqueta:     etiqueta,
    direccion:    direccion,
    lat:          dirEditMapLat,
    lng:          dirEditMapLng,
    es_principal: principal ? 1 : 0,
  };

  try {
    var r, data;
    if (dirEditandoId) {
      payload.id = dirEditandoId;
      r = await fetch('api/clientes_direcciones.php', {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    } else {
      payload.cliente_id = cliDetActualId;
      r = await fetch('api/clientes_direcciones.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    }
    data = await r.json();
    if (data.ok) {
      cerrarDirModalAdmin();
      await cargarDireccionesAdmin(cliDetActualId);
      showToast(dirEditandoId ? 'Dirección actualizada' : 'Dirección agregada');
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function eliminarDirAdmin(id) {
  document.getElementById('confirmMsg').textContent = '¿Eliminás esta dirección?';
  confirmCallback = async function() {
    try {
      var r = await fetch('api/clientes_direcciones.php?id=' + id, { method: 'DELETE' });
      var data = await r.json();
      if (data.ok) {
        await cargarDireccionesAdmin(cliDetActualId);
        showToast('Dirección eliminada');
      } else {
        showToast(data.error || 'Error', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

async function setPrincipalDirAdmin(id) {
  try {
    var r = await fetch('api/clientes_direcciones.php', {
      method: 'PATCH', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, es_principal: 1 }),
    });
    var data = await r.json();
    if (data.ok) {
      await cargarDireccionesAdmin(cliDetActualId);
      showToast('Dirección principal actualizada');
    } else {
      showToast(data.error || 'Error', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

/* ===== Modal editar cliente ===== */
var clienteEditandoId = null;

function abrirEditarCliente(id) {
  var c = clientes.find(function(x) { return x.id === id; });
  if (!c) return;
  clienteEditandoId = id;
  document.getElementById('cliNombre').value     = c.nombre     || '';
  document.getElementById('cliCelular').value    = c.celular    || '';
  document.getElementById('cliCorreo').value     = c.correo     || '';
  document.getElementById('cliContrasena').value = c.contrasena || '';

  document.getElementById('cliModalBackdrop').classList.add('open');
  document.getElementById('cliNombre').focus();
}

function cerrarModalCliente() {
  document.getElementById('cliModalBackdrop').classList.remove('open');
  clienteEditandoId = null;
}

async function guardarCliente() {
  if (!clienteEditandoId) return;
  var body = {
    id:          clienteEditandoId,
    nombre:      document.getElementById('cliNombre').value.trim(),
    celular:    document.getElementById('cliCelular').value.trim(),
    correo:      document.getElementById('cliCorreo').value.trim(),
    contrasena:  document.getElementById('cliContrasena').value.trim(),
  };
  if (!body.nombre) { showToast('El nombre es obligatorio', true); return; }
  try {
    var res  = await fetch(CLI_API, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    var data = await res.json();
    if (data.ok) {
      var c = clientes.find(function(x) { return x.id === clienteEditandoId; });
      if (c) { c.nombre = body.nombre; c.celular = body.celular; c.correo = body.correo; c.contrasena = body.contrasena; }
      cerrarModalCliente();
      renderClientes();
      showToast('Cliente actualizado');
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function eliminarCliente(id, nombre) {
  document.getElementById('confirmMsg').textContent = '¿Eliminás el cliente "' + nombre + '"? Sus pedidos no se eliminarán.';
  confirmCallback = async function() {
    try {
      var res = await fetch(CLI_API + '?id=' + id, { method: 'DELETE' });
      var data = await res.json();
      if (data.ok) {
        showToast('Cliente eliminado');
        cargarClientes();
      } else {
        showToast(data.error || 'Error al eliminar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

/* ===== Repartidores ===== */
var repartidores = [];
var repSearchTimer = null;
var filtroBusqRep = '';
var repEditandoId = null;
var repMapLat = null;
var repMapLng = null;

function onSearchRepartidor(val) {
  clearTimeout(repSearchTimer);
  repSearchTimer = setTimeout(function() { filtroBusqRep = val; cargarRepartidores(); }, 300);
}

async function cargarRepartidores() {
  try {
    var url = REP_API + '?q=' + encodeURIComponent(filtroBusqRep);
    var res = await fetch(url);
    var data = await res.json();
    if (data.ok) {
      repartidores = data.data || [];
      renderRepartidores();
      if (data.stats) {
        document.getElementById('repStatTotal').textContent = data.stats.total;
      }
    } else {
      showToast(data.error || 'Error al cargar repartidores', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function renderRepartidores() {
  var lista = document.getElementById('repartidoresLista');
  if (!repartidores.length) {
    lista.innerHTML = '<div class="table-empty">No hay repartidores registrados</div>';
    return;
  }
  lista.innerHTML = '<div class="table-card"><table class="table"><thead><tr>' +
    '<th>Nombre / Correo</th><th>Celular</th><th>Dirección / Ubicación</th><th></th>' +
    '</tr></thead><tbody>' +
    repartidores.map(function(r) { return renderFilaRepartidor(r); }).join('') +
    '</tbody></table></div>';
}

function renderFilaRepartidor(r) {
  var correo = r.correo ? '<br><a class="cli-email" href="mailto:' + esc(r.correo) + '">' + esc(r.correo) + '</a>' : '';
  var dir    = r.direccion ? esc(r.direccion.length > 40 ? r.direccion.substring(0,40) + '…' : r.direccion) : '—';
  var mapa   = (r.lat && r.lng)
    ? '<br><a class="cli-mapa" href="https://www.google.com/maps?q=' + r.lat + ',' + r.lng + '" target="_blank" rel="noopener">🗺️ Ver ubicación</a>'
    : '';
  return '<tr id="rep-row-' + r.id + '" style="cursor:pointer" onclick="abrirDetalleRepartidor(' + r.id + ')">' +
    '<td><strong>' + esc(r.nombre) + '</strong>' + correo + '</td>' +
    '<td>' + esc(r.celular || '—') + '</td>' +
    '<td>' + dir + mapa + '</td>' +
    '<td><div class="actions" onclick="event.stopPropagation()">' +
      '<button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleRepartidor(' + r.id + ')">🔍</button>' +
      '<button class="btn-icon-sm" title="Editar" onclick="abrirEditarRepartidor(' + r.id + ')">✏️</button>' +
      '<button class="btn-icon-sm" title="Eliminar" onclick="eliminarRepartidor(' + r.id + ',\'' + esc(r.nombre).replace(/'/g, "\\'") + '\')">🗑️</button>' +
    '</div></td>' +
    '</tr>';
}

function abrirDetalleRepartidor(id) {
  var r = repartidores.find(function(x) { return x.id === id; });
  if (!r) return;

  document.getElementById('repDetNombre').textContent    = r.nombre || '—';
  document.getElementById('repDetCelular').textContent   = r.celular || '—';
  document.getElementById('repDetCorreo').textContent    = r.correo || '—';
  document.getElementById('repDetDireccion').textContent = r.direccion || '—';
  document.getElementById('repDetContrasena').textContent = r.contrasena || '—';
  document.getElementById('repDetClave').textContent      = r.clave      || '—';

  var ubiEl = document.getElementById('repDetUbicacion');
  if (r.lat && r.lng) {
    ubiEl.innerHTML = '<a href="https://www.google.com/maps?q=' + r.lat + ',' + r.lng + '" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600">📍 Ver en Google Maps</a>'
      + ' <span style="color:var(--text-secondary);font-size:.8rem">(' + parseFloat(r.lat).toFixed(6) + ', ' + parseFloat(r.lng).toFixed(6) + ')</span>';
  } else {
    ubiEl.innerHTML = '<span style="color:var(--text-secondary)">Sin ubicación registrada</span>';
  }

  document.getElementById('btnRepDetEditar').onclick = function() {
    cerrarDetalleRepartidor();
    abrirEditarRepartidor(id);
  };

  document.getElementById('repDetBackdrop').classList.add('open');
}

function cerrarDetalleRepartidor() {
  document.getElementById('repDetBackdrop').classList.remove('open');
}

function abrirNuevoRepartidor() {
  repEditandoId = null;
  document.getElementById('repModalTitulo').textContent = 'Nuevo repartidor';
  document.getElementById('repNombre').value     = '';
  document.getElementById('repCelular').value    = '';
  document.getElementById('repCorreo').value     = '';
  document.getElementById('repDireccion').value  = '';
  document.getElementById('repContrasena').value = '';
  document.getElementById('repClave').value      = '';
  repMapLat = null;
  repMapLng = null;
  document.getElementById('repMapInfo').innerHTML = 'Sin ubicación seleccionada.';
  document.getElementById('repModalBackdrop').classList.add('open');
  document.getElementById('repNombre').focus();
}

function abrirEditarRepartidor(id) {
  var r = repartidores.find(function(x) { return x.id === id; });
  if (!r) return;
  repEditandoId = id;
  document.getElementById('repModalTitulo').textContent = 'Editar repartidor';
  document.getElementById('repNombre').value     = r.nombre     || '';
  document.getElementById('repCelular').value    = r.celular    || '';
  document.getElementById('repCorreo').value     = r.correo     || '';
  document.getElementById('repDireccion').value  = r.direccion  || '';
  document.getElementById('repContrasena').value = r.contrasena || '';
  document.getElementById('repClave').value      = r.clave      || '';

  repMapLat = r.lat ? parseFloat(r.lat) : null;
  repMapLng = r.lng ? parseFloat(r.lng) : null;
  document.getElementById('repMapInfo').innerHTML = (repMapLat && repMapLng)
    ? '📍 <strong>' + repMapLat.toFixed(6) + ', ' + repMapLng.toFixed(6) + '</strong> — <a href="https://www.google.com/maps?q=' + repMapLat + ',' + repMapLng + '" target="_blank" style="color:var(--primary)">Ver en Maps</a>'
    : 'Sin ubicación seleccionada.';

  document.getElementById('repModalBackdrop').classList.add('open');
  document.getElementById('repNombre').focus();
}

function cerrarModalRepartidor() {
  document.getElementById('repModalBackdrop').classList.remove('open');
  repEditandoId = null;
  repMapLat = null;
  repMapLng = null;
}

async function guardarRepartidor() {
  var nombre = document.getElementById('repNombre').value.trim();
  if (!nombre) { showToast('El nombre es obligatorio', true); return; }

  var body = {
    nombre:     nombre,
    celular:    document.getElementById('repCelular').value.trim(),
    correo:     document.getElementById('repCorreo').value.trim(),
    direccion:  document.getElementById('repDireccion').value.trim(),
    contrasena: document.getElementById('repContrasena').value.trim(),
    clave:      document.getElementById('repClave').value.trim(),
    lat:        repMapLat,
    lng:        repMapLng,
  };

  var method = 'POST';
  if (repEditandoId) { body.id = repEditandoId; method = 'PUT'; }

  try {
    var res  = await fetch(REP_API, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    var data = await res.json();
    if (data.ok) {
      cerrarModalRepartidor();
      cargarRepartidores();
      showToast(repEditandoId ? 'Repartidor actualizado' : 'Repartidor creado');
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function eliminarRepartidor(id, nombre) {
  document.getElementById('confirmMsg').textContent = '¿Eliminás el repartidor "' + nombre + '"?';
  confirmCallback = async function() {
    try {
      var res  = await fetch(REP_API + '?id=' + id, { method: 'DELETE' });
      var data = await res.json();
      if (data.ok) {
        showToast('Repartidor eliminado');
        cargarRepartidores();
      } else {
        showToast(data.error || 'Error al eliminar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

/* ===== Proveedores ===== */
var proveedores = [];
var provSearchTimer = null;
var filtroBusqProv = '';
var provEditandoId = null;
var provMapLat = null;
var provMapLng = null;

function onSearchProveedor(val) {
  clearTimeout(provSearchTimer);
  provSearchTimer = setTimeout(function() { filtroBusqProv = val; cargarProveedores(); }, 300);
}

async function cargarProveedores() {
  try {
    var url = PROV_API + '?q=' + encodeURIComponent(filtroBusqProv);
    var res = await fetch(url);
    var data = await res.json();
    if (data.ok) {
      proveedores = data.data || [];
      renderProveedores();
      if (data.stats) {
        document.getElementById('provStatTotal').textContent = data.stats.total;
      }
    } else {
      showToast(data.error || 'Error al cargar proveedores', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function renderProveedores() {
  var lista = document.getElementById('proveedoresLista');
  if (!proveedores.length) {
    lista.innerHTML = '<div class="table-empty">No hay proveedores registrados</div>';
    return;
  }
  lista.innerHTML = '<div class="table-card"><table class="table"><thead><tr>' +
    '<th>Nombre</th><th>Domicilio</th><th>Correo</th><th>Ubicación</th><th></th>' +
    '</tr></thead><tbody>' +
    proveedores.map(function(p) { return renderFilaProveedor(p); }).join('') +
    '</tbody></table></div>';
}

function renderFilaProveedor(p) {
  var correo = p.correo ? '<a class="cli-email" href="mailto:' + esc(p.correo) + '">' + esc(p.correo) + '</a>' : '—';
  var dom = p.domicilio ? esc(p.domicilio.length > 40 ? p.domicilio.substring(0,40) + '…' : p.domicilio) : '—';
  var mapa = (p.lat && p.lng)
    ? '<a class="cli-mapa" href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng + '" target="_blank" rel="noopener">🗺️ Ver ubicación</a>'
    : '—';
  return '<tr>' +
    '<td><strong>' + esc(p.nombre) + '</strong></td>' +
    '<td>' + dom + '</td>' +
    '<td>' + correo + '</td>' +
    '<td>' + mapa + '</td>' +
    '<td><div class="actions">' +
      '<button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleProveedor(' + p.id + ')">🔍</button>' +
      '<button class="btn-icon-sm" title="Editar" onclick="abrirEditarProveedor(' + p.id + ')">✏️</button>' +
      '<button class="btn-icon-sm" title="Eliminar" onclick="eliminarProveedor(' + p.id + ',\'' + esc(p.nombre).replace(/'/g, "\\'") + '\')">🗑️</button>' +
    '</div></td>' +
    '</tr>';
}

/* ===== Modal detalle proveedor ===== */
function abrirDetalleProveedor(id) {
  var p = proveedores.find(function(x) { return x.id === id; });
  if (!p) return;

  document.getElementById('provDetNombre').textContent   = p.nombre || '—';
  document.getElementById('provDetDomicilio').textContent = p.domicilio || '—';
  document.getElementById('provDetCorreo').textContent   = p.correo || '—';

  var ubiEl = document.getElementById('provDetUbicacion');
  if (p.lat && p.lng) {
    ubiEl.innerHTML = '<a href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng + '" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600">📍 Ver en Google Maps</a>'
      + ' <span style="color:var(--text-secondary);font-size:.8rem">(' + parseFloat(p.lat).toFixed(6) + ', ' + parseFloat(p.lng).toFixed(6) + ')</span>';
  } else {
    ubiEl.innerHTML = '<span style="color:var(--text-secondary)">Sin ubicación registrada</span>';
  }

  document.getElementById('btnProvDetEditar').onclick = function() {
    cerrarDetalleProveedor();
    abrirEditarProveedor(id);
  };

  document.getElementById('provDetBackdrop').classList.add('open');
}

function cerrarDetalleProveedor() {
  document.getElementById('provDetBackdrop').classList.remove('open');
}

function abrirNuevoProveedor() {
  provEditandoId = null;
  document.getElementById('provModalTitle').textContent = 'Nuevo proveedor';
  document.getElementById('provNombre').value = '';
  document.getElementById('provDomicilio').value = '';
  document.getElementById('provCorreo').value = '';
  provMapLat = null;
  provMapLng = null;
  document.getElementById('provMapInfo').textContent = 'Sin ubicación seleccionada.';
  document.getElementById('provModalBackdrop').classList.add('open');
  document.getElementById('provNombre').focus();
}

function abrirEditarProveedor(id) {
  var p = proveedores.find(function(x) { return x.id === id; });
  if (!p) return;
  provEditandoId = id;
  document.getElementById('provModalTitle').textContent = 'Editar proveedor';
  document.getElementById('provNombre').value = p.nombre || '';
  document.getElementById('provDomicilio').value = p.domicilio || '';
  document.getElementById('provCorreo').value = p.correo || '';
  provMapLat = p.lat ? parseFloat(p.lat) : null;
  provMapLng = p.lng ? parseFloat(p.lng) : null;
  document.getElementById('provMapInfo').innerHTML = (provMapLat && provMapLng)
    ? '📍 <strong>' + provMapLat.toFixed(6) + ', ' + provMapLng.toFixed(6) + '</strong> — <a href="https://www.google.com/maps?q=' + provMapLat + ',' + provMapLng + '" target="_blank" style="color:var(--primary)">Ver en Maps</a>'
    : 'Sin ubicación seleccionada.';
  document.getElementById('provModalBackdrop').classList.add('open');
  document.getElementById('provNombre').focus();
}

function cerrarModalProveedor() {
  document.getElementById('provModalBackdrop').classList.remove('open');
  provEditandoId = null;
  provMapLat = null;
  provMapLng = null;
}

async function guardarProveedor() {
  var body = {
    nombre:    document.getElementById('provNombre').value.trim(),
    domicilio: document.getElementById('provDomicilio').value.trim(),
    correo:    document.getElementById('provCorreo').value.trim(),
    lat:       provMapLat,
    lng:       provMapLng,
  };
  if (!body.nombre) { showToast('El nombre es obligatorio', true); return; }

  try {
    var method, url;
    if (provEditandoId) {
      body.id = provEditandoId;
      method = 'PUT';
    } else {
      method = 'POST';
    }
    var res = await fetch(PROV_API, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    var data = await res.json();
    if (data.ok) {
      cerrarModalProveedor();
      cargarProveedores();
      showToast(provEditandoId ? 'Proveedor actualizado' : 'Proveedor creado');
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function eliminarProveedor(id, nombre) {
  document.getElementById('confirmMsg').textContent = '¿Eliminás el proveedor "' + nombre + '"?';
  confirmCallback = async function() {
    try {
      var res = await fetch(PROV_API + '?id=' + id, { method: 'DELETE' });
      var data = await res.json();
      if (data.ok) {
        showToast('Proveedor eliminado');
        cargarProveedores();
      } else {
        showToast(data.error || 'Error al eliminar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

/* ===== Compras ===== */
const ESTADOS_COMPRA = {
  pendiente:  { label: 'Pendiente',  emoji: '⏳', color: '#3b82f6' },
  confirmada: { label: 'Confirmada', emoji: '✅', color: '#f59e0b' },
  cancelada:  { label: 'Cancelada',  emoji: '❌', color: '#ef4444' },
};

var compras = [];
var filtroEstadoCompra = 'todos';
var filtroBusqCompra = '';
var compraActual = null;
var compSearchTimer = null;
var compItemCounter = 0;

function onSearchCompra(val) {
  clearTimeout(compSearchTimer);
  compSearchTimer = setTimeout(function() { filtroBusqCompra = val; cargarCompras(); }, 300);
}
function onFiltroEstadoCompra(val) {
  filtroEstadoCompra = val;
  cargarCompras();
}

async function cargarCompras() {
  var params = [];
  if (filtroEstadoCompra && filtroEstadoCompra !== 'todos') params.push('estado=' + encodeURIComponent(filtroEstadoCompra));
  if (filtroBusqCompra) params.push('q=' + encodeURIComponent(filtroBusqCompra));
  var qs = params.length ? '?' + params.join('&') : '';

  try {
    var res = await fetch(COMP_API + qs);
    var data = await res.json();
    if (data.ok) {
      compras = data.data || [];
      renderCompStats(data.stats || {});
      renderCompras();
    } else {
      showToast(data.error || 'Error al cargar compras', true);
    }
  } catch (e) {
    showToast('Error de conexión al cargar compras', true);
  }
}

function renderCompStats(stats) {
  var total = 0, monto = 0;
  for (var key in stats) {
    total += stats[key].cant;
    monto += stats[key].monto;
  }
  document.getElementById('compStatTotal').textContent = total;
  document.getElementById('compStatPendiente').textContent = (stats.pendiente ? stats.pendiente.cant : 0);
  document.getElementById('compStatConfirmada').textContent = (stats.confirmada ? stats.confirmada.cant : 0);
  document.getElementById('compStatMonto').textContent = '$' + monto.toLocaleString('es-AR');
}

function renderCompras() {
  var lista = document.getElementById('comprasLista');
  if (!compras.length) {
    lista.innerHTML = '<div class="table-empty">No hay compras para los filtros aplicados</div>';
    return;
  }
  lista.innerHTML = compras.map(function(c) {
    var est = ESTADOS_COMPRA[c.estado] || ESTADOS_COMPRA.pendiente;
    var itemCount = 0;
    for (var i = 0; i < (c.items || []).length; i++) {
      itemCount += (c.items[i].cantidad || 1);
    }
    var fecha = c.fecha ? new Date(c.fecha).toLocaleString('es-AR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' }) : '';
    return '<div class="ped-card" onclick="abrirCompra(' + c.id + ')">' +
      '<div class="ped-card-head">' +
        '<span class="ped-card-num">' + esc(c.numero) + '</span>' +
        '<span class="ped-card-badge" style="background:' + est.color + '15;color:' + est.color + '">' + est.emoji + ' ' + est.label + '</span>' +
      '</div>' +
      '<div class="ped-card-body">' +
        '<div class="ped-card-cliente">🏭 ' + esc(c.proveedor) + '</div>' +
        '<div class="ped-card-footer">' +
          '<span class="ped-card-items">' + itemCount + ' producto' + (itemCount !== 1 ? 's' : '') + '</span>' +
          '<span class="ped-card-total">$' + Number(c.total).toLocaleString('es-AR') + '</span>' +
          '<span class="ped-card-fecha">' + fecha + '</span>' +
        '</div>' +
      '</div>' +
    '</div>';
  }).join('');
}

// ---- Modal nueva compra ----

async function abrirNuevaCompra() {
  compItemCounter = 0;
  document.getElementById('compItemsWrap').innerHTML = '';
  document.getElementById('compNotas').value = '';
  document.getElementById('compTotal').textContent = '0';

  // Cargar proveedores en select
  var sel = document.getElementById('compProveedor');
  sel.innerHTML = '<option value="">— Seleccionar proveedor —</option>';
  try {
    var res = await fetch(PROV_API);
    var data = await res.json();
    if (data.ok) {
      (data.data || []).forEach(function(p) {
        sel.innerHTML += '<option value="' + p.id + '" data-nombre="' + esc(p.nombre) + '">' + esc(p.nombre) + '</option>';
      });
    }
  } catch (e) {}

  agregarItemCompra();
  document.getElementById('compModalBackdrop').classList.add('open');
}

function onCompProveedorChange() {
  // placeholder for future logic
}

function agregarItemCompra() {
  compItemCounter++;
  var idx = compItemCounter;
  var wrap = document.getElementById('compItemsWrap');
  var row = document.createElement('div');
  row.className = 'comp-item-row';
  row.id = 'compItem' + idx;
  row.innerHTML =
    '<select class="comp-item-prod" id="compItemProd' + idx + '" onchange="onCompItemProdChange(' + idx + ')">' +
      '<option value="">— Producto —</option>' +
    '</select>' +
    '<input type="number" class="comp-item-precio" id="compItemPrecio' + idx + '" placeholder="Precio" min="0" step="0.01" oninput="calcTotalCompra()">' +
    '<input type="number" class="comp-item-cant" id="compItemCant' + idx + '" value="1" min="1" oninput="calcTotalCompra()">' +
    '<button type="button" class="btn-icon-sm" onclick="quitarItemCompra(' + idx + ')" title="Quitar">✕</button>';
  wrap.appendChild(row);

  // Poblar select de productos
  var sel = row.querySelector('select');
  productos.forEach(function(p) {
    sel.innerHTML += '<option value="' + p.id + '" data-nombre="' + esc(p.nombre) + '" data-precio="' + (p.precio_venta ?? 0) + '">' + esc(p.nombre) + '</option>';
  });
}

function onCompItemProdChange(idx) {
  var sel = document.getElementById('compItemProd' + idx);
  var opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.precio) {
    document.getElementById('compItemPrecio' + idx).value = opt.dataset.precio;
    calcTotalCompra();
  }
}

function quitarItemCompra(idx) {
  var el = document.getElementById('compItem' + idx);
  if (el) el.remove();
  calcTotalCompra();
}

function calcTotalCompra() {
  var rows = document.getElementById('compItemsWrap').querySelectorAll('.comp-item-row');
  var total = 0;
  rows.forEach(function(row) {
    var precio = parseFloat(row.querySelector('.comp-item-precio').value) || 0;
    var cant   = parseInt(row.querySelector('.comp-item-cant').value) || 1;
    total += precio * cant;
  });
  document.getElementById('compTotal').textContent = total.toLocaleString('es-AR');
}

function cerrarCompModal() {
  document.getElementById('compModalBackdrop').classList.remove('open');
}

async function guardarCompra() {
  var sel = document.getElementById('compProveedor');
  var provId = sel.value;
  var provNombre = provId ? sel.options[sel.selectedIndex].dataset.nombre : '';
  if (!provId) { showToast('Seleccioná un proveedor', true); return; }

  var items = [];
  var rows = document.getElementById('compItemsWrap').querySelectorAll('.comp-item-row');
  rows.forEach(function(row) {
    var prodSel = row.querySelector('.comp-item-prod');
    var prodOpt = prodSel.options[prodSel.selectedIndex];
    var nombre = (prodOpt && prodOpt.dataset.nombre) ? prodOpt.dataset.nombre : prodSel.value;
    var prodId = prodSel.value ? parseInt(prodSel.value) : null;
    var precio = parseFloat(row.querySelector('.comp-item-precio').value) || 0;
    var cant   = parseInt(row.querySelector('.comp-item-cant').value) || 1;
    if (nombre || prodId) {
      items.push({ producto_id: prodId, nombre: nombre, precio: precio, cantidad: cant });
    }
  });

  if (!items.length) { showToast('Agregá al menos un producto', true); return; }

  var body = {
    proveedor_id: parseInt(provId),
    proveedor: provNombre,
    notas: document.getElementById('compNotas').value.trim(),
    items: items,
  };

  try {
    var res = await fetch(COMP_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    var data = await res.json();
    if (data.ok) {
      cerrarCompModal();
      showToast('Compra ' + data.numero + ' creada');
      cargarCompras();
    } else {
      showToast(data.error || 'Error al crear compra', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

// ---- Modal detalle compra ----

function abrirCompra(id) {
  compraActual = null;
  for (var i = 0; i < compras.length; i++) {
    if (compras[i].id == id) { compraActual = compras[i]; break; }
  }
  if (!compraActual) return;

  document.getElementById('compDetTitle').textContent = compraActual.numero;
  document.getElementById('compDetFecha').textContent = compraActual.fecha ? new Date(compraActual.fecha).toLocaleString('es-AR') : '';
  document.getElementById('compDetProveedor').textContent = '🏭 ' + compraActual.proveedor;

  var notasEl = document.getElementById('compDetNotas');
  if (compraActual.notas) {
    notasEl.textContent = '📝 ' + compraActual.notas;
    notasEl.style.display = '';
  } else {
    notasEl.style.display = 'none';
  }

  var itemsHtml = '';
  var items = compraActual.items || [];
  for (var i = 0; i < items.length; i++) {
    var it = items[i];
    var subtotal = (it.precio * (it.cantidad || 1));
    itemsHtml += '<div class="ped-item-row">' +
      '<span class="ped-item-name">' + esc(it.nombre) + ' ×' + (it.cantidad || 1) + '</span>' +
      '<span class="ped-item-price">$' + Number(subtotal).toLocaleString('es-AR') + '</span>' +
    '</div>';
  }
  document.getElementById('compDetItems').innerHTML = itemsHtml;
  document.getElementById('compDetTotal').textContent = '$' + Number(compraActual.total).toLocaleString('es-AR');

  // Estado buttons
  var btnsHtml = '';
  var keys = ['pendiente', 'confirmada', 'cancelada'];
  for (var k = 0; k < keys.length; k++) {
    var key = keys[k];
    var e = ESTADOS_COMPRA[key];
    var active = compraActual.estado === key;
    btnsHtml += '<button class="ped-estado-btn' + (active ? ' active' : '') + '" ' +
      'style="--est-color:' + e.color + '" ' +
      'onclick="cambiarEstadoCompra(\'' + key + '\')">' +
      e.emoji + ' ' + e.label + '</button>';
  }
  document.getElementById('compEstadoBtns').innerHTML = btnsHtml;

  document.getElementById('compDetModalBackdrop').classList.add('open');
}

function cerrarCompDetModal() {
  document.getElementById('compDetModalBackdrop').classList.remove('open');
  compraActual = null;
}

async function cambiarEstadoCompra(nuevoEstado) {
  if (!compraActual) return;
  try {
    var res = await fetch(COMP_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: compraActual.id, estado: nuevoEstado })
    });
    var data = await res.json();
    if (data.ok) {
      compraActual.estado = nuevoEstado;
      showToast('Estado actualizado a: ' + ESTADOS_COMPRA[nuevoEstado].label);
      abrirCompra(compraActual.id);
      cargarCompras();
    } else {
      showToast(data.error || 'Error al cambiar estado', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

async function eliminarCompra() {
  if (!compraActual) return;
  var num = compraActual.numero;
  document.getElementById('confirmMsg').textContent = '¿Eliminás la compra "' + num + '"? Esta acción no se puede deshacer.';
  confirmCallback = async function() {
    try {
      var res = await fetch(COMP_API + '?id=' + compraActual.id, { method: 'DELETE' });
      var data = await res.json();
      if (data.ok) {
        cerrarCompDetModal();
        showToast('Compra ' + num + ' eliminada');
        cargarCompras();
      } else {
        showToast(data.error || 'Error al eliminar', true);
      }
    } catch (e) {
      showToast('Error de conexión', true);
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

/* ===== Configuración ===== */
async function cargarConfiguracion() {
  try {
    var res = await fetch(CFG_API);
    var data = await res.json();
    if (data.ok && data.data) {
      var min = data.data.pedido_minimo || '0';
      document.getElementById('cfgPedidoMinimo').value = min;
      document.getElementById('cfgPedidoMinimoHint').textContent =
        'Valor actual: $' + Number(min).toLocaleString('es-AR') + (min === '0' ? ' \u2014 Dej\u00e1 en 0 para no aplicar m\u00ednimo.' : '');

      // Centro de distribución
      var cLat = data.data.centro_dist_lat || '';
      var cLng = data.data.centro_dist_lng || '';
      centroDistLat = cLat ? parseFloat(cLat) : null;
      centroDistLng = cLng ? parseFloat(cLng) : null;
      actualizarCentroInfo();

      // Precio por km
      var precioKm = data.data.precio_km || '0';
      cfgPrecioKm = parseFloat(precioKm);
      document.getElementById('cfgPrecioKm').value = precioKm;
      document.getElementById('cfgPrecioKmHint').textContent =
        'Valor actual: $' + Number(precioKm).toLocaleString('es-AR', {minimumFractionDigits:0, maximumFractionDigits:2}) + ' / km' + (precioKm === '0' ? ' — Dejá en 0 para no cobrar envío por distancia.' : '');
    }
  } catch (e) {
    showToast('Error al cargar configuraci\u00f3n', true);
  }
}

async function guardarConfig() {
  var pedidoMinimo = document.getElementById('cfgPedidoMinimo').value || '0';
  var precioKm = document.getElementById('cfgPrecioKm').value || '0';
  var body = { pedido_minimo: pedidoMinimo, precio_km: precioKm };
  if (centroDistLat !== null && centroDistLng !== null) {
    body.centro_dist_lat = String(centroDistLat);
    body.centro_dist_lng = String(centroDistLng);
  }
  try {
    var res = await fetch(CFG_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    var data = await res.json();
    if (data.ok) {
      showToast('Configuraci\u00f3n guardada');
      document.getElementById('cfgPedidoMinimoHint').textContent =
        'Valor actual: $' + Number(pedidoMinimo).toLocaleString('es-AR') + (pedidoMinimo === '0' ? ' \u2014 Dej\u00e1 en 0 para no aplicar m\u00ednimo.' : '');
      var saved = document.getElementById('configSaved');
      saved.style.display = 'inline';
      setTimeout(function() { saved.style.display = 'none'; }, 2000);
    } else {
      showToast(data.error || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexi\u00f3n', true);
  }
}

/* ===== Distancia Haversine ===== */
function calcDistanciaKm(lat1, lng1, lat2, lng2) {
  var R = 6371;
  var dLat = (lat2 - lat1) * Math.PI / 180;
  var dLng = (lng2 - lng1) * Math.PI / 180;
  var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/* ===== Mapa centro de distribución ===== */
var centroDistLat = null;
var centroDistLng = null;
var cfgPrecioKm = 0;
var mapaSelector = null;
var mapaMarker = null;
var miniMapa = null;
var miniMarker = null;
var mapaContext = 'config'; // 'config' | 'cliente' | 'proveedor'
var cliMapLat = null;
var cliMapLng = null;

function actualizarCentroInfo() {
  var info = document.getElementById('cfgCentroInfo');
  var miniEl = document.getElementById('cfgMiniMapa');
  if (centroDistLat !== null && centroDistLng !== null) {
    info.innerHTML = '\ud83d\udccd Ubicaci\u00f3n: <strong>' + centroDistLat.toFixed(5) + ', ' + centroDistLng.toFixed(5) + '</strong>';
    miniEl.style.display = 'block';
    // Renderizar mini mapa
    setTimeout(function() {
      var center = { lat: centroDistLat, lng: centroDistLng };
      miniMapa = new google.maps.Map(miniEl, {
        center: center,
        zoom: 15,
        mapId: 'DEMO_MAP_ID',
        disableDefaultUI: true,
        gestureHandling: 'none',
        clickableIcons: false
      });
      miniMarker = new google.maps.marker.AdvancedMarkerElement({ position: center, map: miniMapa });
    }, 100);
  } else {
    info.textContent = 'Sin ubicaci\u00f3n configurada.';
    miniEl.style.display = 'none';
  }
}

function abrirMapaSelector(context) {
  mapaContext = context || 'config';
  document.getElementById('mapaBackdrop').classList.add('open');

  setTimeout(function() {
    var activeLat = mapaContext === 'direccion' ? dirEditMapLat
                  : mapaContext === 'cliente'   ? cliMapLat
                  : mapaContext === 'proveedor' ? provMapLat
                  : mapaContext === 'repartidor'? repMapLat
                  : centroDistLat;
    var activeLng = mapaContext === 'direccion' ? dirEditMapLng
                  : mapaContext === 'cliente'   ? cliMapLng
                  : mapaContext === 'proveedor' ? provMapLng
                  : mapaContext === 'repartidor'? repMapLng
                  : centroDistLng;
    var defaultLat = activeLat || -31.5375;
    var defaultLng = activeLng || -68.5364;
    var center = { lat: defaultLat, lng: defaultLng };

    var activeLat2 = mapaContext === 'direccion' ? dirEditMapLat
                   : mapaContext === 'cliente'   ? cliMapLat
                   : mapaContext === 'proveedor' ? provMapLat
                   : mapaContext === 'repartidor'? repMapLat
                   : centroDistLat;
    mapaSelector = new google.maps.Map(document.getElementById('mapaSelector'), {
      center: center,
      zoom: activeLat2 ? 16 : 12,
      mapId: 'DEMO_MAP_ID'
    });

    mapaMarker = new google.maps.marker.AdvancedMarkerElement({
      position: center,
      map: mapaSelector,
      gmpDraggable: true
    });

    mapaSelector.addListener('click', function(e) {
      mapaMarker.position = e.latLng;
    });
  }, 200);
}

function cerrarMapaSelector() {
  document.getElementById('mapaBackdrop').classList.remove('open');
  mapaSelector = null;
  mapaMarker = null;
}

function aceptarUbicacion() {
  if (!mapaMarker) return;
  // AdvancedMarkerElement expone .position (LatLng o LatLngLiteral). Normalizamos a números.
  var raw = mapaMarker.position;
  if (!raw) return;
  var lat = (typeof raw.lat === 'function') ? raw.lat() : raw.lat;
  var lng = (typeof raw.lng === 'function') ? raw.lng() : raw.lng;

  if (mapaContext === 'direccion') {
    dirEditMapLat = lat;
    dirEditMapLng = lng;
    document.getElementById('dirEditMapInfo').innerHTML =
      '📍 <strong>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</strong>';
    cerrarMapaSelector();
    showToast('Ubicación seleccionada');
  } else if (mapaContext === 'cliente') {
    cliMapLat = lat;
    cliMapLng = lng;
    document.getElementById('cliMapInfo').innerHTML =
      '📍 <strong>' + cliMapLat.toFixed(6) + ', ' + cliMapLng.toFixed(6) + '</strong>';
    cerrarMapaSelector();
    showToast('Ubicación del cliente seleccionada');
  } else if (mapaContext === 'proveedor') {
    provMapLat = lat;
    provMapLng = lng;
    document.getElementById('provMapInfo').innerHTML =
      '📍 <strong>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</strong>';
    cerrarMapaSelector();
    showToast('Ubicación del proveedor seleccionada');
  } else if (mapaContext === 'repartidor') {
    repMapLat = lat;
    repMapLng = lng;
    document.getElementById('repMapInfo').innerHTML =
      '📍 <strong>' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</strong>';
    cerrarMapaSelector();
    showToast('Ubicación del repartidor seleccionada');
  } else {
    centroDistLat = lat;
    centroDistLng = lng;
    cerrarMapaSelector();
    actualizarCentroInfo();
    showToast('Ubicaci\u00f3n seleccionada. Record\u00e1 guardar la configuraci\u00f3n.');
  }
}

/* ===== Modal Nuevo Mensaje ===== */
function abrirNuevoMensaje() {
  // Poblar selector de clientes
  const sel = document.getElementById('msgClienteSelect');
  sel.innerHTML = '<option value="">— Completar manualmente —</option>';
  (clientes || []).forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.dataset.nombre = c.nombre || '';
    opt.dataset.correo = c.correo || '';
    opt.dataset.celular = c.celular || '';
    opt.textContent = c.nombre + (c.celular ? ' · ' + c.celular : '');
    sel.appendChild(opt);
  });

  // Reset form
  document.querySelector('input[name="msgCanal"][value="email"]').checked = true;
  onMsgCanalChange('email');
  document.getElementById('msgClienteSelect').value = '';
  document.getElementById('msgDestinatario').value = '';
  document.getElementById('msgDestino').value = '';
  document.getElementById('msgAsunto').value = '';
  document.getElementById('msgCuerpo').value = '';
  document.getElementById('btnEnviarMensaje').disabled = false;
  document.getElementById('btnEnviarMensaje').textContent = 'Enviar mensaje';

  document.getElementById('msgModalBackdrop').classList.add('open');
}

function cerrarMsgModal() {
  document.getElementById('msgModalBackdrop').classList.remove('open');
}

function onMsgCanalChange(canal) {
  const asuntoGroup = document.getElementById('msgAsuntoGroup');
  const destinoLabel = document.getElementById('msgDestinoLabel');
  const destinoInput = document.getElementById('msgDestino');
  if (canal === 'email') {
    asuntoGroup.style.display = '';
    destinoLabel.textContent = 'Email del destinatario *';
    destinoInput.placeholder = 'email@ejemplo.com';
    destinoInput.type = 'email';
  } else {
    asuntoGroup.style.display = 'none';
    destinoLabel.textContent = 'Teléfono del destinatario *';
    destinoInput.placeholder = 'Ej: 5491112345678';
    destinoInput.type = 'tel';
  }
  // Re-fill from selected client
  const sel = document.getElementById('msgClienteSelect');
  if (sel.value) onMsgClienteChange(sel.value);
}

function onMsgClienteChange(clienteId) {
  if (!clienteId) return;
  const sel = document.getElementById('msgClienteSelect');
  const opt = sel.querySelector('option[value="' + clienteId + '"]');
  if (!opt) return;
  const canal = document.querySelector('input[name="msgCanal"]:checked').value;
  document.getElementById('msgDestinatario').value = opt.dataset.nombre || '';
  document.getElementById('msgDestino').value = canal === 'email'
    ? (opt.dataset.correo || '')
    : (opt.dataset.celular || '');
}

async function enviarMensaje() {
  const canal       = document.querySelector('input[name="msgCanal"]:checked').value;
  const destinatario = document.getElementById('msgDestinatario').value.trim();
  const destino     = document.getElementById('msgDestino').value.trim();
  const asunto      = document.getElementById('msgAsunto').value.trim();
  const cuerpo      = document.getElementById('msgCuerpo').value.trim();

  if (!destinatario || !destino || !cuerpo) {
    showToast('Completá los campos obligatorios');
    return;
  }
  if (canal === 'email' && !asunto) {
    showToast('El asunto es obligatorio para correo');
    return;
  }

  const btn = document.getElementById('btnEnviarMensaje');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    const res  = await fetch('api/enviar_mensaje', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ canal, destinatario, destino, asunto, cuerpo }),
    });
    const data = await res.json();
    if (data.ok) {
      cerrarMsgModal();
      showToast('Mensaje enviado correctamente');
      cargarMensajes();
    } else {
      showToast('Error: ' + (data.error || 'No se pudo enviar'));
      btn.disabled = false;
      btn.textContent = 'Enviar mensaje';
    }
  } catch {
    showToast('Sin conexión al servidor');
    btn.disabled = false;
    btn.textContent = 'Enviar mensaje';
  }
}

/* ===== Inventarios ===== */
const INV_API = 'api/inventarios';
let invBusqueda = '';

async function cargarInventarios() {
  const tbody = document.getElementById('invTbody');
  tbody.innerHTML = '<tr class="spinner-row"><td colspan="8"><div class="spin"></div></td></tr>';
  try {
    const qs = invBusqueda ? '?q=' + encodeURIComponent(invBusqueda) : '';
    const data = await fetch(INV_API + qs).then(r => r.json());
    if (!data.ok) throw new Error(data.error);

    const s = data.stats || {};
    document.getElementById('invStatTotal').textContent = s.total ?? '—';
    const ul = s.ultimo ? new Date(s.ultimo).toLocaleDateString('es-AR') : '—';
    document.getElementById('invStatUltimo').textContent = ul;

    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">Sin inventarios registrados</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => `
      <tr style="cursor:pointer" onclick="abrirDetalleInventario(${r.id})">
        <td>${r.id}</td>
        <td><strong>${esc(r.numero)}</strong></td>
        <td style="color:var(--muted)">${esc(r.notas || '—')}</td>
        <td style="text-align:center">${r.productos}</td>
        <td><span class="badge ${r.estado === 'cerrado' ? 'badge-green' : 'badge-warn'}">${r.estado}</span></td>
        <td style="color:var(--muted)">${esc(r.usuario_nombre || '—')}</td>
        <td>${new Date(r.created_at).toLocaleString('es-AR')}</td>
        <td onclick="event.stopPropagation()">
          <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="eliminarInventario(${r.id}, '${esc(r.numero)}')">🗑</button>
        </td>
      </tr>`).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:24px">${esc(e.message)}</td></tr>`;
  }
}

function onSearchInv(val) {
  invBusqueda = val.trim();
  cargarInventarios();
}

async function abrirNuevoInventario() {
  document.getElementById('invNuevoNotas').value = '';
  const tbody = document.getElementById('invNuevoItems');
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr>';
  document.getElementById('invNuevoBackdrop').classList.add('open');
  document.getElementById('invGuardarBtn').disabled = false;
  document.getElementById('invGuardarBtn').textContent = 'Guardar inventario';

  try {
    const data = await fetch('api/productos').then(r => r.json());
    const prods = (data.data || []).sort((a, b) => a.nombre.localeCompare(b.nombre));
    if (!prods.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">Sin productos registrados</td></tr>';
      return;
    }
    tbody.innerHTML = prods.map(p => `
      <tr>
        <td>${esc(p.nombre)}</td>
        <td style="text-align:center;color:var(--muted)">${p.stock_actual ?? 0}</td>
        <td style="text-align:center">
          <input type="number" min="0" step="1" value="${p.stock_actual ?? 0}"
            class="form-input inv-qty-input" style="width:80px;text-align:center;padding:4px 8px"
            data-id="${p.id}" data-nombre="${esc(p.nombre)}" data-anterior="${p.stock_actual ?? 0}"
            oninput="invActualizarDif(this)">
        </td>
        <td style="text-align:center" id="invDif_${p.id}">0</td>
      </tr>`).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--danger);padding:16px">${esc(e.message)}</td></tr>`;
  }
}

function invActualizarDif(input) {
  const anterior = parseInt(input.dataset.anterior) || 0;
  const contado  = parseInt(input.value) || 0;
  const dif      = contado - anterior;
  const cell     = document.getElementById('invDif_' + input.dataset.id);
  if (!cell) return;
  cell.textContent = (dif > 0 ? '+' : '') + dif;
  cell.style.color = dif > 0 ? 'var(--success)' : dif < 0 ? 'var(--danger)' : '';
}

function cerrarNuevoInventario() {
  document.getElementById('invNuevoBackdrop').classList.remove('open');
}

async function guardarInventario() {
  const btn = document.getElementById('invGuardarBtn');
  const notas = document.getElementById('invNuevoNotas').value.trim();
  const inputs = document.querySelectorAll('.inv-qty-input');
  if (!inputs.length) { showToast('Carga los productos primero'); return; }

  const items = Array.from(inputs).map(inp => ({
    producto_id:     parseInt(inp.dataset.id),
    nombre:          inp.dataset.nombre,
    stock_anterior:  parseInt(inp.dataset.anterior) || 0,
    cantidad_contada: parseInt(inp.value) || 0,
  }));

  btn.disabled = true;
  btn.textContent = 'Guardando…';
  try {
    const data = await fetch(INV_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ notas, items }),
    }).then(r => r.json());

    if (!data.ok) throw new Error(data.error);
    cerrarNuevoInventario();
    showToast('Inventario ' + data.numero + ' guardado');
    cargarInventarios();
  } catch (e) {
    showToast('Error: ' + e.message);
    btn.disabled = false;
    btn.textContent = 'Guardar inventario';
  }
}

async function abrirDetalleInventario(id) {
  document.getElementById('invDetalleNumero').textContent = 'Cargando…';
  document.getElementById('invDetalleInfo').innerHTML = '';
  document.getElementById('invDetalleItems').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr>';
  document.getElementById('invDetalleBackdrop').classList.add('open');

  try {
    const data = await fetch(INV_API + '?id=' + id).then(r => r.json());
    if (!data.ok) throw new Error(data.error);
    const inv = data.data;
    document.getElementById('invDetalleNumero').textContent = inv.numero;
    document.getElementById('invDetalleInfo').innerHTML = `
      <span class="badge ${inv.estado === 'cerrado' ? 'badge-green' : 'badge-warn'}">${inv.estado}</span>
      <span style="color:var(--muted);font-size:.85rem">${new Date(inv.created_at).toLocaleString('es-AR')}</span>
      ${inv.usuario_nombre ? `<span style="color:var(--muted);font-size:.85rem">👤 ${esc(inv.usuario_nombre)}</span>` : ''}
      ${inv.notas ? `<span style="color:var(--text);font-size:.85rem">📝 ${esc(inv.notas)}</span>` : ''}
      <span style="color:var(--muted);font-size:.85rem">${inv.productos} productos</span>`;

    const items = inv.items || [];
    document.getElementById('invDetalleItems').innerHTML = items.length ? items.map(it => {
      const dif = it.diferencia;
      const difColor = dif > 0 ? 'var(--success)' : dif < 0 ? 'var(--danger)' : '';
      return `<tr>
        <td>${esc(it.nombre)}</td>
        <td style="text-align:center;color:var(--muted)">${it.stock_anterior}</td>
        <td style="text-align:center">${it.cantidad_contada}</td>
        <td style="text-align:center;font-weight:600;color:${difColor}">${dif > 0 ? '+' : ''}${dif}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">Sin ítems</td></tr>';
  } catch (e) {
    document.getElementById('invDetalleNumero').textContent = 'Error';
    document.getElementById('invDetalleItems').innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--danger);padding:16px">${esc(e.message)}</td></tr>`;
  }
}

function cerrarDetalleInventario() {
  document.getElementById('invDetalleBackdrop').classList.remove('open');
}

async function eliminarInventario(id, numero) {
  if (!confirm('¿Eliminar inventario ' + numero + '? Esta acción no se puede deshacer.')) return;
  try {
    const data = await fetch(INV_API + '?id=' + id, { method: 'DELETE' }).then(r => r.json());
    if (!data.ok) throw new Error(data.error);
    showToast('Inventario eliminado');
    cargarInventarios();
  } catch (e) {
    showToast('Error: ' + e.message);
  }
}

let pendingDetail = null; // { seccion, id }

function irSeccion(seccion) {
  const navEl = document.querySelector('[data-section="' + seccion + '"]');
  cambiarSeccion(seccion, navEl);
}

function irDetalle(seccion, id) {
  pendingDetail = { seccion, id };
  irSeccion(seccion);
}

function resolverPendingDetail(seccion) {
  if (!pendingDetail || pendingDetail.seccion !== seccion) return;
  const id = pendingDetail.id;
  pendingDetail = null;
  if      (seccion === 'productos')   abrirDetalleProducto(id);
  else if (seccion === 'pedidos')     abrirPedido(id);
  else if (seccion === 'clientes')    abrirDetalleCliente(id);
  else if (seccion === 'mensajes')    abrirDetalleMensaje(id);
}

/* ===== Dashboard ===== */
async function cargarDashboard() {
  const fmt = n => new Intl.NumberFormat('es-AR').format(n);
  const money = n => '$' + fmt(n);

  try {
    const [rProd, rCli, rPed, rComp, rMsg] = await Promise.all([
      fetch('api/productos').then(r => r.json()),
      fetch('api/clientes').then(r => r.json()),
      fetch('api/pedidos').then(r => r.json()),
      fetch('api/compras').then(r => r.json()),
      fetch('api/mensajes').then(r => r.json()),
    ]);

    // Stats cards
    if (rProd.ok)  document.getElementById('dashProd').textContent     = rProd.stats?.total ?? rProd.data?.length ?? '—';
    if (rCli.ok) {
      document.getElementById('dashCli').textContent         = rCli.stats?.total         ?? '—';
      document.getElementById('dashUsrOnline').textContent   = rCli.stats?.en_linea      ?? '—';
      document.getElementById('dashUsrActivos').textContent  = rCli.stats?.activos_hoy   ?? '—';
      document.getElementById('dashUsrNuevos').textContent   = rCli.stats?.nuevos_semana ?? '—';
    }
    if (rPed.ok) {
      document.getElementById('dashPedTotal').textContent = rPed.stats?.total ?? '—';
      document.getElementById('dashPedHoy').textContent   = rPed.stats?.hoy ?? '—';
      document.getElementById('dashVentas').textContent   = rPed.stats?.monto_total ? money(rPed.stats.monto_total) : '—';
    }
    if (rComp.ok)  document.getElementById('dashCompras').textContent  = rComp.stats?.monto_total ? money(rComp.stats.monto_total) : (rComp.stats?.total ?? '—');
    if (rMsg.ok)   document.getElementById('dashMensajes').textContent = rMsg.stats?.total ?? '—';
    if (rCli.ok)   document.getElementById('dashProv').textContent     = '—';

    // Proveedores
    fetch('api/proveedores').then(r => r.json()).then(d => {
      if (d.ok) document.getElementById('dashProv').textContent = d.stats?.total ?? d.data?.length ?? '—';
    });

    const rowStyle = 'style="cursor:pointer" class="dash-link"';

    // Últimos pedidos
    const pedBody = document.getElementById('dashPedidosBody');
    const peds = (rPed.data || []).slice(0, 8);
    pedBody.innerHTML = peds.length ? peds.map(p => {
      const estadoClass = p.estado === 'entregado' ? 'badge-green' : p.estado === 'cancelado' ? 'badge-red' : 'badge-warn';
      return `<tr ${rowStyle} onclick="irDetalle('pedidos',${p.id})"><td><strong>#${p.numero}</strong></td><td>${esc(p.cliente_nombre || '—')}</td><td>${money(p.total)}</td><td><span class="badge ${estadoClass}">${p.estado}</span></td></tr>`;
    }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">Sin pedidos</td></tr>';

    // Últimos clientes
    const cliBody = document.getElementById('dashClientesBody');
    const clis = (rCli.data || []).slice(0, 8);
    cliBody.innerHTML = clis.length ? clis.map(c =>
      `<tr ${rowStyle} onclick="irDetalle('clientes',${c.id})"><td><strong>${esc(c.nombre)}</strong></td><td>${esc(c.celular || '—')}</td><td>${c.total_pedidos}</td></tr>`
    ).join('') : '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:16px">Sin clientes</td></tr>';

    // Últimos mensajes
    const msgBody = document.getElementById('dashMensajesBody');
    const msgs = (rMsg.data || []).slice(0, 8);
    msgBody.innerHTML = msgs.length ? msgs.map(m => {
      const canal = m.canal === 'email' ? '📧' : '💬';
      const estadoClass = m.estado === 'enviado' ? 'badge-green' : 'badge-warn';
      return `<tr ${rowStyle} onclick="irDetalle('mensajes',${m.id})"><td>${canal} ${m.canal}</td><td>${esc(m.destino || m.destinatario)}</td><td><span class="badge ${estadoClass}">${m.estado}</span></td></tr>`;
    }).join('') : '<tr><td colspan="3" style="text-align:center;color:var(--muted);padding:16px">Sin mensajes</td></tr>';

    // STOP crítico: productos con stock_actual < stock_minimo
    const stBody = document.getElementById('dashStockBody');
    const criticos = (rProd.data || []).filter(p => p.stock_minimo > 0 && p.stock_actual < p.stock_minimo);
    stBody.innerHTML = criticos.length ? criticos.map(p => {
      const aComprar = Math.max(0, (p.stock_recomendado ?? 0) - p.stock_actual);
      const prov = p.proveedor_id ? (PROVEEDORES.find(v => v.id === p.proveedor_id)?.nombre ?? '—') : '—';
      return `<tr ${rowStyle} onclick="irDetalle('productos',${p.id})"><td>${esc(p.nombre)}</td><td><span class="badge ${p.stock_actual === 0 ? 'badge-red' : 'badge-warn'}">${p.stock_actual}</span></td><td><strong>${aComprar}</strong></td><td>${esc(prov)}</td></tr>`;
    }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--muted);padding:16px">Sin stock crítico</td></tr>';

  } catch(e) {
    console.error('Dashboard error:', e);
  }
}

/* ===== Fecha/hora sistema ===== */
async function cargarFechasSistema() {
  try {
    var res  = await fetch('api/sistema');
    var data = await res.json();
    if (data.ok) {
      document.getElementById('cfgFechaServidor').textContent = data.servidor;
      document.getElementById('cfgFechaBD').textContent       = data.base_datos;
    }
  } catch (e) {
    document.getElementById('cfgFechaServidor').textContent = 'Error';
    document.getElementById('cfgFechaBD').textContent       = 'Error';
  }
}

/* ===== Carritos ===== */
let carritosList        = [];
let filtroEstadoCarrito = 'todos';
let filtroBusquedaCarrito = '';
let cartSearchTimer     = null;
let cartDetalleActual   = null;

async function cargarCarritos() {
  document.getElementById('carritosTbody').innerHTML =
    `<tr class="spinner-row"><td colspan="8"><div class="spin"></div></td></tr>`;
  try {
    const qs  = new URLSearchParams({ estado: filtroEstadoCarrito, q: filtroBusquedaCarrito }).toString();
    const res = await fetch(`${CART_API}?${qs}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    carritosList = data.data || [];
    renderCarritos();
    renderStatsCarritos(data.stats);
  } catch (e) {
    document.getElementById('carritosTbody').innerHTML =
      `<tr><td colspan="8" class="table-empty">Error al cargar carritos</td></tr>`;
  }
}

function renderStatsCarritos(stats) {
  if (!stats) return;
  document.getElementById('cartStatTotal').textContent      = stats.total      ?? '—';
  document.getElementById('cartStatActivos').textContent    = stats.activos    ?? '—';
  document.getElementById('cartStatAbandonados').textContent = stats.abandonados ?? '—';
  document.getElementById('cartStatExitosos').textContent   = stats.exitosos   ?? '—';
}

function renderCarritos() {
  const tbody = document.getElementById('carritosTbody');
  if (!carritosList.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="table-empty">Sin carritos para los filtros aplicados</td></tr>`;
    return;
  }
  tbody.innerHTML = carritosList.map(c => {
    const cliente     = c.cliente_nombre ? esc(c.cliente_nombre) : '<span style="color:var(--muted)">Sin cuenta</span>';
    const estadoBadge = cartEstadoBadge(c.estado);
    const fecha       = new Date(c.updated_at).toLocaleString('es-AR', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' });
    const inactivo    = cartTiempoInactivo(Number(c.minutos_inactivo));
    const total       = '$' + Number(c.total).toLocaleString('es-AR', { minimumFractionDigits: 2 });
    const showReminder = c.estado === 'abandonado' || c.estado === 'activo';
    const sesionBadge = cartSesionBadge(c.session_id);
    return `<tr>
      <td class="td-id">#${c.id}</td>
      <td>${cliente}</td>
      <td style="text-align:center">${sesionBadge}</td>
      <td style="text-align:center">${c.items_count}</td>
      <td style="text-align:center">${c.unidades_total}</td>
      <td>${total}</td>
      <td>${estadoBadge}</td>
      <td style="font-size:.82rem;color:var(--muted)">${fecha}</td>
      <td style="font-size:.82rem;color:var(--muted)">${inactivo}</td>
      <td>
        <div class="actions">
          <button class="btn-icon-sm" title="Ver detalle" onclick="abrirDetalleCarrito(${c.id})">🔍</button>
          <button class="btn-icon-sm" title="Eliminar carrito" onclick="confirmarEliminarCarrito(${c.id})">🗑️</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function cartSesionBadge(sessionId) {
  if (!sessionId) return '<span style="color:var(--muted);font-size:.8rem">—</span>';
  const short = sessionId.slice(0, 8).toUpperCase();
  return `<span class="badge" style="background:var(--surface2);color:var(--muted);font-family:monospace;font-size:.75rem" title="${esc(sessionId)}">${short}</span>`;
}

function cartEstadoBadge(estado) {
  const map = {
    activo:     '<span class="badge" style="background:rgba(59,130,246,.15);color:#3b82f6">🟢 Activo</span>',
    abandonado: '<span class="badge badge-red">🔴 Abandonado</span>',
    exitoso:    '<span class="badge badge-stock">✅ Exitoso</span>',
  };
  return map[estado] || `<span class="badge">${esc(estado)}</span>`;
}

function cartTiempoInactivo(minutos) {
  if (!minutos && minutos !== 0) return '—';
  if (minutos < 60)   return minutos + ' min';
  if (minutos < 1440) return Math.floor(minutos / 60) + ' h';
  return Math.floor(minutos / 1440) + ' días';
}

async function abrirDetalleCarrito(id, autoRecordatorio = false) {
  document.getElementById('cartDetalleBackdrop').classList.add('open');
  document.getElementById('cartDetalleTitle').textContent  = `Carrito #${id}`;
  document.getElementById('cartDetalleFecha').textContent  = '';
  document.getElementById('cartDetalleCliente').innerHTML  = '<div class="spin"></div>';
  document.getElementById('cartDetalleItems').innerHTML    = '<tr><td colspan="4" style="text-align:center;padding:20px"><div class="spin"></div></td></tr>';
  document.getElementById('cartDetalleTotal').textContent  = '';

  try {
    const res  = await fetch(`${CART_API}?id=${id}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    const c = data.data;
    cartDetalleActual = c;

    document.getElementById('cartDetalleTitle').textContent = `Carrito #${c.id}`;
    document.getElementById('cartDetalleFecha').innerHTML =
      'Creado: ' + new Date(c.created_at).toLocaleString('es-AR') +
      (c.session_id ? `&nbsp;&nbsp;${cartSesionBadge(c.session_id)}` : '');

    const cli = c.cliente_nombre
      ? `<span class="badge badge-cat">${esc(c.cliente_nombre)}</span>
         ${c.cliente_correo ? `<span class="badge" style="background:var(--surface2)">${esc(c.cliente_correo)}</span>` : ''}
         ${c.cliente_celular ? `<span class="badge" style="background:var(--surface2)">${esc(c.cliente_celular)}</span>` : ''}`
      : `<span style="color:var(--muted);font-size:.9rem">Cliente no registrado</span>`;
    document.getElementById('cartDetalleCliente').innerHTML = cli;

    document.getElementById('cartDetalleEstadoBadge').innerHTML = cartEstadoBadge(c.estado);
    const sel = document.getElementById('cartDetalleEstadoSel');
    sel.value = c.estado;

    const items = c.items || [];
    if (!items.length) {
      document.getElementById('cartDetalleItems').innerHTML =
        '<tr><td colspan="4" class="table-empty">Sin productos</td></tr>';
    } else {
      document.getElementById('cartDetalleItems').innerHTML = items.map(it => {
        const sub = Number(it.precio) * Number(it.cantidad);
        return `<tr>
          <td>${esc(it.nombre)}</td>
          <td style="text-align:center">$${Number(it.precio).toLocaleString('es-AR', {minimumFractionDigits:2})}</td>
          <td style="text-align:center">${it.cantidad}</td>
          <td style="text-align:right">$${sub.toLocaleString('es-AR', {minimumFractionDigits:2})}</td>
        </tr>`;
      }).join('');
    }

    document.getElementById('cartDetalleTotal').textContent =
      'Total: $' + Number(c.total).toLocaleString('es-AR', { minimumFractionDigits: 2 });

    const btnRec = document.getElementById('cartRecordatorioBtn');
    const canRemind = (c.estado === 'activo' || c.estado === 'abandonado') &&
                      (c.cliente_correo || c.cliente_celular);
    btnRec.style.display = canRemind ? '' : 'none';

    if (autoRecordatorio && canRemind) enviarRecordatorioCarrito();

  } catch (e) {
    document.getElementById('cartDetalleCliente').innerHTML =
      `<span style="color:var(--danger)">Error al cargar: ${esc(e.message)}</span>`;
  }
}

function cerrarDetalleCarrito() {
  document.getElementById('cartDetalleBackdrop').classList.remove('open');
  cartDetalleActual = null;
}

async function cambiarEstadoCarrito() {
  if (!cartDetalleActual) return;
  const estado = document.getElementById('cartDetalleEstadoSel').value;
  try {
    const res  = await fetch(CART_API, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: cartDetalleActual.id, estado }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    cartDetalleActual.estado = estado;
    document.getElementById('cartDetalleEstadoBadge').innerHTML = cartEstadoBadge(estado);
    showToast('Estado actualizado');
    cargarCarritos();
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  }
}

async function enviarRecordatorioCarrito() {
  if (!cartDetalleActual) return;
  const c = cartDetalleActual;
  const canal = c.cliente_celular ? 'whatsapp' : 'email';
  const destino = canal === 'whatsapp' ? c.cliente_celular : c.cliente_correo;
  const nombre  = c.cliente_nombre || 'cliente';
  const cuerpo  = `Hola ${nombre}! 👋 Tenés productos esperándote en tu carrito. ¡No te olvides de completar tu compra!\n\n👉 https://app.repo.com.ar/carrito`;
  const btn = document.getElementById('cartRecordatorioBtn');
  btn.textContent = 'Enviando...';
  btn.disabled    = true;
  try {
    const res  = await fetch('api/enviar_mensaje', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ canal, destinatario: nombre, destino, asunto: 'Tu carrito te espera', cuerpo }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error al enviar');
    showToast('Recordatorio enviado por ' + canal);
  } catch (e) {
    showToast('Error al enviar: ' + e.message, 'error');
  } finally {
    btn.textContent = '📩 Enviar recordatorio';
    btn.disabled    = false;
  }
}

function confirmarEliminarCarrito(id) {
  if (!id) return;
  document.getElementById('confirmMsg').textContent = `¿Eliminás el carrito #${id}? Esta acción no se puede deshacer.`;
  confirmCallback = async () => {
    const res = await fetch(`${CART_API}?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (data.ok) {
      cerrarDetalleCarrito();
      showToast('Carrito eliminado');
      cargarCarritos();
    } else {
      showToast('Error al eliminar', 'error');
    }
  };
  document.getElementById('confirmBackdrop').classList.add('open');
}

function onSearchCarrito(val) {
  clearTimeout(cartSearchTimer);
  cartSearchTimer = setTimeout(() => {
    filtroBusquedaCarrito = val.trim();
    cargarCarritos();
  }, 300);
}

function onFiltroEstadoCarrito(val) {
  filtroEstadoCarrito = val;
  cargarCarritos();
}

/* ===== Init ===== */
document.addEventListener('DOMContentLoaded', async () => {
  adminTema.init();
  await Promise.all([cargarCategorias(), cargarProveedoresLista()]);
  poblarSelects();
  cargarDashboard();
  initDragDrop();
  cargarConfiguracion();

  // cerrar modal con Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { cerrarModal(); catModal.cerrar(); cerrarPedModal(); cerrarMapaSelector(); cerrarConfirm(false); cerrarMsgModal(); cerrarDetalleMensaje(); cerrarDetalleCliente(); cerrarDetalleProducto(); cerrarDetalleProveedor(); cerrarDetalleEvento(); cerrarModalUsuario(); cerrarDetalleCarrito(); }
  });
});
