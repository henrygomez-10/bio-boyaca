# Registro de cambios

Historial de cambios del proyecto. Fechas en formato AAAA-MM-DD.

> El nombre y el lema del proyecto se definen en `config/config.php`
> (`app.name`, `app.tagline`) o con la variable de entorno `APP_NAME`. **No se
> hardcodean** en vistas ni controladores.

## 2026-07-20 · Diagramas de flujo por perfil

### Añadido

- **`docs/FLUJOS.md`**: recorridos de cada perfil por el sistema, en diagramas
  mermaid. Incluye una **vista general del ciclo comercial** (cómo encajan los
  cuatro perfiles entre sí) y un diagrama por perfil: **visitante** (navega y
  llena el carrito sin sesión), **consumidor** (catálogo → carrito → checkout →
  historial), **productor** (publicar, gestionar pedidos, billetera y retiro) y
  **administrador** (paneles de solo lectura). Cierra con una tabla de los
  controles que protegen cada flujo. Enlazado desde `README.md`,
  `ARQUITECTURA.md`, `COMPORTAMIENTO.md` y el índice de módulos.

### Notas

- Los 10 diagramas mermaid del proyecto (los 5 nuevos y los 5 previos) se
  validaron renderizándolos con `@mermaid-js/mermaid-cli`, para garantizar que
  GitHub no muestre bloques rotos.

## 2026-07-20 · Documentación de comportamiento y verificación del clon

### Añadido

- **`docs/COMPORTAMIENTO.md`**: referencia transversal para desarrolladores.
  Reúne lo que estaba repartido entre los documentos de módulo: puesta en marcha
  desde un clon, tabla completa de **rutas × rol** (incluidas las comprobaciones
  de propiedad), **ciclo de vida del pedido** con diagrama de estados, reglas de
  **cálculo del dinero** (tarifa plana de logística, importes congelados, ingreso
  del productor por líneas propias), **validaciones** de cada formulario,
  seguridad transversal, modelo de datos y una lista de **comportamientos que
  sorprenden**. Enlazado desde `README.md`, `ARQUITECTURA.md` y el índice de
  módulos.

### Verificado

- **Un clon del repositorio arranca sin pasos extra**, pese a que `.gitignore`
  excluya `storage/data/*.json`, las imágenes subidas y `.env`. Comprobado
  clonando en limpio y ejecutando el flujo completo: arranque con catálogo vacío,
  `seed.php`, login, carrito, checkout, billetera del productor y subida de
  imagen. Las carpetas sobreviven gracias a sus `.gitkeep` y los `.json` se crean
  en la primera escritura. No se requiere `.env`, ni Composer, ni la extensión GD.

### Corregido

- **`README.md`**: eliminada la nota obsoleta que afirmaba que el equipo no tenía
  PHP instalado. La sección de puesta en marcha ahora parte del `git clone`,
  explica por qué un clon vacío funciona y advierte de que **sembrar con el
  servidor levantado corrompe los datos** (el driver JSON no es concurrente).

### Documentado como limitación conocida

- `updateOrderStatus()` **no valida transiciones**: acepta cualquiera de los cinco
  estados, así que un pedido puede saltar de `pending` a `delivered` o retroceder.
  No hay máquina de estados.
- **Cancelar un pedido no repone el stock**: el descuento se hace en el checkout
  y no se revierte.

## 2026-07-19 · Carrito, logística y billetera

### Añadido

- **Carrito de compras multi-producto.** Nuevo `App\Core\Cart`: vive en la
  **sesión** (`$_SESSION['cart'] = [product_id => qty]`), guarda solo id y
  cantidad, y re-resuelve nombre/precio/stock contra el repositorio en cada
  pantalla para que un cambio de precio o una rotura de stock se vean **antes**
  de pagar. Salvaguarda de 99 unidades por línea. El carrito **se puede llenar
  sin sesión iniciada**; solo el checkout exige rol `consumer`, para no cortar la
  navegación del catálogo.
- **Módulo Carrito y checkout** (`App\Controllers\CartController`): `add`,
  `updateQty`, `remove`, `show` y `checkout`, con vista `src/Views/cart/show.php`
  ("Resumen de tu pedido"). Rutas nuevas: `GET /carrito`,
  `POST /carrito/agregar`, `POST /carrito/cantidad`, `POST /carrito/eliminar`.
  El checkout valida la dirección de entrega, **revalida el stock línea por
  línea** justo antes de cobrar, descuenta stock, guarda la dirección del usuario
  para la próxima compra y vacía el carrito.
