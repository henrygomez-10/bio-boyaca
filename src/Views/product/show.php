<?php
/**
 * src/Views/product/show.php — Módulo DETALLE DEL PRODUCTO.
 * Variables: $product, $producer (?array), $auth.
 */
use App\Models\Product;

$user      = $auth->user();
$isConsumer = $user !== null && $user['role'] === 'consumer';
$stock     = (int) ($product['stock'] ?? 0);
?>
<nav class="breadcrumb"><a href="/catalogo">← Volver al catálogo</a></nav>

<article class="product-detail">
    <div class="product-detail__media <?= !empty($product['image']) ? 'has-image' : '' ?>" aria-hidden="true">
        <?php if (!empty($product['image'])): ?>
            <img src="<?= e($product['image']) ?>" alt="<?= e($product['name'] ?? '') ?>">
        <?php else: ?>
            <?= e(mb_substr($product['name'] ?? '?', 0, 1)) ?>
        <?php endif; ?>
    </div>

    <div class="product-detail__info">
        <p class="tag" data-cat="<?= e(Product::categorySlug($product['category'] ?? '')) ?>"><?= e($product['category'] ?? '') ?></p>
        <h1 class="product-detail__title"><?= e($product['name'] ?? '') ?></h1>
        <p class="product-detail__price">
            <?= e(Product::formatPrice($product['price'] ?? 0)) ?>
            <?php if (!empty($product['unit'])): ?>
                <small class="muted">/ <?= e($product['unit']) ?></small>
            <?php endif; ?>
        </p>

        <?php if (!empty($product['origin'])): ?>
            <p class="product-detail__origin">Origen: <strong><?= e($product['origin']) ?></strong>, Boyacá</p>
        <?php endif; ?>

        <p class="product-detail__desc"><?= nl2br(e($product['description'] ?? 'Sin descripción.')) ?></p>

        <p class="product-detail__stock">
            <?= $stock > 0 ? 'Disponibles: ' . $stock : 'Sin stock disponible' ?>
        </p>

        <?php if ($producer): ?>
            <p class="muted">Vendido por <strong><?= e($producer['name']) ?></strong></p>
        <?php endif; ?>

        <?php if ($stock > 0 && ($isConsumer || $user === null)): ?>
            <?php // El carrito se puede llenar sin sesión; el login se pide al pagar. ?>
            <form action="/carrito/agregar" method="post" class="buy-form">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <input type="hidden" name="return_to" value="/producto/<?= e($product['id']) ?>">
                <label for="qty">Cantidad</label>
                <input type="number" id="qty" name="qty" value="1" min="1" max="<?= $stock ?>">
                <button type="submit" class="btn btn--primary">Agregar al carrito</button>
            </form>
        <?php elseif ($stock < 1): ?>
            <p class="muted">Este producto está agotado por ahora.</p>
        <?php else: ?>
            <p class="muted">Solo los consumidores pueden realizar pedidos.</p>
        <?php endif; ?>
    </div>
</article>
