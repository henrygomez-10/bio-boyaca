<?php
/**
 * src/Views/admin/orders.php — Listado global de pedidos (admin).
 * Variables: $orders, $statuses.
 */
use App\Models\Order;
use App\Models\Product;
?>
<section class="section">
    <h1 class="section__title">Pedidos</h1>

    <?php if (empty($orders)): ?>
        <p class="empty">No hay pedidos registrados.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Artículos</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e(substr($o['id'], -6)) ?></td>
                        <td><?= array_sum(array_map(fn($it) => (int) $it['qty'], $o['items'])) ?> artículo(s)</td>
                        <td><?= e(Product::formatPrice($o['total'])) ?></td>
                        <td><span class="badge badge--<?= e($o['status']) ?>"><?= e(Order::label($o['status'])) ?></span></td>
                        <td><?= e(substr($o['created_at'] ?? '', 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
