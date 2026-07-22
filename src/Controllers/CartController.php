<?php
/**
 * src/Controllers/CartController.php
 * -----------------------------------------------------------------------------
 * Módulo CARRITO Y CHECKOUT.
 *
 * El carrito vive en la sesión (App\Core\Cart) y NO exige sesión iniciada: se
 * puede llenar como invitado. La autenticación se pide solo al confirmar el
 * pedido, para no cortar la navegación del catálogo.
 *
 * Las líneas se re-resuelven contra el repositorio en cada pantalla, de modo que
 * el precio y el stock que ve el consumidor antes de pagar son los vigentes.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cart;
use App\Core\Controller;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;

class CartController extends Controller
{
    /** Añade un producto al carrito y vuelve a donde estaba el usuario. */
    public function add(array $params): void
    {
        $productId = (string) $this->input('product_id', '');
        $qty       = max(1, (int) $this->input('qty', 1));

        $product = (new ProductRepository($this->container->db()))->find($productId);

        if ($product === null) {
            $this->flash('error', 'El producto ya no está disponible.');
            $this->redirect('/catalogo');
        }

        if ((int) ($product['stock'] ?? 0) < 1) {
            $this->flash('error', 'Ese producto está agotado.');
            $this->redirect('/catalogo');
        }

        Cart::add($productId, $qty);
        $this->flash('success', $product['name'] . ' se agregó a tu carrito.');

        // Vuelve a la página de origen si es una ruta interna; si no, al catálogo.
        $this->redirect($this->safeReturnTo('/catalogo'));
    }

    /** Cambia la cantidad de una línea del carrito. */
    public function updateQty(array $params): void
    {
        Cart::setQty(
            (string) $this->input('product_id', ''),
            (int) $this->input('qty', 1)
        );
        $this->redirect('/carrito');
    }

    /** Quita una línea del carrito. */
    public function remove(array $params): void
    {
        Cart::remove((string) $this->input('product_id', ''));
        $this->flash('success', 'Producto eliminado del carrito.');
        $this->redirect('/carrito');
    }

    /**
     * "Resumen de tu Pedido": líneas, dirección de entrega y desglose económico
     * (subtotal + logística Boyacá-Bogotá + total a pagar).
     */
    public function show(array $params): void
    {
        $lines = $this->resolveLines();
        $items = $this->toOrderItems($lines);

        $this->render('cart/show', [
            'title'      => 'Resumen de tu pedido',
            'lines'      => $lines,
            'subtotal'   => Order::computeSubtotal($items),
            'shipping'   => Order::computeShipping($items),
            'total'      => Order::computeTotal($items),
            'localities' => Order::localities(),
            'address'    => $this->lastAddress(),
        ]);
    }

    /**
     * Confirma el pedido: valida stock y dirección, crea el pedido con el
     * desglose ya calculado, descuenta stock y vacía el carrito.
     */
    public function checkout(array $params): void
    {
        $this->requireRole('consumer');
        $me = $this->auth->user();

        $lines = $this->resolveLines();

        if ($lines === []) {
            $this->flash('error', 'Tu carrito está vacío.');
            $this->redirect('/catalogo');
        }

        // Dirección de entrega.
        $locality = (string) $this->input('locality', '');
        $address  = (string) $this->input('address', '');

        if (!in_array($locality, Order::localities(), true)) {
            $this->flash('error', 'Selecciona una localidad de Bogotá válida.');
            $this->redirect('/carrito');
        }
        if (trim($address) === '') {
            $this->flash('error', 'Escribe la dirección de entrega.');
            $this->redirect('/carrito');
        }

        // Revalida stock justo antes de cobrar: entre que se llenó el carrito y
        // ahora, otro consumidor puede haber comprado las últimas unidades.
        foreach ($lines as $line) {
            if ($line['qty'] > (int) $line['product']['stock']) {
                $this->flash('error', 'No hay stock suficiente de "'
                    . $line['product']['name'] . '". Ajusta la cantidad.');
                $this->redirect('/carrito');
            }
        }

        $items = $this->toOrderItems($lines);

        (new OrderRepository($this->container->db()))->create([
            'consumer_id' => $me['id'],
            'items'       => $items,
            'subtotal'    => Order::computeSubtotal($items),
            'shipping'    => Order::computeShipping($items),
            'total'       => Order::computeTotal($items),
            'locality'    => $locality,
            'address'     => trim($address),
            'status'      => Order::STATUS_PENDING,
        ]);

        // Descuenta stock de cada producto.
        $products = new ProductRepository($this->container->db());
        foreach ($lines as $line) {
            $products->update($line['product']['id'], [
                'stock' => (int) $line['product']['stock'] - $line['qty'],
            ]);
        }

        // Recuerda la dirección para prellenar el próximo pedido.
        (new UserRepository($this->container->db()))->update($me['id'], [
            'locality' => $locality,
            'address'  => trim($address),
        ]);

        Cart::clear();

        $this->flash('success', '¡Pedido confirmado! Gracias por comprarle al campo boyacense.');
        $this->redirect('/consumidor/pedidos');
    }

    // -------------------------------------------------------------------------
    // Utilidades privadas.
    // -------------------------------------------------------------------------

    /**
     * Convierte el mapa id=>cantidad de la sesión en líneas completas, leyendo
     * cada producto del repositorio. Descarta los productos que ya no existen
     * (y los saca del carrito) para no arrastrar basura hasta el pago.
     *
     * @return array<int,array{product:array<string,mixed>,qty:int,subtotal:float}>
     */
    private function resolveLines(): array
    {
        $repo  = new ProductRepository($this->container->db());
        $lines = [];

        foreach (Cart::items() as $productId => $qty) {
            $product = $repo->find((string) $productId);

            if ($product === null) {
                Cart::remove((string) $productId);
                continue;
            }

            $qty = max(1, (int) $qty);

            $lines[] = [
                'product'  => $product,
                'qty'      => $qty,
                'subtotal' => (float) $product['price'] * $qty,
            ];
        }

        return $lines;
    }

    /**
     * Traduce las líneas resueltas al formato 'items' que persiste el pedido.
     * Se congelan nombre y precio: el histórico no debe cambiar si el productor
     * edita el producto más tarde.
     *
     * @return array<int,array<string,mixed>>
     */
    private function toOrderItems(array $lines): array
    {
        return array_map(static fn (array $line): array => [
            'product_id'  => $line['product']['id'],
            'producer_id' => $line['product']['producer_id'] ?? null,
            'name'        => $line['product']['name'],
            'price'       => (float) $line['product']['price'],
            'qty'         => $line['qty'],
            'unit'        => $line['product']['unit']   ?? null,
            'origin'      => $line['product']['origin'] ?? null,
        ], $lines);
    }

    /**
     * Dirección guardada del consumidor, para prellenar el checkout.
     *
     * @return array{locality:string,address:string}
     */
    private function lastAddress(): array
    {
        // Puede no haber sesión: el carrito se llena también como invitado.
        $me = $this->auth->user() ?? [];

        return [
            'locality' => (string) ($me['locality'] ?? ''),
            'address'  => (string) ($me['address']  ?? ''),
        ];
    }

    /**
     * Devuelve la URL de retorno del formulario solo si es una ruta interna.
     * Evita usarla como vector de redirección abierta hacia otro dominio.
     */
    private function safeReturnTo(string $fallback): string
    {
        $to = (string) $this->input('return_to', '');

        return (str_starts_with($to, '/') && !str_starts_with($to, '//'))
            ? $to
            : $fallback;
    }
}
