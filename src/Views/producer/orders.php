<?php
/**
 * src/Views/producer/orders.php — Pedidos recibidos por el productor.
 * Variables: $orders, $statuses.
 */
use App\Models\Order;
use App\Models\Product;
?>
<section class="section">
    <h1 class="section__title">Pedidos recibidos</h1>

    <?php if (empty($orders)): ?>
        <p class="empty">Aún no has recibido pedidos.</p>
    <?php else: ?>
        <?php foreach ($orders as $o): ?>
            <article class="order-card">
                <header class="order-card__head">
                    <span class="order-card__id">Pedido #<?= e(substr($o['id'], -6)) ?></span>
                    <span class="badge badge--<?= e($o['status']) ?>"><?= e(Order::label($o['status'])) ?></span>
                </header>
                <ul class="order-card__items">
                    <?php foreach ($o['items'] as $it): ?>
                        <li><?= (int) $it['qty'] ?> × <?= e($it['name']) ?> — <?= e(Product::formatPrice($it['price'])) ?></li>
                    <?php endforeach; ?>
                </ul>
                <footer class="order-card__foot">
                    <strong>Total: <?= e(Product::formatPrice($o['total'])) ?></strong>
                    <form action="/productor/pedidos/<?= e($o['id']) ?>/estado" method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <select name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $o['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn--ghost">Actualizar</button>
                    </form>
                </footer>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
