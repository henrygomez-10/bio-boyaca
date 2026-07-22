<?php
/**
 * src/Views/admin/products.php — Listado global de productos (admin).
 * Variables: $products.
 */
use App\Models\Product;
?>
<section class="section">
    <h1 class="section__title">Productos</h1>
    <table class="table">
        <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th></tr></thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><a href="/producto/<?= e($p['id']) ?>"><?= e($p['name']) ?></a></td>
                    <td><?= e($p['category']) ?></td>
                    <td><?= e(Product::formatPrice($p['price'])) ?></td>
                    <td><?= (int) $p['stock'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
