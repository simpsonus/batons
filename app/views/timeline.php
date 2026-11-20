<?php
// Chronologie, avec filtre (panneau) : groupes et catégories, recherche dans les notes, période.
// Variables : $events, $found (nombre trouvé si recherche ou période, sinon null), $page,
// $filter (g, c, cats, q, p, du, au, crit), $cats, $byGroup, $groups, $counts.
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
    'g' => implode(',', $g), 'c' => implode(',', $c)] + $extra + $filter['crit'], static fn($v) => $v !== '' && $v !== null));
$byCats = $filter['cats'] !== [];
$nCrit = (int) $byCats + (int) ($filter['q'] !== '') + (int) ($filter['p'] !== '');
$filtered = $nCrit > 0;
$nEvents = $found ?? array_sum(array_map(static fn($id) => $counts[$id] ?? 0, $filter['cats']));
$highlight = $filter['q'];
$pager = static fn(int $p): string => $tlUrl($filter['g'], $filter['c'], ['page' => $p ?: null]);
?>
<h1 class="tab-title">Chronologie</h1>

<?php if ($cats): ?>
<?php
$summary = 'Toute la chronologie';
if ($filtered) {
    $bits = [];
    if ($byCats) {
        $parts = array_map(static fn($gid) => $groupNames[$gid] ?? '', $filter['g']);
        foreach ($cats as $cat) {
            if (in_array((int) $cat['id'], $filter['c'], true)) {
                $parts[] = $cat['name'];
            }
        }
        $bits[] = implode(', ', array_slice($parts, 0, 3)) . (count($parts) > 3 ? '…' : '');
    }
    if ($filter['q'] !== '') {
        $bits[] = '« ' . $filter['q'] . ' »';
    }
    if ($filter['p'] === 'dates') {
        $d = static function (string $ymd): string {
            $x = new DateTimeImmutable($ymd, Batons\Time::tz());
            return Batons\Time::shortDate($x) . ($x->format('Y') !== date('Y') ? ' ' . $x->format('Y') : '');
        };
        $bits[] = $filter['du'] === $filter['au'] ? 'le ' . $d($filter['du']) : 'du ' . $d($filter['du']) . ' au ' . $d($filter['au']);
    } elseif ($filter['p'] !== '') {
        $bits[] = ['7' => '7 jours', '30' => '30 jours', '365' => '12 mois'][$filter['p']];
    }
    $summary = implode(' · ', $bits) . ' · ' . number_format($nEvents, 0, ',', ' ') . ' bâton' . ($nEvents > 1 ? 's' : '');
}
?>
<div class="tl-filter">
    <span class="fsplit<?= $filtered ? ' on' : '' ?>">
        <a class="fs-main" href="<?= $tlUrl($filter['g'], $filter['c'], ['filter' => 1]) ?>" data-filter-open aria-haspopup="dialog">
            <?= ui_icon('filter') ?> Filtrer<?php if ($filtered): ?><span aria-hidden="true"> · <?= $nCrit ?></span><span class="sr"> (<?= $nCrit ?> critère<?= $nCrit > 1 ? 's' : '' ?> actif<?= $nCrit > 1 ? 's' : '' ?>)</span><?php endif; ?></a>
        <?php if ($filtered): ?><a class="fs-x" href="<?= url('timeline') ?>" aria-label="Effacer le filtre" title="Effacer le filtre"><?= ui_icon('close') ?></a><?php endif; ?>
    </span>
    <p class="tl-sum" role="status"><?= e($summary) ?></p>
</div>
<?php if (isset($_GET['filter'])): ?>
    <section class="card tl-panel-inline"><?php $panelId = 'p1'; require __DIR__ . '/_timeline_filter.php'; ?></section>
<?php endif; ?>
<?php endif; ?>

<?php if (!$events): ?>
    <p class="muted"><?= $filtered ? 'Aucun bâton pour ce filtre.' : 'Aucun bâton pour l\'instant. Appuie sur une tuile de l\'écran <a href="' . url() . '">Catégories</a> pour commencer.' ?></p>
<?php else: ?>
    <?php require __DIR__ . '/_event_list.php'; ?>
    <?php if ($found !== null && !$hasMore && $page === 0): ?>
        <p class="muted center tl-found"><?= number_format($found, 0, ',', ' ') ?> bâton<?= $found > 1 ? 's' : '' ?> trouvé<?= $found > 1 ? 's' : '' ?></p>
    <?php endif; ?>
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
