<?php
/**
 * src/Repositories/WithdrawalRepository.php
 * -----------------------------------------------------------------------------
 * Acceso a la colección "withdrawals" (retiros de fondos del productor).
 *
 * NOTA: los retiros son SIMULADOS. Se registra la solicitud para dejar traza en
 * la billetera, pero no existe integración con ninguna pasarela de pago ni
 * movimiento de dinero real. Cuando se conecte una pasarela, este repositorio es
 * el punto donde enganchar el estado real de la transferencia.
 *
 * Un retiro (withdrawal) tiene la forma:
 *   [
 *     'id'          => string,
 *     'producer_id' => string,
 *     'amount'      => float,
 *     'status'      => string,            // 'requested' (único estado por ahora)
 *     'created_at'  => string(ISO-8601),
 *   ]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

class WithdrawalRepository extends BaseRepository
{
    protected string $collection = 'withdrawals';

    /** Retiros solicitados por un productor, del más reciente al más antiguo. */
    public function ofProducer(string $producerId): array
    {
        $rows = $this->where(['producer_id' => $producerId]);

        usort($rows, static fn (array $a, array $b): int
            => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $rows;
    }

    /** Suma de todo lo ya retirado (o en proceso) por un productor. */
    public function totalWithdrawn(string $producerId): float
    {
        $total = 0.0;
        foreach ($this->ofProducer($producerId) as $row) {
            $total += (float) ($row['amount'] ?? 0);
        }
        return round($total, 2);
    }
}
