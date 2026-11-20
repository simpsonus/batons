<?php
// Panneau de filtre de la chronologie (feuille sur téléphone, fenêtre sur grand écran, page sans JS avec ?filter=1) :
// recherche dans les notes, période, groupes et catégories. Variables : $cats, $byGroup, $groupNames, $filter, $counts, $panelId.
$catById = [];
foreach ($cats as $cat) {
    $catById[(int) $cat['id']] = $cat;
}
$many = count($cats) > 15;
?>
<form method="get" action="./" class="tl-panel" data-filter-form>
    <input type="hidden" name="r" value="timeline">
    <input type="hidden" name="f" value="1">
    <div class="tl-panel-head">
        <h2 id="filter-title-<?= $panelId ?>">Filtrer la chronologie</h2>
    </div>
    <label class="tl-label" for="tl-q-<?= $panelId ?>">Rechercher dans les notes</label>
    <div class="tl-q">
        <?= ui_icon('search') ?>
        <input type="search" id="tl-q-<?= $panelId ?>" name="q" value="<?= e($filter['q']) ?>" maxlength="100" enterkeyhint="search"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-describedby="tl-q-help-<?= $panelId ?>" data-filter-q>
    </div>
    <p class="muted small tl-help" id="tl-q-help-<?= $panelId ?>">Dans les notes et les titres d'œuvres, sans tenir compte des accents ni des majuscules.</p>
    <fieldset class="tl-period">
        <legend class="tl-label">Période</legend>
        <div class="kind-choice">
            <?php foreach (['' => 'Tout', '7' => '7 jours', '30' => '30 jours', '365' => '12 mois', 'dates' => 'Dates…'] as $v => $lbl): ?>
                <label class="chip wide"><input type="radio" name="p" value="<?= $v ?>" <?= $filter['p'] === (string) $v ? 'checked' : '' ?>><span><?= $lbl ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="row tl-dates" data-filter-dates>
            <label>Du <input type="date" name="du" value="<?= e($filter['du']) ?>"></label>
            <label>Au <input type="date" name="au" value="<?= e($filter['au']) ?>"></label>
        </div>
    </fieldset>
    <div class="tl-panel-head tl-cats-head">
        <h3 class="tl-label">Groupes et catégories</h3>
        <button type="button" class="btn ghost small-btn" data-check-all hidden>Tout cocher</button>
    </div>
    <?php if ($many): ?>
        <label class="tl-search"><?= ui_icon('search') ?><span class="sr">Chercher une catégorie</span>
            <input type="search" placeholder="Chercher une catégorie" data-filter-search autocomplete="off" autocorrect="off" spellcheck="false"></label>
    <?php endif; ?>
    <div class="tl-groups">
        <?php foreach ($groupNames as $gid => $gname): if (empty($byGroup[$gid])) { continue; }
            $members = $byGroup[$gid];
            $nOn = count(array_intersect($members, $filter['cats']));
            $all = $nOn === count($members);
            $listId = 'tlg-' . $panelId . '-' . $gid; ?>
            <div class="tl-group" data-group data-partial="<?= $nOn && !$all ? '1' : '0' ?>">
                <div class="tl-group-head">
                    <label class="tl-group-label">
                        <input type="checkbox" name="g[]" value="<?= $gid ?>" data-group-box <?= $all ? 'checked' : '' ?>>
                        <b><?= e($gname) ?></b> <span class="muted small" data-group-state><?= $all ? 'tout le groupe' : $nOn . ' sur ' . count($members) ?></span>
                    </label>
                    <button type="button" class="icon-btn tl-toggle" data-group-toggle aria-expanded="true" aria-controls="<?= $listId ?>"
                            aria-label="Afficher les catégories de <?= e($gname) ?>" hidden><?= ui_icon('down') ?></button>
                </div>
                <div class="tl-group-list" id="<?= $listId ?>">
                    <?php foreach ($members as $cid): $cat = $catById[$cid]; ?>
                        <label class="tl-cat" data-name="<?= e(mb_strtolower($cat['name'])) ?>">
                            <input type="checkbox" name="c[]" value="<?= $cid ?>" data-n="<?= (int) ($counts[$cid] ?? 0) ?>" <?= in_array($cid, $filter['cats'], true) ? 'checked' : '' ?>>
                            <span class="icon sm" aria-hidden="true" style="<?= cvars($cat['color']) ?>"><?= icon($cat['icon']) ?></span>
                            <span class="grow"><?= e($cat['name']) ?><?= $cat['archived'] ? ' <span class="muted small">(archivée)</span>' : '' ?></span>
                            <span class="muted small"><?= (int) ($counts[$cid] ?? 0) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-actions tl-panel-actions">
        <button class="btn accent" data-filter-submit>Filtrer</button>
        <?php if ($filter['cats'] || $filter['crit']): ?><a class="btn ghost" href="<?= url('timeline') ?>">Tout afficher</a><?php endif; ?>
        <a class="btn ghost" href="<?= url('timeline', array_filter(['g' => implode(',', $filter['g']), 'c' => implode(',', $filter['c'])]) + $filter['crit']) ?>" data-filter-close>Annuler</a>
    </div>
</form>
