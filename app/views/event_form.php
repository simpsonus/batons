<?php use Batons\Time;
$closeTo = from_url(url('category', ['id' => $ev['category_id']])); ?>
<div class="page-head">
    <h1>Modifier le bâton</h1>
    <div class="head-tools"><?= close_link($closeTo, 'Fermer sans enregistrer') ?></div>
</div>
<p class="muted"><span class="icon sm" aria-hidden="true" style="<?= cvars($ev['color']) ?>"><?= icon($ev['icon']) ?></span> <?= e($ev['category_name']) ?></p>

<form class="card" method="post" action="<?= url('event_save') ?>">
    <?= csrf() ?>
    <input type="hidden" name="back" value="<?= e($closeTo) ?>">
    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
    <div class="row">
        <label>Quand
            <input type="datetime-local" name="occurred_at" value="<?= e(Time::toLocalInput($ev['occurred_at'])) ?>" required>
        </label>
        <label>Valeur<?= $ev['unit'] ? ' (' . e($ev['unit']) . ')' : '' ?>
            <input type="text" name="value" inputmode="decimal" value="<?= e($ev['value'] !== null ? str_replace('.', ',', (string) (float) $ev['value']) : '') ?>" autocomplete="off">
        </label>
    </div>
    <?php if (!$ev['work_id'] && $ev['periods']): ?>
        <fieldset>
            <legend>Ce bâton marque</legend>
            <div class="kind-choice">
                <?php foreach (['' => 'Un bâton simple', 'start' => 'Le début', 'finish' => 'La fin'] as $k => $label): ?>
                    <label class="chip wide"><input type="radio" name="milestone" value="<?= $k ?>" <?= (string) $ev['milestone'] === $k ? 'checked' : '' ?>><span><?= $label ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>
    <?php if ($ev['work_id']): ?>
        <p><strong><?= e($ev['work_title']) ?></strong></p>
        <?php if ($ev['kind'] === 'serie'): ?>
            <div class="row">
                <label class="narrow">Saison <input type="number" name="season" min="1" inputmode="numeric" value="<?= e($ev['season']) ?>"></label>
                <label class="narrow">Épisode <input type="number" name="episode" min="1" inputmode="numeric" value="<?= e($ev['episode']) ?>"></label>
            </div>
        <?php elseif ($ev['kind'] === 'livre'): ?>
            <label class="narrow">Tome <input type="number" name="volume" min="1" inputmode="numeric" value="<?= e($ev['volume']) ?>"></label>
        <?php endif; ?>
        <?php if (in_array($ev['kind'], Batons\Works::TRACKED, true)): $ml = Batons\Works::milestoneLabels($ev['kind']); ?>
            <fieldset>
                <legend>Ce bâton marque</legend>
                <div class="kind-choice">
                    <?php foreach (['', 'start', 'finish'] as $k): ?>
                        <label class="chip wide"><input type="radio" name="milestone" value="<?= $k ?>" <?= (string) $ev['milestone'] === $k ? 'checked' : '' ?>><span><?= $ml[$k] ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <?= rating_input($ev['rating'] !== null ? (int) $ev['rating'] : null) ?>
    <?php endif; ?>
    <label>Note<?= $ev['work_id'] ? ' écrite' : '' ?>
        <input type="text" name="note" maxlength="500" value="<?= e($ev['note']) ?>" autocomplete="off">
    </label>
    <div class="form-actions">
        <button class="btn accent">Mettre à jour</button>
        <a class="btn ghost" href="<?= e($closeTo) ?>">Annuler</a>
    </div>
</form>

<form method="post" action="<?= url('event_delete') ?>" data-confirm="Supprimer ce bâton ?" class="danger-zone">
    <?= csrf() ?>
    <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
    <button class="btn danger">Supprimer ce bâton</button>
</form>