- **Logística Boyacá-Bogotá.** `Order::SHIPPING_FEE = 6000.0`, tarifa **plana por
  pedido** (no por línea ni por productor). Nuevos `Order::computeSubtotal()` y
  `Order::computeShipping()`. El pedido persiste su desglose ya calculado
  (`subtotal`, `shipping`, `total`) para que el histórico no cambie si mañana
  varía la tarifa, junto con la dirección de entrega (`locality`, `address`).
- **Estado de pedido "En tránsito"** (`Order::STATUS_SHIPPED = 'shipped'`): ya son
  **5 estados**. Nuevo `Order::inTransitStatuses()` (`confirmed` + `shipped`) para
  la billetera, y `Order::localities()` con las 19 localidades de Bogotá.
- **Billetera del productor.** `ProducerController::wallet()` y `withdraw()`,
  rutas `GET /productor/billetera` y `POST /productor/billetera/retiro`, vista
  `src/Views/producer/wallet.php` y repositorio `WithdrawalRepository`
  (colección `withdrawals`). Muestra ingresos del mes, pedidos entregados/en
  tránsito, gráfico de ventas por semana y saldo disponible. Los ingresos se
  calculan **solo sobre las líneas del pedido que pertenecen a ese productor**,
  nunca sobre `order['total']` (que incluye envío y puede mezclar productores).
  **Los retiros son SIMULADOS**: se registra la solicitud para dejar traza y
  descontar el saldo, pero **no hay pasarela de pago** ni transferencia real.
- **Campos nuevos de producto**: `unit` (unidad de venta) y `origin` (municipio de
  Boyacá), **ambos obligatorios** y validados contra lista cerrada en
  `ProducerController::validateProduct()`. Nuevos `Product::units()`,
  `Product::origins()` (34 municipios) y `Product::categoryHints()` (texto de
  ayuda por categoría bajo el selector del formulario).
- **Navegación doble**: icono de carrito con contador (`.cart-badge`,
  `Cart::count()`) en la barra superior, y **navegación inferior fija solo en
  móvil** (`.bottom-nav`, ≤720px) con ítems según el rol — el productor ve Panel /
  Productos / Pedidos / Billetera; el resto ve Inicio / Catálogo / Carrito / Mi
  cuenta.
- **Menú superior colapsable en móvil** (≤720px): los enlaces de `.main-nav` se
  pliegan tras el botón hamburguesa `#navToggle`, que gestiona `aria-expanded`,
  el cierre con Escape o al tocar fuera, y el reinicio del estado al volver a
  escritorio. El carrito y el conmutador de tema salieron del `<nav>` a
  `.header-actions` para seguir visibles con el menú cerrado. Es mejora
  progresiva: el colapso está condicionado a `html.js` (clase que añade el script
  del `<head>`), así que sin JavaScript el menú se queda desplegado.
- **Componentes de interfaz nuevos**: `.chips`/`.chip` (filtro de categoría con
  scroll horizontal), `.bottom-nav`, `.nav-toggle`, `.header-actions`,
  `.cart-badge`, `.cart-item`, `.summary`, `.address-box`, `.wallet-hero`,
  `.stat-grid`, `.chart` y `.camera-field`.
- **Documentación**: nuevos `docs/modules/carrito.md` y `docs/modules/billetera.md`;
  diagrama mermaid del flujo de compra (catálogo → carrito → checkout → pedido) en
  `docs/modules/README.md`.

### Corregido

- **Contraste del botón primario dentro del menú.** La regla `.main-nav a`
  imponía `color: var(--fg)` a *todos* los enlaces del menú, incluido
  `<a class="btn btn--primary">Crear cuenta</a>`, que quedaba con tinta oscura
  sobre verde. La regla pasa a `.main-nav a:not(.btn)`, de modo que un enlace con
  aspecto de botón conserva su propio color y relleno.

### Cambiado

- **Nombre del proyecto definido: `BioBoyacá`**, con el lema *"Del campo boyacense
  a tu mesa en Bogotá"* (`config/config.php` → `app.name` y `app.tagline`). Deja
  de estar en pausa. Se mantiene la regla de **centralizarlo en config y no
  hardcodearlo**: las vistas siguen usando `$appName` / `$tagline`.
