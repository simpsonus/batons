<?php
use Batons\Time;
use Batons\Works;

$defSeason = $next['season'] ?? ($last['season'] ?? 1);
$defEpisode = $next['episode'] ?? ($last['episode'] ?? 1);
$defVolume = $next['volume'] ?? null;
$maxSeason = $seasons ? max(array_keys($seasons)) : null;
?>
<div class="page-head work-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="poster md" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" width="80" height="120"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
        <div>
            <h1><?= e($w['title']) ?></h1>
            <p class="muted"><?= e(implode(' · ', array_filter([$w['year'], $w['subtitle']]))) ?></p>
            <?php if ($last): ?>
                <p class="small">Dernier bâton : <?= e(trim(Works::progress($last['season'], $last['episode'], $last['volume']) . ' · ' . Time::ago($last['occurred_at']), ' ·')) ?>
                    · <a href="<?= url('event_edit', ['id' => $last['id']]) ?>">le modifier</a></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="head-tools"><?= close_link($last ? url('work', ['id' => $c['id'], 'work' => $w['id']]) : url('work_add', ['id' => $c['id']]), 'Fermer sans enregistrer') ?></div>
</div>

<h2 class="card-title">Nouveau bâton</h2>
<form class="card" method="post" action="<?= url('work_log', ['id' => $c['id'], 'work' => $w['id']]) ?>" style="--c: <?= e($c['color']) ?>" aria-label="Nouveau bâton pour <?= e($w['title']) ?>">
    <?= csrf() ?>
    <?php if ($c['kind'] === 'serie'): ?>
        <div class="row">
            <label class="narrow">Saison
                <input type="number" name="season" min="1" max="999" inputmode="numeric" value="<?= (int) $defSeason ?>" <?= $maxSeason ? 'list="seasons"' : '' ?>>
            </label>
            <label class="narrow">Épisode
                <input type="number" name="episode" min="1" max="9999" inputmode="numeric" value="<?= (int) $defEpisode ?>">
            </label>
        </div>
        <?php if ($seasons): ?>
            <datalist id="seasons"><?php foreach ($seasons as $n => $cnt): ?><option value="<?= $n ?>"><?php endforeach; ?></datalist>
            <p class="muted small field-help"><?= count($seasons) ?> saison<?= count($seasons) > 1 ? 's' : '' ?> :
                <?= e(implode(', ', array_map(fn($n, $cnt) => "S$n ($cnt ép.)", array_keys($seasons), $seasons))) ?></p>
        <?php endif; ?>
        <?php if (!$next && $last): ?><p class="muted small field-help">Tu es à jour des épisodes connus.</p><?php endif; ?>
    <?php elseif ($c['kind'] === 'livre'): ?>
        <label class="narrow">Tome <span class="muted">facultatif</span>
            <input type="number" name="volume" min="1" max="9999" inputmode="numeric" value="<?= $defVolume ? (int) $defVolume : '' ?>">
        </label>
    <?php endif; ?>

    <?php if (in_array($c['kind'], Works::TRACKED, true)): $ml = Works::milestoneLabels($c['kind']); ?>
        <fieldset>
            <legend>Ce bâton marque</legend>
            <div class="kind-choice">
                <?php foreach (['', 'start', 'finish'] as $k): ?>
                    <label class="chip wide"><input type="radio" name="milestone" value="<?= $k ?>" <?= ($last ? '' : 'start') === $k ? 'checked' : '' ?>><span><?= $ml[$k] ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>
    <label>Quand
        <input type="datetime-local" name="occurred_at" value="<?= e(Time::toLocalInput()) ?>">
    </label>
    <?= rating_input(null) ?>
    <label>Note écrite <span class="muted">facultatif</span>
        <input type="text" name="note" maxlength="500" autocomplete="off">
    </label>
    <?php if ($w['source'] === 'rawg'): ?><p class="muted small">Données de jeux : <a href="https://rawg.io" target="_blank" rel="noopener">RAWG<span class="sr"> (nouvel onglet)</span></a>.</p><?php endif; ?>
    <div class="form-actions">
        <button class="btn accent">+ Ajouter le bâton</button>
        <a class="btn ghost" href="<?= $last ? url('work', ['id' => $c['id'], 'work' => $w['id']]) : url('work_add', ['id' => $c['id']]) ?>">Annuler</a>
    </div>
</form>
