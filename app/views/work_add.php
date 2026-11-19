<?php
use Batons\Catalog;
use Batons\Works;

$kindLabel = ['film' => 'un film', 'serie' => 'une série', 'livre' => 'un livre', 'jeu' => 'un jeu'][$c['kind']];
?>
<div class="page-head cat-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="icon big" aria-hidden="true"><?= icon($c['icon']) ?></span>
        <h1><?= e($c['name']) ?></h1>
        <?= help_box('Comment ça marche', array_merge([
    'serie' => ['<strong>▶ S02E05</strong> : ajoute cet épisode, vu maintenant.', 'Un autre épisode ou une autre date : touche le titre, puis « + Ajouter un épisode… ».'],
    'film' => ['Touche le titre pour revoir l\'historique ou ajouter un visionnage avec date et note.'],
    'livre' => ['<strong>▶ Tome 2</strong> (« tome 1 lu ») : marque le tome en cours comme lu aujourd\'hui et commence le suivant.', '<strong>Tome 1 lu ✓</strong> : marque seulement le tome en cours comme lu ; le tome suivant reste proposé.', 'Tu ne liras pas la suite ? Touche le titre, puis « J\'arrête là ».'],
    'jeu' => ['<strong>▶ Partie</strong> : ajoute une partie jouée maintenant.', '<strong>Terminé ✓</strong> : marque le jeu comme fini aujourd\'hui (la durée est calculée depuis son début).', 'Tu abandonnes le jeu ? Touche le titre, puis « J\'arrête là ».'],
][$c['kind']], [
            'Une autre date (hier, la semaine dernière) : touche le titre, puis « + Ajouter… » et choisis la date.',
            'Une erreur ? « Annuler » dans le message qui s\'affiche en haut défait l\'action.',
        ])) ?>
    </div>
    <a class="head-link" href="<?= url('category', ['id' => $c['id']]) ?>">Fiche<span class="sr"> de <?= e($c['name']) ?></span> <?= ui_icon('chevron') ?></a>
</div>

<?php if ($continue): ?>
    <section class="card" style="--c: <?= e($c['color']) ?>" aria-labelledby="cont-title">
        <h2 id="cont-title" class="card-title">Continuer</h2>
        <ul class="work-list">
            <?php foreach ($continue as $item): $w = $item['work']; $n = $item['next']; ?>
                <li>
                    <span class="poster sm" aria-hidden="true"><?php if (poster_url($w)): ?><img src="<?= e(poster_url($w)) ?>" alt="" width="46" height="69"><?php else: ?><?= icon($c['icon']) ?><?php endif; ?></span>
                    <span class="work-text">
                        <a class="work-title" href="<?= url('work', ['id' => $c['id'], 'work' => $w['id']]) ?>"><?= e($w['title']) ?></a>
                        <?php
                        $stC = in_array($c['kind'], Works::TRACKED, true) ? Works::status((int) $w['id'], (int) $c['id']) : null;
                        $volC = $c['kind'] === 'livre' ? Works::currentVolume((int) $w['id'], (int) $c['id']) : null;
                        ?>
                        <span class="muted small"><?php if ($stC && $stC['state'] === 'playing'): ?><?= $volC ? 'Tome ' . $volC . ' en cours' : 'En cours' ?> depuis <?= e(Works::daysLabel((int) $stC['days'])) ?><?php elseif ($stC && $stC['state'] === 'done'): ?><?= $volC ? 'Tome ' . $volC . ' lu' : 'Terminé' ?> · <?= e(Batons\Time::ago($w['last_at'])) ?><?php else: ?>dernier : <?= e(Batons\Time::ago($w['last_at'])) ?><?php endif; ?></span>
                    </span>
                    <?php $back = here(); require __DIR__ . '/_work_actions.php'; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card" aria-labelledby="search-title">
    <h2 id="search-title" class="card-title">Ajouter <?= $kindLabel ?></h2>
    <?php if (!$sources): ?>
        <p class="muted">La recherche <?= $c['kind'] === 'jeu' ? 'de jeux demande une clé RAWG' : 'de films demande une clé TMDB' ?> gratuite :
            <a href="<?= url('account') ?>">Menu › Compte › Catalogues</a>. En attendant, saisis le titre à la main.</p>
    <?php else: ?>
        <form method="get" action="./" role="search" data-work-search>
            <input type="hidden" name="r" value="work_add">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <label for="work-q">Titre</label>
            <div class="search-row">
                <input type="search" id="work-q" name="q" value="<?= e($q) ?>" autocomplete="off" placeholder="Rechercher <?= $kindLabel ?>…" <?= $continue ? '' : 'autofocus' ?>>
                <button class="btn">Chercher</button>
            </div>
        </form>
        <div data-work-results aria-live="polite">
            <?php require __DIR__ . '/_work_results.php'; ?>
        </div>
        <p class="muted small">Recherche via <?= e(implode(', ', $sources)) ?> : le texte tapé est envoyé à ce service.
            <?php if (in_array('TMDB', $sources, true)): ?>Ce produit utilise l'API TMDB mais n'est ni approuvé ni certifié par TMDB.<?php endif; ?>
            <?php if (in_array('RAWG', $sources, true)): ?>Données de jeux : <a href="https://rawg.io" target="_blank" rel="noopener">RAWG<span class="sr"> (nouvel onglet)</span></a>.<?php endif; ?></p>
    <?php endif; ?>

    <details class="manual" <?= !$sources || ($q !== '' && !$results) ? 'open' : '' ?>>
        <summary>Saisir à la main</summary>
        <form method="post" action="<?= url('work_manual', ['id' => $c['id']]) ?>">
            <?= csrf() ?>
            <div class="row">
                <label>Titre
                    <input type="text" name="title" value="<?= e($q) ?>" maxlength="200" required>
                </label>
                <label><?= $c['kind'] === 'livre' ? 'Auteur' : ($c['kind'] === 'jeu' ? 'Plateforme' : 'Précision') ?> <span class="muted">facultatif</span>
                    <input type="text" name="subtitle" maxlength="200">
                </label>
            </div>
            <button class="btn ghost">Continuer</button>
        </form>
    </details>
</section>
