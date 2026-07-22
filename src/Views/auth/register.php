<?php
/**
 * src/Views/auth/register.php — Registro.
 * Variables: $publicRoles (array), $errors (array), $old (array), $roles (labels).
 */
?>
<section class="auth-card">
    <h1 class="section__title">Crear cuenta</h1>

    <form action="/registro" method="post" class="form" novalidate>
        <?= csrf_field() ?>

        <div class="form__group">
            <label for="name">Nombre completo</label>
            <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
            <?php if (!empty($errors['name'])): ?><small class="form__error"><?= e($errors['name']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?><small class="form__error"><?= e($errors['email']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" minlength="6" required>
            <?php if (!empty($errors['password'])): ?><small class="form__error"><?= e($errors['password']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label>Quiero registrarme como</label>
            <div class="radio-group">
                <?php foreach ($publicRoles as $r): ?>
                    <label class="radio">
                        <input type="radio" name="role" value="<?= e($r) ?>" <?= $r === 'consumer' ? 'checked' : '' ?>>
                        <?= e($roles[$r] ?? $r) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($errors['role'])): ?><small class="form__error"><?= e($errors['role']) ?></small><?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--block">Crear cuenta</button>
    </form>

    <p class="auth-card__foot">¿Ya tienes cuenta? <a href="/login">Inicia sesión</a></p>
</section>
