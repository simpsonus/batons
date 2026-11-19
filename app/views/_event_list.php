<?php
// Liste de bâtons groupés par jour local. Variables : $events, $showCategory (bool, optionnel), $c (catégorie, optionnel).
use Batons\Chart;
use Batons\Time;

$showCategory ??= false;
$day = null;
?>
<?php foreach ($events as $ev): ?>
    <?php $local = Time::local($ev['occurred_at']); $d = $local->format('Y-m-d'); ?>
    <?php if ($d !== $day): ?>
        <?php if ($day !== null): ?></ul><?php endif; $day = $d; ?>
        <h3 class="day"><?= e(Time::longDate($local)) ?></h3>
        <ul class="events">
    <?php endif; ?>
    <li style="--c: <?= e($ev['color'] ?? $c['color']) ?>">
        <span class="time"><?= $local->format('H:i') ?></span>
        <span class="body">
            <?php if ($showCategory): ?>
                <a class="cat" href="<?= url('category', ['id' => $ev['category_id']]) ?>"><span class="icon sm"><?= icon($ev['icon']) ?></span><?= e($ev['category_name']) ?></a>
            <?php endif; ?>
            <?php if ($ev['value'] !== null): ?>
                <span class="value"><?= e(Chart::num($ev['value'])) ?><?= ($u = $ev['unit'] ?? $c['unit'] ?? null) ? ' ' . e($u) : '' ?></span>
            <?php endif; ?>
            <?php if ($ev['note'] !== null): ?><span class="note"><?= e($ev['note']) ?></span><?php endif; ?>
            <?php if (!$showCategory && $ev['value'] === null && $ev['note'] === null): ?><span class="muted">|</span><?php endif; ?>
        </span>
        <span class="row-actions">
            <a href="<?= url('event_edit', ['id' => $ev['id']]) ?>" title="Modifier">Modifier</a>
            <form method="post" action="<?= url('event_delete') ?>" data-confirm="Supprimer ce bâton ?">
                <?= csrf() ?>
                <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="link danger" title="Supprimer">Suppr.</button>
            </form>
        </span>
    </li>
<?php endforeach; ?>
<?php if ($day !== null): ?></ul><?php endif; ?>
