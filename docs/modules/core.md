# Núcleo (Core)

## Propósito

Piezas de infraestructura compartidas por toda la aplicación: enrutamiento, controlador base, renderizado de vistas, contenedor de dependencias, autenticación, helpers globales, capa de persistencia (interfaz + drivers) y repositorios. Ningún módulo de negocio (Inicio, Catálogo, Productor, etc.) debería hablar directamente con `$_SESSION`, archivos JSON o SQL: todo pasa por estas piezas.

## Archivos involucrados

```
bootstrap.php
public/index.php
config/config.php
routes/web.php
src/Core/Router.php
src/Core/Controller.php
src/Core/View.php
src/Core/Container.php
src/Core/Auth.php
src/Core/Cart.php
src/Core/ImageUploader.php
src/Core/helpers.php
src/Core/Database/DatabaseInterface.php
src/Core/Database/DatabaseFactory.php
src/Core/Database/JsonDatabase.php
src/Core/Database/PostgresDatabase.php
src/Core/Database/MongoDatabase.php
src/Repositories/BaseRepository.php
src/Repositories/UserRepository.php
src/Repositories/ProductRepository.php
src/Repositories/OrderRepository.php
src/Repositories/WithdrawalRepository.php
src/Models/User.php
src/Models/Product.php
src/Models/Order.php
```

## Flujo de una petición (front controller)

```
petición HTTP
  -> public/index.php
       -> bootstrap.php            (BASE_PATH, autoloader PSR-4, config, sesión)
       -> new Container($config)
       -> new Router($container)
       -> routes/web.php($router)  (registra todas las rutas)
       -> $router->dispatch(METHOD, URI)
            -> busca ruta que matchee método + patrón
            -> instancia "App\Controllers\{Clase}" con el Container
            -> llama al método de acción con los parámetros de ruta
                 -> Controller::requireAuth() / requireRole() (si aplica)
                 -> lógica del controlador (usa Repositorios -> DatabaseInterface)
                 -> Controller::render() -> View::render()
                      -> incluye la vista PHP en src/Views/**
                      -> envuelve en src/Views/layouts/main.php (si $layout = true)
       -> respuesta HTML
```

Si ninguna ruta coincide, `Router::notFound()` responde `404` con `errors/404`.

## `public/index.php` (front controller)

Antes de enrutar, `index.php` hace dos cosas clave:

1. **Servir archivos estáticos bajo el servidor embebido.** Cuando la app corre con
   `php -S localhost:8000 -t public public/index.php` (index.php como *router
   script*), TODAS las peticiones entran por aquí, incluidas `/assets/css/style.css`,
   `/assets/js/main.js` y las imágenes de `/uploads/...`. Por eso, si `PHP_SAPI === 'cli-server'`
   y la URL apunta a un **archivo real** dentro de `public/`, se hace `return false`
   para que el servidor lo entregue con su `Content-Type` correcto. Con Apache/Nginx
   esta comprobación no aplica. **El argumento `public/index.php` en el comando es
   obligatorio**: sin él, rutas con id (p. ej. `/producto/{id}`) fallan porque el
   servidor embebido las trataría como archivos.
2. **Verificación CSRF global.** Toda petición `POST` debe traer un campo `_csrf`
   válido (comparado con `csrf_token()` mediante `hash_equals`). Si falta o no coincide,
   responde `419` y no se ejecuta ninguna acción. Al ser global, ninguna acción `POST`
   puede saltarse la comprobación.

## `bootstrap.php`

Se incluye una única vez desde `public/index.php` (`$config = require dirname(__DIR__) . '/bootstrap.php';`). Responsabilidades:

