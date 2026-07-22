<?php
/**
 * src/Views/auth/login.php — Inicio de sesión.
 * Variables: $error (?string), $old (array).
 */
?>
<section class="auth-card">
    <h1 class="section__title">Iniciar sesión</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form action="/login" method="post" class="form" novalidate>
        <?= csrf_field() ?>

        <div class="form__group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
        </div>

        <div class="form__group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Entrar</button>
    </form>

    <p class="auth-card__foot">¿No tienes cuenta? <a href="/registro">Crea una</a></p>
</section>
