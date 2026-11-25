<?php
// Renommer un groupe (page utilisée sans JavaScript, et pour confirmer une fusion).
// Variables : $g (groupe), $name (nom saisi), $error, $conflict (autre groupe portant déjà ce nom).
$back = safe_from($_POST['back'] ?? null) ?? from_url(url('category_order'));
?>
<div class="page-head">
    <h1><?= $conflict ? 'Réunir les groupes ?' : 'Renommer le groupe' ?></h1>
    <div class="head-tools"><?= close_link($back, 'Fermer sans enregistrer') ?></div>
</div>

<?php if ($conflict): ?>
    <form class="card" method="post" action="<?= url('group_rename', ['id' => $g['id']]) ?>">
        <?= csrf() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <input type="hidden" name="label" value="<?= e($name) ?>">
        <input type="hidden" name="merge" value="1">
        <p>Le groupe <strong><?= e($conflict['name']) ?></strong> existe déjà.
            Y réunir les catégories de <strong><?= e($g['name']) ?></strong> ? Elles se placeront à la suite des siennes,
            et le groupe <?= e($g['name']) ?> disparaîtra.</p>
        <?php if ((bool) $g['hidden'] !== (bool) $conflict['hidden']): ?>
            <p class="muted"><?= $conflict['hidden']
                ? 'Attention : « ' . e($conflict['name']) . ' » est masqué ; ces catégories seront donc masquées elles aussi.'
                : 'Attention : « ' . e($g['name']) . ' » est masqué, pas « ' . e($conflict['name']) . ' » ; ces catégories redeviendront visibles.' ?></p>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn accent">Réunir</button>
            <a class="btn ghost" href="<?= e($back) ?>">Annuler</a>
        </div>
    </form>
<?php else: ?>
    <form class="card" method="post" action="<?= url('group_rename', ['id' => $g['id']]) ?>">
        <?= csrf() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <label for="g-label">Intitulé du groupe</label>
        <input type="search" data-plain enterkeyhint="done" id="g-label" name="label" value="<?= e($name) ?>" maxlength="80" required autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="false" data-1p-ignore data-lpignore="true"
            <?= $error ? 'aria-invalid="true" aria-describedby="g-label-err"' : '' ?> autofocus>
        <?php if ($error): ?><p class="error" id="g-label-err"><?= e($error) ?></p><?php endif; ?>
        <p class="muted small">Si cet intitulé est déjà celui d'un autre groupe, on te proposera de réunir les deux.</p>
        <div class="form-actions">
            <button class="btn accent">Renommer</button>
            <a class="btn ghost" href="<?= e($back) ?>">Annuler</a>
        </div>
    </form>
<?php endif; ?>
