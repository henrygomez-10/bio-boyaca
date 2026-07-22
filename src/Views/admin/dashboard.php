<?php
/**
 * src/Views/admin/dashboard.php — Panel de administración (básico).
 * Variables: $metrics (array asociativo etiqueta => número).
 */
?>
<section class="section">
    <h1 class="section__title">Panel de administración</h1>

    <div class="stat-row stat-row--wrap">
        <?php foreach ($metrics as $label => $value): ?>
            <div class="stat">
                <span class="stat__num"><?= (int) $value ?></span>
                <span class="stat__label"><?= e(ucfirst($label)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel-links">
        <a class="btn btn--ghost" href="/admin/usuarios">Usuarios</a>
        <a class="btn btn--ghost" href="/admin/productos">Productos</a>
        <a class="btn btn--ghost" href="/admin/pedidos">Pedidos</a>
    </div>
</section>