1. Define `BASE_PATH` (raíz del proyecto).
2. Registra el autoloader: usa `vendor/autoload.php` de Composer si existe; si no, registra un autoloader PSR-4 propio (`spl_autoload_register`) que mapea el namespace `App\` → carpeta `src/`. Esto permite correr el proyecto sin `composer install`.
3. Carga `config/config.php` en `$config`.
4. Configura `date_default_timezone_set()` y el modo de errores (`display_errors`/`error_reporting`) según `config['app']['debug']`.
5. Arranca la sesión (`session_name()` + `session_start()`) usando `config['session']['name']`.
6. Devuelve `$config` a quien lo incluya.

## `config/config.php`

Array de configuración plano, sin clase de configuración. Secciones:

- `app`: `name`, `tagline`, `env`, `debug`, `base_url`, `timezone`.
- `database`: `driver` (`json` | `postgres` | `mongo`) + sub-arrays de conexión por driver.
- `session`: `name`, `lifetime`.
- `roles`: mapa `slug => etiqueta legible` (`consumer`, `producer`, `admin`).

**Nombre del proyecto**: `config['app']['name']` se resuelve como `getenv('APP_NAME') ?: 'BioBoyacá'`, y `config['app']['tagline']` es `'Del campo boyacense a tu mesa en Bogotá'`. Sigue siendo el **único lugar** que hay que tocar si alguna vez cambian (o exportar la variable de entorno `APP_NAME`): se propagan automáticamente a título de página, encabezado y pie de página vía `View::render()`, que inyecta `$appName` y `$tagline` en todas las vistas. **No hardcodear el nombre** en vistas ni controladores.

## `App\Core\Router`

- Registra rutas con `get(pattern, handler)` y `post(pattern, handler)`.
- Convierte patrones con parámetros (`/producto/{id}`) a regex con grupos con nombre: `{nombre}` → `(?<nombre>[^/]+)` (método `patternToRegex`).
- `dispatch(method, uri)` recorta la barra final de la URI, recorre las rutas registradas en orden y ejecuta la primera coincidencia exacta de método + patrón.
- `run(handler, params)` interpreta `"Controlador@metodo"`, antepone el namespace `App\Controllers\`, instancia el controlador pasándole el `Container` y llama al método con los parámetros de ruta capturados (ej. `['id' => '123']`).
- Si nada coincide, responde `404` renderizando `errors/404` directamente (sin pasar por un controlador).

## `App\Core\Controller` (clase abstracta base)

Heredada por los 8 controladores (`Home`, `Auth`, `Catalog`, `Product`, `Cart`, `Producer`, `Consumer`, `Admin`). Provee:

| Método | Uso |
|---|---|
| `render(view, data=[], layout=true)` | Delegado a `View::render()`, hace `echo`. |
| `redirect(path)` | `header('Location: ...')` + `exit` (tipo `never`). |
| `input(key, default=null)` | Lee de `$_POST` con prioridad sobre `$_GET`; hace `trim()` si es string. |
| `isPost()` | `$_SERVER['REQUEST_METHOD'] === 'POST'`. |
| `requireAuth()` | Redirige a `/login` si no hay sesión (`Auth::check()`). |
| `requireRole(...roles)` | Llama `requireAuth()` y además exige que `Auth::user()['role']` esté en la lista; si no, `403` + vista `errors/403`. |
| `flash(type, message)` | Guarda `$_SESSION['flash'][type]`; se lee y consume con la función global `flash()` en las vistas. |

El constructor recibe el `Container` y crea `$this->view = new View($container)` y `$this->auth = $container->auth()`.

## `App\Core\View`

- `render(view, data=[], layout=true)`: incluye `src/Views/{view}.php` dentro de un buffer (`ob_start`/`ob_get_clean`), inyectando `$data` con `extract()`.
- Añade automáticamente a `$data` (si no vienen ya definidas): `appName`, `tagline`, `roles` (desde `config`), `auth` (instancia de `Auth`), `title`.
- Si `layout = true` (por defecto), vuelve a capturar `src/Views/layouts/main.php` pasándole `content` = el HTML ya renderizado de la vista concreta.
- Lanza `RuntimeException` si el archivo de vista no existe.

## `App\Core\Container`

Contenedor de dependencias mínimo (no hay autowiring). Guarda `$config` y expone:

- `config(?key)`: toda la configuración o una sección.
- `db(): DatabaseInterface`: instancia (singleton, vía `??=`) el driver activo con `DatabaseFactory::make($config['database'])`.
- `auth(): Auth`: instancia (singleton) el servicio `Auth`.

Los controladores llaman típicamente `new ProductRepository($this->container->db())` dentro de cada acción (no hay inyección automática de repositorios).

## `App\Core\Auth`

Ver detalle funcional en `docs/modules/autenticacion.md`. Resumen técnico:

- Se construye con un `UserRepository` propio (`new UserRepository($container->db())`).
- `check()`: `isset($_SESSION['user_id'])`.
- `user()`: `UserRepository::find($_SESSION['user_id'])` o `null`.
- `hasRole(role)`: compara `user()['role']`.
- `register(data)`, `attempt(email, password)`, `login(user)`, `logout()`.

## `src/Core/helpers.php`

Funciones globales (guardadas con `function_exists` para evitar redeclaración), cargadas en `bootstrap.php` (para que estén disponibles en TODAS las vistas, incluso las que se renderizan sin layout):

- `e(value)`: `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- `flash(type)`: lee y **consume** (`unset`) `$_SESSION['flash'][type]`.
- `csrf_token()`: genera/reutiliza `$_SESSION['csrf_token']` (`bin2hex(random_bytes(16))`).
- `csrf_field()`: `<input type="hidden" name="_csrf" ...>` con el token.

La **verificación** del token ocurre en `public/index.php`: toda petición `POST` compara su campo `_csrf` con `csrf_token()` mediante `hash_equals()` antes de despachar; si no coincide responde `419`. Ver `docs/modules/autenticacion.md`.

