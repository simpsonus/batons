<?php
$hasMore = count($events) > 100;
$events = array_slice($events, 0, 100);
$showCategory = true;
$dayLevel = 2;
?>
<h1 class="sr">Chronologie</h1>

<?php if (!$events): ?>
    <p class="muted">Aucun bâton pour l'instant. Touche une tuile sur l'écran <a href="<?= url() ?>">Catégories</a> pour commencer.</p>
<?php else: ?>
    <?php require __DIR__ . '/_event_list.php'; ?>
    <div class="pager">
        <?php if ($page > 0): ?><a class="btn ghost" href="<?= url('timeline', ['page' => $page - 1]) ?>">← Plus récents</a><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn ghost" href="<?= url('timeline', ['page' => $page + 1]) ?>">Plus anciens →</a><?php endif; ?>
    </div>
<?php endif; ?>
