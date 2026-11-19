<?php
$r = 'menu';
$authMethod = Batons\Auth::method();
$unlocked = Batons\Privacy::isUnlocked();
?>
<h1>Menu</h1>
<nav class="card menu-page appmenu-panel" aria-label="Menu Bâtons">
    <?php require __DIR__ . '/_menu.php'; ?>
</nav>
