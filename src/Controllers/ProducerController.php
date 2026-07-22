<?php
/**
 * src/Controllers/ProducerController.php
 * -----------------------------------------------------------------------------
 * Módulo PERFIL DEL PRODUCTOR. Gestión de productos (CRUD) y de los pedidos que
 * incluyen productos suyos. Todas las acciones exigen el rol 'producer'.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\WithdrawalRepository;

class ProducerController extends Controller
{
    /** Panel resumen del productor. */
    public function dashboard(array $params): void
    {
        $this->requireRole('producer');
        $me = $this->auth->user();

        $products = new ProductRepository($this->container->db());
        $orders   = new OrderRepository($this->container->db());

        $this->render('producer/dashboard', [
            'title'       => 'Panel del productor',
            'myProducts'  => $products->ofProducer($me['id']),
            'myOrders'    => $orders->ofProducer($me['id']),
        ]);
    }

    /** Listado de productos del productor. */
    public function products(array $params): void
    {
        $this->requireRole('producer');
        $me   = $this->auth->user();
        $repo = new ProductRepository($this->container->db());

        $this->render('producer/products', [
            'title'    => 'Mis productos',
            'products' => $repo->ofProducer($me['id']),
        ]);
    }

    /** Formulario de creación de producto. */
    public function createForm(array $params): void
    {
        $this->requireRole('producer');
        $this->render('producer/product_form', [
            'title'      => 'Nuevo producto',
            'product'    => null,
            'categories' => Product::categories(),
            'hints'      => Product::categoryHints(),
            'units'      => Product::units(),
            'origins'    => Product::origins(),
            'action'     => '/productor/productos',
            'errors'     => [],
        ]);
    }

    /** Guarda un producto nuevo. */
    public function store(array $params): void
    {
        $this->requireRole('producer');
        $me   = $this->auth->user();
        $data = $this->validateProduct();

        // Imagen (opcional).
        $img = $this->uploader()->handle($_FILES['image'] ?? null);
        if (!$img['ok']) {
            $data['errors']['image'] = $img['error'];
        }

        if ($data['errors']) {
            $this->render('producer/product_form', [
                'title'      => 'Nuevo producto',
                'product'    => $data['values'],
                'categories' => Product::categories(),
            'hints'      => Product::categoryHints(),
            'units'      => Product::units(),
            'origins'    => Product::origins(),
                'action'     => '/productor/productos',
                'errors'     => $data['errors'],
            ]);
            return;
        }

        $extra = ['producer_id' => $me['id']];
        if ($img['path'] !== null) {
            $extra['image'] = $img['path'];
        }

        $repo = new ProductRepository($this->container->db());
        $repo->create($data['values'] + $extra);

        $this->flash('success', 'Producto creado.');
        $this->redirect('/productor/productos');
    }

    /** Formulario de edición. */
    public function editForm(array $params): void
    {
        $this->requireRole('producer');
        $product = $this->ownedProductOrFail($params['id'] ?? '');

        $this->render('producer/product_form', [
            'title'      => 'Editar producto',
            'product'    => $product,
            'categories' => Product::categories(),
            'hints'      => Product::categoryHints(),
            'units'      => Product::units(),
            'origins'    => Product::origins(),
            'action'     => '/productor/productos/' . $product['id'],
            'errors'     => [],
        ]);
    }

    /** Actualiza un producto existente. */
    public function update(array $params): void
    {
        $this->requireRole('producer');
        $product = $this->ownedProductOrFail($params['id'] ?? '');
        $data    = $this->validateProduct();

        // Imagen (opcional). Si no se sube una nueva, se conserva la actual.
        $img = $this->uploader()->handle($_FILES['image'] ?? null);
        if (!$img['ok']) {
            $data['errors']['image'] = $img['error'];
        }

        if ($data['errors']) {
            $this->render('producer/product_form', [
                'title'      => 'Editar producto',
                // Conserva la imagen actual para que la vista previa siga visible.
                'product'    => $data['values'] + ['id' => $product['id'], 'image' => $product['image'] ?? null],
                'categories' => Product::categories(),
            'hints'      => Product::categoryHints(),
            'units'      => Product::units(),
            'origins'    => Product::origins(),
                'action'     => '/productor/productos/' . $product['id'],
                'errors'     => $data['errors'],
            ]);
            return;
        }

        $changes = $data['values'];
        if ($img['path'] !== null) {
            $changes['image'] = $img['path'];
            // Borra la imagen anterior para no dejar archivos huérfanos.
            $this->uploader()->delete($product['image'] ?? null);
        }

        $repo = new ProductRepository($this->container->db());
        $repo->update($product['id'], $changes);

        $this->flash('success', 'Producto actualizado.');
        $this->redirect('/productor/productos');
    }

    /** Elimina un producto. */
    public function destroy(array $params): void
    {
        $this->requireRole('producer');
        $product = $this->ownedProductOrFail($params['id'] ?? '');

        // Borra también su imagen del disco, si tiene.
        $this->uploader()->delete($product['image'] ?? null);

        (new ProductRepository($this->container->db()))->delete($product['id']);

        $this->flash('success', 'Producto eliminado.');
        $this->redirect('/productor/productos');
    }

    /** Pedidos que incluyen productos del productor. */
    public function orders(array $params): void
    {
        $this->requireRole('producer');
        $me   = $this->auth->user();
        $repo = new OrderRepository($this->container->db());

        $this->render('producer/orders', [
            'title'    => 'Pedidos recibidos',
            'orders'   => $repo->ofProducer($me['id']),
            'statuses' => Order::statuses(),
        ]);
    }

    /** Actualiza el estado de un pedido. */
    public function updateOrderStatus(array $params): void
    {
        $this->requireRole('producer');
        $me     = $this->auth->user();
        $status = (string) $this->input('status', '');

        if (!array_key_exists($status, Order::statuses())) {
            $this->flash('error', 'Estado no válido.');
            $this->redirect('/productor/pedidos');
        }

        $orders = new OrderRepository($this->container->db());
        $order  = $orders->find($params['id'] ?? '');

        // Control de propiedad: el productor solo puede tocar pedidos que
        // incluyan al menos un producto suyo. Evita modificar pedidos ajenos
        // conociendo su id.
        if ($order === null || !$this->orderHasMyProduct($order, $me['id'])) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Acceso denegado']);
            return;
        }

        $orders->update($order['id'], ['status' => $status]);

        $this->flash('success', 'Estado del pedido actualizado.');
        $this->redirect('/productor/pedidos');
    }

    /**
     * Billetera y ventas del productor.
     *
     * Todas las cifras se calculan SOLO sobre las líneas del pedido que
     * pertenecen a este productor: un pedido puede mezclar productos de varios
     * productores, y la logística no le corresponde a ninguno de ellos. Por eso
     * los ingresos salen de las líneas y nunca del campo 'total' del pedido
     * (que sí incluye el envío).
     */
    public function wallet(array $params): void
    {
        $this->requireRole('producer');
        $me = $this->auth->user();

        $orders      = (new OrderRepository($this->container->db()))->ofProducer($me['id']);
        $withdrawals = new WithdrawalRepository($this->container->db());

        $monthIncome  = 0.0;   // Ingresos ya entregados de ESTE mes (tarjeta principal).
        $lifetime     = 0.0;   // Ingresos entregados de todos los tiempos.
        $delivered    = 0;
        $inTransit    = 0;
        $weeks        = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];

        $thisMonth = date('Y-m');
        $transit   = Order::inTransitStatuses();

        foreach ($orders as $order) {
            $status  = (string) ($order['status'] ?? '');
            $revenue = $this->producerRevenue($order, $me['id']);
            $created = (string) ($order['created_at'] ?? '');
            $isThisMonth = str_starts_with($created, $thisMonth);

            if ($status === Order::STATUS_DELIVERED) {
                $lifetime += $revenue;
                if ($isThisMonth) {
                    $monthIncome += $revenue;
                    $delivered++;
                }
            } elseif (in_array($status, $transit, true) && $isThisMonth) {
                $inTransit++;
            }

            // Gráfico: ventas del mes en curso, contando lo entregado y lo que
            // ya está en camino (es venta hecha, aunque aún no se haya cobrado).
            if ($isThisMonth
                && ($status === Order::STATUS_DELIVERED || in_array($status, $transit, true))) {
                $weeks[$this->weekOfMonth($created)] += $revenue;
            }
        }

        $available = round($lifetime - $withdrawals->totalWithdrawn($me['id']), 2);

        $this->render('producer/wallet', [
            'title'       => 'Mi billetera y ventas',
            'monthIncome' => round($monthIncome, 2),
            'delivered'   => $delivered,
            'inTransit'   => $inTransit,
            'weeks'       => $weeks,
            'available'   => max(0.0, $available),
            'withdrawals' => $withdrawals->ofProducer($me['id']),
        ]);
    }

    /**
     * Registra una solicitud de retiro de fondos.
     *
     * SIMULADO: no hay pasarela de pago ni transferencia real. Solo se deja el
     * registro para que el saldo disponible se descuente de forma coherente.
     */
    public function withdraw(array $params): void
    {
        $this->requireRole('producer');
        $me = $this->auth->user();

        // Recalcula el disponible en el servidor: nunca confiar en un importe
        // que venga del formulario.
        $orders      = (new OrderRepository($this->container->db()))->ofProducer($me['id']);
        $withdrawals = new WithdrawalRepository($this->container->db());

        $lifetime = 0.0;
        foreach ($orders as $order) {
            if (($order['status'] ?? '') === Order::STATUS_DELIVERED) {
                $lifetime += $this->producerRevenue($order, $me['id']);
            }
        }

        $available = round($lifetime - $withdrawals->totalWithdrawn($me['id']), 2);

        if ($available <= 0) {
            $this->flash('error', 'No tienes fondos disponibles para retirar.');
            $this->redirect('/productor/billetera');
        }

        $withdrawals->create([
            'producer_id' => $me['id'],
            'amount'      => $available,
            'status'      => 'requested',
        ]);

        $this->flash('success', 'Solicitud de retiro registrada por '
            . Product::formatPrice($available)
            . '. Se transferirá a tu cuenta en los próximos días hábiles.');
        $this->redirect('/productor/billetera');
    }

    // -------------------------------------------------------------------------
    // Utilidades privadas.
    // -------------------------------------------------------------------------

    /** Suma las líneas de un pedido que pertenecen al productor indicado. */
    private function producerRevenue(array $order, string $producerId): float
    {
        $sum = 0.0;
        foreach ($order['items'] ?? [] as $item) {
            if (($item['producer_id'] ?? null) === $producerId) {
                $sum += (float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0);
            }
        }
        return $sum;
    }

    /** Semana del mes (1..4) de una fecha ISO. Los días 22+ caen en la semana 4. */
    private function weekOfMonth(string $isoDate): int
    {
        $day = (int) date('j', strtotime($isoDate) ?: time());
        return min(4, (int) ceil($day / 7));
    }

    /**
     * Valida y normaliza los datos del formulario de producto.
     *
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    private function validateProduct(): array
    {
        $errors = [];

        $name        = (string) $this->input('name', '');
        $description = (string) $this->input('description', '');
        $category    = (string) $this->input('category', '');
        $price       = (float) $this->input('price', 0);
        $stock       = (int) $this->input('stock', 0);
        $unit        = (string) $this->input('unit', '');
        $origin      = (string) $this->input('origin', '');

        if (trim($name) === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if (!in_array($category, Product::categories(), true)) {
            $errors['category'] = 'Selecciona una categoría válida.';
        }
        if ($price <= 0) {
            $errors['price'] = 'El precio debe ser mayor que 0.';
        }
        if ($stock < 0) {
            $errors['stock'] = 'El stock no puede ser negativo.';
        }
        if (!in_array($unit, Product::units(), true)) {
            $errors['unit'] = 'Selecciona una unidad de medida válida.';
        }
        if (!in_array($origin, Product::origins(), true)) {
            $errors['origin'] = 'Selecciona el municipio de origen.';
        }

        return [
            'values' => [
                'name'        => trim($name),
                'description' => trim($description),
                'category'    => $category,
                'price'       => $price,
                'stock'       => $stock,
                'unit'        => $unit,
                'origin'      => $origin,
            ],
            'errors' => $errors,
        ];
    }

    /** Uploader configurado para las imágenes de productos. */
    private function uploader(): ImageUploader
    {
        return new ImageUploader(
            BASE_PATH . '/public/uploads/products',
            '/uploads/products'
        );
    }

    /**
     * ¿El pedido incluye al menos un producto del productor dado?
     */
    private function orderHasMyProduct(array $order, string $producerId): bool
    {
        foreach ($order['items'] ?? [] as $item) {
            if (($item['producer_id'] ?? null) === $producerId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recupera un producto y verifica que pertenezca al productor actual.
     * Si no existe o no es suyo, corta con 403/404.
     */
    private function ownedProductOrFail(string $id): array
    {
        $repo    = new ProductRepository($this->container->db());
        $product = $repo->find($id);
        $me      = $this->auth->user();

        if ($product === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Producto no encontrado']);
            exit;
        }
        if (($product['producer_id'] ?? null) !== $me['id']) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Acceso denegado']);
            exit;
        }
        return $product;
    }
}
