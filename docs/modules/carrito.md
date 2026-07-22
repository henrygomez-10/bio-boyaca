# Módulo Carrito y Checkout

## Propósito

Carrito de compras multi-producto y confirmación del pedido. Sustituye a la compra
de "un producto por pedido" que antes vivía en `ConsumerController::placeOrder`
(eliminado). El carrito vive en la **sesión**, no en la base de datos: es un estado
temporal del navegador que solo se convierte en datos persistentes cuando el
consumidor confirma el pedido.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/carrito` | `CartController@show` |
| POST | `/carrito/agregar` | `CartController@add` |
| POST | `/carrito/cantidad` | `CartController@updateQty` |
| POST | `/carrito/eliminar` | `CartController@remove` |
| POST | `/pedido` | `CartController@checkout` |

Definidas en `routes/web.php`. `POST /carrito/agregar` se invoca tanto desde las
tarjetas del catálogo (`src/Views/catalog/index.php`) como desde la ficha del
producto (`src/Views/product/show.php`).

## Archivos involucrados

- `src/Core/Cart.php` (estado del carrito en sesión)
- `src/Controllers/CartController.php`
- `src/Views/cart/show.php`
- `src/Repositories/ProductRepository.php`, `OrderRepository.php`, `UserRepository.php`
- `src/Models/Order.php` (`computeSubtotal()`, `computeShipping()`, `computeTotal()`, `localities()`, `SHIPPING_FEE`, `STATUS_PENDING`)

## `App\Core\Cart` (estado en sesión)

Estructura: `$_SESSION['cart'] = [ '<product_id>' => <qty:int>, ... ]`.

Se guarda **únicamente el id y la cantidad**. El nombre, el precio y el stock se
resuelven contra el repositorio cada vez que se pinta el carrito, para que un
cambio de precio o una rotura de stock se reflejen **antes** de pagar.

| Método | Uso |
|---|---|
| `items()` | Mapa `product_id => cantidad` (array vacío si no hay carrito). |
| `add(id, qty=1)` | Añade unidades **acumulando** si el producto ya estaba. |
| `setQty(id, qty)` | Fija la cantidad exacta de una línea; `qty < 1` la elimina. Solo actúa sobre líneas ya existentes. |
| `remove(id)` | Quita una línea. |
| `clear()` | Vacía el carrito (tras confirmar el pedido). |
| `count()` | Total de unidades, para el contador `.cart-badge` del encabezado. |
| `isEmpty()` | ¿No hay líneas? |

Salvaguarda: `MAX_QTY = 99` unidades por línea, aplicada tanto en `add()` como en
`setQty()`, para acotar entradas absurdas.

## Flujo

### Añadir al carrito (`add`)

1. Lee `product_id` y `qty` (`qty` se normaliza con `max(1, ...)`).
2. Busca el producto; si no existe, flash de error y redirige a `/catalogo`.
3. Si `stock < 1`, flash de error "Ese producto está agotado." y redirige a `/catalogo`.
4. `Cart::add()` y flash de éxito con el nombre del producto.
5. Redirige a `return_to` **solo si es una ruta interna** (`safeReturnTo()`); si no, al catálogo. Así el consumidor vuelve al catálogo con su búsqueda/filtro intactos sin abrir un vector de redirección abierta hacia otro dominio.

### Resumen del pedido (`show`)

1. `resolveLines()` convierte el mapa `id => qty` de la sesión en líneas completas leyendo cada producto del repositorio. Los productos que ya no existen se **descartan y se sacan del carrito**, para no arrastrar basura hasta el pago.
2. Calcula el desglose con `Order::computeSubtotal()`, `computeShipping()` y `computeTotal()`.
3. Renderiza `cart/show` con las líneas, el desglose, `Order::localities()` y la última dirección guardada del usuario (`lastAddress()`, que tolera que **no haya sesión**).

### Confirmar el pedido (`checkout`)

1. Exige rol `consumer` (`requireRole('consumer')`) — **único punto del módulo que pide sesión**.
2. Si el carrito quedó vacío, flash de error y redirige a `/catalogo`.
3. Valida la **dirección de entrega**: `locality` debe estar en `Order::localities()` y `address` no puede estar en blanco. Si falla, vuelve a `/carrito`.
4. **Revalida el stock** justo antes de cobrar: entre que se llenó el carrito y ahora, otro consumidor puede haber comprado las últimas unidades. Si alguna línea excede el stock, flash de error nombrando el producto y vuelve a `/carrito`.
5. Crea el pedido en `orders` con el desglose ya calculado (`subtotal`, `shipping`, `total`), `locality`, `address` y `status = STATUS_PENDING`.
6. Descuenta el stock de cada producto.
7. Guarda `locality` y `address` en el usuario para prellenar el próximo pedido.
8. `Cart::clear()`, flash de éxito y redirige a `/consumidor/pedidos`.

## Datos / colecciones

- `orders`: crea documentos con `consumer_id`, `items[]`, `subtotal`, `shipping`, `total`, `locality`, `address`, `status`.
- `products`: lee precio/stock y actualiza el stock tras la compra.
- `users`: actualiza `locality` y `address` (última dirección usada).

Estructura de un pedido:

```
[
  'id'          => string,
  'consumer_id' => string,
  'items'       => [
     ['product_id'=>..., 'producer_id'=>..., 'name'=>..., 'price'=>...,
      'qty'=>..., 'unit'=>..., 'origin'=>...],
  ],
  'subtotal'    => float,   // suma de price * qty de las líneas
  'shipping'    => float,   // 6000.0 (tarifa plana Boyacá-Bogotá)
  'total'       => float,   // subtotal + shipping
  'locality'    => string,  // localidad de Bogotá
  'address'     => string,
  'status'      => 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled',
  'created_at'  => string ISO-8601,
]
```

Las líneas **congelan** `name`, `price`, `unit` y `origin` en el momento de la
compra (`toOrderItems()`): el histórico no debe cambiar si el productor edita el
producto más tarde. Por la misma razón el pedido persiste su desglose económico ya
calculado y no lo recalcula al mostrarlo.

## Reglas de negocio / validaciones

- **El carrito no exige sesión.** Se puede llenar como invitado; la autenticación se pide solo al confirmar, para no cortar la navegación del catálogo. Al redirigir a `/login`, el carrito sobrevive porque vive en la misma sesión.
- **Solo el rol `consumer` puede confirmar.** Un `producer` o `admin` autenticado que haga `POST /pedido` recibe `403`.
- **Logística**: `Order::SHIPPING_FEE = 6000.0`, tarifa **plana por pedido** (no por línea ni por productor). Un carrito vacío no paga envío (`computeShipping()` devuelve `0.0`). **`Order::computeTotal()` incluye el envío.**
- **Cantidad**: mínimo 1 (se corrige silenciosamente), máximo 99 por línea.
- **Stock**: se comprueba al añadir (`stock >= 1`) y se **revalida por línea** en el checkout contra la cantidad pedida.
- **Localidad**: lista cerrada (`Order::localities()`, 19 localidades de Bogotá); se valida en servidor con `in_array(..., true)`, no solo en el `<select>`.
- El descuento de stock **no es atómico** con la creación del pedido: son operaciones separadas sobre el driver JSON, que no tiene transacciones. Una falla intermedia podría dejar inconsistencia.

## Control de acceso

| Acción | Requisito |
|---|---|
| `show`, `add`, `updateQty`, `remove` | Ninguno (público, incluso sin sesión). |
| `checkout` | `requireRole('consumer')`. |

Todos los formularios del módulo son `POST` y pasan por la verificación CSRF
global de `public/index.php`.

## Notas / mejoras futuras

- No hay cancelación del pedido desde el lado del consumidor (solo el productor cambia `status`).
- Falta manejo transaccional/rollback si la actualización de stock fallara tras crear el pedido.
- La tarifa de envío es una constante; una mejora futura sería calcularla por localidad o por peso/volumen del pedido.
- El carrito se pierde al expirar la sesión (2 horas); no se persiste para usuarios autenticados.
