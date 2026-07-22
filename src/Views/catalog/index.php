<?php
/**
 * src/Views/catalog/index.php — Módulo CATÁLOGO.
 * Variables: $products, $categories, $q, $category, $auth.
 *
 * El filtro por categoría se pinta como "chips" (una fila de píldoras) en vez de
 * un desplegable: es el patrón de los bocetos y funciona mejor en móvil. Cada
 * chip es un enlace GET que conserva el término de búsqueda actual.
 */
use App\Models\Product;

$user       = $auth->user();
$isConsumer = $user === null || ($user['role'] ?? '') === 'consumer';

/** Construye la URL de un chip conservando la búsqueda activa. */
$chipUrl = static function (string $cat) use ($q): string {
    $params = array_filter(['q' => $q, 'categoria' => $cat], static fn ($v) => $v !== '');
    return '/catalogo' . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="section">
    <h1 class="section__title">Catálogo de productos</h1>

    <form action="/catalogo" method="get" class="filters">
        <input type="search" name="q" placeholder="Buscar arepas, huevos, miel..." value="<?= e($q) ?>">
        <?php if ($category !== ''): ?>
            <input type="hidden" name="categoria" value="<?= e($category) ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn--primary">Buscar</button>
    </form>

    <nav class="chips" aria-label="Filtrar por categoría">
        <a class="chip <?= $category === '' ? 'chip--active' : '' ?>" href="<?= e($chipUrl('')) ?>">Todos</a>
        <?php foreach ($categories as $c): ?>
            <a class="chip <?= $c === $category ? 'chip--active' : '' ?>" href="<?= e($chipUrl($c)) ?>"><?= e($c) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($products)): ?>
        <p class="empty">No se encontraron productos con esos criterios.</p>
    <?php else: ?>
        <div class="grid grid--cards">
            <?php foreach ($products as $p): ?>
                <?php $stock = (int) ($p['stock'] ?? 0); ?>
                <article class="card">
                    <a class="card__media <?= !empty($p['image']) ? 'has-image' : '' ?>" href="/producto/<?= e($p['id']) ?>">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name'] ?? '') ?>" loading="lazy">
                        <?php else: ?>
                            <span aria-hidden="true"><?= e(mb_substr($p['name'] ?? '?', 0, 1)) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="card__body">
                        <h3 class="card__title">
                            <a href="/producto/<?= e($p['id']) ?>"><?= e($p['name'] ?? '') ?></a>
                        </h3>
                        <span class="tag" data-cat="<?= e(Product::categorySlug($p['category'] ?? '')) ?>"><?= e($p['category'] ?? '') ?></span>

                        <?php if (!empty($p['origin'])): ?>
                            <p class="card__origin">Origen: <?= e($p['origin']) ?></p>
                        <?php endif; ?>

                        <p class="card__price">
                            <?= e(Product::formatPrice($p['price'] ?? 0)) ?>
                            <?php if (!empty($p['unit'])): ?>
                                <small class="muted">/ <?= e($p['unit']) ?></small>
                            <?php endif; ?>
                        </p>
                        <p class="card__stock <?= $stock > 0 ? '' : 'is-out' ?>">
                            <?= $stock > 0 ? 'Disponibles: ' . $stock : 'Sin stock' ?>
                        </p>
                    </div>

                    <?php if ($isConsumer && $stock > 0): ?>
                        <form action="/carrito/agregar" method="post" class="card__add">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                            <?php // Vuelve al catálogo conservando búsqueda y filtro activos. ?>
                            <input type="hidden" name="return_to" value="<?= e($chipUrl($category)) ?>">
                            <button type="submit" class="btn btn--primary btn--block">Agregar</button>
                        </form>
                    <?php else: ?>
                        <a class="card__link" href="/producto/<?= e($p['id']) ?>">Ver detalle</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
