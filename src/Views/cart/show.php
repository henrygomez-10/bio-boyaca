<?php
/**
 * src/Views/cart/show.php — Módulo CARRITO / CHECKOUT ("Resumen de tu pedido").
 * Variables: $lines, $subtotal, $shipping, $total, $localities, $address, $auth.
 *
 * Muestra las líneas del carrito, la dirección de entrega y el desglose
 * económico (subtotal + logística Boyacá-Bogotá + total a pagar).
 */
use App\Models\Order;
use App\Models\Product;

$user       = $auth->user();
$isConsumer = $user !== null && ($user['role'] ?? '') === 'consumer';
?>
<section class="section section--narrow">
    <div class="page-head">
        <h1 class="section__title">Resumen de tu pedido</h1>
        <a class="btn btn--ghost" href="/catalogo">Seguir comprando</a>
    </div>

    <?php if (empty($lines)): ?>
        <p class="empty">Tu carrito está vacío. Explora el catálogo y agrega productos del campo boyacense.</p>
        <p><a class="btn btn--primary" href="/catalogo">Ir al catálogo</a></p>
    <?php else: ?>

        <div class="checkout">
            <div class="checkout__items">
                <?php foreach ($lines as $line): ?>
                    <?php $p = $line['product']; ?>
                    <article class="cart-item">
                        <div class="cart-item__main">
                            <h3 class="cart-item__name">
                                <?= (int) $line['qty'] ?>x <?= e($p['name'] ?? '') ?>
                                <?php if (!empty($p['unit'])): ?>
                                    <span class="muted">(<?= e($p['unit']) ?>)</span>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($p['origin'])): ?>
                                <p class="cart-item__meta">Origen: <?= e($p['origin']) ?></p>
                            <?php endif; ?>

                            <div class="cart-item__actions">
                                <form action="/carrito/cantidad" method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                                    <label class="sr-only" for="qty-<?= e($p['id']) ?>">Cantidad</label>
                                    <input type="number" id="qty-<?= e($p['id']) ?>" name="qty"
                                           value="<?= (int) $line['qty'] ?>" min="1"
                                           max="<?= (int) ($p['stock'] ?? 1) ?>" class="qty-input">
                                    <button type="submit" class="btn btn--ghost btn--sm">Actualizar</button>
                                </form>
                                <form action="/carrito/eliminar" method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">Quitar</button>
                                </form>
                            </div>
                        </div>
                        <p class="cart-item__price"><?= e(Product::formatPrice($line['subtotal'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="checkout__aside">
                <form action="/pedido" method="post" class="form">
                    <?= csrf_field() ?>

                    <h2 class="section__subtitle">Dirección de entrega</h2>

                    <div class="form__group">
                        <label for="locality">Localidad de Bogotá</label>
                        <select id="locality" name="locality" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($localities as $l): ?>
                                <option value="<?= e($l) ?>" <?= $l === $address['locality'] ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form__group">
                        <label for="address">Dirección</label>
                        <input type="text" id="address" name="address"
                               placeholder="Carrera 104 # 145-20, Apto 301"
                               value="<?= e($address['address']) ?>" required>
                    </div>

                    <div class="summary">
                        <p class="summary__line">
                            <span>Subtotal productos</span>
                            <span><?= e(Product::formatPrice($subtotal)) ?></span>
                        </p>
                        <p class="summary__line">
                            <span><?= e(Order::SHIPPING_LABEL) ?></span>
                            <span><?= e(Product::formatPrice($shipping)) ?></span>
                        </p>
                        <p class="summary__total">
                            <span>Total a pagar</span>
                            <span><?= e(Product::formatPrice($total)) ?></span>
                        </p>
                    </div>

                    <?php if ($isConsumer): ?>
                        <button type="submit" class="btn btn--primary btn--block">Confirmar y pagar</button>
                    <?php elseif ($user === null): ?>
                        <p class="muted">Inicia sesión como consumidor para confirmar tu pedido.</p>
                        <a class="btn btn--primary btn--block" href="/login">Iniciar sesión</a>
                    <?php else: ?>
                        <p class="muted">Solo las cuentas de consumidor pueden realizar pedidos.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    <?php endif; ?>
</section>
