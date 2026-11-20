<?php
// Exporter : choisir quoi, puis télécharger. Variables : $categories (visibles, archives comprises), $hiddenLocked.
$closeTo = from_url(url());
?>
<div class="page-head">
    <h1>Exporter</h1>
    <div class="head-tools"><?= close_link($closeTo, 'Fermer') ?></div>
</div>

<form class="card" method="get" action="./">
    <input type="hidden" name="r" value="export">
    <input type="hidden" name="download" value="1">
    <fieldset>
        <legend>Que veux-tu exporter ?</legend>
        <div class="check">
            <input type="radio" name="what" value="all" id="x-all" checked>
            <label for="x-all">Tout <span class="muted">(<?= count($categories) ?> catégorie<?= count($categories) > 1 ? 's' : '' ?>, archives comprises)</span></label>
        </div>
        <div class="check">
            <input type="radio" name="what" value="one" id="x-one">
            <label for="x-one">Une seule catégorie</label>
        </div>
        <label class="export-cat">Catégorie
            <select name="cat" data-export-cat>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['archived'] ? ' (archivée)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </fieldset>
    <p class="muted small">Un fichier CSV (séparateur « ; », lisible dans Excel, Numbers ou LibreOffice) avec la date, la catégorie,
        le groupe, la valeur, la note et, pour les œuvres, le titre, l'épisode ou le tome et la note sur 10.
        Il peut être réimporté tel quel dans Bâtons, ici ou ailleurs.</p>
    <?php if ($hiddenLocked): ?>
        <p class="export-note small"><?= ui_icon('lock') ?> Les éléments masqués ne sont pas inclus tant qu'ils sont verrouillés.
            <a href="<?= url('unlock', from_here([])) ?>">Les afficher</a> pour les exporter aussi.</p>
    <?php endif; ?>
    <div class="form-actions">
        <button class="btn accent"><?= ui_icon('export') ?> Télécharger le fichier CSV</button>
        <a class="btn ghost" href="<?= e($closeTo) ?>">Annuler</a>
    </div>
</form>
