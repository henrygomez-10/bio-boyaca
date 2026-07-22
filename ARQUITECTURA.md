# Arquitectura del proyecto

Documento de referencia de la arquitectura, comandos y convenciones del proyecto.

## Qué es este proyecto

**BioBoyacá** — *Del campo boyacense a tu mesa en Bogotá*. Marketplace que conecta
**productores** campesinos de Boyacá con **consumidores** de Bogotá. PHP puro con
patrón **MVC**, sin framework. La persistencia está **abstraída** detrás de una
interfaz, con un driver activo basado en archivos **JSON** (para desarrollo en
localhost) y drivers **PostgreSQL** y **MongoDB** ya preparados como stubs.

> El nombre y el lema del proyecto se definen en un único lugar:
> `config/config.php` → `app.name` y `app.tagline` (o la variable de entorno
> `APP_NAME`). **No hardcodees el nombre en ningún otro sitio**; usa siempre
> `$appName` / `$tagline` en vistas o `config('app')['name']`. Si algún día
> cambia, se cambia ahí y se propaga a toda la aplicación.

## Comandos

Requiere **PHP >= 8.1** (con `ext-json`, ya incluida). No requiere Composer: el
autoloader PSR-4 propio vive en `bootstrap.php`.

```bash
# Levantar la app (servidor embebido de PHP). Document root = public/
# El último argumento (index.php) es el ROUTER: enruta TODA petición al front
# controller. Es obligatorio para que rutas con id (ej. /producto/{id}) funcionen.
php -S localhost:8000 -t public public/index.php

# Cargar datos de demostración (3 usuarios + 8 productos + 7 pedidos)
php scripts/seed.php

# (Opcional) Si usas Composer para el autoloader optimizado
composer dump-autoload
```

No hay framework de tests configurado todavía. Para comprobar sintaxis de un
archivo: `php -l ruta/al/archivo.php`.

### Cuentas de demo (tras `php scripts/seed.php`)

| Rol        | Email                   | Password     |
|------------|-------------------------|--------------|
| Admin      | admin@demo.test         | admin123     |
| Productor  | productor@demo.test     | producer123  |
| Consumidor | consumidor@demo.test    | consumer123  |

## Arquitectura (el panorama)

Flujo de una petición:

```
public/index.php (front controller)
  -> bootstrap.php        (autoloader PSR-4, helpers, config, sesión)
  -> verificación CSRF    (solo POST)
  -> App\Core\Router      (routes/web.php: "Controlador@metodo")
  -> App\Controllers\*    (lógica; usan Repositorios, nunca el driver de BD)
  -> App\Core\View        (renderiza src/Views/*.php dentro de layouts/main.php)
```

Puntos clave que requieren leer varios archivos para entenderse:

- **Capa de persistencia intercambiable.** `App\Core\Database\DatabaseInterface`
  define un contrato orientado a **colecciones/documentos** (`all/find/where/insert/update/delete`),
  no a SQL. `DatabaseFactory` instancia el driver según `config['database']['driver']`
  (`json` | `postgres` | `mongo`). Cambiar de motor = cambiar esa sola clave, sin
  tocar controladores. Los controladores hablan con **Repositorios**
  (`App\Repositories\*`), que a su vez usan el driver. Nunca acceder al driver
  directamente desde un controlador o vista.

- **Driver JSON activo.** `JsonDatabase` guarda cada colección (`users`,
  `products`, `orders`, `withdrawals`) como un array en
  `storage/data/<colección>.json`. Los
  `.json` están en `.gitignore`. Es solo para desarrollo (no concurrente).

- **Drivers Postgres/Mongo = stubs.** `PostgresDatabase` y `MongoDatabase` lanzan
  excepción "pendiente" en cada método. La guía para implementarlos está en
  `docs/db/postgres.md` y `docs/db/mongo-cluster.md`; el esquema SQL en
  `sql/schema.sql`. El `DatabaseInterface` se diseñó en términos de documentos
  precisamente para que el mapeo a Mongo sea casi 1:1.

- **Autenticación y roles.** `App\Core\Auth` gestiona registro/login/logout por
  sesión (bcrypt vía `password_hash`). Solo guarda `user_id` en sesión. Tres
  roles: `consumer`, `producer`, `admin` (constantes en `App\Models\User`;
  etiquetas legibles en `config['roles']`). El rol `admin` **no** se crea por
  registro público (solo por seed). Control de acceso en el controlador base
  (`requireAuth()`, `requireRole(...)`).

