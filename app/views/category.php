<?php
use Batons\Chart;
use Batons\Time;

$id = (int) $c['id'];
$hasValues = (int) $stats['with_value'] > 0;
$fmt = static fn($p) => $metric === 'sum' && $hasValues
    ? Chart::num($p['v'] ?? 0) . ($c['unit'] ? ' ' . $c['unit'] : '')
    : (string) (int) $p['n'];
$hasMore = count($events) > 50;
$events = array_slice($events, 0, 50);
?>
<div class="page-head cat-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="icon big"><?= icon($c['icon']) ?></span>
        <div>
            <h1><?= e($c['name']) ?></h1>
            <?php if ($c['group_name']): ?><p class="muted"><?= e($c['group_name']) ?></p><?php endif; ?>
            <?php if ($c['archived']): ?><p class="badge">Archivée</p><?php endif; ?>
        </div>
    </div>
    <div class="actions">
        <a class="btn ghost" href="<?= url('category_edit', ['id' => $id]) ?>">Modifier</a>
    </div>
</div>

<form class="card add-form" method="post" action="<?= url('event_add') ?>" style="--c: <?= e($c['color']) ?>">
    <?= csrf() ?>
    <input type="hidden" name="category_id" value="<?= $id ?>">
    <div class="row">
        <label>Quand
            <input type="datetime-local" name="occurred_at" value="<?= e(Time::toLocalInput()) ?>">
        </label>
        <label>Valeur<?= $c['unit'] ? ' (' . e($c['unit']) . ')' : '' ?> <span class="muted">facultatif</span>
            <input type="text" name="value" inputmode="decimal" placeholder="ex. 2,5" autocomplete="off">
        </label>
    </div>
    <label>Note <span class="muted">facultatif</span>
        <input type="text" name="note" maxlength="500" autocomplete="off">
    </label>
    <button class="btn accent">+ Ajouter un bâton</button>
</form>

<section class="stats">
    <div class="stat wide">
        <span class="k">Dernier bâton</span>
        <span class="v"><?= e(Time::ago($stats['last_at'])) ?></span>
        <?php if ($stats['last_at']): ?><span class="s"><?= e(Time::longDate(Time::local($stats['last_at']))) ?> à <?= Time::local($stats['last_at'])->format('H:i') ?></span><?php endif; ?>
    </div>
    <div class="stat"><span class="k">Aujourd'hui</span><span class="v"><?= e($fmt($stats['periods']['today'])) ?></span></div>
    <div class="stat"><span class="k">Cette semaine</span><span class="v"><?= e($fmt($stats['periods']['week'])) ?></span></div>
    <div class="stat"><span class="k">Ce mois</span><span class="v"><?= e($fmt($stats['periods']['month'])) ?></span></div>
    <div class="stat"><span class="k">Cette année</span><span class="v"><?= e($fmt($stats['periods']['year'])) ?></span></div>
    <div class="stat"><span class="k">Au total</span><span class="v"><?= e($fmt(['n' => $stats['total'], 'v' => $stats['total_value']])) ?></span></div>
    <div class="stat"><span class="k">Intervalle moyen</span><span class="v"><?= $stats['avg_interval'] !== null ? e(Time::duration($stats['avg_interval'])) : '—' ?></span></div>
</section>

<section class="card">
    <div class="chart-head">
        <nav class="seg" aria-label="Échelle">
            <?php foreach (['day' => '30 jours', 'week' => '12 semaines', 'month' => '12 mois'] as $k => $label): ?>
                <a href="<?= url('category', ['id' => $id, 'scale' => $k, 'metric' => $metric]) ?>" <?= $scale === $k ? 'aria-current="true"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($hasValues): ?>
            <nav class="seg" aria-label="Mesure">
                <a href="<?= url('category', ['id' => $id, 'scale' => $scale, 'metric' => 'count']) ?>" <?= $metric === 'count' ? 'aria-current="true"' : '' ?>>Nombre</a>
                <a href="<?= url('category', ['id' => $id, 'scale' => $scale, 'metric' => 'sum']) ?>" <?= $metric === 'sum' ? 'aria-current="true"' : '' ?>>Somme<?= $c['unit'] ? ' (' . e($c['unit']) . ')' : '' ?></a>
            </nav>
        <?php endif; ?>
    </div>
    <?= Chart::bars($series, $metric, $c['color'], $c['unit']) ?>
</section>

<section>
    <h2>Historique</h2>
    <?php if (!$events): ?>
        <p class="muted">Aucun bâton pour l'instant.</p>
    <?php else: ?>
        <?php require __DIR__ . '/_event_list.php'; ?>
        <div class="pager">
            <?php if ($page > 0): ?><a class="btn ghost" href="<?= url('category', ['id' => $id, 'scale' => $scale, 'page' => $page - 1]) ?>">← Plus récents</a><?php endif; ?>
            <?php if ($hasMore): ?><a class="btn ghost" href="<?= url('category', ['id' => $id, 'scale' => $scale, 'page' => $page + 1]) ?>">Plus anciens →</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
