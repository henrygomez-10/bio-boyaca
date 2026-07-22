<?php
/**
 * src/Repositories/OrderRepository.php
 * -----------------------------------------------------------------------------
 * Acceso a la colección "orders".
 *
 * Un pedido (order) tiene la forma:
 *   [
 *     'id'          => string,
 *     'consumer_id' => string,           // quién compra
 *     'items'       => [                 // líneas del pedido
 *        ['product_id'=>..., 'producer_id'=>..., 'name'=>..., 'price'=>..., 'qty'=>...],
 *        ...
 *     ],
 *     'total'       => float,
 *     'status'      => string,           // ver App\Models\Order
 *     'created_at'  => string(ISO-8601),
 *   ]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

class OrderRepository extends BaseRepository
{
    protected string $collection = 'orders';

    /** Pedidos realizados por un consumidor. */
    public function ofConsumer(string $consumerId): array
    {
        return $this->where(['consumer_id' => $consumerId]);
    }

    /**
     * Pedidos que contienen al menos un producto de un productor dado.
     * (Se filtra en memoria porque el criterio está dentro de 'items'.)
     *
     * @return array<int,array<string,mixed>>
     */
    public function ofProducer(string $producerId): array
    {
        return array_values(array_filter(
            $this->all(),
            static function (array $order) use ($producerId): bool {
                foreach ($order['items'] ?? [] as $item) {
                    if (($item['producer_id'] ?? null) === $producerId) {
                        return true;
                    }
                }
                return false;
            }
        ));
    }
}
