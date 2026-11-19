<?php
// Résultats de recherche dans un catalogue. Variables : $c (catégorie), $q, $results.
?>
<?php if ($q !== '' && !$results): ?>
    <p class="muted">Aucun résultat pour « <?= e($q) ?> ». Tu peux l'ajouter à la main ci-dessous.</p>
<?php endif; ?>
<?php if ($results): ?>
    <ul class="work-results">
        <?php foreach ($results as $r): ?>
            <li>
                <form method="post" action="<?= url('work_pick', ['id' => $c['id']]) ?>">
                    <?= csrf() ?>
                    <input type="hidden" name="source" value="<?= e($r['source']) ?>">
                    <input type="hidden" name="sid" value="<?= e($r['source_id']) ?>">
                    <input type="hidden" name="title" value="<?= e($r['title']) ?>">
                    <input type="hidden" name="subtitle" value="<?= e($r['subtitle']) ?>">
                    <input type="hidden" name="year" value="<?= e($r['year']) ?>">
                    <input type="hidden" name="poster" value="<?= e($r['poster']) ?>">
                    <button class="work-pick">
                        <span class="poster sm" aria-hidden="true"><?php if ($r['poster']): ?><img src="<?= e(url('thumb', ['u' => $r['poster']])) ?>" alt="" loading="lazy" width="46" height="69"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
                        <span class="work-text">
                            <strong><?= e($r['title']) ?></strong>
                            <span class="muted small"><?= e(implode(' · ', array_filter([$r['year'], $r['subtitle']]))) ?></span>
                        </span>
                        <span class="sr">, choisir</span>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
