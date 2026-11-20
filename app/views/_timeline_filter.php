<?php
// Panneau de réglage fin du filtre de la chronologie (feuille sur téléphone, page sans JS avec ?filter=1).
// Variables : $cats, $byGroup, $groupNames, $filter, $counts, $panelId.
$catById = [];
foreach ($cats as $cat) {
    $catById[(int) $cat['id']] = $cat;
}
$many = count($cats) > 15;
?>
<form method="get" action="./" class="tl-panel" data-filter-form>
    <input type="hidden" name="r" value="timeline">
    <div class="tl-panel-head">
        <h2 id="filter-title-<?= $panelId ?>">Filtrer la chronologie</h2>
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
        <?php if ($filter['cats']): ?><a class="btn ghost" href="<?= url('timeline') ?>">Tout afficher</a><?php endif; ?>
        <a class="btn ghost" href="<?= url('timeline', array_filter(['g' => implode(',', $filter['g']), 'c' => implode(',', $filter['c'])])) ?>" data-filter-close>Annuler</a>
    </div>
</form>
