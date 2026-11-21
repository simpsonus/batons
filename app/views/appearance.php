<?php
// Apparence : thème, style des tuiles, dégradé, forme. Aperçu en direct (JS) ; sans JS, on enregistre puis on voit.
use Batons\Theme;

if (!$previewCats) {
    $previewCats = [['name' => 'Café', 'icon' => '☕', 'color' => '#c2410c'], ['name' => 'Arrosage', 'icon' => '🪴', 'color' => '#047857']];
}
$sample = '#c2410c';
$closeTo = from_url(url());

/** Un groupe de vignettes (boutons radio). $vis : classe(s) de la vignette pour une valeur. */
$options = static function (string $field, array $choices, string $current, callable $vis) use ($sample): string {
    $h = '<div class="app-opts">';
    foreach ($choices as $v => $label) {
        $h .= '<label class="app-opt"><input type="radio" name="' . $field . '" value="' . e($v) . '"' . ($v === $current ? ' checked' : '') . '>'
            . '<span class="vis ' . $vis($v) . '" style="' . cvars($sample) . ' ' . e(Theme::tileVars($sample)) . '" aria-hidden="true"><span class="vis-ic"></span></span>'
            . '<span class="app-opt-label">' . e($label) . '</span></label>';
    }
    return $h . '</div>';
};
$section = static fn(string $icon, string $title): string =>
    '<legend class="app-legend"><span class="app-legend-ic">' . ui_icon($icon) . '</span>' . e($title) . '</legend>';
?>
<div class="page-head">
    <h1>Apparence</h1>
    <div class="head-tools"><?= close_link($closeTo, 'Fermer') ?></div>
</div>

<form method="post" action="<?= url('appearance') ?>" class="appearance" data-appearance>
    <?= csrf() ?>
    <input type="hidden" name="back" value="<?= e(here()) ?>">

    <div class="appearance-preview" aria-hidden="true">
        <div class="grid">
            <?php foreach ($previewCats as $pc): ?>
                <div class="tile" style="<?= cvars($pc['color']) ?> <?= e(Theme::tileVars($pc['color'])) ?>">
                    <span class="tap">
                        <span class="icon"><?= icon($pc['icon']) ?></span>
                        <span class="name"><?= e($pc['name']) ?></span>
                        <span class="meta"><b>2</b> aujourd'hui</span>
                    </span>
                    <span class="more"><?= ui_icon('chevron') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <fieldset class="card app-sec">
        <?= $section('theme', 'Thème') ?>
        <?= $options('theme', Theme::THEMES, $theme['theme'], static fn($v) => 'vis-theme-' . $v) ?>
        <p class="muted small">« Automatique » suit le réglage clair ou sombre de l'appareil.</p>
    </fieldset>

    <fieldset class="card app-sec">
        <?= $section('grid', 'Style des tuiles') ?>
        <?= $options('tile_style', Theme::STYLES, $theme['style'], static fn($v) => 'vis-style-' . $v) ?>
    </fieldset>

    <fieldset class="card app-sec" data-grad-field>
        <?= $section('palette', 'Dégradé') ?>
        <?= $options('tile_grad', Theme::GRADS, $theme['grad'], static fn($v) => 'vis-grad-' . $v) ?>
        <p class="muted small">Pour le style « Dégradé ».</p>
    </fieldset>

    <fieldset class="card app-sec">
        <?= $section('shape', 'Forme') ?>
        <?= $options('tile_shape', Theme::SHAPES, $theme['shape'], static fn($v) => 'vis-shape-' . $v) ?>
    </fieldset>

    <fieldset class="card app-sec">
        <?= $section('clock', 'Messages') ?>
        <div class="kind-choice">
            <label class="chip wide"><input type="radio" name="flash_auto" value="1" <?= $flashAuto ? 'checked' : '' ?>><span>Disparaissent seuls</span></label>
            <label class="chip wide"><input type="radio" name="flash_auto" value="0" <?= $flashAuto ? '' : 'checked' ?>><span>Restent affichés</span></label>
        </div>
        <p class="muted small">Le message en haut ou en bas de l'écran (« Bâton ajouté… », « Annuler ») disparaît au bout de quelques
            secondes ; une barre montre le temps restant. Il reste tant que le doigt, la souris ou le clavier est dessus.
            Un bâton peut toujours être supprimé depuis son historique.</p>
    </fieldset>

    <p class="muted small">Le réglage suit ton compte, sur tous tes appareils. Le texte des tuiles reste lisible quelle que soit la couleur d'une catégorie.</p>
    <div class="form-actions">
        <button class="btn accent">Enregistrer</button>
        <a class="btn ghost" href="<?= e($closeTo) ?>">Annuler</a>
    </div>
</form>
