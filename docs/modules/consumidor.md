# Módulo Perfil del Consumidor

## Propósito

Panel privado para usuarios con rol `consumer`: resumen de cuenta e historial de
pedidos.

> La **compra en sí** (carrito, dirección de entrega y confirmación del pedido) ya
> no vive aquí: se trasladó a `CartController`. Ver [`carrito.md`](./carrito.md).
> El antiguo `ConsumerController::placeOrder` fue **eliminado**, y la ruta
> `POST /pedido` apunta ahora a `CartController@checkout`.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/consumidor` | `ConsumerController@dashboard` |
| GET | `/consumidor/pedidos` | `ConsumerController@orders` |

Definidas en `routes/web.php`. A `/consumidor/pedidos` se llega también por
redirección tras confirmar un pedido en el checkout.

## Archivos involucrados

- `src/Controllers/ConsumerController.php`
- `src/Views/consumer/dashboard.php`, `src/Views/consumer/orders.php`
- `src/Repositories/OrderRepository.php`
- `src/Models/Order.php` (`statuses()`)

## Flujo

Ambas acciones son de solo lectura:

1. Exigen rol `consumer` (`requireRole('consumer')`).
2. Recuperan los pedidos con `OrderRepository::ofConsumer($me['id'])`.
3. `orders` pasa además `Order::statuses()` para pintar la etiqueta legible del estado.

## Datos / colecciones

- `orders`: solo lectura, filtrando por `consumer_id`.

Cada pedido trae su desglose económico ya congelado (`subtotal`, `shipping`,
`total`), la dirección de entrega (`locality`, `address`) y las líneas `items[]`
con `name`, `price`, `qty`, `unit` y `origin` capturados en el momento de la
compra. La vista no recalcula nada: muestra lo que se guardó, de modo que un
cambio posterior de precio o de tarifa de envío no altera el histórico.

Estados posibles de un pedido (`Order::statuses()`, 5 en total):

| Estado | Etiqueta |
|---|---|
| `pending` | Pendiente |
| `confirmed` | Confirmado |
| `shipped` | En tránsito |
| `delivered` | Entregado |
| `cancelled` | Cancelado |

## Control de acceso

`dashboard` y `orders` llaman `requireRole('consumer')`: exigen sesión y rol
`consumer`. Sin sesión se redirige a `/login`; con otro rol se responde `403`
(`errors/403`).

## Notas / mejoras futuras

- No hay cancelación de pedido desde el lado del consumidor (solo el productor puede cambiar `status` vía `/productor/pedidos/{id}/estado`).
- No hay paginación en el historial de pedidos.
- El consumidor no puede editar su perfil; su dirección de entrega solo se actualiza como efecto secundario del checkout (se guarda la última usada para prellenar la siguiente compra).
