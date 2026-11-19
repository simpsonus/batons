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
    <div class="head-tools">
        <?= help_box('Comment ça marche', array_filter([
            $c['kind'] === 'serie' ? '<strong>▶ S02E05</strong> : ajoute l\'épisode suivant, vu maintenant.' : null,
            $c['kind'] === 'livre' ? '<strong>▶ Tome 2</strong> (« tome 1 lu ») : marque le tome en cours comme lu aujourd\'hui et commence le suivant.' : null,
            $c['kind'] === 'livre' ? '<strong>Tome 1 lu ✓</strong> : marque seulement le tome en cours comme lu ; la suite reste proposée.' : null,
            $c['kind'] === 'jeu' ? '<strong>▶ Partie</strong> : ajoute une partie jouée maintenant. <strong>Terminé ✓</strong> : le jeu est fini aujourd\'hui.' : null,
            '<strong>+ Ajouter ' . e($verb) . '…</strong> : choisir la date, ' . ($c['kind'] === 'serie' ? 'l\'épisode, ' : ($c['kind'] === 'livre' ? 'le tome, ' : '')) . (in_array($c['kind'], Works::TRACKED, true) ? 'le début ou la fin, ' : '') . 'une note sur 10.',
            $c['kind'] !== 'film' ? '<strong>J\'arrête là</strong> : ne plus proposer ce titre dans « Continuer » (tu peux reprendre quand tu veux).' : null,
            'Le crayon de l\'historique corrige un bâton (date, épisode, note…), la corbeille le supprime.',
        ])) ?>
        <?= close_link(url('work_add', ['id' => $c['id']]), 'Fermer la fiche (retour à ' . $c['name'] . ')') ?>
    </div>
</div>

<?php if ($w['stopped_at']): ?>
    <section class="card notice" style="--c: <?= e($c['color']) ?>">
        <p>Tu as arrêté ce titre le <?= e(Time::longDate(Time::local($w['stopped_at']))) ?> : il n'est plus proposé dans « Continuer ».</p>
        <form method="post" action="<?= url('work_resume', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <button class="btn ghost" title="Proposer de nouveau ce titre dans « Continuer »">Reprendre</button>
        </form>
    </section>
<?php endif; ?>

<?php if (in_array($c['kind'], Works::TRACKED, true)): $st = Works::status((int) $w['id'], (int) $c['id']); $ml = Works::milestoneLabels($c['kind']); ?>
    <section class="card game-status" style="--c: <?= e($c['color']) ?>" aria-labelledby="st-title">
        <?php $curVol = $c['kind'] === 'livre' ? Works::currentVolume((int) $w['id'], (int) $c['id']) : null; ?>
        <h2 id="st-title" class="card-title">Progression<?= $curVol ? ' · tome ' . $curVol : '' ?></h2>
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
                <?php $finTxt = $curVol ? 'Tome ' . $curVol . ' lu' : $ml['done']; ?>
                <button class="btn accent" title="<?= e($curVol ? 'Marque le tome ' . $curVol . ' comme lu aujourd\'hui ; le tome ' . ($curVol + 1) . ' restera proposé' : 'Marque ' . ($c['kind'] === 'livre' ? 'le livre comme lu' : 'le jeu comme terminé') . ' aujourd\'hui') ?>"><?= e($finTxt) ?> ✓ aujourd'hui</button>
            <?php else: ?>
                <input type="hidden" name="milestone" value="start">
                <button class="btn <?= $st['state'] === 'done' ? 'ghost' : 'accent' ?>" title="<?= $st['state'] === 'done' ? 'Recommence ce titre depuis le début, aujourd\'hui' : 'Marque le début aujourd\'hui' ?>"><?= $st['state'] === 'done' ? 'Recommencer aujourd\'hui' : 'Commencé aujourd\'hui' ?></button>
            <?php endif; ?>
        </form>
        <p class="muted small">Autre date ? « + Ajouter <?= $verb ?>… » puis « <?= e($ml['start']) ?> » ou « <?= e($ml['finish']) ?> », ou modifie le bâton dans l'historique.</p>
    </section>
<?php endif; ?>

<?php if ($c['kind'] === 'livre' && ($vols = Works::volumes((int) $w['id'], (int) $c['id']))): ?>
    <section class="card" aria-labelledby="vol-title">
        <h2 id="vol-title" class="card-title">Tome par tome</h2>
        <ul class="period-list">
            <?php foreach ($vols as $v): ?>
                <li>
                    <span>Tome <?= $v['volume'] ?> · <?= e(Time::shortDate(Time::local($v['start']))) ?>
                        → <?= $v['finish'] ? e(Time::shortDate(Time::local($v['finish']))) : '<em>en cours</em>' ?></span>
                    <strong><?= e(Works::daysLabel((int) $v['days'])) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="actions work-actions" style="--c: <?= e($c['color']) ?>">
    <?php if ($last): $n = $next; $back = here(); $hideFinish = true; require __DIR__ . '/_work_actions.php'; endif; ?>
    <a class="btn <?= $next && $last ? 'ghost' : 'accent' ?>" href="<?= url('work_log', ['id' => $c['id'], 'work' => $w['id']]) ?>"
       title="Ajouter <?= e($verb) ?> en choisissant la date<?= $c['kind'] === 'serie' ? ', l\'épisode' : ($c['kind'] === 'livre' ? ', le tome' : '') ?> ou une note">+ Ajouter <?= $verb ?>…</a>
    <?php if ($last && $c['kind'] !== 'film' && !$w['stopped_at']): ?>
        <form method="post" action="<?= url('work_stop', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <button class="btn ghost" title="Ne plus proposer ce titre dans « Continuer » (ex. : tu ne liras pas la suite). Tu pourras reprendre.">J'arrête là</button>
        </form>
    <?php endif; ?>
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
