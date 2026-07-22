<?php
/**
 * src/Controllers/HomeController.php
 * -----------------------------------------------------------------------------
 * Módulo INICIO. Página de bienvenida con productos destacados.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ProductRepository;

class HomeController extends Controller
{
    public function index(array $params): void
    {
        $products = new ProductRepository($this->container->db());

        // Muestra hasta 6 productos como destacados en la portada.
        $featured = array_slice($products->all(), 0, 6);

        $this->render('home/index', [
            'title'    => $this->container->config('app')['name'] . ' · Inicio',
            'featured' => $featured,
        ]);
    }
}
