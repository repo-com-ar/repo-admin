# Lider Admin

Panel de administración web para el sistema **Lider Online**. Permite gestionar todos los aspectos del negocio desde una interfaz centralizada con soporte mobile.

## Acceso

```
/lider-admin/index.php
```

No requiere build. Es HTML + PHP + JS vanilla.

---

## Módulos

| Sección | Descripción |
|---|---|
| **Inicio** | Dashboard con estadísticas globales y accesos directos a cada módulo |
| **Productos** | ABM de productos con imagen, precio, stock, categorías y drag & drop para reordenar |
| **Categorías** | ABM de categorías con emoji e imagen |
| **Pedidos** | Listado de pedidos con cambio de estado, detalle de ítems y mapa de entrega |
| **Clientes** | Listado de clientes con historial de pedidos, edición y ubicación GPS |
| **Compras** | Registro de compras a proveedores con ítems y estados |
| **Proveedores** | ABM de proveedores con domicilio y ubicación GPS |
| **Mensajes** | Historial de mensajes enviados por WhatsApp y correo. Permite redactar y enviar nuevos mensajes |
| **Eventos** | Log de actividad de usuarios en la app |
| **Configuración** | Parámetros del sistema: datos del negocio, integraciones, datarocket, Google Maps |

---

## API endpoints

Todos en `/lider-admin/api/`:

| Archivo | Métodos | Descripción |
|---|---|---|
| `productos.php` | GET, POST, PUT, DELETE | CRUD productos + upload de imagen |
| `categorias.php` | GET, POST, PUT, DELETE | CRUD categorías |
| `pedidos.php` | GET, PUT, DELETE | Listado y actualización de estado de pedidos |
| `clientes.php` | GET, PUT, DELETE | CRUD clientes |
| `compras.php` | GET, POST, PUT, DELETE | CRUD compras |
| `proveedores.php` | GET, POST, PUT, DELETE | CRUD proveedores |
| `mensajes.php` | GET | Listado de mensajes enviados |
| `enviar_mensaje.php` | POST | Envía un mensaje vía datarocket (WhatsApp o email) |
| `eventos.php` | GET | Listado de eventos de actividad |
| `configuracion.php` | GET, POST | Lectura y escritura de parámetros |
| `upload.php` | POST | Subida de imágenes de productos |

---

## Estructura de archivos

```
lider-admin/
├── index.php               # SPA principal (HTML + sidebar + todos los modales)
├── api/                    # Endpoints REST PHP
├── assets/
│   ├── css/admin.css       # Estilos del panel
│   └── js/admin.js         # Lógica completa del panel (vanilla JS)
└── data/                   # Archivos de datos locales
```

---

## Base de datos

Comparte la base de datos `lider` con lider-app. Conexión definida en `/config/db.php`.

Tablas principales: `productos`, `categorias`, `pedidos`, `pedido_items`, `clientes`, `compras`, `compra_items`, `proveedores`, `mensajes`, `eventos`, `configuracion`.

Para crear o migrar el esquema completo ejecutar:

```
/setup/install.php
```

---

## Integraciones

- **datarocket** — proxy de mensajería para WhatsApp (Evolution) y email (AWS SES)
- **Google Maps** — selector de ubicación GPS para clientes, proveedores y pedidos
- **Upload de imágenes** — almacenamiento local en `/data/` o ruta configurada

---

## Notas de desarrollo

- No usa frameworks JS ni bundlers. Todo es vanilla JS en un único `admin.js`.
- Los modales siguen el patrón CSS: `classList.add('open')` / `classList.remove('open')`.
- El sidebar en mobile se abre con el botón ☰ del topbar y se cierra tocando el overlay.
- Las tablas usan `.table-card` como contenedor para scroll horizontal en mobile.
