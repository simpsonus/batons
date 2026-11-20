<?php
// Réorganisation : ordre des groupes entre eux, puis ordre des catégories dans chaque groupe.
$byGroup = [];
foreach ($categories as $c) {
    $byGroup[$c['group_id'] ?? 0][] = $c;
}
$sections = [];
foreach ($groups as $g) {
    $sections[] = ['id' => (int) $g['id'], 'name' => $g['name'], 'hidden' => (bool) $g['hidden'], 'items' => $byGroup[$g['id']] ?? []];
}
// « Sans groupe » toujours présent quand il y a des groupes : on peut y déposer une catégorie.
if (!empty($byGroup[0]) || $groups) {
    $sections[] = ['id' => 0, 'name' => $groups ? 'Sans groupe' : null, 'hidden' => false, 'items' => $byGroup[0] ?? []];
}
$groupCount = count($groups);

/** Bouton monter/descendre (POST). */
$moveButton = static function (string $route, int $id, string $dir, string $label, bool $enabled): string {
    $verb = $dir === 'up' ? 'Monter' : 'Descendre';
    return '<form method="post" action="' . url($route) . '">' . csrf()
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="dir" value="' . $dir . '">'
        . '<input type="hidden" name="back" value="' . e(here()) . '">'
        . '<button class="icon-btn nojs-move" aria-label="' . $verb . ' ' . e($label) . '" title="' . $verb . '"' . ($enabled ? '' : ' disabled') . '>'
        . ui_icon($dir) . '</button></form>';
};
?>
<div class="page-head">
    <h1>Réorganiser</h1>
    <div class="head-tools">
        <?= close_link(from_url(url()), 'Fermer') ?>
    </div>
</div>
<p class="muted nojs-move">Les flèches des groupes déplacent un groupe entier ; celles des catégories les déplacent à l'intérieur de leur groupe.
    Pour changer une catégorie de groupe, utilise le crayon ✎ à côté de son nom, sur sa page.</p>
<p class="muted js-only" hidden>Fais glisser la poignée <span aria-hidden="true">⠿</span> d'une catégorie (dans son groupe ou vers un autre) ou d'un groupe.
    Un simple appui sur la poignée propose aussi Monter, Descendre et Déplacer vers un groupe. L'ordre est enregistré aussitôt.</p>
<p class="order-status" role="status" aria-live="polite" data-order-status></p>
<?php if ($groupCount > 0): ?>
<div class="seg order-view" role="group" aria-label="Affichage" data-fold-view hidden>
    <button type="button" data-fold-view-btn="all" aria-pressed="true">Tout</button>
    <button type="button" data-fold-view-btn="groups" aria-pressed="false">Groupes seuls</button>
</div>
<?php endif; ?>

