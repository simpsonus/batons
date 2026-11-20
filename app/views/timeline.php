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
$tlUrl = static fn(array $g, array $c, array $extra = []): string => url('timeline', array_filter([
    'g' => implode(',', $g), 'c' => implode(',', $c)] + $extra, static fn($v) => $v !== '' && $v !== null));
$filtered = $filter['cats'] !== [];
$nEvents = array_sum(array_map(static fn($id) => $counts[$id] ?? 0, $filter['cats']));
$pager = static fn(int $p): string => $tlUrl($filter['g'], $filter['c'], ['page' => $p ?: null]);
?>
<h1 class="tab-title">Chronologie</h1>

<?php if ($cats): ?>
<?php
$summary = 'Toute la chronologie';
if ($filtered) {
    $parts = array_map(static fn($gid) => $groupNames[$gid] ?? '', $filter['g']);
    foreach ($cats as $cat) {
        if (in_array((int) $cat['id'], $filter['c'], true)) {
            $parts[] = $cat['name'];
        }
    }
    $summary = implode(', ', array_slice($parts, 0, 3)) . (count($parts) > 3 ? '…' : '')
        . ' · ' . number_format($nEvents, 0, ',', ' ') . ' bâton' . ($nEvents > 1 ? 's' : '');
}
?>
<div class="tl-filter">
    <a class="fchip fset<?= $filtered ? ' on' : '' ?>" href="<?= $tlUrl($filter['g'], $filter['c'], ['filter' => 1]) ?>" data-filter-open aria-haspopup="dialog">
        <?= ui_icon('filter') ?> Filtrer<?php if ($filtered): ?><span aria-hidden="true"> · <?= count($filter['cats']) ?></span><span class="sr"> (<?= count($filter['cats']) ?> catégories cochées)</span><?php endif; ?></a>
    <p class="tl-sum" role="status"><?= e($summary) ?></p>
    <?php if ($filtered): ?>
        <a class="fchip fclear" href="<?= url('timeline') ?>" aria-label="Effacer le filtre" title="Effacer le filtre"><?= ui_icon('close') ?></a>
    <?php endif; ?>
</div>
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
