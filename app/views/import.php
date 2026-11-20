<?php
// Import en trois étapes. $step : source | files | check | done ; $source : chronicling | batons | null ; $report.
$closeTo = from_url(url());
$keep = static fn(array $p = []): array => keep_from($p);
$titles = ['source' => 'Importer', 'files' => $source === 'batons' ? 'Sauvegarde Bâtons' : 'Chronicling', 'check' => 'Vérifier', 'done' => 'Import terminé'];
$n = ['source' => 1, 'files' => 2, 'check' => 3, 'done' => 3][$step];
$plural = static fn(int $k, string $one, string $many): string => $k . ' ' . ($k > 1 ? $many : $one);
?>
<div class="page-head">
    <h1><?= e($titles[$step]) ?></h1>
    <div class="head-tools"><?= close_link($closeTo, $step === 'done' ? 'Fermer' : 'Abandonner l\'import') ?></div>
</div>
<?php if ($step !== 'done'): ?>
    <ol class="steps" aria-label="Étapes de l'import">
        <?php foreach (['Provenance', 'Fichiers', 'Vérification'] as $i => $lbl): ?>
            <li class="<?= $i + 1 <= $n ? 'on' : '' ?>"<?= $i + 1 === $n ? ' aria-current="step"' : '' ?>><span class="sr"><?= $i + 1 ?>. <?= $lbl ?><?= $i + 1 < $n ? ' (fait)' : '' ?></span></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($step === 'source'): ?>
    <p class="step-q">D'où viennent tes données ?</p>
    <div class="choice-list">
        <a class="choice-card" href="<?= url('import', $keep(['source' => 'chronicling'])) ?>">
            <span class="choice-ic" aria-hidden="true"><?= ui_icon('import') ?></span>
            <span class="grow"><b>Chronicling</b><span class="muted">L'app iPhone : exporte tes données en CSV depuis ses réglages.</span></span>
            <?= ui_icon('chevron') ?>
        </a>
        <a class="choice-card" href="<?= url('import', $keep(['source' => 'batons'])) ?>">
            <span class="choice-ic" aria-hidden="true"><?= ui_icon('archive') ?></span>
            <span class="grow"><b>Sauvegarde Bâtons</b><span class="muted">Un fichier obtenu par « Exporter », ici ou sur une autre installation.</span></span>
            <?= ui_icon('chevron') ?>
        </a>
    </div>
    <p class="muted small">Rien n'est importé tant que tu n'as pas vérifié le contenu et confirmé. Relancer un import ne crée pas de doublons.</p>

<?php elseif ($step === 'files'): ?>
    <form class="card" method="post" action="<?= url('import_check', $keep(['source' => $source])) ?>" enctype="multipart/form-data">
        <?= csrf() ?>
        <input type="hidden" name="source" value="<?= e($source) ?>">
        <p class="step-q">Choisis <?= $source === 'batons' ? 'le fichier de sauvegarde' : 'les fichiers exportés' ?></p>
        <?php if ($source === 'batons'): ?>
            <ul class="file-list">
                <li><span><?= ui_icon('archive') ?> <code>batons-AAAA-MM-JJ.csv</code></span><span class="muted">tous les bâtons, catégories et groupes</span></li>
            </ul>
        <?php else: ?>
            <ul class="file-list">
                <li><span><?= ui_icon('import') ?> <code>…_events_….csv</code></span><span class="muted">indispensable : les bâtons</span></li>
                <li><span><?= ui_icon('import') ?> <code>…_categories_….csv</code></span><span class="muted">facultatif : icônes, couleurs, archives</span></li>
                <li><span><?= ui_icon('import') ?> <code>…_groups_….csv</code></span><span class="muted">facultatif : groupes</span></li>
            </ul>
            <p class="muted small">Envoie-les ensemble. Les icônes Apple sont converties vers l'icône Material Design la plus proche quand c'est possible.</p>
        <?php endif; ?>
        <label class="drop-zone">
            <?= ui_icon('import') ?>
            <span><b>Choisir <?= $source === 'batons' ? 'le fichier' : 'les fichiers' ?></b><span class="muted small"><?= $source === 'batons' ? 'un fichier CSV' : 'un ou plusieurs fichiers CSV à la fois' ?></span></span>
            <input type="file" name="files[]" accept=".csv,text/csv" <?= $source === 'batons' ? '' : 'multiple' ?> required data-file-input>
        </label>
        <p class="muted small" data-file-names aria-live="polite"></p>
        <div class="form-actions">
            <button class="btn accent">Vérifier le contenu</button>
            <a class="btn ghost" href="<?= url('import', $keep()) ?>">‹ Changer de provenance</a>
        </div>
    </form>