## `App\Core\Cart`

Carrito de compras en **sesión**, no en base de datos. Es la única pieza de Core que guarda estado de negocio fuera de la BD, y lo hace a propósito: el carrito es un estado temporal del navegador que solo se persiste cuando el consumidor confirma el pedido.

- Estructura: `$_SESSION['cart'] = [ '<product_id>' => <qty:int>, ... ]`. **Solo id y cantidad**: nombre, precio y stock se re-resuelven contra `ProductRepository` en cada pantalla, de modo que un cambio de precio o una rotura de stock se vean antes de pagar.
- API estática: `items()`, `add(id, qty=1)` (acumula), `setQty(id, qty)` (fija; `qty < 1` elimina la línea), `remove(id)`, `clear()`, `count()` (unidades totales, para el contador del encabezado) e `isEmpty()`.
- Salvaguarda `MAX_QTY = 99` unidades por línea.
- **No requiere sesión iniciada**: se usa igual como invitado. El rol `consumer` se exige solo en el checkout. Detalle funcional en `docs/modules/carrito.md`.

## `App\Core\ImageUploader`

Servicio de subida de imágenes usado por el módulo del productor para las fotos de producto. Aislado del controlador para poder validar y reutilizar en un solo lugar.

- Constructor: `new ImageUploader($destDir, $publicPrefix)` — carpeta física destino y prefijo de URL pública (ej. `.../public/uploads/products` y `/uploads/products`).
- `handle(?array $file): array` → `['ok'=>bool, 'path'=>?string, 'error'=>?string]`. La imagen es **opcional** (sin archivo = `ok` con `path=null`). Validaciones: archivo realmente subido (`is_uploaded_file`), imagen real (`getimagesize`, no confía en la extensión/mime del navegador), lista blanca `image/jpeg|png|webp|gif`, tamaño ≤ 2 MB. Guarda con nombre aleatorio `bin2hex(random_bytes(8)).ext`.
- `delete(?string $webPath): void` — borra el archivo solo si la ruta pertenece a la carpeta gestionada (evita borrados fuera de `uploads`).

## Diseño / temas (interfaz)

Aunque el CSS/JS no es "Core PHP", conviene documentar el mecanismo de tema:

- `public/assets/css/style.css` define la identidad **verde institucional** como tokens en `:root` (tema claro por defecto): marca `--verde: #1B7A4B`, sólidos `--verde-solido: #15633C`, hover `--verde-hover: #0F4D2E`, acento `--verde-suave: #E6F2EB`, ámbar de aviso `--ambar: #C8860D`, lienzo `--bg: #F7F9F8`.
- **Distinción clave**: los tokens de *marca* son constantes; solo los **alias funcionales** (`--bg`, `--card-bg`, `--fg`, `--border`, `--muted`, `--primary`, `--warn`, `--error` y sus variantes) se reasignan en `:root[data-theme="dark"]` (opcional, no automático), donde el fondo es un neutro verdoso `#141A17` — no negro puro — y el primario se aclara a `#34A76B` para conservar contraste AA.
- El botón `#themeToggle` (en `layouts/main.php`) alterna `data-theme` en `<html>` vía `main.js` y guarda la preferencia en `localStorage` (`tema`). Un script en el `<head>` la aplica antes de pintar (sin parpadeo).
- Mobile-first con tres tramos: móvil `≤720px`, tablet `721–1024px`, escritorio `>1024px`. El layout pinta además una navegación inferior (`.bottom-nav`) visible solo en el tramo móvil, con ítems según el rol, y el contador del carrito (`.cart-badge`) alimentado por `Cart::count()`.
- El layout añade `?v=<filemtime>` a `style.css`/`main.js` (cache-busting).

## Capa de base de datos

### `App\Core\Database\DatabaseInterface`

Contrato orientado a colecciones/documentos (no SQL), para que JSON, Postgres o Mongo puedan implementarlo de forma intercambiable:

```php
all(string $collection): array
find(string $collection, string $id): ?array
where(string $collection, array $criteria): array   // igualdad simple
insert(string $collection, array $document): array  // genera id si falta
update(string $collection, string $id, array $changes): ?array  // merge
delete(string $collection, string $id): bool
```

### `App\Core\Database\DatabaseFactory`

`DatabaseFactory::make($dbConfig)` hace `match ($dbConfig['driver'])`:

| driver | Clase |
|---|---|
| `json` (default) | `JsonDatabase($dbConfig['json']['path'])` |
| `postgres` | `PostgresDatabase($dbConfig['postgres'])` |
| `mongo` | `MongoDatabase($dbConfig['mongo'])` |
| otro | `InvalidArgumentException` |

Cambiar de motor es cuestión de cambiar `config['database']['driver']` (o la env `DB_DRIVER`); los repositorios y controladores no cambian.

