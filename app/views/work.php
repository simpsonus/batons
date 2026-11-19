<?php
use Batons\Time;
use Batons\Works;

$verb = ['serie' => 'un épisode', 'film' => 'un visionnage', 'livre' => 'une lecture', 'jeu' => 'une partie'][$c['kind']] ?? 'un bâton';
$ratings = array_filter(array_column($events, 'rating'), static fn($r) => $r !== null);
$avg = $ratings ? (int) round(array_sum($ratings) / count($ratings)) : null;
?>
<div class="page-head work-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="poster md" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" width="80" height="120"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
        <div>
            <h1><?= e($w['title']) ?></h1>
            <p class="muted"><?= e(implode(' · ', array_filter([$w['year'], $w['subtitle']]))) ?></p>
            <p class="small">
                <a href="<?= url('category', ['id' => $c['id']]) ?>"><?= e($c['name']) ?></a>
                · <?= count($events) ?> bâton<?= count($events) > 1 ? 's' : '' ?>
                <?= $avg ? ' · ' . rating_badge($avg) : '' ?>
            </p>
        </div>
    </div>
</div>

<?php if (in_array($c['kind'], Works::TRACKED, true)): $st = Works::status((int) $w['id'], (int) $c['id']); $ml = Works::milestoneLabels($c['kind']); ?>
    <section class="card game-status" style="--c: <?= e($c['color']) ?>" aria-labelledby="st-title">
        <h2 id="st-title" class="card-title">Progression</h2>
        <?php if ($st['state'] === 'none'): ?>
            <p class="muted">Pas encore commencé.</p>
        <?php else: ?>
            <dl class="dates">
                <div><dt>Commencé</dt><dd><?= e(Time::longDate(Time::local($st['start']))) ?></dd></div>
                <div><dt><?= $ml['done'] ?></dt><dd><?= $st['finish'] ? e(Time::longDate(Time::local($st['finish']))) : '<span class="muted">en cours</span>' ?></dd></div>
                <div><dt><?= $st['state'] === 'done' ? 'Durée' : 'Depuis' ?></dt><dd class="big"><?= e(Works::daysLabel((int) $st['days'])) ?></dd></div>
            </dl>
        <?php endif; ?>
        <form method="post" action="<?= url('work_mark', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <?php if ($st['state'] === 'playing'): ?>
                <input type="hidden" name="milestone" value="finish">
                <button class="btn accent">🏁 <?= $ml['done'] ?> aujourd'hui</button>
            <?php else: ?>
                <input type="hidden" name="milestone" value="start">
                <button class="btn <?= $st['state'] === 'done' ? 'ghost' : 'accent' ?>"><?= $st['state'] === 'done' ? 'Recommencer aujourd\'hui' : 'Commencé aujourd\'hui' ?></button>
            <?php endif; ?>
        </form>
        <p class="muted small">Autre date ? « + Ajouter <?= $verb ?>… » puis « <?= e($ml['start']) ?> » ou « <?= e($ml['finish']) ?> », ou modifie le bâton dans l'historique.</p>
    </section>
<?php endif; ?>

<div class="actions work-actions" style="--c: <?= e($c['color']) ?>">
    <?php if ($next && empty($next['reading']) && $last && !($c['kind'] === 'jeu' && ($st['state'] ?? '') === 'done')): ?>
        <form method="post" action="<?= url('work_next', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <input type="hidden" name="back" value="<?= e(here()) ?>">
            <button class="btn accent next-btn" aria-label="Ajouter <?= e((Works::progressLong($next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null) ?: 'une partie')) ?> maintenant">
                <span aria-hidden="true">▶</span> <?= e((Works::progress($next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null) ?: 'Partie')) ?>
            </button>
        </form>
    <?php endif; ?>
    <a class="btn <?= $next && $last ? 'ghost' : 'accent' ?>" href="<?= url('work_log', ['id' => $c['id'], 'work' => $w['id']]) ?>">+ Ajouter <?= $verb ?>…</a>
</div>

<section>
    <h2>Historique</h2>
    <?php if (!$events): ?>
        <p class="muted">Aucun bâton pour cette œuvre.</p>
    <?php else: ?>
        <?php $events = array_map(static fn($e) => $e + ['color' => $c['color']], $events); $hideWork = true; require __DIR__ . '/_event_list.php'; ?>
    <?php endif; ?>
</section>
<?php if ($w['source'] === 'rawg'): ?><p class="muted small">Données de jeux : <a href="https://rawg.io" target="_blank" rel="noopener">RAWG<span class="sr"> (nouvel onglet)</span></a>.</p><?php endif; ?>
