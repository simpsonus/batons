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
<div class="page-head cat-head" style="<?= cvars($c['color']) ?>">
    <div class="cat-title">
        <span class="icon big" aria-hidden="true"><?= icon($c['icon']) ?></span>
        <div>
            <div class="title-line">
                <h1><?= e($c['name']) ?></h1>
                <a class="icon-btn edit-btn" href="<?= url('category_edit', from_here(['id' => $id])) ?>" aria-label="Modifier la catégorie <?= e($c['name']) ?>" title="Modifier la catégorie (nom, icône, couleur, groupe…)"><?= ui_icon('pencil') ?></a>
            </div>
            <?php if ($c['group_name']): ?><p class="muted"><?= e($c['group_name']) ?></p><?php endif; ?>
            <?php if ($c['archived']): ?><p class="badge">Archivée</p><?php endif; ?>
            <?php if ($c['hidden'] || $c['group_hidden']): ?><p class="badge"><?= ui_icon('eye-off') ?> Masquée<?= !$c['hidden'] ? ' (via son groupe)' : '' ?></p><?php endif; ?>
        </div>
    </div>
    <div class="head-tools">
        <?= close_link(from_url($c['kind'] !== 'simple' ? url('work_add', ['id' => $id]) : url()), 'Fermer') ?>
    </div>
</div>

