<?php
// Liste de bâtons groupés par jour local.
// Variables : $events, $showCategory (bool, facultatif), $c (catégorie, facultatif), $dayLevel (2 ou 3, facultatif).
use Batons\Chart;
use Batons\Time;

$showCategory ??= false;
$dayLevel ??= 3;
$day = null;
?>
<?php foreach ($events as $ev): ?>
    <?php
    $local = Time::local($ev['occurred_at']);
    $d = $local->format('Y-m-d');
    $catName = $ev['category_name'] ?? $c['name'];
    $label = $catName . ', ' . Time::longDate($local) . ' à ' . $local->format('H:i');
    ?>
    <?php if ($d !== $day): ?>
        <?php if ($day !== null): ?></ul><?php endif; $day = $d; ?>
        <h<?= $dayLevel ?> class="day"><?= e(Time::longDate($local)) ?></h<?= $dayLevel ?>>
        <ul class="events">
    <?php endif; ?>
    <li style="--c: <?= e($ev['color'] ?? $c['color']) ?>">
        <span class="time"><time datetime="<?= e($local->format(DATE_ATOM)) ?>"><?= $local->format('H:i') ?></time></span>
        <span class="body">
            <?php if ($showCategory): ?>
                <a class="cat" href="<?= url('category', ['id' => $ev['category_id']]) ?>"><span class="icon sm" aria-hidden="true"><?= icon($ev['icon']) ?></span><?= e($ev['category_name']) ?></a>
            <?php endif; ?>
            <?php if ($ev['value'] !== null): ?>
                <span class="value"><?= e(Chart::num($ev['value'])) ?><?= ($u = $ev['unit'] ?? $c['unit'] ?? null) ? ' ' . e($u) : '' ?></span>
            <?php endif; ?>
            <?php if ($ev['note'] !== null): ?><span class="note"><?= e($ev['note']) ?></span><?php endif; ?>
        </span>
        <span class="row-actions">
            <a class="icon-btn" href="<?= url('event_edit', ['id' => $ev['id']]) ?>" aria-label="Modifier le bâton : <?= e($label) ?>" title="Modifier"><?= ui_icon('pencil') ?></a>
            <form method="post" action="<?= url('event_delete') ?>" data-confirm="Supprimer ce bâton (<?= e($label) ?>) ?">
                <?= csrf() ?>
                <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="icon-btn danger" aria-label="Supprimer le bâton : <?= e($label) ?>" title="Supprimer"><?= ui_icon('trash') ?></button>
            </form>
        </span>
    </li>
<?php endforeach; ?>
<?php if ($day !== null): ?></ul><?php endif; ?>
