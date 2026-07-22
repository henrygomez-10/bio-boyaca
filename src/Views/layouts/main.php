<?php
/**
 * src/Views/layouts/main.php
 * -----------------------------------------------------------------------------
 * Layout principal. Envuelve todas las vistas. Recibe:
 *   $content  string  HTML ya renderizado de la vista.
 *   $title    string  Título de la página.
 *   $appName  string  Nombre global de la app (config['app']['name']).
 *   $auth     Auth    Servicio de autenticación (para el menú).
 * -----------------------------------------------------------------------------
 */

require_once BASE_PATH . '/src/Core/helpers.php';

use App\Core\Cart;

/** @var \App\Core\Auth $auth */
$user = $auth->user();

// Contador del carrito (vive en sesión, no en BD) y ruta actual, para marcar el
// elemento activo de la navegación inferior en móvil.
$cartCount = Cart::count();
$path      = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

/** ¿La ruta actual empieza por este prefijo? Marca el ítem activo del menú. */
$isActive = static function (string $prefix) use ($path): string {
    $active = $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
    return $active ? ' bottom-nav__item--active' : '';
};

// Destino del ítem "Mi cuenta" según el rol de quien navega.
$accountHref = match ($user['role'] ?? null) {
    'producer' => '/productor',
    'admin'    => '/admin',
    'consumer' => '/consumidor',
    default    => '/login',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? $appName) ?></title>
    <script>
        /* Aplica el tema guardado ANTES de pintar para evitar parpadeo.
           Por defecto (sin preferencia guardada) el diseño es claro/blanco. */
        (function () {
            /* Marca que hay JS disponible. El menú móvil solo se colapsa bajo
               .js: sin JavaScript no habría forma de volver a abrirlo, así que
               en ese caso se queda desplegado. */
            document.documentElement.classList.add('js');
            try {
                var t = localStorage.getItem('tema');
                if (t === 'dark' || t === 'light') {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) {}
        })();
    </script>
    <?php
    // Cache-busting: añade la fecha de modificación como versión (?v=...) para
    // que el navegador recargue el CSS/JS automáticamente cuando cambien.
    $cssV = @filemtime(BASE_PATH . '/public/assets/css/style.css') ?: time();
    $jsV  = @filemtime(BASE_PATH . '/public/assets/js/main.js') ?: time();
    ?>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= $cssV ?>">
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/">
            <span class="brand__mark">◈</span>
            <span class="brand__name"><?= e($appName) ?></span>
        </a>

        <?php
        /* Acciones siempre visibles, también con el menú colapsado: el carrito
           (con su contador) y el tema no deben esconderse tras la hamburguesa. */
        ?>
        <div class="header-actions">
            <a class="cart-link" href="/carrito" aria-label="Ver carrito">
                <span aria-hidden="true">🛒</span>
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= (int) $cartCount ?></span>
                <?php endif; ?>
            </a>

            <button type="button" id="themeToggle" class="theme-toggle"
                    aria-label="Cambiar entre tema claro y oscuro" title="Tema claro / oscuro">
                <span class="theme-toggle__icon" aria-hidden="true">☾</span>
            </button>

            <?php // Solo se muestra en móvil (el CSS lo oculta en escritorio). ?>
            <button type="button" id="navToggle" class="nav-toggle"
                    aria-expanded="false" aria-controls="mainNav" aria-label="Abrir menú">
                <span class="nav-toggle__icon" aria-hidden="true">☰</span>
            </button>
        </div>

        <nav class="main-nav" id="mainNav">
            <a href="/">Inicio</a>
            <a href="/catalogo">Catálogo</a>

            <?php if ($user === null): ?>
                <a href="/login">Iniciar sesión</a>
                <a class="btn btn--primary" href="/registro">Crear cuenta</a>
            <?php else: ?>
                <?php if ($user['role'] === 'producer'): ?>
                    <a href="/productor">Panel productor</a>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <a href="/admin">Administración</a>
                <?php else: ?>
                    <a href="/consumidor">Mi perfil</a>
                <?php endif; ?>
                <form action="/logout" method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost">Salir</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert--success container"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert--error container"><?= e($msg) ?></div>
<?php endif; ?>

<main class="container site-main">
    <?= $content ?>
</main>

<?php
/* Navegación inferior: solo visible en móvil (el CSS la oculta en escritorio,
   donde manda la barra superior). Cambia según el rol: el productor gestiona su
   negocio, el resto compra. */
?>
<nav class="bottom-nav" aria-label="Navegación principal">
    <?php if (($user['role'] ?? null) === 'producer'): ?>
        <a class="bottom-nav__item<?= $isActive('/productor') ?>" href="/productor">
            <span class="bottom-nav__icon" aria-hidden="true">🏠</span>
            <span class="bottom-nav__label">Panel</span>
        </a>
        <a class="bottom-nav__item<?= $isActive('/productor/productos') ?>" href="/productor/productos">
            <span class="bottom-nav__icon" aria-hidden="true">🧺</span>
            <span class="bottom-nav__label">Productos</span>
        </a>
        <a class="bottom-nav__item<?= $isActive('/productor/pedidos') ?>" href="/productor/pedidos">
            <span class="bottom-nav__icon" aria-hidden="true">📦</span>
            <span class="bottom-nav__label">Pedidos</span>
        </a>
        <a class="bottom-nav__item<?= $isActive('/productor/billetera') ?>" href="/productor/billetera">
            <span class="bottom-nav__icon" aria-hidden="true">💵</span>
            <span class="bottom-nav__label">Billetera</span>
        </a>
    <?php else: ?>
        <a class="bottom-nav__item<?= $isActive('/') ?>" href="/">
            <span class="bottom-nav__icon" aria-hidden="true">🏠</span>
            <span class="bottom-nav__label">Inicio</span>
        </a>
        <a class="bottom-nav__item<?= $isActive('/catalogo') ?>" href="/catalogo">
            <span class="bottom-nav__icon" aria-hidden="true">🧺</span>
            <span class="bottom-nav__label">Catálogo</span>
        </a>
        <a class="bottom-nav__item<?= $isActive('/carrito') ?>" href="/carrito">
            <span class="bottom-nav__icon" aria-hidden="true">🛒</span>
            <span class="bottom-nav__label">Carrito<?= $cartCount > 0 ? ' (' . (int) $cartCount . ')' : '' ?></span>
        </a>
        <a class="bottom-nav__item<?= $isActive($accountHref) ?>" href="<?= e($accountHref) ?>">
            <span class="bottom-nav__icon" aria-hidden="true">👤</span>
            <span class="bottom-nav__label">Mi cuenta</span>
        </a>
    <?php endif; ?>
</nav>

<footer class="site-footer">
    <div class="container">
        <p><?= e($appName) ?> · <?= e($tagline ?? '') ?></p>
        <p class="muted">Proyecto en desarrollo · Persistencia local (JSON)</p>
    </div>
</footer>

<script src="/assets/js/main.js?v=<?= $jsV ?>"></script>
</body>
</html>
