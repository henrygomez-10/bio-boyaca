<?php
/**
 * src/Core/Cart.php
 * -----------------------------------------------------------------------------
 * Carrito de compras. Vive en la SESIÓN, no en la base de datos: es un estado
 * temporal del navegador que solo se convierte en datos persistentes cuando el
 * consumidor confirma el pedido.
 *
 * Estructura en sesión:
 *   $_SESSION['cart'] = [ '<product_id>' => <qty:int>, ... ]
 *
 * Se guarda únicamente el id y la cantidad. El nombre, el precio y el stock se
 * resuelven contra el repositorio cada vez que se pinta el carrito, para que un
 * cambio de precio o una rotura de stock se reflejen antes de pagar.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

final class Cart
{
    /** Cantidad máxima por línea, como salvaguarda ante entradas absurdas. */
    private const MAX_QTY = 99;

    /** @return array<string,int> Mapa product_id => cantidad. */
    public static function items(): array
    {
        $cart = $_SESSION['cart'] ?? [];
        return is_array($cart) ? $cart : [];
    }

    /** Añade unidades de un producto (acumula si ya estaba en el carrito). */
    public static function add(string $productId, int $qty = 1): void
    {
        if ($productId === '' || $qty < 1) {
            return;
        }
        $cart = self::items();
        $cart[$productId] = min(self::MAX_QTY, ($cart[$productId] ?? 0) + $qty);
        $_SESSION['cart'] = $cart;
    }

    /** Fija la cantidad exacta de una línea. Cantidad 0 o menor = eliminarla. */
    public static function setQty(string $productId, int $qty): void
    {
        if ($qty < 1) {
            self::remove($productId);
            return;
        }
        $cart = self::items();
        if (isset($cart[$productId])) {
            $cart[$productId] = min(self::MAX_QTY, $qty);
            $_SESSION['cart'] = $cart;
        }
    }

    public static function remove(string $productId): void
    {
        $cart = self::items();
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
    }

    /** Vacía el carrito (tras confirmar el pedido). */
    public static function clear(): void
    {
        $_SESSION['cart'] = [];
    }

    /** Número total de unidades, para el contador del icono del carrito. */
    public static function count(): int
    {
        return array_sum(self::items());
    }

    public static function isEmpty(): bool
    {
        return self::items() === [];
    }
}
