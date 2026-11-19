<?php use Batons\Time; ?>
<div class="page-head">
    <h1>Modifier le bâton</h1>
</div>
<p class="muted"><span class="icon sm" style="--c: <?= e($ev['color']) ?>"><?= icon($ev['icon']) ?></span> <?= e($ev['category_name']) ?></p>

<form class="card" method="post" action="<?= url('event_save') ?>">
    <?= csrf() ?>
    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
    <div class="row">
        <label>Quand
            <input type="datetime-local" name="occurred_at" value="<?= e(Time::toLocalInput($ev['occurred_at'])) ?>" required>
        </label>
        <label>Valeur<?= $ev['unit'] ? ' (' . e($ev['unit']) . ')' : '' ?>
            <input type="text" name="value" inputmode="decimal" value="<?= e($ev['value'] !== null ? str_replace('.', ',', (string) (float) $ev['value']) : '') ?>" autocomplete="off">
        </label>
    </div>
    <label>Note
        <input type="text" name="note" maxlength="500" value="<?= e($ev['note']) ?>" autocomplete="off">
    </label>
    <div class="form-actions">
        <button class="btn accent">Enregistrer</button>
        <a class="btn ghost" href="<?= url('category', ['id' => $ev['category_id']]) ?>">Annuler</a>
    </div>
</form>

<form method="post" action="<?= url('event_delete') ?>" data-confirm="Supprimer ce bâton ?" class="danger-zone">
    <?= csrf() ?>
    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
    <button class="btn danger">Supprimer ce bâton</button>
</form>
