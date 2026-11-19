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

<div class="actions work-actions" style="--c: <?= e($c['color']) ?>">
    <?php if ($next && $last): ?>
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
