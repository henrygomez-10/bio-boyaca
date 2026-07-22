<?php
/**
 * src/Views/producer/products.php — Gestión de productos del productor.
 * Variables: $products.
 */
use App\Models\Product;
?>
<section class="section">
    <div class="page-head">
        <h1 class="section__title">Mis productos</h1>
        <a class="btn btn--primary" href="/productor/productos/nuevo">+ Nuevo producto</a>
    </div>

    <?php if (empty($products)): ?>
        <p class="empty">Todavía no has publicado productos.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="cell-product">
                            <span class="thumb <?= empty($p['image']) ? 'thumb--letter' : '' ?>">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?= e($p['image']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <?= e(mb_substr($p['name'] ?? '?', 0, 1)) ?>
                                <?php endif; ?>
                            </span>
                            <?= e($p['name']) ?>
                        </td>
                        <td><?= e($p['category']) ?></td>
                        <td><?= e(Product::formatPrice($p['price'])) ?></td>
                        <td><?= (int) $p['stock'] ?></td>
                        <td class="table__actions">
                            <a href="/productor/productos/<?= e($p['id']) ?>/editar">Editar</a>
                            <form action="/productor/productos/<?= e($p['id']) ?>/eliminar" method="post"
                                  onsubmit="return confirm('¿Eliminar este producto?');" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