### `App\Core\Database\JsonDatabase` (driver ACTIVO)

- Cada colección es un archivo `storage/data/{colección}.json` con un array de documentos.
- `find`/`where` recorren el array en memoria (`O(n)`).
- `insert` genera `id` con `bin2hex(random_bytes(8))` (16 hex, **sin punto**) si no viene dado, y agrega `created_at`. Se evita a propósito el `uniqid('', true)` original porque su punto hacía que el servidor embebido tratara rutas como `/producto/{id}` como archivos estáticos (404); además da URLs limpias.
- `update` hace `array_merge($doc, $changes)` y agrega `updated_at`; el `id` no se puede sobrescribir vía `changes` (se hace `unset($changes['id'])`).
- `delete` filtra el array y reescribe el archivo si el documento existía.
- Escritura con `file_put_contents(..., LOCK_EX)` (bloqueo básico, no apto para alta concurrencia).
- El nombre de colección se sanea con `preg_replace('/[^a-zA-Z0-9_\-]/', '', $collection)` antes de construir la ruta del archivo (evita path traversal).

### `App\Core\Database\PostgresDatabase` / `MongoDatabase` (STUBS)

Ambas clases implementan `DatabaseInterface` pero cada método lanza `RuntimeException` ("aún no implementado"). Guardan `$config` y dejan comentado el código de conexión (PDO / `MongoDB\Client`) listo para activar. **No están operativas todavía.**

## Repositorios (`App\Repositories\*`)

Capa intermedia entre controladores y `DatabaseInterface`; los controladores **nunca** llaman al driver directamente.

- `BaseRepository` (abstracta): define `protected string $collection` (la fija cada hija) y expone `all()`, `find(id)`, `where(criteria)`, `create(data)`, `update(id, changes)`, `delete(id)`, `count()`. Todo delega en `$this->db->{método}($this->collection, ...)`.
- `UserRepository` (`collection = 'users'`): + `findByEmail(email)`, `ofRole(role)`.
- `ProductRepository` (`collection = 'products'`): + `ofProducer(producerId)`, `ofCategory(category)`, `search(term)` (substring en `name`+`description`).
- `OrderRepository` (`collection = 'orders'`): + `ofConsumer(consumerId)`, `ofProducer(producerId)` (filtra en memoria si algún `item.producer_id` coincide, porque el criterio está anidado dentro de `items`).
- `WithdrawalRepository` (`collection = 'withdrawals'`): + `ofProducer(producerId)` (ordenado del más reciente al más antiguo) y `totalWithdrawn(producerId)`. Los retiros son **simulados**: se registra la solicitud, no hay pasarela de pago. Ver `docs/modules/billetera.md`.

## Modelos (`App\Models\*`)

No son ORM ni entidades con estado; son clases `final` con constantes y utilidades estáticas sobre los arrays que devuelve la capa de persistencia:

- `User`: constantes de rol (`ROLE_CONSUMER`, `ROLE_PRODUCER`, `ROLE_ADMIN`) y `publicRoles()` (roles seleccionables en el registro público).
- `Product`: `categories()` (lista fija de **8** categorías), `categoryHints()` / `categoryHint()` (texto de ayuda por categoría), `units()` (unidades de venta), `origins()` (34 municipios de Boyacá), `categorySlug()` (gancho de estilos `data-cat`) y `formatPrice()`.
- `Order`: constantes de estado (`STATUS_PENDING`, `STATUS_CONFIRMED`, **`STATUS_SHIPPED`**, `STATUS_DELIVERED`, `STATUS_CANCELLED`), `statuses()` (mapa estado→etiqueta, 5 estados), `label(status)`, `inTransitStatuses()` (`confirmed` + `shipped`), `localities()` (19 localidades de Bogotá), la tarifa `SHIPPING_FEE = 6000.0` con su `SHIPPING_LABEL`, y el desglose económico `computeSubtotal(items)` / `computeShipping(items)` / `computeTotal(items)`.

> ⚠️ **`Order::computeTotal()` incluye el envío** (`subtotal + shipping`), no es la simple suma de líneas. Para calcular lo que le corresponde a un productor concreto hay que sumar sus propias líneas, nunca leer `order['total']`.

## Notas / mejoras futuras

- El `Container` no hace autowiring; cada controlador crea sus repositorios manualmente (`new XRepository($this->container->db())`) en cada acción. Podría centralizarse en el `Container` o en el constructor del controlador base.
- `PostgresDatabase` y `MongoDatabase` son stubs no funcionales; activar cualquiera de los dos requiere implementarlos por completo antes de cambiar `DB_DRIVER`.
- `JsonDatabase` no es apta para alta concurrencia (bloqueo de archivo simple, sin transacciones); documentado explícitamente en el propio archivo fuente.