<?php if ($c['kind'] !== 'simple'): ?>
    <?php
    $works = Batons\Works::inCategory($uid, $id);
    $statuses = [];
    if (in_array($c['kind'], Batons\Works::TRACKED, true)) {
        $mlC = Batons\Works::milestoneLabels($c['kind']);
        foreach ($works as $w) {
            $statuses[$w['id']] = Batons\Works::status((int) $w['id'], $id);
        }
        $doneDays = array_column(array_filter($statuses, static fn($s) => $s['state'] === 'done'), 'days');
    }
    ?>
    <?php
    // Livres et jeux : « En cours » (avec actions), puis « Lus / Terminés » et « Arrêtés », repliés.
    // Films et séries : une seule liste (derniers vus), sans notion de fin.
    $tracked = in_array($c['kind'], Batons\Works::TRACKED, true);
    $groupsW = ['open' => [], 'done' => [], 'stopped' => []];
    foreach ($works as $w) {
        $w['_last'] = Batons\Works::lastEvent((int) $w['id'], $id);
        $w['_next'] = Batons\Works::next($uid, $w, $w['_last']);
        $st = $statuses[$w['id']] ?? null;
        $finished = $tracked && $st && $st['state'] === 'done';
        if ($tracked && $w['stopped_at'] && !$finished) {
            $groupsW['stopped'][] = $w;   // arrêté en cours de route
        } elseif ($finished && ($w['stopped_at'] || !($c['kind'] === 'livre' && isset($w['_next']['volume'])))) {
            $groupsW['done'][] = $w;      // fini ; un livre en tomes dont le suivant reste à commencer reste « En cours »,
                                          // sauf « Ne pas lire la suite » (stopped_at après un tome fini)
        } else {
            $groupsW['open'][] = $w;
        }
    }
    $isBook = $c['kind'] === 'livre';
    $doneDays = array_map(static fn($w) => (int) $statuses[$w['id']]['days'], $groupsW['done']);
    $doneWord = $isBook ? 'lu' : 'terminé';
    $inDays = static fn(int $d, string $verb): string => $d === 0 ? $verb . ' le jour même' : $verb . ' en ' . Batons\Works::daysLabel($d);
    $dateShort = static function (string $utc): string {
        $d = Time::local($utc);
        return Time::shortDate($d) . ($d->format('Y') !== date('Y') ? ' ' . $d->format('Y') : '');
    };
    $plural = static fn(int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
    // Ligne d'une œuvre. $mode : 'open' (état + actions), 'done' (durée, date, note), 'stopped' (date d'arrêt).
    $workRow = function (array $w, string $mode) use ($c, $id, $uid, $statuses, $inDays, $dateShort, $isBook, $doneWord): void {
        $lastW = $w['_last'];
        $stW = $statuses[$w['id']] ?? null;
        $vW = $isBook && $stW ? Batons\Works::currentVolume((int) $w['id'], $id) : null;
        if ($mode === 'done') {
            $bits = [($vW ? 'Tome ' . $vW . ' ' : '') . $inDays((int) $stW['days'], $doneWord), $dateShort($stW['finish'])];
        } elseif ($mode === 'stopped') {
            $bits = ['arrêté le ' . $dateShort($w['stopped_at']), Batons\Works::progress($lastW['season'] ?? null, $lastW['episode'] ?? null, $lastW['volume'] ?? null)];
        } elseif ($stW) {
            // Livre / jeu : l'essentiel, sans répétition (« Tome 1 · commencé il y a 12 jours »).
            $since = static fn(int $d): string => $d === 0 ? 'commencé aujourd\'hui' : 'commencé il y a ' . Batons\Works::daysLabel($d);
            $bits = $stW['state'] === 'done'
                ? [($vW ? 'Tome ' . $vW . ' ' : '') . 'lu le ' . $dateShort($stW['finish']), trim($inDays((int) $stW['days'], ''))]
                : [$vW ? 'Tome ' . $vW : null, $vW ? $since((int) $stW['days']) : ucfirst($since((int) $stW['days']))];
        } else {
            $bits = [
                Batons\Works::progress($lastW['season'] ?? null, $lastW['episode'] ?? null, $lastW['volume'] ?? null),
                Time::ago($w['last_at']),
                (int) $w['n'] > 1 ? $w['n'] . ' bâtons' : null,
            ];
        } ?>
        <li>
            <span class="poster sm" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" loading="lazy" width="46" height="69"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
            <span class="work-text">
                <a class="work-title" href="<?= url('work', from_here(['id' => $id, 'work' => $w['id']])) ?>"><?= e($w['title']) ?></a>
                <span class="muted small"><?= e(implode(' · ', array_filter($bits))) ?></span>
                <?php if ($w['avg_rating'] !== null): ?><?= rating_badge((int) round((float) $w['avg_rating'])) ?><?php endif; ?>
            </span>
            <?php if ($mode === 'open') { $n = $w['_next']; $back = here(); require __DIR__ . '/_work_actions.php'; } ?>
        </li>
    <?php };
    ?>
    <section class="card" style="<?= cvars($c['color']) ?>" aria-labelledby="works-title">
        <div class="card-head">
            <h2 id="works-title" class="card-title"><?= $tracked ? 'En cours' : e(Batons\Catalog::KINDS[$c['kind']]) ?> <span class="muted small"><?= count($tracked ? $groupsW['open'] : $works) ?></span></h2>
            <a class="btn ghost" href="<?= url('work_add', ['id' => $id]) ?>"><?= ui_icon('search') ?> Ajouter <?= ['film' => 'un film', 'serie' => 'une série', 'livre' => 'un livre', 'jeu' => 'un jeu'][$c['kind']] ?></a>
        </div>
        <?php if (!$works): ?>
            <p class="muted">Rien pour l'instant : « + Ajouter » pour chercher <?= ['film' => 'un film', 'serie' => 'une série', 'livre' => 'un livre', 'jeu' => 'un jeu'][$c['kind']] ?>.</p>
        <?php elseif (!$groupsW['open']): ?>
            <p class="muted"><?= $isBook ? 'Aucun livre en cours.' : 'Aucun jeu en cours.' ?></p>
        <?php else: ?>
            <ul class="work-list">
                <?php foreach (array_slice($groupsW['open'], 0, 30) as $w) { $workRow($w, 'open'); } ?>
            </ul>
        <?php endif; ?>
        <?php if ($groupsW['done']): ?>
            <details class="works-more">
                <summary><?= ui_icon('chevron') ?><span><strong><?= $isBook ? 'Lus' : 'Terminés' ?></strong>
                    <?php $avgD = $doneDays ? array_sum($doneDays) / count($doneDays) : null; ?>
                    <span class="muted"> · <?= e($plural(count($groupsW['done']), $isBook ? 'livre' : 'jeu', $isBook ? 'livres' : 'jeux')) ?><?php if ($avgD !== null): ?>
                        · <?= e($avgD < 1 ? 'moins d\'un jour' : Batons\Works::daysLabel((int) round($avgD))) ?> en moyenne<?php endif; ?></span></span></summary>
                <ul class="work-list">
                    <?php foreach (array_slice($groupsW['done'], 0, 200) as $w) { $workRow($w, 'done'); } ?>
                </ul>
            </details>
        <?php endif; ?>
        <?php if ($groupsW['stopped']): ?>
            <details class="works-more">
                <summary><?= ui_icon('chevron') ?><span><strong>Arrêtés</strong> <span class="muted"> · <?= count($groupsW['stopped']) ?></span></span></summary>
                <ul class="work-list">
                    <?php foreach (array_slice($groupsW['stopped'], 0, 200) as $w) { $workRow($w, 'stopped'); } ?>
                </ul>
            </details>
        <?php endif; ?>
        <?php if ($c['kind'] === 'jeu' && $works): ?><p class="muted small">Données de jeux : <a href="https://rawg.io" target="_blank" rel="noopener">RAWG<span class="sr"> (nouvel onglet)</span></a>.</p><?php endif; ?>
    </section>
<?php else: ?>
<?php if ($c['periods']):
    $periods = Batons\Periods::list($id);
    $openP = ($periods && $periods[0]['finish'] === null) ? $periods[0] : null;
    $pStats = Batons\Periods::stats($periods); ?>
    <section class="card game-status" style="<?= cvars($c['color']) ?>" aria-labelledby="per-title">
        <div class="card-head">
            <h2 id="per-title" class="card-title">Périodes</h2>
            <?= help_box('Les périodes', [
                'Sur l\'accueil, <strong>un appui sur la tuile commence</strong> une période (« en cours ») ; <strong>l\'appui suivant la termine</strong>.',
                'Une seule période à la fois. La durée compte les jours du début à la fin.',
                'Une période passée : ajoute un bâton ci-dessous en choisissant « Le début » puis un autre avec « La fin », chacun à sa date.',
                'Une erreur ? « Annuler » dans le message en haut, ou le crayon de l\'historique pour corriger une date.',
            ]) ?>
        </div>
        <?php if ($openP): ?>
            <p><strong>En cours</strong> depuis le <?= e(Time::longDate(Time::local($openP['start']))) ?>
                (<?= e(Batons\Works::daysLabel((int) $openP['days'])) ?>).</p>
        <?php endif; ?>
        <form method="post" action="<?= url('tap') ?>">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="back" value="<?= e(here()) ?>">
            <button class="btn <?= $openP ? 'accent' : 'ghost' ?>"><?= $openP ? '🏁 Terminer aujourd\'hui' : 'Commencer aujourd\'hui' ?></button>
        </form>
        <?php if ($pStats['count']): ?>
            <dl class="dates">
                <div><dt>Périodes terminées</dt><dd class="big"><?= $pStats['count'] ?></dd></div>
                <div><dt>Durée moyenne</dt><dd class="big"><?= e(Batons\Works::daysLabel($pStats['avg'])) ?></dd></div>
                <div><dt>La plus longue</dt><dd class="big"><?= e(Batons\Works::daysLabel($pStats['max'])) ?></dd></div>
            </dl>
        <?php endif; ?>
        <?php if ($periods): ?>
            <ul class="period-list">
                <?php foreach (array_slice($periods, 0, 20) as $p): ?>
                    <li>
                        <span><?= e(Time::shortDate(Time::local($p['start']))) ?> <?= Time::local($p['start'])->format('Y') ?>
                            → <?= $p['finish'] ? e(Time::shortDate(Time::local($p['finish']))) . ' ' . Time::local($p['finish'])->format('Y') : '<em>en cours</em>' ?></span>
                        <strong><?= e(Batons\Works::daysLabel((int) $p['days'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Aucune période pour l'instant. Un appui sur la tuile, ou « Commencer aujourd'hui », en ouvre une.</p>
        <?php endif; ?>
        <p class="muted small">Autre date ? Ajoute un bâton ci-dessous en choisissant « Le début » ou « La fin », ou modifie un bâton de l'historique.</p>
    </section>
<?php endif; ?>

<form class="card add-form" method="post" action="<?= url('event_add') ?>" style="<?= cvars($c['color']) ?>" aria-label="Ajouter un bâton avec date, valeur ou note">
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
    <?php if ($c['periods']): ?>
        <fieldset>
            <legend>Ce bâton marque</legend>
            <div class="kind-choice">
                <?php foreach (['' => 'Un bâton simple', 'start' => 'Le début', 'finish' => 'La fin'] as $k => $label): ?>
                    <label class="chip wide"><input type="radio" name="milestone" value="<?= $k ?>" <?= $k === '' ? 'checked' : '' ?>><span><?= $label ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>
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
    <?php if (!empty($stats['next_at'])): ?>
        <div class="stat wide">
            <span class="k">Prochain bâton <span class="muted">(pas encore compté)</span></span>
            <span class="v"><?= e(Time::ago($stats['next_at'])) ?></span>
            <span class="s"><?= e(Time::longDate(Time::local($stats['next_at']))) ?> à <?= Time::local($stats['next_at'])->format('H:i') ?></span>
        </div>
    <?php endif; ?>
    <div class="stat"><span class="k">Aujourd'hui</span><span class="v"><?= e($fmt($stats['periods']['today'])) ?></span></div>
    <div class="stat"><span class="k">Cette semaine</span><span class="v"><?= e($fmt($stats['periods']['week'])) ?></span></div>
    <div class="stat"><span class="k">Ce mois</span><span class="v"><?= e($fmt($stats['periods']['month'])) ?></span></div>
    <div class="stat"><span class="k">Cette année</span><span class="v"><?= e($fmt($stats['periods']['year'])) ?></span></div>
    <div class="stat"><span class="k">Au total</span><span class="v"><?= e($fmt(['n' => $stats['total'], 'v' => $stats['total_value']])) ?></span></div>
    <div class="stat"><span class="k">Intervalle moyen</span><span class="v"><?= $stats['avg_interval'] !== null ? e(Time::duration($stats['avg_interval'])) : '—' ?></span></div>
</section>

<?php
[$calStart, $calCounts] = Batons\Events::daily($id, 52, (bool) $c['count_future']);
if ($c['periods']) {
    // Jours couverts par une période : au moins le premier niveau de couleur.
    foreach (Batons\Periods::coveredDays(Batons\Periods::list($id)) as $d => $_) {
        $calCounts[$d] = max(1, $calCounts[$d] ?? 0);
    }
}
$calDays = count($calCounts);
$byMonth = [];
foreach ($calCounts as $d => $n) {
    $k = substr($d, 0, 7);
    $byMonth[$k] = ['days' => ($byMonth[$k]['days'] ?? 0) + 1, 'n' => ($byMonth[$k]['n'] ?? 0) + $n];
}
krsort($byMonth);
?>
<section class="card" aria-labelledby="cal-title" style="<?= cvars($c['color']) ?>">
    <h2 id="cal-title" class="card-title">Calendrier <span class="muted small"><?= $calDays ?> jour<?= $calDays > 1 ? 's' : '' ?> <?= $c['periods'] ? 'avec bâton ou en période' : 'avec bâton' ?> sur 12 mois</span></h2>
    <div class="cal-wrap">
        <?= Chart::calendarDays() ?>
        <div class="cal-scroll" data-scroll-end tabindex="0" role="group" aria-label="Calendrier des 12 derniers mois (défilement horizontal)">
            <?= Chart::calendar($calStart, $calCounts) ?>
        </div>
    </div>
    <div class="cal-legend" aria-hidden="true">
        <i class="cal-0"></i><span>aucun</span>
        <?php foreach (Chart::calendarScale($calCounts)['legend'] as $lvl => $lbl): ?><i class="cal-<?= (int) $lvl ?>"></i><span><?= e($lbl) ?></span><?php endforeach; ?>
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

<section class="card chart-card" id="graph" aria-labelledby="chart-title" data-chart-card>
    <h2 class="sr" id="chart-title">Graphique</h2>
    <div class="chart-head">
        <nav class="seg" aria-label="Échelle">
            <?php foreach (['day' => '30 jours', 'week' => '12 semaines', 'month' => '12 mois'] as $k => $label): ?>
                <a href="<?= url('category', keep_from(['id' => $id, 'scale' => $k, 'metric' => $metric])) ?>#graph" <?= $scale === $k ? 'aria-current="true"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($hasValues): ?>
            <nav class="seg" aria-label="Mesure">
                <a href="<?= url('category', keep_from(['id' => $id, 'scale' => $scale, 'metric' => 'count'])) ?>#graph" <?= $metric === 'count' ? 'aria-current="true"' : '' ?>>Nombre</a>
                <a href="<?= url('category', keep_from(['id' => $id, 'scale' => $scale, 'metric' => 'sum'])) ?>#graph" <?= $metric === 'sum' ? 'aria-current="true"' : '' ?>>Somme<?= $c['unit'] ? ' (' . e($c['unit']) . ')' : '' ?></a>
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
            <?php if ($page > 0): ?><a class="btn ghost" href="<?= url('category', keep_from(['id' => $id, 'scale' => $scale, 'page' => $page - 1])) ?>">← Plus récents</a><?php endif; ?>
            <?php if ($hasMore): ?><a class="btn ghost" href="<?= url('category', keep_from(['id' => $id, 'scale' => $scale, 'page' => $page + 1])) ?>">Plus anciens →</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
