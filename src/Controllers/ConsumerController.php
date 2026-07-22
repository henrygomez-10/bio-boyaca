<?php
/**
 * src/Controllers/ConsumerController.php
 * -----------------------------------------------------------------------------
 * Módulo PERFIL DEL CONSUMIDOR. Panel del consumidor y su historial de pedidos.
 * Exige rol 'consumer'.
 *
 * La compra en sí (carrito y checkout) vive en App\Controllers\CartController.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Order;
use App\Repositories\OrderRepository;

class ConsumerController extends Controller
{
    /** Panel resumen del consumidor. */
    public function dashboard(array $params): void
    {
        $this->requireRole('consumer');
        $me   = $this->auth->user();
        $repo = new OrderRepository($this->container->db());

        $this->render('consumer/dashboard', [
            'title'  => 'Mi perfil',
            'orders' => $repo->ofConsumer($me['id']),
        ]);
    }

    /** Historial de pedidos del consumidor. */
    public function orders(array $params): void
    {
        $this->requireRole('consumer');
        $me   = $this->auth->user();
        $repo = new OrderRepository($this->container->db());

        $this->render('consumer/orders', [
            'title'    => 'Mis pedidos',
            'orders'   => $repo->ofConsumer($me['id']),
            'statuses' => Order::statuses(),
        ]);
    }

}
