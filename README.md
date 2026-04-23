# repo-admin — Panel de administración

Dashboard web para el equipo del negocio. Gestión de pedidos, inventario, proveedores, clientes, repartidores, mensajería y configuración del sistema.

---

## Tecnologías

- PHP (APIs REST, sin framework)
- JavaScript vanilla
- MySQL vía PDO (conexión compartida con `repo-api`)
- Google Maps API (selector de dirección con lat/lng)
- datarocket / Evolution (WhatsApp y email)

---

## Estructura

```
repo-admin/
├── index.php              # SPA principal (dashboard con sidebar y modales)
├── login.php              # Autenticación del administrador
├── setup.php              # Configuración inicial del sistema
├── ticket.php             # Generación de ticket de pedido (server-side)
├── api/
│   ├── auth.php           # Login email + contraseña → JWT
│   ├── pedidos.php        # CRUD pedidos, cambio de estado, distancia
│   ├── productos.php      # CRUD productos + carga de imágenes
│   ├── categorias.php     # CRUD categorías
│   ├── clientes.php       # CRUD clientes
│   ├── repartidores.php   # CRUD repartidores
│   ├── proveedores.php    # CRUD proveedores
│   ├── compras.php        # Órdenes de compra a proveedores
│   ├── inventarios.php    # Control de stock y alertas
│   ├── usuarios.php       # Gestión de usuarios admin
│   └── enviar_mensaje.php # Enviar WhatsApp/email
├── lib/
│   ├── auth_check.php     # Middleware JWT (requireAuth, authUser)
│   └── jwt.php            # Encode/decode JWT HS256
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

---

## Autenticación

Email + contraseña. El JWT se guarda en la cookie `repo_token` (TTL: 7 días).

El token incluye: `id`, `nombre`, `correo`, `rol: admin`.

`requireAuth()` en cada endpoint redirige a `login.php` si el token es inválido o está ausente.

---

## Estados de pedido

```
pendiente → preparacion → asignacion → reparto → entregado
                                               ↘ cancelado
```

El cambio de `pendiente` a `preparacion` dispara el cálculo de distancia y tiempo estimado vía Google Distance Matrix.

---

## Funcionalidades principales

- **Pedidos**: listado en tiempo real, filtro por estado, cambio de estado, eliminación
- **Ticket de impresión**: `ticket.php?id=X` genera el ticket desde la base de datos
- **Inventario**: stock mínimo y recomendado con alertas de reposición
- **Compras**: órdenes de compra a proveedores con ítems y totales
- **Mensajería**: historial WhatsApp/email enviados, composición de nuevos mensajes
- **Repartidores**: alta, baja y modificación de cuentas
- **Configuración**: centro de distribución (lat/lng), parámetros del sistema
- **Google Maps**: selector de dirección con pin arrastreable en formularios

---

## Ticket de pedido

`ticket.php` genera el ticket server-side (PHP puro). Se abre en ventana nueva y dispara `window.print()` automáticamente. Formato:

```
--------------------------------
Cliente: Nombre
Domicilio: Dirección
Celular: XXXXXXXXXX
Correo: correo@ejemplo.com
--------------------------------
Repartidor: Nombre
Celular: XXXXXXXXXX
--------------------------------
[tabla de productos]
TOTAL  $XX.XXX
```

---

## Dependencias externas

| Servicio | Uso |
|---|---|
| `repo-api/config/db.php` | Conexión a la base de datos compartida |
| Google Maps API | Selector de dirección y cálculo de distancia |
| datarocket / Evolution | WhatsApp y email |
