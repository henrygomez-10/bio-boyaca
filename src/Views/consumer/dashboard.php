<?php
/**
 * src/Views/consumer/dashboard.php — Perfil del consumidor.
 * Variables: $orders, $auth.
 */
use App\Models\Order;
use App\Models\Product;

$user = $auth->user();
$recent = array_slice(array_reverse($orders), 0, 3);
?>
<section class="section">
    <h1 class="section__title">Hola, <?= e($user['name']) ?></h1>

    <div class="stat-row">
        <div class="stat"><span class="stat__num"><?= count($orders) ?></span><span class="stat__label">Pedidos realizados</span></div>
    </div>

    <div class="panel-links">
        <a class="btn btn--primary" href="/catalogo">Explorar catálogo</a>
        <a class="btn btn--ghost" href="/consumidor/pedidos">Ver todos mis pedidos</a>
    </div>

    <h2 class="section__subtitle">Últimos pedidos</h2>
    <?php if (empty($recent)): ?>
        <p class="empty">Todavía no has hecho ningún pedido.</p>
    <?php else: ?>
        <ul class="order-list">
            <?php foreach ($recent as $o): ?>
                <li class="order-list__item">
                    <span>Pedido #<?= e(substr($o['id'], -6)) ?></span>
                    <span class="badge badge--<?= e($o['status']) ?>"><?= e(Order::label($o['status'])) ?></span>
                    <strong><?= e(Product::formatPrice($o['total'])) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
