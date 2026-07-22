<?php
/**
 * src/Views/producer/dashboard.php — Panel del productor.
 * Variables: $myProducts, $myOrders, $auth.
 */
$user = $auth->user();
?>
<section class="section">
    <div class="page-head">
        <h1 class="section__title">Hola, <?= e($user['name']) ?></h1>
        <a class="btn btn--primary" href="/productor/productos/nuevo">+ Nuevo producto</a>
    </div>

    <div class="stat-row">
        <div class="stat"><span class="stat__num"><?= count($myProducts) ?></span><span class="stat__label">Productos</span></div>
        <div class="stat"><span class="stat__num"><?= count($myOrders) ?></span><span class="stat__label">Pedidos</span></div>
    </div>

    <div class="panel-links">
        <a class="btn btn--primary" href="/productor/billetera">Mi billetera y ventas</a>
        <a class="btn btn--ghost" href="/productor/productos">Gestionar productos</a>
        <a class="btn btn--ghost" href="/productor/pedidos">Ver pedidos</a>
    </div>
</section>