- **CSRF.** Verificación global de POST en `public/index.php`. Todo formulario
  debe incluir `<?= csrf_field() ?>`. Los helpers de vista (`e()`, `csrf_field()`,
  `flash()`, `csrf_token()`) viven en `src/Core/helpers.php` y se cargan en
  `bootstrap.php` (importante: antes de renderizar vistas).

- **Modelos = value helpers.** Los datos viajan como **arrays** (vienen del
  driver). `App\Models\*` no son entidades ORM; aportan constantes y utilidades
  para evitar strings mágicos: `Product` (8 categorías cerradas, `units()`,
  `origins()` con municipios de Boyacá, `categoryHints()`, `categorySlug()`,
  `formatPrice()`) y `Order` (5 estados incluido `STATUS_SHIPPED` → "En tránsito",
  `inTransitStatuses()`, `localities()` de Bogotá, `SHIPPING_FEE` y los tres
  `compute*()` del desglose económico).

- **Servir estáticos + router obligatorio.** Con `php -S` se pasa `public/index.php`
  como *router script*, así que TODA petición entra por él. `index.php` hace
  `return false` para archivos reales de `public/` (CSS/JS/`uploads/`) bajo
  `cli-server`; sin el router, rutas con id (`/producto/{id}`) fallarían. Los ids
  se generan con `bin2hex(random_bytes(8))` (sin punto) por esta razón.

- **Subida de imágenes.** `App\Core\ImageUploader` valida (imagen real vía
  `getimagesize`, lista blanca JPG/PNG/WEBP/GIF, ≤ 2 MB) y guarda en
  `public/uploads/products/` (ignorado por git salvo `.gitkeep`). El producto
  guarda la ruta pública en el campo opcional `image`. `ProducerController`
  gestiona alta/reemplazo/borrado del archivo. Las vistas muestran la foto o el
  medallón con la inicial como respaldo.

- **Carrito en sesión, no en BD.** `App\Core\Cart` guarda
  `$_SESSION['cart'] = [product_id => qty]` — **solo id y cantidad**. Nombre,
  precio y stock se re-resuelven contra `ProductRepository` cada vez que se pinta
  el carrito, para que un cambio de precio o una rotura de stock se vean antes de
  pagar. El carrito se puede llenar **sin sesión iniciada** (como invitado); el
  rol `consumer` solo se exige en el checkout (`CartController@checkout`), para no
  cortar la navegación del catálogo.

- **El pedido congela su desglose económico.** `Order::computeTotal()` **incluye
  la logística** (`computeSubtotal()` + `computeShipping()`). La tarifa
  `Order::SHIPPING_FEE` = `6000.0` es plana Boyacá→Bogotá y se cobra **una sola
  vez por pedido**, sin importar cuántas líneas lleve. El pedido persiste
  `subtotal`, `shipping` y `total` ya calculados, de modo que el histórico no
  cambia si mañana varía la tarifa. Ojo al leer ingresos de un productor:
  `order['total']` incluye envío y puede mezclar varios productores.

- **Diseño y tema.** Sistema de tokens en `public/assets/css/style.css`
  (identidad **verde institucional**: lienzo casi blanco levemente verdoso
  `--bg: #F7F9F8`, marca `--verde: #1B7A4B`, sólidos `--verde-solido: #15633C`,
  hover `--verde-hover: #0F4D2E`, acento `--verde-suave: #E6F2EB` y un ámbar
  puntual `--ambar: #C8860D` para avisos, "en tránsito" y ofertas). La paleta de
  marca es **constante**: el tema oscuro solo reasigna los *alias funcionales*
  (`--bg`, `--card-bg`, `--fg`, `--border`, `--primary`, `--warn`, `--error`).
  **Tema claro por defecto**; el oscuro es opcional vía `:root[data-theme="dark"]`
  + botón `#themeToggle` (persistido en `localStorage`, aplicado en `<head>` sin
  parpadeo). Mobile-first con tres tramos: móvil (≤720px), tablet (721–1024px) y
  escritorio (>1024px). El layout añade `?v=<filemtime>` a CSS/JS
  (cache-busting). Colorear categorías: `.tag[data-cat="..."]`.

- **Navegación doble.** Barra superior con icono de carrito y contador
  (`.cart-badge`, alimentado por `Cart::count()`) + **navegación inferior fija
  solo en móvil** (`.bottom-nav`, oculta desde 721px) cuyos ítems cambian según el
  rol: el productor ve Panel / Productos / Pedidos / Billetera; el resto ve
  Inicio / Catálogo / Carrito / Mi cuenta.

