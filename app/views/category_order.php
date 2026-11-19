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
if (!empty($byGroup[0])) {
    $sections[] = ['id' => 0, 'name' => $groups ? 'Sans groupe' : null, 'hidden' => false, 'items' => $byGroup[0]];
}
$groupCount = count($groups);

/** Bouton monter/descendre (POST). */
$moveButton = static function (string $route, int $id, string $dir, string $label, bool $enabled): string {
    $verb = $dir === 'up' ? 'Monter' : 'Descendre';
    return '<form method="post" action="' . url($route) . '">' . csrf()
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="dir" value="' . $dir . '">'
        . '<input type="hidden" name="back" value="' . e(here()) . '">'
        . '<button class="icon-btn" aria-label="' . $verb . ' ' . e($label) . '" title="' . $verb . '"' . ($enabled ? '' : ' disabled') . '>'
        . ui_icon($dir) . '</button></form>';
};
?>
<div class="page-head">
    <h1>Réorganiser</h1>
    <div class="actions"><a class="btn" href="<?= url() ?>">Terminé</a></div>
</div>
<p class="muted">Les flèches des groupes déplacent un groupe entier ; celles des catégories les déplacent à l'intérieur de leur groupe. Pour changer une catégorie de groupe, utilise « Modifier » sur sa fiche.</p>

<?php foreach ($sections as $gi => $sec): ?>
    <section class="order-group" aria-labelledby="grp-<?= $gi ?>">
        <div class="order-group-head">
            <h2 id="grp-<?= $gi ?>"><?= e($sec['name'] ?? 'Catégories') ?></h2>
            <?php if ($sec['id'] > 0): ?>
                <div class="order-buttons">
                    <form method="post" action="<?= url('group_hide') ?>">
                        <?= csrf() ?>
                        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                        <input type="hidden" name="hidden" value="<?= $sec['hidden'] ? '0' : '1' ?>">
                        <input type="hidden" name="back" value="<?= e(here()) ?>">
                        <button class="icon-btn<?= $sec['hidden'] ? ' is-hidden' : '' ?>" aria-pressed="<?= $sec['hidden'] ? 'true' : 'false' ?>"
                                aria-label="Masquer le groupe <?= e($sec['name']) ?>" title="<?= $sec['hidden'] ? 'Groupe masqué : le rendre visible' : 'Masquer le groupe' ?>">
                            <?= ui_icon($sec['hidden'] ? 'eye-off' : 'eye') ?></button>
                    </form>
                    <?php if ($groupCount > 1): ?>
                        <?= $moveButton('group_move', $sec['id'], 'up', 'le groupe ' . $sec['name'], $gi > 0) ?>
                        <?= $moveButton('group_move', $sec['id'], 'down', 'le groupe ' . $sec['name'], $gi < $groupCount - 1) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$sec['items']): ?>
            <p class="muted small">Aucune catégorie active dans ce groupe.</p>
        <?php else: ?>
            <ul class="order-list">
                <?php $n = count($sec['items']); foreach ($sec['items'] as $i => $c): ?>
                    <li style="--c: <?= e($c['color']) ?>">
                        <span class="icon sm" aria-hidden="true"><?= icon($c['icon']) ?></span>
                        <span class="grow"><?= e($c['name']) ?><?php if ($c['hidden']): ?> <span class="hidden-mark" title="Catégorie masquée"><?= ui_icon('eye-off') ?><span class="sr">(masquée)</span></span><?php endif; ?></span>
                        <?php if ($n > 1): ?>
                            <span class="order-buttons">
                                <?= $moveButton('category_move', (int) $c['id'], 'up', $c['name'], $i > 0) ?>
                                <?= $moveButton('category_move', (int) $c['id'], 'down', $c['name'], $i < $n - 1) ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
