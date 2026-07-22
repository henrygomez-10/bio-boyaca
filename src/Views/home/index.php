<?php
/**
 * src/Views/home/index.php — Módulo INICIO.
 * Variables: $featured (array de productos), $appName, $tagline.
 */
use App\Models\Product;
?>
<section class="hero">
    <h1 class="hero__title">Bienvenido a <?= e($appName) ?></h1>
    <p class="hero__subtitle"><?= e($tagline) ?></p>
    <div class="hero__actions">
        <a class="btn btn--primary" href="/catalogo">Ver catálogo</a>
        <a class="btn btn--ghost" href="/registro">Soy productor</a>
    </div>
</section>

<section class="section">
    <h2 class="section__title">Productos destacados</h2>

    <?php if (empty($featured)): ?>
        <p class="empty">Aún no hay productos publicados. ¡Vuelve pronto!</p>
    <?php else: ?>
        <div class="grid grid--cards">
            <?php foreach ($featured as $p): ?>
                <article class="card">
                    <div class="card__media <?= !empty($p['image']) ? 'has-image' : '' ?>" aria-hidden="true">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name'] ?? '') ?>" loading="lazy">
                        <?php else: ?>
                            <?= e(mb_substr($p['name'] ?? '?', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="card__body">
                        <h3 class="card__title"><?= e($p['name'] ?? '') ?></h3>
                        <span class="tag" data-cat="<?= e(Product::categorySlug($p['category'] ?? '')) ?>"><?= e($p['category'] ?? '') ?></span>
                        <p class="card__price"><?= e(Product::formatPrice($p['price'] ?? 0)) ?></p>
                    </div>
                    <a class="card__link" href="/producto/<?= e($p['id']) ?>">Ver detalle</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
