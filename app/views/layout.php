<?php
$r = $_GET['r'] ?? 'home';
$authMethod = Batons\Auth::method();          // null sur les pages de connexion
$unlocked = $authMethod !== null && Batons\Privacy::isUnlocked();
// Écrans rattachés à l'onglet Catégories (pastille allumée).
$themeAttr = $authMethod !== null ? Batons\Theme::htmlAttr(Batons\Theme::current(Batons\Auth::id())) : '';
$catRoutes = ['home', 'category_order', 'category', 'category_new', 'category_edit', 'work_add', 'work', 'work_log', 'group_rename'];
?>
<!DOCTYPE html>
<html lang="fr"<?= $themeAttr ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php if ($themeAttr === ''): ?>
    <meta name="theme-color" content="#f7f4ee" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#161412" media="(prefers-color-scheme: dark)">
    <?php else: ?>
    <meta name="theme-color" content="<?= str_contains($themeAttr, 'dark') ? '#161412' : '#f7f4ee' ?>">
    <?php endif; ?>
    <title><?= e($title === 'Bâtons' ? 'Bâtons' : $title . ' · Bâtons') ?></title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Bâtons">
    <link rel="manifest" href="assets/manifest.webmanifest">
    <?php /* Feuilles par composant, dans l'ordre de leur numéro (public/assets/css/NN-nom.css). */
    foreach (glob(dirname(__DIR__) . '/public/assets/css/[0-9][0-9]-*.css') as $cssFile): ?>
    <link rel="stylesheet" href="assets/css/<?= e(basename($cssFile)) ?>?v=<?= Batons\VERSION ?>">
    <?php endforeach; ?>
</head>
<body<?= $authMethod !== null ? ' class="tabbed ' . Batons\Theme::bodyClasses(Batons\Theme::current(Batons\Auth::id())) . '"' : '' ?><?= $unlocked ? ' data-relock="' . Batons\Privacy::remaining() . '"' : '' ?>>
<a class="skip" href="#contenu">Aller au contenu</a>
<header class="topbar">
    <?php if ($authMethod === null): ?>
    <span class="brand">
        <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false"><g stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M7 7v18M12 7v18M17 7v18M22 7v18"/><path d="M4 21 26 11" class="slash"/></g></svg>
        <span>Bâtons</span>
    </span>
    <?php else: ?>
    <details class="appmenu" data-appmenu>
        <summary class="brand" aria-label="Menu Bâtons">
            <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false"><g stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M7 7v18M12 7v18M17 7v18M22 7v18"/><path d="M4 21 26 11" class="slash"/></g></svg>
            <span aria-hidden="true">Bâtons</span>
            <svg class="ui caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z"/></svg>
        </summary>
        <nav class="appmenu-panel" aria-label="Menu Bâtons">
            <?php require __DIR__ . '/_menu.php'; ?>
        </nav>
    </details>
    <?php endif; ?>
    <?php if ($unlocked): ?>
        <form method="post" action="<?= url('lock') ?>" class="lock-quick">
            <?= csrf() ?>
            <button class="icon-btn" aria-label="Éléments masqués visibles : verrouiller" title="Verrouiller les éléments masqués"><?= ui_icon('lock-open') ?></button>
        </form>
    <?php endif; ?>
    <?php if ($authMethod !== null): ?>
    <nav aria-label="Navigation principale">
        <a href="<?= url() ?>" <?= in_array($r, $catRoutes, true) ? 'aria-current="page"' : '' ?>><?= ui_icon('grid') ?>Catégories</a>
        <a href="<?= url('timeline') ?>" <?= $r === 'timeline' ? 'aria-current="page"' : '' ?>><?= ui_icon('clock') ?>Chronologie</a>
    </nav>
    <?php endif; ?>
</header>

<?php if ($flash): ?>
    <div class="flash" role="status" data-flash>
        <span class="flash-msg"><?= e($flash['message']) ?></span>
        <?php if (!empty($flash['action'])): ?>
            <form method="post" action="<?= url($flash['action']['route'], $flash['action']['params'] ?? []) ?>">
                <?= csrf() ?>
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="flash-btn"><?= e($flash['action']['label']) ?></button>
            </form>
        <?php endif; ?>
        <?php if ($flash['undo']): ?>
            <form method="post" action="<?= url('undo') ?>">
                <?= csrf() ?>
                <input type="hidden" name="ids" value="<?= e($flash['undo']) ?>">
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="flash-btn"><?= e($flash['undo_label'] ?? 'Annuler') ?></button>
            </form>
        <?php endif; ?>
        <button type="button" class="flash-close" data-flash-close aria-label="Fermer le message" hidden><?= ui_icon('close') ?></button>
    </div>
<?php endif; ?>

<main id="contenu" tabindex="-1">
    <?= $content ?>
</main>

<?php if ($authMethod !== null):
    $menuRoutes = ['menu', 'appearance', 'export', 'import_check', 'archives', 'import', 'account', 'privacy', 'unlock']; ?>
<nav class="tabbar" aria-label="Navigation principale">
    <div class="tabbar-row">
        <a href="<?= url() ?>" <?= in_array($r, $catRoutes, true) ? 'aria-current="page"' : '' ?>><span class="tab-ico"><?= ui_icon('grid') ?></span>Catégories</a>
        <a href="<?= url('timeline') ?>" <?= $r === 'timeline' ? 'aria-current="page"' : '' ?>><span class="tab-ico"><?= ui_icon('clock') ?></span>Chronologie</a>
        <a href="<?= url('menu') ?>" data-menu-open aria-haspopup="dialog" <?= in_array($r, $menuRoutes, true) ? 'aria-current="page"' : '' ?>><span class="tab-ico"><?= ui_icon('menu') ?><?php if ($unlocked): ?><span class="tab-dot" aria-hidden="true"></span><?php endif; ?></span>Menu<?php if ($unlocked): ?><span class="sr"> (éléments masqués visibles)</span><?php endif; ?></a>
    </div>
</nav>
<dialog class="sheet" data-menu-sheet aria-labelledby="sheet-title">
    <h2 id="sheet-title" class="sr" tabindex="-1" data-sheet-title>Menu Bâtons</h2>
    <nav class="appmenu-panel" aria-label="Menu Bâtons">
        <?php require __DIR__ . '/_menu.php'; ?>
    </nav>
    <form method="dialog"><button class="btn ghost sheet-close">Fermer</button></form>
</dialog>
<?php endif; ?>


<script src="assets/app.js?v=<?= Batons\VERSION ?>" defer></script>
</body>
</html>
