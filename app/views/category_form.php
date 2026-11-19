<?php
$isEdit = !empty($c['id']);
$palette = ['#c2410c', '#b91c1c', '#be185d', '#7c3aed', '#1d4ed8', '#0e7490', '#047857', '#4d7c0f', '#a16207', '#57534e'];
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h1>
</div>

<form class="card" method="post" action="<?= url('category_save') ?>">
    <?= csrf() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>

    <div class="row">
        <label class="grow">Nom
            <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="80" required autofocus placeholder="ex. Café, Arrosage du ficus, Migraine">
            <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
        </label>
        <label class="narrow">Icône
            <input type="text" name="icon" value="<?= e($c['icon']) ?>" maxlength="8" placeholder="☕" class="icon-input">
            <?php if (isset($errors['icon'])): ?><span class="error"><?= e($errors['icon']) ?></span><?php endif; ?>
        </label>
    </div>

    <fieldset>
        <legend>Couleur</legend>
        <div class="swatches">
            <?php foreach ($palette as $col): ?>
                <label class="swatch" style="--c: <?= $col ?>">
                    <input type="radio" name="color" value="<?= $col ?>" <?= strtolower($c['color']) === $col ? 'checked' : '' ?>>
                    <span></span>
                </label>
            <?php endforeach; ?>
            <label class="swatch custom" title="Autre couleur">
                <input type="color" name="color_custom" value="<?= e($c['color']) ?>" data-color-custom>
            </label>
        </div>
    </fieldset>

    <div class="row">
        <label class="grow">Groupe <span class="muted">facultatif</span>
            <input type="text" name="group" value="<?= e($c['group_name']) ?>" list="groups" maxlength="60" placeholder="ex. Santé, Maison">
            <datalist id="groups"><?php foreach ($groups as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
        </label>
        <label class="narrow">Unité <span class="muted">facultatif</span>
            <input type="text" name="unit" value="<?= e($c['unit']) ?>" maxlength="20" placeholder="km, €, mg…">
        </label>
    </div>

    <div class="form-actions">
        <button class="btn accent"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        <a class="btn ghost" href="<?= $isEdit ? url('category', ['id' => $c['id']]) : url() ?>">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="danger-zone">
        <form method="post" action="<?= url('category_archive') ?>">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn ghost"><?= $c['archived'] ? 'Restaurer' : 'Archiver' ?></button>
        </form>
        <form method="post" action="<?= url('category_delete') ?>" data-confirm="Supprimer « <?= e($c['name']) ?> » et TOUS ses bâtons ? C'est définitif.">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn danger">Supprimer définitivement</button>
        </form>
    </div>
<?php endif; ?>
