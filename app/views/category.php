<?php
use Batons\Chart;
use Batons\Time;

$id = (int) $c['id'];
$hasValues = (int) $stats['with_value'] > 0;
$fmt = static fn($p) => $metric === 'sum' && $hasValues
    ? Chart::num($p['v'] ?? 0) . ($c['unit'] ? ' ' . $c['unit'] : '')
    : (string) (int) $p['n'];
$hasMore = count($events) > 50;
$events = array_slice($events, 0, 50);
?>
<div class="page-head cat-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="icon big" aria-hidden="true"><?= icon($c['icon']) ?></span>
        <div>
            <h1><?= e($c['name']) ?></h1>
            <?php if ($c['group_name']): ?><p class="muted"><?= e($c['group_name']) ?></p><?php endif; ?>
            <?php if ($c['archived']): ?><p class="badge">Archivée</p><?php endif; ?>
            <?php if ($c['hidden'] || $c['group_hidden']): ?><p class="badge"><?= ui_icon('eye-off') ?> Masquée<?= !$c['hidden'] ? ' (via son groupe)' : '' ?></p><?php endif; ?>
        </div>
    </div>
    <div class="actions">
        <a class="btn ghost" href="<?= url('category_edit', ['id' => $id]) ?>">Modifier</a>
    </div>
</div>

<?php if ($c['kind'] !== 'simple'): ?>
    <?php
    $works = Batons\Works::inCategory($uid, $id);
    $statuses = [];
    if ($c['kind'] === 'jeu') {
        foreach ($works as $w) {
            $statuses[$w['id']] = Batons\Works::status((int) $w['id'], $id);
        }
        $doneDays = array_column(array_filter($statuses, static fn($s) => $s['state'] === 'done'), 'days');
    }
    ?>
    <section class="card" style="--c: <?= e($c['color']) ?>" aria-labelledby="works-title">
        <div class="card-head">
            <h2 id="works-title" class="card-title"><?= e(Batons\Catalog::KINDS[$c['kind']]) ?> <span class="muted small"><?= count($works) ?></span></h2>
            <a class="btn accent" href="<?= url('work_add', ['id' => $id]) ?>">+ Ajouter</a>
        </div>
        <?php if (!empty($doneDays)): ?>
            <p class="small"><?= count($doneDays) ?> jeu<?= count($doneDays) > 1 ? 'x' : '' ?> terminé<?= count($doneDays) > 1 ? 's' : '' ?>,
                en <strong><?= e(Batons\Works::daysLabel((int) round(array_sum($doneDays) / count($doneDays)))) ?></strong> en moyenne.</p>
        <?php endif; ?>
        <?php if (!$works): ?>
            <p class="muted">Rien pour l'instant : « + Ajouter » pour chercher <?= ['film' => 'un film', 'serie' => 'une série', 'livre' => 'un livre', 'jeu' => 'un jeu'][$c['kind']] ?>.</p>
        <?php else: ?>
            <ul class="work-list">
                <?php foreach (array_slice($works, 0, 30) as $w):
                    $lastW = Batons\Works::lastEvent((int) $w['id'], $id);
                    $nextW = Batons\Works::next($uid, $w, $lastW); ?>
                    <li>
                        <span class="poster sm" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" loading="lazy" width="46" height="69"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
                        <span class="work-text">
                            <a class="work-title" href="<?= url('work', ['id' => $id, 'work' => $w['id']]) ?>"><?= e($w['title']) ?></a>
                            <?php $stW = $statuses[$w['id']] ?? null; ?>
                            <span class="muted small"><?= e(implode(' · ', array_filter([
                                $stW ? ($stW['state'] === 'done' ? 'Terminé en ' . Batons\Works::daysLabel((int) $stW['days']) : 'En cours depuis ' . Batons\Works::daysLabel((int) $stW['days'])) : null,
                                Batons\Works::progress($lastW['season'] ?? null, $lastW['episode'] ?? null, $lastW['volume'] ?? null),
                                Time::ago($w['last_at']),
                                (int) $w['n'] > 1 ? $w['n'] . ' bâtons' : null,
                            ]))) ?></span>
                            <?php if ($w['avg_rating'] !== null): ?><?= rating_badge((int) round((float) $w['avg_rating'])) ?><?php endif; ?>
                        </span>
                        <?php if ($nextW && !($stW && $stW['state'] === 'done')): ?>
                            <form method="post" action="<?= url('work_next', ['id' => $id, 'work' => $w['id']]) ?>">
                                <?= csrf() ?>
                                <input type="hidden" name="back" value="<?= e(here()) ?>">
                                <button class="btn ghost next-btn" aria-label="Ajouter <?= e($w['title']) ?>, <?= e((Batons\Works::progressLong($nextW['season'] ?? null, $nextW['episode'] ?? null, $nextW['volume'] ?? null) ?: 'une partie')) ?>, maintenant">
                                    <span aria-hidden="true">▶</span> <?= e((Batons\Works::progress($nextW['season'] ?? null, $nextW['episode'] ?? null, $nextW['volume'] ?? null) ?: 'Partie')) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($c['kind'] === 'jeu' && $works): ?><p class="muted small">Données de jeux : <a href="https://rawg.io" target="_blank" rel="noopener">RAWG<span class="sr"> (nouvel onglet)</span></a>.</p><?php endif; ?>
    </section>