<?php else: /* check ou done */ ?>
    <?php
    $r = $report;
    $ok = $r['events_imported'] > 0 || $r['categories_created'];
    ?>
    <section class="card report">
        <?php if ($r['files']): ?>
            <ul class="file-list">
                <?php foreach ($r['files'] as $f): ?>
                    <li class="ok"><span><?= ui_icon('check') ?> <?= e($f['name']) ?></span><span class="muted"><?= e(Batons\Importer::KINDS[$f['kind']] ?? $f['kind']) ?> · <?= $plural((int) $f['rows'], 'ligne', 'lignes') ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <h2><?= $step === 'check' ? ($ok ? 'Voici ce qui sera importé' : ($r['events_skipped'] ? 'Rien de nouveau à importer' : 'Rien à importer')) : ($ok ? 'C\'est fait' : 'Rien n\'a été importé') ?></h2>
        <dl class="summary">
            <div><dt><?= $step === 'check' ? 'Bâtons à ajouter' : 'Bâtons importés' ?></dt><dd><?= number_format((int) $r['events_imported'], 0, ',', ' ') ?></dd></div>
            <?php if ($r['events_skipped']): ?><div><dt>Déjà présents, ignorés</dt><dd><?= number_format((int) $r['events_skipped'], 0, ',', ' ') ?></dd></div><?php endif; ?>
            <?php if ($r['categories_created']): ?>
                <div><dt><?= $step === 'check' ? 'Nouvelles catégories' : 'Catégories créées' ?></dt><dd><?= count($r['categories_created']) ?></dd></div>
                <p class="summary-names"><?= e(implode(', ', $r['categories_created'])) ?></p>
            <?php endif; ?>
            <?php if ($r['categories_existing']): ?>
                <div><dt>Catégories déjà présentes, gardées telles quelles</dt><dd><?= count($r['categories_existing']) ?></dd></div>
                <p class="summary-names"><?= e(implode(', ', $r['categories_existing'])) ?></p>
            <?php endif; ?>
        </dl>
        <?php if ($r['errors']): ?>
            <details <?= $ok ? '' : 'open' ?>>
                <summary><?= $plural(count($r['errors']), 'avertissement', 'avertissements') ?></summary>
                <ul class="errors"><?php foreach (array_slice($r['errors'], 0, 50) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </details>
        <?php endif; ?>

        <?php if ($step === 'check'): ?>
            <?php if ($ok): ?>
                <form method="post" action="<?= url('import', $keep(['source' => $source])) ?>" class="form-actions">
                    <?= csrf() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="source" value="<?= e((string) $source) ?>">
                    <button class="btn accent">Importer <?= $plural((int) $r['events_imported'], 'bâton', 'bâtons') ?></button>
                    <a class="btn ghost" href="<?= e($closeTo) ?>">Annuler</a>
                </form>
            <?php else: ?>
                <div class="form-actions"><a class="btn" href="<?= url('import', $keep(['source' => $source])) ?>">‹ Choisir d'autres fichiers</a></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="form-actions"><a class="btn accent" href="<?= url() ?>">Voir mes catégories</a></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
