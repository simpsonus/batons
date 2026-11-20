<?php
// Chronologie, avec filtre par groupes (pastilles cumulables) et par catégories (panneau de réglage fin).
// Variables : $events, $page, $filter (g, c, cats), $cats, $byGroup, $groups, $counts.
$hasMore = count($events) > 100;
$events = array_slice($events, 0, 100);
$showCategory = true;
$dayLevel = 2;

$groupNames = [];
foreach ($groups as $g) {
    $groupNames[(int) $g['id']] = $g['name'];
}
$groupNames[0] = 'Sans groupe';      // toujours en dernier
// Groupes affichés en pastilles : ceux qui ont des catégories visibles, dans l'ordre de Réorganiser, « Sans groupe » à la fin.
$pillGroups = array_values(array_filter(array_keys($groupNames), static fn($gid) => !empty($byGroup[$gid])));
if (count($pillGroups) === 1 && $pillGroups[0] === 0) {
    $pillGroups = [];      // aucune organisation en groupes : seul le panneau a du sens
}
$tlUrl = static fn(array $g, array $c, array $extra = []): string => url('timeline', array_filter([
    'g' => implode(',', $g), 'c' => implode(',', $c)] + $extra, static fn($v) => $v !== '' && $v !== null));
$filtered = $filter['cats'] !== [];
$loose = $filter['c'] !== [];
$nEvents = array_sum(array_map(static fn($id) => $counts[$id] ?? 0, $filter['cats']));
$pager = static fn(int $p): string => $tlUrl($filter['g'], $filter['c'], ['page' => $p ?: null]);
?>
<h1 class="tab-title">Chronologie</h1>

<?php if ($cats): ?>
<nav class="tl-filter" aria-label="Filtrer la chronologie">
    <div class="tl-chips" data-chips>
        <a class="fchip<?= $filtered ? '' : ' on' ?>" href="<?= url('timeline') ?>"<?= $filtered ? '' : ' aria-current="true"' ?>>Tout<?php if (!$filtered): ?><span class="sr"> (affiché)</span><?php endif; ?></a>
        <?php if ($loose): ?>
            <?php $ng = count($filter['g']); $nc = count($filter['c']); ?>
            <a class="fchip on" href="<?= url('timeline') ?>" aria-current="true">
                <?= $ng ? $ng . ' groupe' . ($ng > 1 ? 's' : '') . ', ' : '' ?><?= $nc ?> catégorie<?= $nc > 1 ? 's' : '' ?>
                <span aria-hidden="true">✕</span><span class="sr"> : filtre actif, retirer</span></a>
        <?php else: ?>
            <?php foreach ($pillGroups as $gid): $on = in_array($gid, $filter['g'], true);
                $next = $on ? array_values(array_diff($filter['g'], [$gid])) : array_merge($filter['g'], [$gid]); ?>
                <a class="fchip<?= $on ? ' on' : '' ?>" href="<?= $tlUrl($next, []) ?>"<?= $on ? ' aria-current="true" data-on' : '' ?>>
                    <?= e($groupNames[$gid]) ?><?php if ($on): ?> <span aria-hidden="true">✕</span><span class="sr"> (filtre actif, retirer)</span><?php endif; ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <a class="fchip fset<?= $filtered ? ' on' : '' ?>" href="<?= $tlUrl($filter['g'], $filter['c'], ['filter' => 1]) ?>" data-filter-open aria-haspopup="dialog"
       aria-label="Filtrer par catégorie<?= $filtered ? ' (' . count($filter['cats']) . ' cochées)' : '' ?>" title="Filtrer par catégorie">
        <?= ui_icon('filter') ?><?php if ($filtered): ?><span class="fcount" aria-hidden="true"><?= count($filter['cats']) ?></span><?php endif; ?></a>
</nav>
<?php if ($filtered): ?>
    <p class="tl-info" role="status">
        <?php
        $parts = array_map(static fn($gid) => $groupNames[$gid] ?? '', $filter['g']);
        if ($filter['c']) {
            $names = [];
            foreach ($cats as $cat) {
                if (in_array((int) $cat['id'], $filter['c'], true)) {
                    $names[] = $cat['name'];
                }
            }
            $parts = array_merge($parts, $names);
        }
        $nCat = count($filter['cats']);
        ?>
        <?= e(implode(', ', array_slice($parts, 0, 4)) . (count($parts) > 4 ? '…' : '')) ?>
        · <?= $nCat ?> catégorie<?= $nCat > 1 ? 's' : '' ?> · <?= number_format($nEvents, 0, ',', ' ') ?> bâton<?= $nEvents > 1 ? 's' : '' ?>
    </p>
<?php endif; ?>
<?php if (isset($_GET['filter'])): ?>
    <section class="card tl-panel-inline"><?php $panelId = 'p1'; require __DIR__ . '/_timeline_filter.php'; ?></section>
<?php endif; ?>
<?php endif; ?>

<?php if (!$events): ?>
    <p class="muted"><?= $filtered ? 'Aucun bâton pour ce filtre.' : 'Aucun bâton pour l\'instant. Appuie sur une tuile de l\'écran <a href="' . url() . '">Catégories</a> pour commencer.' ?></p>
<?php else: ?>
    <?php require __DIR__ . '/_event_list.php'; ?>
    <div class="pager">
        <?php if ($page > 0): ?><a class="btn ghost" href="<?= $pager($page - 1) ?>">← Plus récents</a><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn ghost" href="<?= $pager($page + 1) ?>">Plus anciens →</a><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($cats && !isset($_GET['filter'])): ?>
<dialog class="sheet filter-sheet" data-filter-sheet aria-labelledby="filter-title-p2">
    <?php $panelId = 'p2'; require __DIR__ . '/_timeline_filter.php'; ?>
</dialog>
<?php endif; ?>
