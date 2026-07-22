<?php
/**
 * src/Views/producer/wallet.php — Módulo BILLETERA DEL PRODUCTOR.
 * Variables: $monthIncome, $delivered, $inTransit, $weeks, $available, $withdrawals.
 *
 * El gráfico de "Comportamiento de ventas" es CSS puro: cada barra recibe su
 * altura como porcentaje respecto a la semana más alta del mes. Sin JS ni
 * librerías externas.
 */
use App\Models\Product;

$maxWeek = max(array_map('floatval', $weeks)) ?: 0.0;
?>
<section class="section">
    <div class="page-head">
        <h1 class="section__title">Mi billetera y ventas</h1>
        <a class="btn btn--ghost" href="/productor">Volver al panel</a>
    </div>

    <div class="wallet">
        <div class="wallet__main">
            <div class="wallet-hero">
                <p class="wallet-hero__label">Ingresos totales (este mes)</p>
                <p class="wallet-hero__amount"><?= e(Product::formatPrice($monthIncome)) ?></p>
                <p class="wallet-hero__note">Libre de comisiones de intermediarios</p>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-card__label">Pedidos entregados</span>
                    <span class="stat-card__value"><?= (int) $delivered ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-card__label">Pedidos en tránsito</span>
                    <span class="stat-card__value stat-card__value--warn"><?= (int) $inTransit ?></span>
                </div>
            </div>

            <div class="chart">
                <h2 class="chart__title">Comportamiento de ventas</h2>
                <?php if ($maxWeek <= 0): ?>
                    <p class="empty">Aún no hay ventas registradas este mes.</p>
                <?php else: ?>
                    <div class="chart__bars">
                        <?php foreach ($weeks as $n => $amount): ?>
                            <?php
                            /* Una semana sin ventas se pinta a 0 (sin barra); las
                               que sí vendieron nunca bajan del 2% para que se vean. */
                            $pct = $amount > 0 ? max(2, (int) round(($amount / $maxWeek) * 100)) : 0;
                            ?>
                            <div class="chart__col">
                                <div class="chart__bar"
                                     style="height: <?= $pct ?>%"
                                     title="Semana <?= (int) $n ?>: <?= e(Product::formatPrice($amount)) ?>"></div>
                                <span class="chart__label">Sem <?= (int) $n ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="wallet__aside">
            <div class="panel">
                <h2 class="section__subtitle">Saldo disponible</h2>
                <p class="wallet__balance"><?= e(Product::formatPrice($available)) ?></p>
                <p class="muted">
                    Corresponde a pedidos ya <strong>entregados</strong> y todavía no retirados.
                </p>

                <form action="/productor/billetera/retiro" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--block"
                            <?= $available <= 0 ? 'disabled' : '' ?>>
                        Retirar fondos a mi cuenta
                    </button>
                </form>
                <small class="muted">
                    Los retiros son simulados en esta versión: se registra la solicitud,
                    pero todavía no hay integración con una pasarela de pagos.
                </small>
            </div>

            <?php if (!empty($withdrawals)): ?>
                <div class="panel">
                    <h2 class="section__subtitle">Retiros solicitados</h2>
                    <ul class="list-plain">
                        <?php foreach (array_slice($withdrawals, 0, 5) as $w): ?>
                            <li>
                                <strong><?= e(Product::formatPrice($w['amount'] ?? 0)) ?></strong>
                                <span class="muted">
                                    · <?= e(date('d/m/Y', strtotime((string) ($w['created_at'] ?? 'now')) ?: time())) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
