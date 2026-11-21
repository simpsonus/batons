<?php
// Action d'une œuvre dans les listes (page de la catégorie, « Continuer », bas de la fiche).
// Règle : UNE action par livre, écrite comme ce qui va se passer, icône toujours à gauche du texte.
//   livre en cours        -> « ✓ J'ai fini le tome N » / « ✓ J'ai fini ce livre » (work_mark finish)
//   tome fini, suite      -> « Commencer le tome N+1 » (work_next)
//   jeu en cours          -> « + Partie jouée » (work_next) et « ✓ J'ai fini ce jeu » : deux actions distinctes
//   série                 -> « ▶ S02E05 vu » (work_next)
// Variables : $c (catégorie), $w (œuvre), $n (étape suivante ou null), $back (URL de retour),
// $hideFinish (facultatif : bouton de fin déjà présent ailleurs, ex. carte Progression de la fiche).
use Batons\Works;

$stA = in_array($c['kind'], Works::TRACKED, true) ? Works::status((int) $w['id'], (int) $c['id']) : null;
$volA = $c['kind'] === 'livre' ? Works::currentVolume((int) $w['id'], (int) $c['id']) : null;
$playing = $stA && $stA['state'] === 'playing';
$acts = [];   // [route, champs cachés, icône, libellé, infobulle, principal ?]

if ($c['kind'] === 'livre') {
    if ($playing && empty($hideFinish)) {
        $acts[] = ['work_mark', ['milestone' => 'finish'], 'check', $volA ? 'J\'ai fini le tome ' . $volA : 'J\'ai fini ce livre',
            $volA ? 'Marque le tome ' . $volA . ' comme lu aujourd\'hui' : 'Marque le livre comme lu aujourd\'hui', true];
    } elseif ($stA && $stA['state'] === 'done' && isset($n['volume']) && !$w['stopped_at']) {
        $acts[] = ['work_next', [], 'book', 'Commencer le tome ' . $n['volume'], 'Commence le tome ' . $n['volume'] . ' aujourd\'hui', true];
    }
} elseif ($c['kind'] === 'jeu') {
    if ($stA && $stA['state'] !== 'done' && $n) {
        $acts[] = ['work_next', [], 'plus', 'Partie jouée', 'Ajoute une partie jouée maintenant', !$playing || !empty($hideFinish)];
    }
    if ($playing && empty($hideFinish)) {
        $acts[] = ['work_mark', ['milestone' => 'finish'], 'check', 'J\'ai fini ce jeu', 'Marque le jeu comme terminé aujourd\'hui', true];
    }
} elseif ($c['kind'] === 'serie' && $n && isset($n['episode'])) {
    $ep = Works::progress($n['season'], $n['episode'], null);
    $acts[] = ['work_next', [], 'play', $ep . ' vu', 'Ajoute l\'épisode ' . $n['episode'] . ' de la saison ' . $n['season'] . ', vu maintenant', true];
}
?>
<?php if ($acts): ?>
<span class="work-btns">
    <?php foreach ($acts as [$route, $fields, $ico, $label, $tip, $main]): ?>
        <form method="post" action="<?= url($route, ['id' => $c['id'], 'work' => $w['id']]) ?>" data-soft>
            <?= csrf() ?>
            <?php foreach ($fields as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="btn <?= $main ? 'accent' : 'ghost' ?> work-act" title="<?= e($tip) ?>" aria-label="<?= e($w['title']) ?> : <?= e(lcfirst($label)) ?>">
                <?= ui_icon($ico) ?><span><?= e($label) ?></span>
            </button>
        </form>
    <?php endforeach; ?>
</span>
<?php endif; ?>
