# Módulo Billetera del Productor

## Propósito

Vista económica del productor: cuánto ha vendido, cómo se reparten sus ventas a lo
largo del mes y cuánto tiene disponible para retirar. Vive en
`ProducerController` (junto al resto del perfil del productor) pero se documenta
aparte por tener reglas de cálculo propias.

> ⚠️ **Los retiros son SIMULADOS.** No hay pasarela de pago ni transferencia real
> de dinero: se registra la solicitud en la colección `withdrawals` para dejar
> traza y descontar el saldo de forma coherente, nada más. Cuando se conecte una
> pasarela, `WithdrawalRepository` es el punto donde enganchar el estado real de
> la transferencia.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/productor/billetera` | `ProducerController@wallet` |
| POST | `/productor/billetera/retiro` | `ProducerController@withdraw` |

Definidas en `routes/web.php`. Ambas exigen rol `producer`.

## Archivos involucrados

- `src/Controllers/ProducerController.php` (`wallet()`, `withdraw()`, `producerRevenue()`, `weekOfMonth()`)
- `src/Views/producer/wallet.php`
- `src/Repositories/WithdrawalRepository.php` (colección `withdrawals`)
- `src/Repositories/OrderRepository.php` (`ofProducer()`)
- `src/Models/Order.php` (`STATUS_DELIVERED`, `inTransitStatuses()`)

## Regla de cálculo fundamental

**Los ingresos se calculan SOLO sobre las líneas del pedido que pertenecen a ese
productor — nunca sobre `order['total']`.** Hay dos razones:

1. Un pedido puede **mezclar productos de varios productores**; el total del pedido no es de nadie en particular.
2. `order['total']` **incluye la logística** (`Order::SHIPPING_FEE`), que no le corresponde al productor.

La suma la hace `producerRevenue($order, $producerId)`: recorre `order['items']` y
acumula `price * qty` solo de las líneas cuyo `producer_id` coincide.

## Métricas (`wallet`)

Partiendo de `OrderRepository::ofProducer($me['id'])`:

| Dato | Cómo se calcula |
|---|---|
| `monthIncome` | Ingresos de pedidos **entregados** (`delivered`) creados en el **mes en curso**. Es la cifra principal (`.wallet-hero`). |
| `delivered` | Número de pedidos entregados del mes en curso. |
| `inTransit` | Número de pedidos del mes en curso en estado `confirmed` o `shipped` (`Order::inTransitStatuses()`). |
| `weeks` | Mapa `1..4 => ingresos`, para el gráfico `.chart` de comportamiento de ventas del mes. |
| `available` | `lifetime - withdrawals->totalWithdrawn()`, acotado a `>= 0`. |
| `withdrawals` | Historial de solicitudes del productor, de la más reciente a la más antigua. |

Matices:

- **`lifetime`** (ingresos entregados de todos los tiempos) no se muestra directamente: es la base del saldo disponible.
- El **gráfico incluye lo entregado y lo que ya está en tránsito** (`confirmed`/`shipped`): es venta hecha aunque aún no se haya cobrado. En cambio `monthIncome` y el saldo disponible cuentan **solo lo entregado**.
- `weekOfMonth()` reparte por semanas con `ceil(día / 7)` acotado a 4: los días 22 en adelante caen todos en la semana 4.

## Retiro de fondos (`withdraw`)

1. Exige rol `producer`.
2. **Recalcula el disponible en el servidor** recorriendo los pedidos entregados y restando lo ya retirado. Nunca confía en un importe que venga del formulario.
3. Si `available <= 0`, flash de error y vuelve a `/productor/billetera`.
4. Crea un documento en `withdrawals` con `producer_id`, `amount` (el disponible **completo**) y `status = 'requested'`.
5. Flash de éxito con el importe formateado y redirige a la billetera.

No hay retiro parcial: se solicita todo el saldo disponible de una vez.

## Datos / colecciones

Colección `withdrawals` (`WithdrawalRepository`):

```
[
  'id'          => string,
  'producer_id' => string,
  'amount'      => float,
  'status'      => string,   // 'requested'
  'created_at'  => string ISO-8601,
]
```

- `ofProducer(id)`: retiros del productor ordenados del más reciente al más antiguo.
- `totalWithdrawn(id)`: suma de todo lo ya retirado (o en proceso), que se descuenta del saldo disponible.

`scripts/seed.php` limpia también esta colección al sembrar.

## Control de acceso

Ambas acciones llaman `requireRole('producer')`. El `POST` de retiro pasa por la
verificación CSRF global.

## Notas / mejoras futuras

- **Sin pasarela de pago**: el retiro no mueve dinero real. El `status` de un retiro nunca cambia de `'requested'` porque no hay proceso que lo actualice.
- El docblock de `WithdrawalRepository` menciona un `App\Models\Withdrawal` para los estados del retiro, pero **esa clase no existe todavía**: el estado se escribe como string literal en el controlador.
- El gráfico asume meses de 4 semanas; los días 29–31 se agrupan en la semana 4.
- Las métricas recorren todos los pedidos del productor en memoria (`O(n)`), coherente con el driver JSON pero a revisar si se migra a un motor con agregaciones.
- No hay retiro parcial ni definición de cuenta bancaria de destino.