<div class="order-board" data-reorder="<?= e(url('reorder')) ?>" data-csrf="<?= e(Batons\Http::csrfToken()) ?>">
<?php foreach ($sections as $gi => $sec): $label = $sec['name'] ?? 'Catégories'; ?>
    <section class="order-group" data-group="<?= $sec['id'] ?>" data-name="<?= e($label) ?>" aria-labelledby="grp-<?= $gi ?>">
        <div class="order-group-head">
            <?php if ($sec['id'] > 0): ?>
                <button type="button" class="icon-btn fold-btn" data-fold aria-expanded="true" aria-controls="list-<?= $gi ?>"
                        aria-label="Replier le groupe <?= e($label) ?>" title="Replier / déplier" hidden><?= ui_icon('down') ?></button>
            <?php endif; ?>
            <h2 id="grp-<?= $gi ?>"><?= e($label) ?><?php if ($sec['id'] > 0): ?> <span class="order-count"><span data-count><?= count($sec['items']) ?></span><span class="sr"> catégories</span></span><?php endif; ?></h2>
            <?php if ($sec['id'] > 0): ?>
                <a class="icon-btn rename-btn" href="<?= url('group_rename', from_here(['id' => $sec['id']])) ?>" data-rename
                   aria-label="Renommer le groupe <?= e($label) ?>" title="Renommer le groupe"><?= ui_icon('pencil') ?></a>
                <form class="rename-form" method="post" action="<?= url('group_rename', ['id' => $sec['id']]) ?>" hidden>
                    <?= csrf() ?>
                    <input type="hidden" name="back" value="<?= e(here()) ?>">
                    <input type="text" name="label" value="<?= e($label) ?>" maxlength="80" required autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="false" data-1p-ignore data-lpignore="true" aria-label="Nouvel intitulé du groupe <?= e($label) ?>">
                    <button class="btn accent">OK</button>
                    <button type="button" class="btn ghost" data-rename-cancel>Annuler</button>
                </form>
                <div class="order-buttons">
                    <form method="post" action="<?= url('group_hide') ?>">
                        <?= csrf() ?>
                        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                        <input type="hidden" name="hidden" value="<?= $sec['hidden'] ? '0' : '1' ?>">
                        <input type="hidden" name="back" value="<?= e(here()) ?>">
                        <button class="icon-btn<?= $sec['hidden'] ? ' is-hidden' : '' ?>" aria-pressed="<?= $sec['hidden'] ? 'true' : 'false' ?>"
                                aria-label="Masquer le groupe <?= e($label) ?>" title="<?= $sec['hidden'] ? 'Groupe masqué : le rendre visible' : 'Masquer le groupe' ?>">
                            <?= ui_icon($sec['hidden'] ? 'eye-off' : 'eye') ?></button>
                    </form>
                    <?php if ($groupCount > 1): ?>
                        <?= $moveButton('group_move', $sec['id'], 'up', 'le groupe ' . $label, $gi > 0) ?>
                        <?= $moveButton('group_move', $sec['id'], 'down', 'le groupe ' . $label, $gi < $groupCount - 1) ?>
                        <button type="button" class="icon-btn drag-handle" data-handle="group" aria-label="Déplacer le groupe <?= e($label) ?>"
                                title="Glisser pour déplacer le groupe (ou appuyer pour les options)" hidden><?= ui_icon('drag') ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <ul class="order-list" id="list-<?= $gi ?>" data-list="<?= $sec['id'] ?>">
            <?php $n = count($sec['items']); foreach ($sec['items'] as $i => $c): ?>
                <li style="<?= cvars($c['color']) ?>" data-id="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>">
                    <span class="icon sm" aria-hidden="true"><?= icon($c['icon']) ?></span>
                    <span class="grow"><?= e($c['name']) ?><?php if ($c['hidden']): ?> <span class="hidden-mark" title="Catégorie masquée"><?= ui_icon('eye-off') ?><span class="sr">(masquée)</span></span><?php endif; ?></span>
                    <span class="order-buttons">
                        <?php if ($n > 1): ?>
                            <?= $moveButton('category_move', (int) $c['id'], 'up', $c['name'], $i > 0) ?>
                            <?= $moveButton('category_move', (int) $c['id'], 'down', $c['name'], $i < $n - 1) ?>
                        <?php endif; ?>
                        <button type="button" class="icon-btn drag-handle" data-handle="cat" aria-label="Déplacer <?= e($c['name']) ?>"
                                title="Glisser pour déplacer (ou appuyer pour les options)" hidden><?= ui_icon('drag') ?></button>
                    </span>
                </li>
            <?php endforeach; ?>
            <li class="order-empty muted small">Aucune catégorie dans ce groupe.</li>
        </ul>
    </section>
<?php endforeach; ?>
</div>

<dialog class="move-menu" data-move-menu aria-labelledby="move-menu-title">
    <h2 id="move-menu-title" class="move-menu-title"></h2>
    <div class="move-menu-actions" data-move-actions></div>
    <form method="dialog"><button class="btn ghost move-menu-close">Fermer</button></form>
</dialog>
