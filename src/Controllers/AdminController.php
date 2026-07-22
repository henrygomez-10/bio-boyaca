<?php
/**
 * src/Controllers/AdminController.php
 * -----------------------------------------------------------------------------
 * Módulo PANEL DE ADMINISTRACIÓN (básico). Vista global de usuarios, productos y
 * pedidos, con métricas simples. Exige rol 'admin'.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;

class AdminController extends Controller
{
    /** Panel con métricas generales. */
    public function dashboard(array $params): void
    {
        $this->requireRole('admin');

        $users    = new UserRepository($this->container->db());
        $products = new ProductRepository($this->container->db());
        $orders   = new OrderRepository($this->container->db());

        $this->render('admin/dashboard', [
            'title'   => 'Administración',
            'metrics' => [
                'usuarios'     => $users->count(),
                'productores'  => count($users->ofRole('producer')),
                'consumidores' => count($users->ofRole('consumer')),
                'productos'    => $products->count(),
                'pedidos'      => $orders->count(),
            ],
        ]);
    }

    /** Listado de todos los usuarios. */
    public function users(array $params): void
    {
        $this->requireRole('admin');
        $users = new UserRepository($this->container->db());

        $this->render('admin/users', [
            'title' => 'Usuarios',
            'users' => $users->all(),
        ]);
    }

    /** Listado de todos los productos. */
    public function products(array $params): void
    {
        $this->requireRole('admin');
        $products = new ProductRepository($this->container->db());

        $this->render('admin/products', [
            'title'    => 'Productos',
            'products' => $products->all(),
        ]);
    }

    /** Listado de todos los pedidos. */
    public function orders(array $params): void
    {
        $this->requireRole('admin');
        $orders = new OrderRepository($this->container->db());

        $this->render('admin/orders', [
            'title'    => 'Pedidos',
            'orders'   => $orders->all(),
            'statuses' => Order::statuses(),
        ]);
    }
}