- **Identidad visual: de "Lienzo" (naranja) a verde institucional.** La paleta
  pasa a un lienzo casi blanco levemente verdoso (`--bg: #F7F9F8`) con marca
  `--verde: #1B7A4B`, sólidos `--verde-solido: #15633C`, hover
  `--verde-hover: #0F4D2E`, acento `--verde-suave: #E6F2EB` y un ámbar puntual
  `--ambar: #C8860D` para avisos, "en tránsito" y ofertas. Los tokens de **marca
  son constantes**; solo los alias funcionales se reasignan en oscuro.
- **Tema oscuro recalibrado**: neutro verdoso (`--bg: #141A17`,
  `--card-bg: #1B2320`), no negro puro, con primario aclarado a `#34A76B` (5.8:1)
  y ámbar a `#E8A93A` (7.7:1) para mantener contraste AA sobre fondo oscuro.
  Breakpoints explícitos en tres tramos: móvil ≤720px, tablet 721–1024px,
  escritorio >1024px.
- **Categorías de producto reemplazadas.** Las 7 genéricas (Frutas, Verduras,
  Lácteos, Panadería, Carnes, Bebidas, Otros) se sustituyen por **8 categorías
  específicas del proyecto**: Lácteos, Huevos, Carne, Miel, Tubérculos y raíces,
  Hortalizas, Café, Arepas tradicionales. `Product::categorySlug()` actualizado
  (`lacteos`, `huevos`, `carne`, `miel`, `tuberculos`, `hortalizas`, `cafe`,
  `arepas`).
- **`Order::computeTotal()` ahora incluye el envío** (`subtotal + shipping`);
  antes era la simple suma de las líneas.
- **`POST /pedido` apunta ahora a `CartController@checkout`** (antes
  `ConsumerController@placeOrder`). El formulario de la ficha de producto y las
  tarjetas del catálogo apuntan a `POST /carrito/agregar` con un `return_to`
  interno, para volver al catálogo conservando búsqueda y filtro.
- **El filtro de categoría del catálogo** pasa de `<select>` a chips (enlaces GET
  que conservan el término de búsqueda activo).
- **Seed regenerado** (`scripts/seed.php`): siembra 8 productos (uno por
  categoría) con unidad y municipio de origen, y **7 pedidos repartidos por las
  semanas del mes en curso** para que el gráfico de la billetera tenga datos.
  Limpia también la colección `withdrawals` y nunca siembra fechas futuras.

### Eliminado

- **`ConsumerController::placeOrder`**, junto con el flujo de "un producto por
  pedido". `ConsumerController` queda solo con el panel y el historial
  (`dashboard`, `orders`); la compra vive íntegramente en `CartController`.

### Notas

- Un mismo pedido **sí puede mezclar productos de varios productores** (antes era
  imposible). Sigue vigente la limitación de que el `status` es único por pedido:
  cualquier productor con una línea en él puede cambiarlo.
- El descuento de stock sigue sin ser atómico con la creación del pedido (el
  driver JSON no tiene transacciones).
- El retiro es **todo o nada**: `withdraw()` siempre solicita el saldo disponible
  completo, no admite importes parciales. Si en el futuro se permiten retiros
  parciales, el estado del retiro (hoy el literal `'requested'`) merecerá pasar a
  constantes en un modelo propio, como el resto de estados del proyecto.

## 2026-07-19

### Añadido

- **Subida de imágenes de producto.** Nuevo servicio `App\Core\ImageUploader`:
  valida que el archivo sea una imagen real (`getimagesize`, no confía en la
  extensión/mime del navegador), lista blanca JPG/PNG/WEBP/GIF, máximo 2 MB, y
  guarda con nombre aleatorio en `public/uploads/products/`. El producto persiste
  la ruta pública en el campo opcional `image`. El formulario del productor usa
  `enctype="multipart/form-data"` con vista previa de la imagen actual. Al
  reemplazar o eliminar un producto se borra el archivo anterior (sin huérfanos).
  Las vistas (inicio, catálogo, detalle, tabla del productor) muestran la foto
  (`object-fit: cover`) o el medallón con la inicial como respaldo.
