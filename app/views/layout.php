<?php $r = $_GET['r'] ?? 'home'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1c1917">
    <title><?= e($title === 'Bâtons' ? 'Bâtons' : $title . ' · Bâtons') ?></title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="manifest" href="assets/manifest.webmanifest">
    <link rel="stylesheet" href="assets/app.css?v=<?= Batons\VERSION ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= url() ?>">
        <svg viewBox="0 0 32 32" aria-hidden="true"><g stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M7 7v18M12 7v18M17 7v18M22 7v18"/><path d="M4 21 26 11" class="slash"/></g></svg>
        <span>Bâtons</span>
    </a>
    <nav>
        <a href="<?= url() ?>" <?= $r === 'home' ? 'aria-current="page"' : '' ?>>Catégories</a>
        <a href="<?= url('timeline') ?>" <?= $r === 'timeline' ? 'aria-current="page"' : '' ?>>Chronologie</a>
    </nav>
</header>

<?php if ($flash): ?>
    <div class="flash" role="status">
        <span><?= e($flash['message']) ?></span>
        <?php if ($flash['undo']): ?>
            <form method="post" action="<?= url('undo') ?>">
                <?= csrf() ?>
                <input type="hidden" name="id" value="<?= (int) $flash['undo'] ?>">
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="link">Annuler</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<main>
    <?= $content ?>
</main>

<footer class="foot">
    <a href="<?= url('archives') ?>">Archives</a> ·
    <a href="<?= url('import') ?>">Importer</a> ·
    <a href="<?= url('export') ?>">Exporter (CSV)</a> ·
    <span>Bâtons <?= Batons\VERSION ?></span>
</footer>
<script src="assets/app.js?v=<?= Batons\VERSION ?>" defer></script>
</body>
</html>
