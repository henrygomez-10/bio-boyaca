<?php
/**
 * src/Views/admin/users.php — Listado de usuarios (admin).
 * Variables: $users, $roles (labels).
 */
?>
<section class="section">
    <h1 class="section__title">Usuarios</h1>
    <table class="table">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Alta</th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="tag"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
                    <td><?= e(substr($u['created_at'] ?? '', 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