<?php else: ?>
<form class="card add-form" method="post" action="<?= url('event_add') ?>" style="--c: <?= e($c['color']) ?>" aria-label="Ajouter un bâton avec date, valeur ou note">
    <?= csrf() ?>
    <input type="hidden" name="category_id" value="<?= $id ?>">
    <div class="row">
        <label>Quand
            <input type="datetime-local" name="occurred_at" value="<?= e(Time::toLocalInput()) ?>">
        </label>
        <label>Valeur<?= $c['unit'] ? ' (' . e($c['unit']) . ')' : '' ?> <span class="muted">facultatif</span>
            <input type="text" name="value" inputmode="decimal" placeholder="ex. 2,5" autocomplete="off">
        </label>
    </div>
    <label>Note <span class="muted">facultatif</span>
        <input type="text" name="note" maxlength="500" autocomplete="off">
    </label>
    <button class="btn accent">+ Ajouter un bâton</button>
</form>
<?php endif; ?>

<h2 class="sr">Statistiques</h2>
<section class="stats">
    <div class="stat wide">
        <span class="k">Dernier bâton</span>
        <span class="v"><?= e(Time::ago($stats['last_at'])) ?></span>
        <?php if ($stats['last_at']): ?><span class="s"><?= e(Time::longDate(Time::local($stats['last_at']))) ?> à <?= Time::local($stats['last_at'])->format('H:i') ?></span><?php endif; ?>
    </div>
    <div class="stat"><span class="k">Aujourd'hui</span><span class="v"><?= e($fmt($stats['periods']['today'])) ?></span></div>
    <div class="stat"><span class="k">Cette semaine</span><span class="v"><?= e($fmt($stats['periods']['week'])) ?></span></div>
    <div class="stat"><span class="k">Ce mois</span><span class="v"><?= e($fmt($stats['periods']['month'])) ?></span></div>
    <div class="stat"><span class="k">Cette année</span><span class="v"><?= e($fmt($stats['periods']['year'])) ?></span></div>
    <div class="stat"><span class="k">Au total</span><span class="v"><?= e($fmt(['n' => $stats['total'], 'v' => $stats['total_value']])) ?></span></div>
    <div class="stat"><span class="k">Intervalle moyen</span><span class="v"><?= $stats['avg_interval'] !== null ? e(Time::duration($stats['avg_interval'])) : '—' ?></span></div>
</section>

