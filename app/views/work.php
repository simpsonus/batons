<?php
use Batons\Time;
use Batons\Works;

$verb = ['serie' => 'un épisode', 'film' => 'un visionnage', 'livre' => 'une lecture', 'jeu' => 'une partie'][$c['kind']] ?? 'un bâton';
$ratings = array_filter(array_column($events, 'rating'), static fn($r) => $r !== null);
$avg = $ratings ? (int) round(array_sum($ratings) / count($ratings)) : null;
?>
<div class="page-head work-head" style="<?= cvars($c['color']) ?>">
    <div class="cat-title">
        <span class="poster md" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" width="80" height="120"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
        <div>
            <h1><?= e($w['title']) ?></h1>
            <p class="muted"><?= e(implode(' · ', array_filter([$w['year'], $w['subtitle']]))) ?></p>
            <p class="small">
                <a href="<?= url('category', from_here(['id' => $c['id']])) ?>"><?= e($c['name']) ?></a>
                · <?= count($events) ?> bâton<?= count($events) > 1 ? 's' : '' ?>
                <?= $avg ? ' · ' . rating_badge($avg) : '' ?>
            </p>
        </div>
    </div>
    <div class="head-tools">
        <?= help_box('Comment ça marche', array_filter([
            $c['kind'] === 'serie' ? '<strong>▶ S02E05</strong> : ajoute l\'épisode suivant, vu maintenant.' : null,
            $c['kind'] === 'livre' ? '<strong>J\'ai fini…</strong> : marque le livre (ou le tome en cours) comme lu aujourd\'hui. Ensuite, <strong>Commencer le tome 2</strong> enchaîne.' : null,
            $c['kind'] === 'jeu' ? '<strong>Partie jouée</strong> : ajoute une partie maintenant. <strong>J\'ai fini ce jeu</strong> : le jeu est terminé aujourd\'hui.' : null,
            '<strong>+ Ajouter ' . e($verb) . '…</strong> : choisir la date, ' . ($c['kind'] === 'serie' ? 'l\'épisode, ' : ($c['kind'] === 'livre' ? 'le tome, ' : '')) . (in_array($c['kind'], Works::TRACKED, true) ? 'le début ou la fin, ' : '') . 'une note sur 10.',
            $c['kind'] !== 'film' ? '<strong>J\'arrête là</strong> (ou <strong>Ne pas lire la suite</strong> après un tome fini) : ne plus proposer ce titre dans « Continuer » (tu peux reprendre quand tu veux).' : null,
            'Le crayon de l\'historique corrige un bâton (date, épisode, note…), la corbeille le supprime.',
        ])) ?>
        <?= close_link(from_url(url('work_add', ['id' => $c['id']])), 'Fermer la fiche') ?>
    </div>
</div>

<?php if ($w['stopped_at']): ?>
    <section class="card notice" style="<?= cvars($c['color']) ?>">
        <p>Tu as arrêté ce titre le <?= e(Time::longDate(Time::local($w['stopped_at']))) ?> : il n'est plus proposé dans « Continuer ».</p>
        <form method="post" action="<?= url('work_resume', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <input type="hidden" name="back" value="<?= e(here()) ?>">
            <button class="btn ghost" title="Proposer de nouveau ce titre dans « Continuer »">Reprendre</button>
        </form>
    </section>
<?php endif; ?>

<?php if (in_array($c['kind'], Works::TRACKED, true)): $st = Works::status((int) $w['id'], (int) $c['id']); $ml = Works::milestoneLabels($c['kind']); ?>
    <section class="card game-status" style="<?= cvars($c['color']) ?>" aria-labelledby="st-title">
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
            <input type="hidden" name="back" value="<?= e(here()) ?>">
            <?php if ($st['state'] === 'playing'): ?>
                <input type="hidden" name="milestone" value="finish">
                <?php $finTxt = $curVol ? 'J\'ai fini le tome ' . $curVol : ($c['kind'] === 'livre' ? 'J\'ai fini ce livre' : 'J\'ai fini ce jeu'); ?>
                <button class="btn accent work-act" title="<?= e($curVol ? 'Marque le tome ' . $curVol . ' comme lu aujourd\'hui' : 'Marque ' . ($c['kind'] === 'livre' ? 'le livre comme lu' : 'le jeu comme terminé') . ' aujourd\'hui') ?>"><?= ui_icon('check') ?><span><?= e($finTxt) ?></span></button>
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

<div class="actions work-actions" style="<?= cvars($c['color']) ?>">
    <?php if ($last): $n = $next; $back = here(); $hideFinish = true; require __DIR__ . '/_work_actions.php'; endif; ?>
    <a class="btn <?= $next && $last ? 'ghost' : 'accent' ?>" href="<?= url('work_log', ['id' => $c['id'], 'work' => $w['id']]) ?>"
       title="Ajouter <?= e($verb) ?> en choisissant la date<?= $c['kind'] === 'serie' ? ', l\'épisode' : ($c['kind'] === 'livre' ? ', le tome' : '') ?> ou une note">+ Ajouter <?= $verb ?>…</a>
    <?php if ($last && $c['kind'] !== 'film' && !$w['stopped_at']):
        // Livre en tomes dont le dernier tome est fini : « Ne pas lire la suite » (rangé dans « Lus »), sinon « J'arrête là ».
        $afterTome = $c['kind'] === 'livre' && isset($next['volume']) && isset($st) && $st['state'] === 'done'; ?>
        <form method="post" action="<?= url('work_stop', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <input type="hidden" name="back" value="<?= e(here()) ?>">
            <button class="btn ghost" title="<?= $afterTome ? 'La suite ne sera plus proposée ; le livre est rangé dans « Lus ». Tu pourras reprendre.' : 'Ne plus proposer ce titre dans « Continuer ». Tu pourras reprendre.' ?>"><?= $afterTome ? 'Ne pas lire la suite' : 'J\'arrête là' ?></button>
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
