<div class="page-head">
    <h1>Réorganiser</h1>
    <div class="actions"><a class="btn" href="<?= url() ?>">Terminé</a></div>
</div>
<p class="muted">L'ordre s'applique à l'intérieur de chaque groupe.</p>

<ul class="order-list">
    <?php foreach ($categories as $i => $c): ?>
        <li style="--c: <?= e($c['color']) ?>">
            <span class="icon sm" aria-hidden="true"><?= icon($c['icon']) ?></span>
            <span class="grow"><?= e($c['name']) ?><?php if ($c['group_name']): ?> <span class="muted">· <?= e($c['group_name']) ?></span><?php endif; ?></span>
            <?php foreach (['up' => 'Monter', 'down' => 'Descendre'] as $dir => $verb): ?>
                <form method="post" action="<?= url('category_move') ?>">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="dir" value="<?= $dir ?>">
                    <input type="hidden" name="back" value="<?= e(here()) ?>">
                    <button class="icon-btn" aria-label="<?= $verb ?> <?= e($c['name']) ?>" title="<?= $verb ?>"><?= ui_icon($dir) ?></button>
                </form>
            <?php endforeach; ?>
        </li>
    <?php endforeach; ?>
</ul>
