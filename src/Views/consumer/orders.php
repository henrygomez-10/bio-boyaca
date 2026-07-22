<?php
/**
 * src/Views/consumer/orders.php — Historial de pedidos del consumidor.
 * Variables: $orders, $statuses.
 */
use App\Models\Order;
use App\Models\Product;
?>
<section class="section">
    <h1 class="section__title">Mis pedidos</h1>

    <?php if (empty($orders)): ?>
        <p class="empty">No has realizado pedidos todavía. <a href="/catalogo">Explora el catálogo</a>.</p>
    <?php else: ?>
        <?php foreach (array_reverse($orders) as $o): ?>
            <article class="order-card">
                <header class="order-card__head">
                    <span class="order-card__id">Pedido #<?= e(substr($o['id'], -6)) ?></span>
                    <span class="badge badge--<?= e($o['status']) ?>"><?= e(Order::label($o['status'])) ?></span>
                </header>
                <ul class="order-card__items">
                    <?php foreach ($o['items'] as $it): ?>
                        <li>
                            <?= (int) $it['qty'] ?> × <?= e($it['name']) ?>
                            — <?= e(Product::formatPrice($it['price'])) ?>
                            <?php if (!empty($it['origin'])): ?>
                                <span class="muted">· <?= e($it['origin']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (!empty($o['address'])): ?>
                    <p class="order-card__address muted">
                        Entrega: <?= e($o['locality'] ?? '') ?> — <?= e($o['address']) ?>
                    </p>
                <?php endif; ?>

                <footer class="order-card__foot">
                    <?php // Los pedidos antiguos pueden no tener el desglose guardado. ?>
                    <?php if (isset($o['subtotal'], $o['shipping'])): ?>
                        <span class="muted">
                            Productos <?= e(Product::formatPrice($o['subtotal'])) ?>
                            + logística <?= e(Product::formatPrice($o['shipping'])) ?>
                        </span>
                    <?php endif; ?>
                    <strong>Total: <?= e(Product::formatPrice($o['total'])) ?></strong>
                </footer>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
