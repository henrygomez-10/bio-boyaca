<?php
/**
 * src/Models/Order.php
 * -----------------------------------------------------------------------------
 * Modelo de dominio para pedidos. En esta versión los datos viajan como arrays
 * (provenientes del driver de BD); este modelo aporta las constantes de estado
 * y utilidades de presentación para no dispersar "strings mágicos" por el código.
 *
 * Un pedido guarda el desglose económico ya calculado (subtotal, logística y
 * total) para que el histórico no cambie si mañana varía la tarifa de envío.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Models;

final class Order
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Tarifa fija de logística entre Boyacá y Bogotá. Se cobra una sola vez por
     * pedido, sin importar cuántos productos lleve.
     */
    public const SHIPPING_FEE = 6000.0;

    /** Etiqueta del cargo de logística, tal como se muestra en el resumen. */
    public const SHIPPING_LABEL = 'Logística Boyacá-Bogotá';

    /** @return array<string,string> Estado => etiqueta legible. */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING   => 'Pendiente',
            self::STATUS_CONFIRMED => 'Confirmado',
            self::STATUS_SHIPPED   => 'En tránsito',
            self::STATUS_DELIVERED => 'Entregado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    public static function label(string $status): string
    {
        return self::statuses()[$status] ?? $status;
    }

    /**
     * Estados que cuentan como venta "en curso" (ya confirmada pero aún no
     * entregada). Se usa en la billetera del productor.
     *
     * @return array<int,string>
     */
    public static function inTransitStatuses(): array
    {
        return [self::STATUS_CONFIRMED, self::STATUS_SHIPPED];
    }

    /**
     * Localidades de Bogotá a las que se entrega. Lista cerrada para mantener
     * consistente la dirección de entrega del pedido.
     *
     * @return array<int,string>
     */
    public static function localities(): array
    {
        return [
            'Antonio Nariño',
            'Barrios Unidos',
            'Bosa',
            'Chapinero',
            'Ciudad Bolívar',
            'Engativá',
            'Fontibón',
            'Kennedy',
            'La Candelaria',
            'Los Mártires',
            'Puente Aranda',
            'Rafael Uribe Uribe',
            'San Cristóbal',
            'Santa Fe',
            'Suba',
            'Teusaquillo',
            'Tunjuelito',
            'Usaquén',
            'Usme',
        ];
    }

    /** Calcula el subtotal de productos a partir de las líneas del pedido. */
    public static function computeSubtotal(array $items): float
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0);
        }
        return round($subtotal, 2);
    }

    /**
     * Costo de logística del pedido. Un carrito vacío no paga envío; cualquier
     * pedido con productos paga la tarifa plana.
     */
    public static function computeShipping(array $items): float
    {
        return $items === [] ? 0.0 : self::SHIPPING_FEE;
    }

    /** Total a pagar = subtotal de productos + logística. */
    public static function computeTotal(array $items): float
    {
        return round(self::computeSubtotal($items) + self::computeShipping($items), 2);
    }
}
