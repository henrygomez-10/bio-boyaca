# Módulo Perfil del Productor

## Propósito

Panel privado para usuarios con rol `producer`: gestión (CRUD) de sus propios productos y gestión del estado de los pedidos que incluyen sus productos.

> La **billetera** (ingresos, gráfico de ventas y retiros) vive en el mismo
> controlador pero se documenta aparte, por tener reglas de cálculo propias:
> ver [`billetera.md`](./billetera.md).

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/productor` | `ProducerController@dashboard` |
| GET | `/productor/billetera` | `ProducerController@wallet` |
| POST | `/productor/billetera/retiro` | `ProducerController@withdraw` |
| GET | `/productor/productos` | `ProducerController@products` |
| GET | `/productor/productos/nuevo` | `ProducerController@createForm` |
| POST | `/productor/productos` | `ProducerController@store` |
| GET | `/productor/productos/{id}/editar` | `ProducerController@editForm` |
| POST | `/productor/productos/{id}` | `ProducerController@update` |
| POST | `/productor/productos/{id}/eliminar` | `ProducerController@destroy` |
| GET | `/productor/pedidos` | `ProducerController@orders` |
| POST | `/productor/pedidos/{id}/estado` | `ProducerController@updateOrderStatus` |

Definidas en `routes/web.php`.

## Archivos involucrados

- `src/Controllers/ProducerController.php`
- `src/Views/producer/dashboard.php`, `producer/products.php`, `producer/product_form.php`, `producer/orders.php`, `producer/wallet.php`
- `src/Repositories/ProductRepository.php`, `src/Repositories/OrderRepository.php`, `src/Repositories/WithdrawalRepository.php`
- `src/Models/Product.php` (`categories()`, `categoryHints()`, `units()`, `origins()`)
- `src/Models/Order.php` (`statuses()`, `inTransitStatuses()`)

## Datos / colecciones

- `products`: crea/edita/elimina documentos con `producer_id` = id del usuario en sesión.
- `orders`: solo lectura y actualización del campo `status`; los pedidos se consultan con `OrderRepository::ofProducer($producerId)`, que filtra en memoria los pedidos donde **al menos un `item`** tenga `item['producer_id'] === $producerId`. Con el carrito multi-producto, un mismo pedido **sí puede mezclar productos de varios productores**, así que cada productor ve el pedido completo aunque solo le pertenezcan algunas líneas.
- `withdrawals`: solicitudes de retiro (ver [`billetera.md`](./billetera.md)).

## Reglas de negocio / validaciones

### CRUD de productos (`validateProduct()` en `ProducerController`)

| Campo | Regla |
|---|---|
| `name` | Obligatorio. |
| `category` | Debe estar en `Product::categories()` (8 categorías cerradas). |
| `price` | Debe ser `> 0`. |
| `stock` | No puede ser negativo (`>= 0`). |
| `unit` | **Obligatorio.** Debe estar en `Product::units()`. |
| `origin` | **Obligatorio.** Debe estar en `Product::origins()` (municipios de Boyacá). |
| `description` | Opcional (se guarda con `trim`). |

Si hay errores, se vuelve a renderizar `producer/product_form` con los valores enviados y los mensajes de error por campo.

### Catálogo cerrado del producto (`App\Models\Product`)

El formulario no acepta texto libre en estos tres campos: todos se validan en servidor con `in_array(..., true)` contra listas fijas, para mantener consistentes los datos del catálogo.

- **`categories()`** — 8 categorías: Lácteos, Huevos, Carne, Miel, Tubérculos y raíces, Hortalizas, Café, Arepas tradicionales.
- **`categoryHints()`** — texto de ayuda por categoría, que se muestra bajo el selector para que el productor sepa qué entra en cada una (p. ej. *Miel → "De abejas o de caña"*). Acceso individual con `categoryHint($category)`.
- **`units()`** — unidad de venta: Unidad, Libra, Kilogramo, Arroba, Docena, Botella, Litro, Bolsa, Paquete. Se muestra junto al precio y al stock, y viaja congelada en la línea del pedido.
- **`origins()`** — 34 municipios de Boyacá como origen del producto (Chiquinquirá, Duitama, Moniquirá, Samacá, Tunja, Villa de Leyva…). Se muestra en la ficha como *Origen: {municipio}, Boyacá*.
- **`categorySlug()`** — slug estable sin acentos ni espacios (`lacteos`, `huevos`, `carne`, `miel`, `tuberculos`, `hortalizas`, `cafe`, `arepas`) usado como `data-cat` en las vistas; el CSS asocia cada slug a un matiz dentro de la identidad verde.

### Control de propiedad (`ownedProductOrFail()`)

Antes de editar/actualizar/eliminar un producto (`editForm`, `update`, `destroy`), el controlador:
1. Busca el producto por id (`ProductRepository::find`).
2. Si no existe → `404` (`errors/404`).
3. Si `product['producer_id'] !== $me['id']` → `403` (`errors/403`).

Esto impide que un productor edite o borre productos de otro productor, aunque conozca el id.

### Imagen del producto (`App\Core\ImageUploader`)

El formulario (`enctype="multipart/form-data"`) permite subir una imagen **opcional**
por producto. La lógica vive en `App\Core\ImageUploader`, no en el controlador:

- **Validación de seguridad**: solo archivos realmente subidos (`is_uploaded_file`),
  verifica que el contenido sea una imagen real con `getimagesize` (no confía en la
  extensión ni en el mime del navegador), lista blanca JPG/PNG/WEBP/GIF y límite de 2 MB.
- **Almacenamiento**: se guarda en `public/uploads/products/` con un nombre aleatorio
  (`bin2hex(random_bytes(8)).ext`); en el producto se guarda la ruta pública en el
  campo `image` (ej. `/uploads/products/ab12….png`). Los archivos subidos se ignoran
  en git (ver `.gitignore`).
- **Alta/edición/borrado**: `store` la asigna si se envió; `update` reemplaza y borra la
  anterior (evita huérfanos); `destroy` borra también el archivo del disco.
- **Presentación**: cuando el producto tiene `image`, las vistas (`home`, `catalog`,
  `product/show`, tabla del productor) muestran la foto (`object-fit: cover`); si no,
  usan el medallón con la inicial como respaldo.

### Estado de pedidos (`updateOrderStatus`)

- El nuevo `status` debe existir en `Order::statuses()`, que ahora tiene **5 estados**: `pending` (Pendiente), `confirmed` (Confirmado), `shipped` (**En tránsito**), `delivered` (Entregado) y `cancelled` (Cancelado). Si no, se muestra un flash de error y se redirige sin aplicar el cambio.
- `shipped` cubre el trayecto Boyacá→Bogotá. Junto con `confirmed` forma `Order::inTransitStatuses()`, el conjunto de "venta en curso" que usa la billetera.
- **Control de propiedad**: antes de aplicar el cambio, `updateOrderStatus` recupera el pedido y verifica con `orderHasMyProduct()` que contenga al menos un ítem cuyo `producer_id` sea el del productor autenticado. Si el pedido no existe o no le pertenece, responde `403` con `errors/403`. Así un productor no puede alterar pedidos ajenos aunque conozca su id.

## Control de acceso

Todas las acciones (incluidas `wallet` y `withdraw`) llaman `$this->requireRole('producer')` al inicio (vía `Core\Controller::requireRole()`), lo que exige sesión iniciada y rol `producer`; de lo contrario redirige a `/login` (sin sesión) o responde `403` (rol distinto).

## Interfaz

En móvil (≤720px) el productor tiene su propia **navegación inferior** (`.bottom-nav`) con cuatro destinos: Panel, Productos, Pedidos y Billetera — distinta de la del consumidor. El ítem activo se marca comparando el prefijo de la ruta actual.

## Notas / mejoras futuras

- Si un pedido tiene productos de varios productores, cualquiera de ellos (todos son "dueños" de una línea) puede cambiar el `status` global del pedido completo, porque el estado es único por pedido y no por línea/ítem. Con el carrito multi-producto este escenario dejó de ser hipotético. Una mejora futura sería llevar el estado a nivel de ítem/subpedido por productor.
- No hay paginación en `productor/productos` ni en `productor/pedidos`.
