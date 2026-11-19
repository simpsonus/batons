<?php
// Boutons d'action d'une œuvre (Continuer, liste de la catégorie) : libellés explicites + infobulles.
// Variables : $c (catégorie), $w (œuvre), $n (étape suivante ou null), $back (URL de retour),
// $hideFinish (facultatif : ne pas afficher le bouton de fin, déjà présent ailleurs).
use Batons\Works;

$stA = in_array($c['kind'], Works::TRACKED, true) ? Works::status((int) $w['id'], (int) $c['id']) : null;
$volA = $c['kind'] === 'livre' ? Works::currentVolume((int) $w['id'], (int) $c['id']) : null;
$playing = $stA && $stA['state'] === 'playing';
$showFinish = $playing && empty($hideFinish);
$showNext = $n && empty($n['reading']) && !($c['kind'] === 'jeu' && $stA && $stA['state'] === 'done');

if ($showNext) {
    if ($c['kind'] === 'livre' && isset($n['volume'])) {
        $nextMain = 'Tome ' . $n['volume'];
        $nextSub = $playing && $volA ? 'tome ' . $volA . ' lu' : 'commencer';
        $nextTip = $playing && $volA
            ? 'Marque le tome ' . $volA . ' comme lu aujourd\'hui et commence le tome ' . $n['volume']
            : 'Commence le tome ' . $n['volume'] . ' aujourd\'hui';
    } elseif (isset($n['episode'])) {
        $nextMain = Works::progress($n['season'], $n['episode'], null);
        $nextSub = 'vu';
        $nextTip = 'Ajoute l\'épisode ' . $n['episode'] . ' de la saison ' . $n['season'] . ', vu maintenant';
    } elseif (isset($n['volume'])) {
        $nextMain = 'Tome ' . $n['volume'];
        $nextSub = 'lu';
        $nextTip = 'Ajoute le tome ' . $n['volume'] . ' maintenant';
    } else {
        $nextMain = 'Partie';
        $nextSub = 'jouée';
        $nextTip = 'Ajoute une partie maintenant';
    }
}
if ($showFinish) {
    $finLabel = $c['kind'] === 'livre' ? ($volA ? 'Tome ' . $volA . ' lu' : 'Lu') : 'Terminé';
    $finTip = $c['kind'] === 'livre'
        ? ($volA ? 'Marque le tome ' . $volA . ' comme lu aujourd\'hui ; le tome ' . ($volA + 1) . ' restera proposé (sinon : « J\'arrête là » dans la fiche du livre)'
                 : 'Marque le livre comme lu aujourd\'hui')
        : 'Marque le jeu comme terminé aujourd\'hui';
}
?>
<?php if ($showNext || $showFinish): ?>
<span class="work-btns">
    <?php if ($showNext): ?>
        <form method="post" action="<?= url('work_next', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn accent next-btn" title="<?= e($nextTip) ?>" aria-label="<?= e($w['title']) ?> : <?= e(lcfirst($nextTip)) ?>">
                <span aria-hidden="true" class="btn-main">▶ <?= e($nextMain) ?></span>
                <span aria-hidden="true" class="btn-sub"><?= e($nextSub) ?></span>
            </button>
        </form>
    <?php endif; ?>
    <?php if ($showFinish): ?>
        <form method="post" action="<?= url('work_mark', ['id' => $c['id'], 'work' => $w['id']]) ?>">
            <?= csrf() ?>
            <input type="hidden" name="milestone" value="finish">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn ghost finish-btn" title="<?= e($finTip) ?>" aria-label="<?= e($w['title']) ?> : <?= e(lcfirst($finTip)) ?>">
                <span aria-hidden="true" class="btn-main"><?= e($finLabel) ?> ✓</span>
                <span aria-hidden="true" class="btn-sub">aujourd'hui</span>
            </button>
        </form>
    <?php endif; ?>
</span>
<?php endif; ?>
