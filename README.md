# BioBoyacá

> **Del campo boyacense a tu mesa en Bogotá.**

Plataforma web que conecta a **productores** campesinos de Boyacá con
**consumidores** de Bogotá. Construida en **PHP puro** (patrón MVC, sin
framework) con una **capa de base de datos intercambiable**: hoy funciona con
persistencia local en **archivos JSON** y está lista para conmutar a
**PostgreSQL** o a un **cluster de MongoDB** sin tocar la lógica de la aplicación.

> El nombre y el lema viven en un único lugar: `config/config.php` → `app.name` y
> `app.tagline` (o la variable de entorno `APP_NAME`). **No se hardcodean** en
> vistas ni controladores: se usa siempre `$appName` / `$tagline`.

## Características

- 🏠 **Inicio** con productos destacados.
- 🔐 **Registro e inicio de sesión** por rol (consumidor / productor).
- 🧺 **Catálogo** con búsqueda y filtro por categoría en chips.
- 📦 **Detalle de producto** con unidad de venta y municipio de origen.
- 🛒 **Carrito de compras** en sesión: se puede llenar sin iniciar sesión y
  admite varios productos por pedido.
- 💳 **Checkout** con dirección de entrega (localidad de Bogotá) y desglose
  económico: subtotal + logística Boyacá-Bogotá ($6.000 por pedido) = total.
- 🌱 **Perfil del productor**: gestión de productos (CRUD) y de pedidos recibidos.
- 💵 **Billetera del productor**: ingresos del mes, gráfico de ventas por semana y
  retiro de fondos (**simulado**, sin pasarela de pago).
- 👤 **Perfil del consumidor**: historial de pedidos.
- 🛠️ **Panel de administración** (básico): métricas y listados.
- 🌙 **Tema claro/oscuro** e interfaz mobile-first con navegación inferior en móvil.

## Requisitos

- **PHP >= 8.1** con `ext-json`. Nada más: no hace falta base de datos, ni
  Composer, ni la extensión GD. En Windows sirve un paquete como XAMPP o
  Laragon; asegúrate de que `php` esté en el `PATH`.

## Puesta en marcha

Un clon funciona **sin pasos adicionales**, aunque `.gitignore` excluya la base
de datos local y las imágenes subidas: las carpetas se conservan con `.gitkeep` y
los archivos `.json` se crean solos en la primera escritura. Tampoco necesitas
crear un `.env` (todos los valores tienen defecto en `config/config.php`).

```bash
git clone https://github.com/henrygomez-10/bio-boyaca.git
cd BioBoyaca

# 1. Cargar datos de demostración (opcional pero recomendado)
php scripts/seed.php

# 2. Levantar el servidor de desarrollo.
#    El último argumento es OBLIGATORIO: actúa como router para que las rutas
#    con id (/producto/{id}) funcionen.
php -S localhost:8000 -t public public/index.php

# 3. Abrir http://localhost:8000
```

> ⚠️ **No ejecutes `seed.php` con el servidor levantado.** El driver JSON no es
> concurrente y los datos se corrompen en silencio. Para el servidor, siembra y
> vuelve a levantarlo.

### Cuentas de prueba (tras el seed)

| Rol        | Email                | Contraseña   |
|------------|----------------------|--------------|
| Admin      | admin@demo.test      | admin123     |
| Productor  | productor@demo.test  | producer123  |
| Consumidor | consumidor@demo.test | consumer123  |

## Estructura del proyecto

```
proyecto/
├── public/                 # Document root (único punto expuesto)
│   ├── index.php           # Front controller
│   └── assets/             # CSS y JS
├── src/
│   ├── Core/               # Router, Controller, View, Auth, Container, helpers
│   │   └── Database/       # Interfaz + drivers (JSON activo, Postgres/Mongo stub)
│   ├── Controllers/        # Un controlador por módulo
│   ├── Models/             # Constantes y utilidades de dominio
│   ├── Repositories/       # Acceso a datos por colección
│   └── Views/              # Plantillas PHP (layout + vistas por módulo)
├── config/config.php       # Configuración global (nombre y lema de la app)
├── routes/web.php          # Definición de rutas
├── scripts/seed.php        # Datos de demostración
├── storage/data/           # Archivos JSON de la BD local (ignorados por git)
├── sql/schema.sql          # Esquema PostgreSQL (para activar ese driver)
└── docs/                   # Documentación por módulo y de la capa de BD
```

## Base de datos

El motor activo se elige en `config/config.php` → `database.driver`:

- `json` — **activo**. Archivos en `storage/data/*.json`. Cero configuración.
- `postgres` — implementación pendiente. Ver [`docs/db/postgres.md`](docs/db/postgres.md) y [`sql/schema.sql`](sql/schema.sql).
- `mongo` — cluster pendiente. Ver [`docs/db/mongo-cluster.md`](docs/db/mongo-cluster.md).

Gracias a `App\Core\Database\DatabaseInterface`, cambiar de motor **no** requiere
modificar controladores ni vistas.

## Documentación

- **Comportamiento completo** (permisos, ciclo de vida del pedido, reglas de
  dinero, validaciones y trampas conocidas):
  [`docs/COMPORTAMIENTO.md`](docs/COMPORTAMIENTO.md) ← *empieza aquí si vas a
  tocar código*
- **Diagramas de flujo por perfil** (visitante, consumidor, productor, admin y
  una vista general del ciclo comercial): [`docs/FLUJOS.md`](docs/FLUJOS.md)
- Arquitectura y convenciones: [`ARQUITECTURA.md`](ARQUITECTURA.md)
- Detalle por módulo: [`docs/modules/`](docs/modules/)
- Capa de base de datos: [`docs/db/`](docs/db/)
- Historial de cambios: [`docs/CHANGELOG.md`](docs/CHANGELOG.md)
