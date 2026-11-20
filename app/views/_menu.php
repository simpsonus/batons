<?php
// Contenu du menu (logo sur grand écran, onglet « Menu » sur téléphone, page « menu » sans JS).
// Variables : $r (route courante), $unlocked, $authMethod.
$cur = fn(string $route): string => $r === $route ? ' aria-current="page"' : '';
?>
<ul>
    <li><a href="<?= url('archives', from_here([])) ?>"<?= $cur('archives') ?>><?= ui_icon('archive') ?> Archives</a></li>
    <li><a href="<?= url('import', from_here([])) ?>"<?= $cur('import') ?>><?= ui_icon('import') ?> Importer</a></li>
    <li><a href="<?= url('export', from_here([])) ?>"<?= $cur('export') ?>><?= ui_icon('export') ?> Exporter</a></li>
    <li><a href="<?= url('appearance', from_here([])) ?>"<?= $cur('appearance') ?>><?= ui_icon('palette') ?> Apparence</a></li>
</ul>
<ul class="appmenu-private">
    <?php if ($unlocked): ?>
        <li><a href="<?= url('privacy', from_here([])) ?>"<?= $cur('privacy') ?>><?= ui_icon('eye') ?> Confidentialité</a></li>
        <li><form method="post" action="<?= url('lock') ?>"><?= csrf() ?><button class="menu-btn"><?= ui_icon('lock') ?> Verrouiller les éléments masqués</button></form></li>
    <?php else: ?>
        <li><a href="<?= url('unlock', from_here([])) ?>"<?= $cur('unlock') ?>><?= ui_icon('eye-off') ?> Afficher les éléments masqués</a></li>
    <?php endif; ?>
</ul>
<ul class="appmenu-private">
    <li><a href="<?= url('account', from_here([])) ?>"<?= $cur('account') ?>><?= ui_icon('account') ?> Compte</a></li>
    <?php if ($authMethod === 'builtin'): ?>
        <li><form method="post" action="<?= url('logout') ?>"><?= csrf() ?><button class="menu-btn"><?= ui_icon('logout') ?> Se déconnecter</button></form></li>
    <?php endif; ?>
</ul>
<p class="appmenu-version">Bâtons <?= Batons\VERSION ?></p>
