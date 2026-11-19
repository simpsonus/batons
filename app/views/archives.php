<?php use Batons\Time; ?>
<div class="page-head"><h1>Archives</h1></div>

<?php if (!$categories): ?>
    <p class="muted">Aucune catégorie archivée. Archiver une catégorie la retire de l'accueil sans perdre ses bâtons.</p>
<?php else: ?>
    <ul class="order-list">
        <?php foreach ($categories as $c): ?>
            <li style="--c: <?= e($c['color']) ?>">
                <span class="icon sm" aria-hidden="true"><?= icon($c['icon']) ?></span>
                <a class="grow" href="<?= url('category', ['id' => $c['id']]) ?>"><?= e($c['name']) ?></a>
                <span class="muted"><?= e(Time::ago($c['last_at'])) ?></span>
                <form method="post" action="<?= url('category_archive') ?>">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button class="btn ghost">Restaurer</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
