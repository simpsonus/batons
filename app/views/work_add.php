<?php
use Batons\Catalog;
use Batons\Works;

$kindLabel = ['film' => 'un film', 'serie' => 'une série', 'livre' => 'un livre', 'jeu' => 'un jeu'][$c['kind']];
?>
<div class="page-head cat-head" style="--c: <?= e($c['color']) ?>">
    <div class="cat-title">
        <span class="icon big" aria-hidden="true"><?= icon($c['icon']) ?></span>
        <h1><?= e($c['name']) ?></h1>
    </div>
    <div class="actions"><a class="btn ghost" href="<?= url('category', ['id' => $c['id']]) ?>">Fiche</a></div>
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
                        <span class="muted small">dernier : <?= e(Batons\Time::ago($w['last_at'])) ?></span>
                    </span>
                    <?php if (!empty($n['reading'])): ?>
                    <a class="btn ghost next-btn" href="<?= url('work', ['id' => $c['id'], 'work' => $w['id']]) ?>">En cours</a>
                    <?php else: ?>
                    <form method="post" action="<?= url('work_next', ['id' => $c['id'], 'work' => $w['id']]) ?>">
                        <?= csrf() ?>
                        <input type="hidden" name="back" value="<?= e(url()) ?>">
                        <button class="btn accent next-btn" aria-label="Ajouter <?= e($w['title']) ?>, <?= e((Works::progressLong($n['season'] ?? null, $n['episode'] ?? null, $n['volume'] ?? null) ?: 'une partie')) ?>, maintenant">
                            <span aria-hidden="true">▶</span> <?= e((Works::progress($n['season'] ?? null, $n['episode'] ?? null, $n['volume'] ?? null) ?: 'Partie')) ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card" aria-labelledby="search-title">
    <h2 id="search-title" class="card-title">Ajouter <?= $kindLabel ?></h2>
    <?php if (!$sources): ?>
        <p class="muted">La recherche <?= $c['kind'] === 'jeu' ? 'de jeux demande une clé RAWG' : 'de films demande une clé TMDB' ?> gratuite :
            <a href="<?= url('account') ?>">menu du logo › Compte › Catalogues</a>. En attendant, saisis le titre à la main.</p>
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
