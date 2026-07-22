<?php
/**
 * src/Views/producer/product_form.php — Alta/edición de producto.
 * Variables: $product (?array), $categories, $hints, $units, $origins, $action, $errors.
 */
$p = $product ?? [];
?>
<section class="section section--narrow">
    <h1 class="section__title"><?= isset($p['id']) ? 'Editar producto' : 'Nuevo producto' ?></h1>

    <form action="<?= e($action) ?>" method="post" class="form" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="form__group">
            <label for="name">¿Qué producto va a vender?</label>
            <input type="text" id="name" name="name" value="<?= e($p['name'] ?? '') ?>" required>
            <?php if (!empty($errors['name'])): ?><small class="form__error"><?= e($errors['name']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label for="category">Categoría</label>
            <select id="category" name="category">
                <option value="">Selecciona...</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= ($p['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['category'])): ?><small class="form__error"><?= e($errors['category']) ?></small><?php endif; ?>
            <?php if (!empty($p['category']) && ($hint = ($hints[$p['category']] ?? ''))): ?>
                <small class="muted"><?= e($hint) ?></small>
            <?php endif; ?>
        </div>

        <div class="form__group">
            <label for="origin">Origen (municipio de Boyacá)</label>
            <select id="origin" name="origin" required>
                <option value="">Selecciona...</option>
                <?php foreach ($origins as $o): ?>
                    <option value="<?= e($o) ?>" <?= ($p['origin'] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['origin'])): ?><small class="form__error"><?= e($errors['origin']) ?></small><?php endif; ?>
        </div>

        <div class="form__row">
            <div class="form__group">
                <label for="price">Precio de venta para la ciudad ($)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e($p['price'] ?? '') ?>" required>
                <?php if (!empty($errors['price'])): ?><small class="form__error"><?= e($errors['price']) ?></small><?php endif; ?>
            </div>
            <div class="form__group">
                <label for="unit">Unidad de medida</label>
                <select id="unit" name="unit" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= e($u) ?>" <?= ($p['unit'] ?? '') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['unit'])): ?><small class="form__error"><?= e($errors['unit']) ?></small><?php endif; ?>
            </div>
        </div>

        <div class="form__group">
            <label for="stock">Unidades disponibles</label>
            <input type="number" id="stock" name="stock" min="0" value="<?= e($p['stock'] ?? 0) ?>">
            <?php if (!empty($errors['stock'])): ?><small class="form__error"><?= e($errors['stock']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label for="image">Tomar foto (recomendado)</label>
            <?php if (!empty($p['image'])): ?>
                <div class="form__image-preview">
                    <img src="<?= e($p['image']) ?>" alt="Imagen actual de <?= e($p['name'] ?? 'el producto') ?>">
                    <span class="muted">Imagen actual — sube otra para reemplazarla.</span>
                </div>
            <?php endif; ?>

            <?php
            /* Zona clicable con borde discontinuo (patrón del boceto). El <label>
               envuelve al input, así que tocar cualquier punto abre el selector.
               No se usa el atributo `capture`: sin él, el móvil ofrece "Tomar
               foto" Y la galería; con él se forzaría siempre la cámara. */
            ?>
            <label class="camera-field" for="image">
                <span class="camera-field__hint">📷 Presione para tomar una foto o elegir de la galería</span>
                <input type="file" id="image" name="image"
                       accept="image/jpeg,image/png,image/webp,image/gif">
            </label>

            <small class="muted">Formatos: JPG, PNG, WEBP o GIF. Máximo 2 MB. Opcional.</small>
            <?php if (!empty($errors['image'])): ?><small class="form__error"><?= e($errors['image']) ?></small><?php endif; ?>
        </div>

        <div class="form__group">
            <label for="description">Descripción</label>
            <textarea id="description" name="description" rows="4"><?= e($p['description'] ?? '') ?></textarea>
        </div>

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">
                <?= isset($p['id']) ? 'Guardar cambios' : 'Publicar al catálogo' ?>
            </button>
            <a class="btn btn--ghost" href="/productor/productos">Cancelar</a>
        </div>
    </form>
</section>