- **Identidad visual "Lienzo"** (sistema de diseño por tokens en
  `public/assets/css/style.css`): fondo casi blanco `#FAF8F4`, marca naranja
  `#E8532E` (botón sólido `#C7431F` por contraste AA), apoyo arena, acento dorado
  `#F2B134`, error terroso. Tipografía "popping" (escala fluida, pesos 800/900,
  precios destacados) y layout de e-commerce (tarjetas con hover, ficha de
  detalle, filtros tipo pill, badges de estado). Contraste AA verificado.
- **Color por categoría.** Nuevo `Product::categorySlug()`; las etiquetas de
  categoría llevan `data-cat` y el CSS las colorea (`frutas`, `verduras`,
  `panaderia`, `lacteos`, `carnes`, `bebidas`, `otros`).
- **Tema claro/oscuro.** Tema claro por defecto; oscuro **opcional** vía
  `:root[data-theme="dark"]` y botón `#themeToggle` en el encabezado. La
  preferencia se guarda en `localStorage` (`tema`) y se aplica desde el `<head>`
  para evitar parpadeos.
- **Cache-busting.** El layout añade `?v=<filemtime>` a `style.css` y `main.js`.
- **Diagramas de flujo (mermaid)** en `docs/modules/README.md`: petición HTTP,
  registro/login con redirección por rol, y subida de imágenes.

### Cambiado

- **Servido de archivos estáticos.** `public/index.php` ahora, bajo el servidor
  embebido (`cli-server`), hace `return false` para archivos reales de `public/`
  (CSS/JS/`uploads/`) para que el servidor los entregue con su `Content-Type`.
  **El comando de arranque debe incluir el router**:
  `php -S localhost:8000 -t public public/index.php` (actualizado en
  `composer.json`, `README.md` y `ARQUITECTURA.md`).
- **Generación de ids.** `JsonDatabase::insert` genera ids con
  `bin2hex(random_bytes(8))` (16 hex, sin punto) en vez de `uniqid('', true)`.
- **Helpers globales** (`e`, `csrf_field`, `flash`, `csrf_token`) se cargan en
  `bootstrap.php` (antes se cargaban en el layout, demasiado tarde).
- **Hover de filas de tabla** con token adaptable al tema (`--fila-hover`): en
  oscuro dejaba texto claro sobre fondo casi blanco (invisible); ahora legible en
  ambos temas.

### Corregido

- **Detalle de producto daba 404**: causado por el punto en los ids antiguos, que
  el servidor embebido interpretaba como archivo estático. Resuelto con ids sin
  punto + router script.
- **Página sin estilos**: al añadir el router, el CSS se enrutaba (404); resuelto
  con el servido de estáticos en `index.php`.
- **Verificación CSRF server-side** global de todo POST en `public/index.php`
  (`hash_equals`, responde `419`).
- **`ProducerController::updateOrderStatus`** ahora verifica la **propiedad** del
  pedido (`orderHasMyProduct`) antes de cambiar su estado (403 si no le pertenece).
- Correcciones de contraste AA en textos secundarios, chips y badges.

### Notas

- La **base de datos** puede vaciarse (`users/products/orders.json` = `[]`); tras
  ello no hay cuentas demo. Restaurar con `php scripts/seed.php`.
- Validado end-to-end (peticiones HTTP reales): registro, login por rol, logout,
  protección de rutas, 403 por rol, catálogo (búsqueda/filtro), CRUD de productos,
  flujo de pedidos consumidor→productor→admin, CSRF (419) y subida de imágenes
  (incluido el rechazo de archivos no-imagen).

## 2026-07-18

### Añadido

- **Estructura inicial del proyecto**: marketplace de productores y consumidores
  en PHP puro (MVC, sin framework). Front controller, router, controlador base,
  vistas, contenedor, autenticación por sesión con roles (`consumer`, `producer`,
  `admin`), helpers y capa de persistencia intercambiable
  (`DatabaseInterface` + `JsonDatabase` activo; `PostgresDatabase` y
  `MongoDatabase` como stubs listos para implementar).
- Módulos: Inicio, Registro/Login, Catálogo, Detalle de producto, Perfil del
  productor (CRUD + pedidos), Perfil del consumidor, Panel de administración.
- `scripts/seed.php` con datos de demostración; esquema `sql/schema.sql`;
  documentación por módulo (`docs/modules/`) y de base de datos (`docs/db/`).
