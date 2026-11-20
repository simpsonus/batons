<?php
$r = 'menu';
$authMethod = Batons\Auth::method();
$unlocked = Batons\Privacy::isUnlocked();
?>
<div class="page-head"><h1>Menu</h1><div class="head-tools"><?= close_link(from_url(url()), 'Fermer') ?></div></div>
<nav class="card menu-page appmenu-panel" aria-label="Menu Bâtons">
    <?php require __DIR__ . '/_menu.php'; ?>
</nav>
