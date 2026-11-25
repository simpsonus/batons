<?php
$isEdit = !empty($c['id']);
$closeTo = from_url($isEdit ? url('category', ['id' => $c['id']]) : url());
$palette = Batons\Theme::palette();
$hues = Batons\Theme::hues();
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h1>
    <div class="head-tools"><?= close_link($closeTo, 'Fermer sans enregistrer') ?></div>
</div>

<form class="card" method="post" action="<?= url('category_save') ?>">
    <?= csrf() ?>
    <input type="hidden" name="back" value="<?= e($closeTo) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><?php endif; ?>

    <section class="form-sec">
        <div class="row">
            <label class="grow">Intitulé
                <input type="search" data-plain enterkeyhint="done" name="label" value="<?= e($c['name']) ?>" maxlength="80" required autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="false" data-1p-ignore data-lpignore="true" <?= $isEdit ? '' : 'autofocus' ?> <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="err-label"' : '' ?> placeholder="ex. Café, Arrosage du ficus, Migraine">
                <?php if (isset($errors['name'])): ?><span class="error" id="err-label"><?= e($errors['name']) ?></span><?php endif; ?>
            </label>
        </div>

        <fieldset>
            <legend>Type</legend>
            <div class="kind-choice">
                <?php foreach (['simple' => 'Simple (un appui = un bâton)', 'serie' => 'Séries', 'film' => 'Films', 'livre' => 'Livres', 'jeu' => 'Jeux vidéo'] as $k => $label): ?>
                    <label class="chip wide"><input type="radio" name="kind" value="<?= $k ?>" <?= ($c['kind'] ?? 'simple') === $k ? 'checked' : '' ?>><span><?= $label ?></span></label>
                <?php endforeach; ?>
            </div>
            <p class="muted small field-help">Séries, films, livres et jeux : chaque bâton dit <em>quoi</em> (titre, épisode, tome, note), avec recherche dans un catalogue.</p>
        </fieldset>
    </section>

    <section class="form-sec" aria-labelledby="fs-look">
        <h2 id="fs-look">Apparence</h2>
        <div class="icon-field" style="<?= cvars($c['color']) ?>">
            <span class="icon big" aria-hidden="true" data-icon-preview><?= icon($c['icon'] ?: '|') ?></span>
            <label class="grow">Icône <span class="muted">un émoji, ou une icône Material Design notée <code>mdi:nom</code></span>
                <input type="text" name="icon" value="<?= e($c['icon']) ?>" maxlength="64" placeholder="☕ ou mdi:coffee" autocomplete="off" data-icon-input <?= isset($errors['icon']) ? 'aria-invalid="true" aria-describedby="err-icon"' : '' ?>>
                <?php if (isset($errors['icon'])): ?><span class="error" id="err-icon"><?= e($errors['icon']) ?></span><?php endif; ?>
            </label>
        </div>
        <?php if (Batons\Icons::available()): ?>
            <div class="icon-picker" data-icon-picker hidden>
                <label class="sr" for="icon-search">Chercher une icône</label>
                <input type="search" id="icon-search" placeholder="Chercher une icône : café, course, pilule, plante…" data-icon-search autocomplete="off">
                <div class="icon-results" data-icon-results role="group" aria-label="Icônes proposées"></div>
                <p class="sr" aria-live="polite" data-icon-status></p>
                <p class="muted small">Icônes <a href="https://pictogrammers.com/library/mdi/" target="_blank" rel="noopener">Material Design Icons<span class="sr"> (nouvel onglet)</span></a>.</p>
            </div>
        <?php endif; ?>

        <?php
        // Couleur en deux étapes (JS) : une teinte parmi 16, puis sa nuance parmi 4. Sans JS : les 16 rangées de 4 nuances.
        $curColor = strtolower($c['color']);
        $curHue = $palette[$curColor][1] ?? null;
        ?>
        <fieldset class="color-pick" data-color-pick>
            <legend>Couleur <span class="muted" data-hue-name><?= $curHue ? '· ' . e($curHue) : '' ?></span></legend>
            <div class="hue-row" role="group" aria-label="Teinte" data-hue-row>
                <?php foreach ($hues as $hue => $tones): ?>
                    <button type="button" class="hue" style="<?= cvars($tones[2]) ?>" data-hue="<?= e($hue) ?>"
                            aria-pressed="<?= $hue === $curHue ? 'true' : 'false' ?>" aria-label="<?= e($hue) ?>" title="<?= e($hue) ?>"><span></span></button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($hues as $hue => $tones): ?>
                <div class="tone-row" role="radiogroup" aria-label="Nuance de <?= e(mb_strtolower($hue)) ?>" data-tones="<?= e($hue) ?>">
                    <span class="tone-hue" aria-hidden="true"><?= e($hue) ?></span>
                    <?php foreach ($tones as $i => $hex): ?>
                        <label class="tone" style="<?= cvars($hex) ?>">
                            <input type="radio" name="color" value="<?= $hex ?>" <?= $curColor === $hex ? 'checked' : '' ?>>
                            <span><?= e(Batons\Theme::TONES[$i]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <label class="hue custom" title="Autre couleur" data-custom-label>
                <input type="color" name="color_custom" value="<?= e($c['color']) ?>" data-color-custom aria-label="Autre couleur (choix libre)">
                <span></span><span class="custom-txt">Autre couleur</span>
            </label>
            <noscript><style>
                /* Sans JS : pas de choix de teinte, les 16 rangées de nuances sont affichées. */
                .hue-row { display: none; } .color-pick .tone-row { display: grid !important; grid-template-columns: auto repeat(4, minmax(0, 1fr)); }
                .color-pick .tone-hue { display: block; } .color-pick .tone span { font-size: .8125rem; font-weight: 500; }
            </style></noscript>
            <p class="muted small" data-custom-note <?= $curHue ? 'hidden' : '' ?>>Couleur personnalisée : choisis une teinte pour revenir à la palette.</p>
        </fieldset>
    </section>

    <section class="form-sec" aria-labelledby="fs-group">
        <h2 id="fs-group">Rangement</h2>
        <div class="row">
            <?php
            // Groupe : liste des groupes existants + « Nouveau groupe… » (fait apparaître le champ, avec JS ; sans JS il reste affiché).
            $curGroup = (string) $c['group_name'];
            $inList = $curGroup === '' || in_array($curGroup, $groups, true);
            ?>
            <div class="grow group-pick" data-group-pick>
                <label for="f-group-pick">Groupe <span class="muted">facultatif</span></label>
                <select id="f-group-pick" name="group_pick" data-group-select>
                    <option value="" <?= $curGroup === '' ? 'selected' : '' ?>>Aucun groupe</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= e($g) ?>" <?= $g === $curGroup ? 'selected' : '' ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                    <option value="__new" <?= $inList ? '' : 'selected' ?>>Nouveau groupe…</option>
                </select>
                <label class="group-new" data-group-new>
                    <span class="group-new-label">Nouveau groupe</span>
                    <input type="search" data-plain enterkeyhint="done" name="group" value="<?= $inList ? '' : e($curGroup) ?>" maxlength="60" autocomplete="off" autocorrect="off"
                           autocapitalize="sentences" spellcheck="false" data-1p-ignore data-lpignore="true" placeholder="ex. Santé, Maison">
                </label>
            </div>
        </div>
    </section>

    <section class="form-sec" aria-labelledby="fs-count">
        <h2 id="fs-count">Comptage</h2>
        <div class="simple-only" data-simple-only>
        <label class="unit-field">Unité <span class="muted">facultatif</span>
            <input type="text" name="unit" value="<?= e($c['unit']) ?>" maxlength="20" placeholder="km, €, mg…" autocomplete="off" aria-describedby="f-unit-help">
        </label>
        <p class="muted small field-help" id="f-unit-help">Pour noter une valeur avec chaque bâton (distance, montant…).</p>

        <div class="check">
            <input type="checkbox" name="periods" value="1" id="f-periods" <?= !empty($c['periods']) ? 'checked' : '' ?> aria-describedby="f-periods-help">
            <label for="f-periods">Suivre des périodes (début et fin)</label>
        </div>
        <p class="muted small field-help" id="f-periods-help">Un appui sur la tuile commence la période (un rhume…),
            l'appui suivant la termine ; la page de la catégorie montre les durées. Une seule période à la fois.</p>
        </div>

        <div class="check">
            <input type="checkbox" name="count_future" value="1" id="f-future" <?= !empty($c['count_future']) ? 'checked' : '' ?> aria-describedby="f-future-help">
            <label for="f-future">Compter les bâtons à venir dans les statistiques</label>
        </div>
        <p class="muted small field-help" id="f-future-help">Un bâton daté de plus tard (une livraison prévue, un rendez-vous) compte-t-il déjà
            dans « Aujourd'hui », « Ce mois », le total, le graphique et le calendrier ? Décoché, il n'y entre qu'une fois son heure passée ;
            la page de la catégorie l'annonce alors comme « Prochain bâton ».</p>
    </section>

    <section class="form-sec" aria-labelledby="fs-priv">
        <h2 id="fs-priv">Confidentialité</h2>
        <div class="check">
            <input type="checkbox" name="hidden" value="1" id="f-hidden" <?= !empty($c['hidden']) ? 'checked' : '' ?>
                   aria-describedby="f-hidden-help<?= isset($errors['hidden']) ? ' err-hidden' : '' ?>" <?= isset($errors['hidden']) ? 'aria-invalid="true"' : '' ?>>
            <label for="f-hidden"><?= ui_icon('eye-off') ?> Masquer cette catégorie</label>
        </div>
        <p class="muted small field-help" id="f-hidden-help">Invisible partout tant que les éléments masqués ne sont pas déverrouillés (Menu).</p>
        <?php if (isset($errors['hidden'])): ?><p class="error" id="err-hidden"><?= e($errors['hidden']) ?></p><?php endif; ?>
    </section>

    <div class="form-actions">
        <button class="btn accent"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        <a class="btn ghost" href="<?= e($closeTo) ?>">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="danger-zone">
        <form method="post" action="<?= url('category_archive') ?>">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn ghost"><?= $c['archived'] ? 'Restaurer' : 'Archiver' ?></button>
        </form>
        <form method="post" action="<?= url('category_delete') ?>" data-confirm="Supprimer « <?= e($c['name']) ?> » et TOUS ses bâtons ? C'est définitif.">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn danger">Supprimer définitivement</button>
        </form>
    </div>
<?php endif; ?>