- **Menú superior colapsable en móvil.** Hasta 720px los enlaces de `.main-nav`
  se pliegan tras un botón hamburguesa (`#navToggle`); el carrito y el botón de
  tema viven fuera del `<nav>`, en `.header-actions`, para seguir visibles con el
  menú cerrado. El comportamiento (aria-expanded, cierre con Escape o al tocar
  fuera, reinicio al volver a escritorio) está en `public/assets/js/main.js`.
  Es **mejora progresiva**: el script del `<head>` añade la clase `js` al `<html>`
  y el colapso está condicionado a `html.js`, así que sin JavaScript el menú se
  queda desplegado en vez de volverse inalcanzable.

## Módulos

Cada módulo tiene su documentación en `docs/modules/`:

| Módulo             | Controlador           | Rutas base                     |
|--------------------|-----------------------|--------------------------------|
| Inicio             | `HomeController`       | `/`                            |
| Autenticación      | `AuthController`       | `/login`, `/registro`, `/logout` |
| Catálogo           | `CatalogController`    | `/catalogo`                    |
| Detalle producto   | `ProductController`    | `/producto/{id}`               |
| Carrito y checkout | `CartController`       | `/carrito/...`, `/pedido`      |
| Perfil productor   | `ProducerController`   | `/productor/...`               |
| Billetera productor| `ProducerController`   | `/productor/billetera/...`     |
| Perfil consumidor  | `ConsumerController`   | `/consumidor/...`              |
| Admin (básico)     | `AdminController`      | `/admin/...`                   |

Tabla completa de rutas (`routes/web.php`):

| Método | URL | Handler |
|---|---|---|
| GET  | `/` | `HomeController@index` |
| GET/POST | `/registro` | `AuthController@showRegister` / `@register` |
| GET/POST | `/login` | `AuthController@showLogin` / `@login` |
| POST | `/logout` | `AuthController@logout` |
| GET  | `/catalogo` | `CatalogController@index` |
| GET  | `/producto/{id}` | `ProductController@show` |
| GET  | `/carrito` | `CartController@show` |
| POST | `/carrito/agregar` | `CartController@add` |
| POST | `/carrito/cantidad` | `CartController@updateQty` |
| POST | `/carrito/eliminar` | `CartController@remove` |
| POST | `/pedido` | `CartController@checkout` |
| GET  | `/productor` | `ProducerController@dashboard` |
| GET  | `/productor/billetera` | `ProducerController@wallet` |
| POST | `/productor/billetera/retiro` | `ProducerController@withdraw` |
| GET  | `/productor/productos` | `ProducerController@products` |
| GET  | `/productor/productos/nuevo` | `ProducerController@createForm` |
| POST | `/productor/productos` | `ProducerController@store` |
| GET  | `/productor/productos/{id}/editar` | `ProducerController@editForm` |
| POST | `/productor/productos/{id}` | `ProducerController@update` |
| POST | `/productor/productos/{id}/eliminar` | `ProducerController@destroy` |
| GET  | `/productor/pedidos` | `ProducerController@orders` |
| POST | `/productor/pedidos/{id}/estado` | `ProducerController@updateOrderStatus` |
| GET  | `/consumidor` | `ConsumerController@dashboard` |
| GET  | `/consumidor/pedidos` | `ConsumerController@orders` |
| GET  | `/admin`, `/admin/usuarios`, `/admin/productos`, `/admin/pedidos` | `AdminController@dashboard` / `@users` / `@products` / `@orders` |

Todas las rutas se registran en `routes/web.php`. El historial de cambios está en
`docs/CHANGELOG.md`; los diagramas de flujo (mermaid) en `docs/modules/README.md`.

Las **reglas transversales** —qué rol puede entrar a cada ruta, ciclo de vida del
pedido, cómo se calcula cada importe, validaciones de cada formulario y las
trampas conocidas— están reunidas en **`docs/COMPORTAMIENTO.md`**. Es el
documento de referencia antes de tocar código de negocio. El recorrido de cada
perfil (visitante, consumidor, productor y administrador) está diagramado en
**`docs/FLUJOS.md`**.

## Convenciones

- `declare(strict_types=1);` en todos los archivos PHP.
- Namespace raíz `App\` → carpeta `src/`.
- Vistas: PHP plano en `src/Views/`, clases CSS con nomenclatura tipo BEM.
  **Siempre** escapar salida con `e()`.
- Añadir una ruta = registrarla en `routes/web.php` + método en el controlador
  correspondiente + vista en `src/Views/`.
- Comentarios y textos de UI en **español**.
