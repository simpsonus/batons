<?php
$r = $_GET['r'] ?? 'home';
$authMethod = Batons\Auth::method();          // null sur les pages de connexion
$unlocked = $authMethod !== null && Batons\Privacy::isUnlocked();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1c1917">
    <title><?= e($title === 'Bâtons' ? 'Bâtons' : $title . ' · Bâtons') ?></title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Bâtons">
    <link rel="manifest" href="assets/manifest.webmanifest">
    <link rel="stylesheet" href="assets/app.css?v=<?= Batons\VERSION ?>">
</head>
<body<?= $unlocked ? ' data-relock="' . Batons\Privacy::remaining() . '"' : '' ?>>
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
            <ul>
                <li><a href="<?= url('archives') ?>" <?= $r === 'archives' ? 'aria-current="page"' : '' ?>>Archives</a></li>
                <li><a href="<?= url('import') ?>" <?= $r === 'import' ? 'aria-current="page"' : '' ?>>Importer</a></li>
                <li><a href="<?= url('export') ?>">Exporter (CSV)</a></li>
            </ul>
            <ul class="appmenu-private">
                <?php if ($unlocked): ?>
                    <li><a href="<?= url('privacy') ?>" <?= $r === 'privacy' ? 'aria-current="page"' : '' ?>><?= ui_icon('eye') ?> Confidentialité</a></li>
                    <li><form method="post" action="<?= url('lock') ?>"><?= csrf() ?><button class="menu-btn"><?= ui_icon('lock') ?> Verrouiller les éléments masqués</button></form></li>
                <?php else: ?>
                    <li><a href="<?= url('unlock') ?>" <?= $r === 'unlock' ? 'aria-current="page"' : '' ?>><?= ui_icon('eye-off') ?> Afficher les éléments masqués</a></li>
                <?php endif; ?>
            </ul>
            <ul class="appmenu-private">
                <li><a href="<?= url('account') ?>" <?= $r === 'account' ? 'aria-current="page"' : '' ?>>Compte</a></li>
                <?php if ($authMethod === 'builtin'): ?>
                    <li><form method="post" action="<?= url('logout') ?>"><?= csrf() ?><button class="menu-btn">Se déconnecter</button></form></li>
                <?php endif; ?>
            </ul>
            <p class="appmenu-version">Bâtons <?= Batons\VERSION ?></p>
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
        <a href="<?= url() ?>" <?= $r === 'home' ? 'aria-current="page"' : '' ?>>Catégories</a>
        <a href="<?= url('timeline') ?>" <?= $r === 'timeline' ? 'aria-current="page"' : '' ?>>Chronologie</a>
    </nav>
    <?php endif; ?>
</header>

<?php if ($flash): ?>
    <div class="flash" role="status" data-flash>
        <span class="flash-msg"><?= e($flash['message']) ?></span>
        <?php if ($flash['undo']): ?>
            <form method="post" action="<?= url('undo') ?>">
                <?= csrf() ?>
                <input type="hidden" name="ids" value="<?= e($flash['undo']) ?>">
                <input type="hidden" name="back" value="<?= e(here()) ?>">
                <button class="flash-btn">Annuler</button>
            </form>
        <?php endif; ?>
        <button type="button" class="flash-close" data-flash-close aria-label="Fermer le message" hidden><?= ui_icon('close') ?></button>
    </div>
<?php endif; ?>

<main id="contenu" tabindex="-1">
    <?= $content ?>
</main>


<script src="assets/app.js?v=<?= Batons\VERSION ?>" defer></script>
</body>
</html>