<?php
[$calStart, $calCounts] = Batons\Events::daily($id);
$calDays = count($calCounts);
$byMonth = [];
foreach ($calCounts as $d => $n) {
    $k = substr($d, 0, 7);
    $byMonth[$k] = ['days' => ($byMonth[$k]['days'] ?? 0) + 1, 'n' => ($byMonth[$k]['n'] ?? 0) + $n];
}
krsort($byMonth);
?>
<section class="card" aria-labelledby="cal-title" style="--c: <?= e($c['color']) ?>">
    <h2 id="cal-title" class="card-title">Calendrier <span class="muted small"><?= $calDays ?> jour<?= $calDays > 1 ? 's' : '' ?> avec bâton sur 12 mois</span></h2>
    <div class="cal-wrap">
        <?= Chart::calendarDays() ?>
        <div class="cal-scroll" data-scroll-end tabindex="0" role="group" aria-label="Calendrier des 12 derniers mois (défilement horizontal)">
            <?= Chart::calendar($calStart, $calCounts) ?>
        </div>
    </div>
    <div class="cal-legend" aria-hidden="true">
        <span>Moins</span><i class="cal-0"></i><i class="cal-1"></i><i class="cal-2"></i><i class="cal-3"></i><i class="cal-4"></i><span>Plus</span>
    </div>
    <div class="sr"><table>
        <caption>Jours avec au moins un bâton, par mois, sur les 12 derniers mois</caption>
        <thead><tr><th scope="col">Mois</th><th scope="col">Jours</th><th scope="col">Bâtons</th></tr></thead>
        <tbody>
        <?php foreach ($byMonth as $ym => $v): ?>
            <tr><th scope="row"><?= e(['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][(int) substr($ym, 5, 2) - 1] . ' ' . substr($ym, 0, 4)) ?></th>
                <td><?= $v['days'] ?></td><td><?= $v['n'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$byMonth): ?><tr><td colspan="3">Aucun bâton sur la période.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<section class="card" aria-labelledby="chart-title">
    <h2 class="sr" id="chart-title">Graphique</h2>
    <div class="chart-head">
        <nav class="seg" aria-label="Échelle">
            <?php foreach (['day' => '30 jours', 'week' => '12 semaines', 'month' => '12 mois'] as $k => $label): ?>
                <a href="<?= url('category', ['id' => $id, 'scale' => $k, 'metric' => $metric]) ?>" <?= $scale === $k ? 'aria-current="true"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($hasValues): ?>
            <nav class="seg" aria-label="Mesure">
                <a href="<?= url('category', ['id' => $id, 'scale' => $scale, 'metric' => 'count']) ?>" <?= $metric === 'count' ? 'aria-current="true"' : '' ?>>Nombre</a>
                <a href="<?= url('category', ['id' => $id, 'scale' => $scale, 'metric' => 'sum']) ?>" <?= $metric === 'sum' ? 'aria-current="true"' : '' ?>>Somme<?= $c['unit'] ? ' (' . e($c['unit']) . ')' : '' ?></a>
            </nav>
        <?php endif; ?>
    </div>
    <?php $scaleLabel = ['day' => '30 derniers jours', 'week' => '12 dernières semaines', 'month' => '12 derniers mois'][$scale]; ?>
    <div aria-hidden="true"><?= Chart::bars($series, $metric, $c['color'], $c['unit']) ?></div>
    <div class="sr"><table>
        <caption><?= $metric === 'sum' ? 'Somme' : 'Nombre de bâtons' ?> sur les <?= $scaleLabel ?></caption>
        <thead><tr><th scope="col">Période</th><th scope="col"><?= $metric === 'sum' ? 'Somme' . ($c['unit'] ? ' (' . e($c['unit']) . ')' : '') : 'Nombre' ?></th></tr></thead>
        <tbody>
        <?php foreach ($series as $p): ?>
            <tr><th scope="row"><?= e($scale === 'day' ? Time::shortDate($p['date']) : ($scale === 'week' ? 'semaine du ' . Time::shortDate($p['date']) : $p['label'] . ' ' . $p['date']->format('Y'))) ?></th>
                <td><?= e(Chart::num($p[$metric])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section>
    <h2>Historique</h2>
    <?php if (!$events): ?>
        <p class="muted">Aucun bâton pour l'instant.</p>
    <?php else: ?>
        <?php require __DIR__ . '/_event_list.php'; ?>
        <div class="pager">
            <?php if ($page > 0): ?><a class="btn ghost" href="<?= url('category', ['id' => $id, 'scale' => $scale, 'page' => $page - 1]) ?>">← Plus récents</a><?php endif; ?>
            <?php if ($hasMore): ?><a class="btn ghost" href="<?= url('category', ['id' => $id, 'scale' => $scale, 'page' => $page + 1]) ?>">Plus anciens →</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
