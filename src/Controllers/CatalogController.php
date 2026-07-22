<?php
/**
 * src/Controllers/CatalogController.php
 * -----------------------------------------------------------------------------
 * Módulo CATÁLOGO. Listado de productos con búsqueda y filtro por categoría.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Repositories\ProductRepository;

class CatalogController extends Controller
{
    public function index(array $params): void
    {
        $repo     = new ProductRepository($this->container->db());
        $term     = (string) $this->input('q', '');
        $category = (string) $this->input('categoria', '');

        // Búsqueda por texto (o todos si no hay término).
        $products = $repo->search($term);

        // Filtro por categoría en memoria.
        if ($category !== '') {
            $products = array_values(array_filter(
                $products,
                static fn (array $p): bool => ($p['category'] ?? '') === $category
            ));
        }

        $this->render('catalog/index', [
            'title'      => 'Catálogo',
            'products'   => $products,
            'categories' => Product::categories(),
            'q'          => $term,
            'category'   => $category,
        ]);
    }
}
