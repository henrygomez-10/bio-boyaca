# Módulo Detalle del Producto

## Propósito

Ficha completa de un producto individual: información, unidad de venta, municipio de origen, stock disponible, datos del productor que lo publica y el formulario para añadirlo al carrito.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/producto/{id}` | `ProductController@show` |

Definida en `routes/web.php`. `{id}` es un parámetro dinámico capturado por el `Router` (`App\Core\Router::patternToRegex`) y entregado como `$params['id']`.

## Archivos involucrados

- `src/Controllers/ProductController.php`
- `src/Views/product/show.php`
- `src/Repositories/ProductRepository.php`
- `src/Repositories/UserRepository.php`
- `src/Models/Product.php` (`formatPrice()`)
- `src/Core/helpers.php` (`csrf_field()`, usado en el formulario de añadir al carrito)

## Flujo

1. `ProductController::show($params)` busca el producto por `$params['id']` con `ProductRepository::find()`.
2. Si no existe, responde `404` y renderiza `errors/404`.
3. Si existe, busca al productor dueño (`product['producer_id']`) con `UserRepository::find()` (puede ser `null` si el usuario fue eliminado).
4. Renderiza `product/show` con `product` y `producer`.

## Datos / colecciones

- Colección `products`: `id`, `name`, `category`, `price`, `description`, `stock`, `producer_id`, `unit`, `origin`, `image`.
- Colección `users`: datos del productor (`name`), mostrados como "Vendido por …".

La ficha muestra el precio acompañado de la **unidad de venta** (`/ Libra`, `/ Docena`…) y una línea de procedencia: *Origen: **{municipio}**, Boyacá*.

## Reglas de negocio / validaciones

- El formulario de compra apunta a `POST /carrito/agregar` (ver [`carrito.md`](./carrito.md)) y muestra el botón **"Agregar al carrito"**. Se muestra siempre que haya `stock > 0`: **no exige sesión iniciada**, porque el carrito se puede llenar como invitado y el login se pide al pagar.
- El input de cantidad tiene `min="1"` y `max="{stock}"` en el HTML (validación de cliente, no garantiza nada en servidor).

## Control de acceso

Ruta pública para ver el producto, y también para añadirlo al carrito. El rol `consumer` solo se exige al **confirmar el pedido** (`POST /pedido` → `CartController@checkout`, ver [`carrito.md`](./carrito.md)).

## Notas / mejoras futuras

- No hay validación server-side sobre `qty` en `ProductController`; la hace `CartController` (al añadir comprueba que el producto exista y tenga stock, y revalida el stock por línea en el checkout).
- Si el productor fue eliminado pero el producto permanece, `$producer` será `null` y la vista simplemente omite el bloque "Vendido por …" (no hay integridad referencial entre colecciones, coherente con el modelo de documentos JSON).
