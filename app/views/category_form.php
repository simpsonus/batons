<?php
$isEdit = !empty($c['id']);
$palette = ['#c2410c' => 'Orange', '#b91c1c' => 'Rouge', '#be185d' => 'Rose', '#7c3aed' => 'Violet', '#1d4ed8' => 'Bleu',
            '#0e7490' => 'Bleu canard', '#047857' => 'Vert', '#4d7c0f' => 'Vert olive', '#a16207' => 'Ocre', '#57534e' => 'Gris'];
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h1>
    <div class="head-tools"><?= close_link($isEdit ? url('category', ['id' => $c['id']]) : url(), 'Fermer sans enregistrer') ?></div>
</div>

<form class="card" method="post" action="<?= url('category_save') ?>">
    <?= csrf() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>

    <div class="row">
        <label class="grow">Nom
            <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="80" required <?= $isEdit ? '' : 'autofocus' ?> <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="err-name"' : '' ?> placeholder="ex. Café, Arrosage du ficus, Migraine">
            <?php if (isset($errors['name'])): ?><span class="error" id="err-name"><?= e($errors['name']) ?></span><?php endif; ?>
        </label>
    </div>

    <fieldset>
        <legend>Type</legend>
        <div class="kind-choice">
            <?php foreach (['simple' => 'Simple (un tap = un bâton)', 'serie' => 'Séries', 'film' => 'Films', 'livre' => 'Livres', 'jeu' => 'Jeux vidéo'] as $k => $label): ?>
                <label class="chip wide"><input type="radio" name="kind" value="<?= $k ?>" <?= ($c['kind'] ?? 'simple') === $k ? 'checked' : '' ?>><span><?= $label ?></span></label>
            <?php endforeach; ?>
        </div>
        <p class="muted small field-help">Séries, films, livres et jeux : chaque bâton dit <em>quoi</em> (titre, épisode, tome, note), avec recherche dans un catalogue.</p>
    </fieldset>

    <div class="check">
        <input type="checkbox" name="periods" value="1" id="f-periods" <?= !empty($c['periods']) ? 'checked' : '' ?> aria-describedby="f-periods-help">
        <label for="f-periods">Suivre des périodes (début et fin)</label>
    </div>
    <p class="muted small field-help" id="f-periods-help">Pour les catégories simples : un tap commence la période (un rhume…),
        le tap suivant la termine ; la fiche montre les durées. Une seule période à la fois.</p>

    <div class="icon-field" style="--c: <?= e($c['color']) ?>">
        <span class="icon big" aria-hidden="true" data-icon-preview><?= icon($c['icon'] ?: '|') ?></span>
        <label class="grow">Icône <span class="muted">un émoji, ou une icône Material Design notée <code>mdi:nom</code></span>
            <input type="text" name="icon" value="<?= e($c['icon']) ?>" maxlength="64" placeholder="☕ ou mdi:coffee" autocomplete="off" data-icon-input <?= isset($errors['icon']) ? 'aria-invalid="true" aria-describedby="err-icon"' : '' ?>>
            <?php if (isset($errors['icon'])): ?><span class="error" id="err-icon"><?= e($errors['icon']) ?></span><?php endif; ?>
        </label>
    </div>
    <?php if (Batons\Icons::available()): ?>
        <div class="icon-picker" data-icon-picker hidden>
            <label class="sr" for="icon-search">Chercher une icône</label>
            <input type="search" id="icon-search" placeholder="Chercher une icône : café, course, pilule, plante…" data-icon-search autocomplete="off">
            <div class="icon-results" data-icon-results role="group" aria-label="Icônes proposées"></div>
            <p class="sr" aria-live="polite" data-icon-status></p>
            <p class="muted small">Icônes <a href="https://pictogrammers.com/library/mdi/" target="_blank" rel="noopener">Material Design Icons<span class="sr"> (nouvel onglet)</span></a>.</p>
        </div>
    <?php endif; ?>

    <fieldset>
        <legend>Couleur</legend>
        <div class="swatches">
            <?php foreach ($palette as $col => $colName): ?>
                <label class="swatch" style="--c: <?= $col ?>">
                     <input type="radio" name="color" value="<?= $col ?>" aria-label="<?= $colName ?>" <?= strtolower($c['color']) === $col ? 'checked' : '' ?>>
                    <span></span>
                </label>
            <?php endforeach; ?>
            <label class="swatch custom" title="Autre couleur">
                <input type="color" name="color_custom" value="<?= e($c['color']) ?>" data-color-custom aria-label="Autre couleur">
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

    <div class="check">
        <input type="checkbox" name="hidden" value="1" id="f-hidden" <?= !empty($c['hidden']) ? 'checked' : '' ?>
               aria-describedby="f-hidden-help<?= isset($errors['hidden']) ? ' err-hidden' : '' ?>" <?= isset($errors['hidden']) ? 'aria-invalid="true"' : '' ?>>
        <label for="f-hidden"><?= ui_icon('eye-off') ?> Masquer cette catégorie</label>
    </div>
    <p class="muted small field-help" id="f-hidden-help">Invisible partout tant que les éléments masqués ne sont pas déverrouillés (Menu).</p>
    <?php if (isset($errors['hidden'])): ?><p class="error" id="err-hidden"><?= e($errors['hidden']) ?></p><?php endif; ?>

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
