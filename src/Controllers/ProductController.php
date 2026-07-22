<?php
/**
 * src/Controllers/ProductController.php
 * -----------------------------------------------------------------------------
 * Módulo DETALLE DE PRODUCTO. Ficha completa de un producto.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;

class ProductController extends Controller
{
    public function show(array $params): void
    {
        $products = new ProductRepository($this->container->db());
        $product  = $products->find($params['id'] ?? '');

        if ($product === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Producto no encontrado']);
            return;
        }

        // Datos del productor dueño del producto.
        $users    = new UserRepository($this->container->db());
        $producer = $users->find($product['producer_id'] ?? '');

        $this->render('product/show', [
            'title'    => $product['name'] ?? 'Producto',
            'product'  => $product,
            'producer' => $producer,
        ]);
    }
}
