<div class="page-head"><h1>Importer</h1></div>

<?php if ($report !== null): ?>
    <section class="card report">
        <?php $ok = $report['events_imported'] > 0 || $report['categories_created']; ?>
        <h2><?= $ok ? 'Import terminé' : ($report['events_skipped'] ? 'Rien de nouveau' : 'Rien d\'importé') ?></h2>
        <ul>
            <li><b><?= (int) $report['events_imported'] ?></b> bâton<?= $report['events_imported'] > 1 ? 's' : '' ?> importé<?= $report['events_imported'] > 1 ? 's' : '' ?></li>
            <?php if ($report['events_skipped']): ?>
                <li><b><?= (int) $report['events_skipped'] ?></b> déjà présent<?= $report['events_skipped'] > 1 ? 's' : '' ?>, ignoré<?= $report['events_skipped'] > 1 ? 's' : '' ?></li>
            <?php endif; ?>
            <?php if ($report['categories_created']): ?>
                <li><b><?= count($report['categories_created']) ?></b> catégorie<?= count($report['categories_created']) > 1 ? 's' : '' ?> créée<?= count($report['categories_created']) > 1 ? 's' : '' ?> : <?= e(implode(', ', $report['categories_created'])) ?></li>
            <?php endif; ?>
            <?php if ($report['categories_existing']): ?>
                <li>Déjà présente<?= count($report['categories_existing']) > 1 ? 's' : '' ?>, conservée<?= count($report['categories_existing']) > 1 ? 's' : '' ?> telle<?= count($report['categories_existing']) > 1 ? 's' : '' ?> quelle<?= count($report['categories_existing']) > 1 ? 's' : '' ?> : <?= e(implode(', ', $report['categories_existing'])) ?></li>
            <?php endif; ?>
        </ul>
        <?php if ($report['errors']): ?>
            <details <?= $ok ? '' : 'open' ?>>
                <summary><?= count($report['errors']) ?> avertissement<?= count($report['errors']) > 1 ? 's' : '' ?></summary>
                <ul class="errors"><?php foreach (array_slice($report['errors'], 0, 50) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </details>
        <?php endif; ?>
        <a class="btn" href="<?= url() ?>">Voir mes catégories</a>
    </section>
<?php endif; ?>

<form class="card" method="post" action="<?= url('import') ?>" enctype="multipart/form-data">
    <?= csrf() ?>
    <label>Fichiers CSV
        <input type="file" name="files[]" accept=".csv,text/csv" multiple required>
    </label>
    <p class="muted">Tu peux sélectionner plusieurs fichiers d'un coup. Relancer un import ne crée pas de doublons.</p>
    <button class="btn accent">Importer</button>
</form>

<section class="help">
    <h2>Depuis Chronicling</h2>
    <p>Dans Chronicling, exporte tes données en CSV : tu obtiens jusqu'à trois fichiers
        (<code>…_events_…</code>, <code>…_categories_…</code>, <code>…_groups_…</code>). Envoie-les ensemble.
        Seul le fichier <em>events</em> est indispensable ; les deux autres apportent les icônes, couleurs, groupes
        et archives.</p>
    <p>Les icônes Apple (SF Symbols) sont converties vers l'icône Material Design la plus proche quand c'est
        possible ; sinon la catégorie reçoit une icône neutre, à changer dans « Modifier ».</p>

    <h2>Depuis une sauvegarde Bâtons</h2>
    <p>Le fichier produit par « Exporter (CSV) » peut être réimporté tel quel, ici ou sur une autre installation.</p>
</section>
