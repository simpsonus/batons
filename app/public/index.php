<?php
declare(strict_types=1);

/**
 * Bâtons — point d'entrée unique.
 * Les routes passent par le paramètre « r » (./?r=category&id=3) : aucune réécriture d'URL nécessaire.
 */

use Batons\Auth;
use Batons\Categories;
use Batons\Events;
use Batons\Http;
use Batons\Privacy;
use Batons\Time;
use Batons\WebAuthn;

require dirname(__DIR__) . '/src/bootstrap.php';

$route = (string) ($_GET['r'] ?? 'home');

// Sonde de santé, sans authentification (Docker HEALTHCHECK, supervision).
if ($route === 'health') {
    header('Content-Type: text/plain');
    Batons\Db::pdo();
    exit('ok');
}

Http::startSession();
if (Http::isPost()) {
    Http::checkCsrf();
}

// --- Connexion (mode builtin) : pages accessibles sans être identifié -------------------
if ($route === 'login' || $route === 'setup') {
    if (!Auth::has('builtin')) {
        Http::redirect();
    }
    if (Auth::method() !== null) {
        Http::redirect(); // déjà identifié
    }
    $setup = Auth::needsSetup();
    if ($route === 'login' && $setup) {
        Http::redirect('setup');
    }
    if ($route === 'setup' && !$setup) {
        Http::redirect('login');
    }
    $error = null;
    $errors = [];
    $username = Http::input('username');
    if (Http::isPost()) {
        if ($setup) {
            if (!Auth::validUsername($username)) {
                $errors['username'] = '2 à 60 caractères : lettres, chiffres, point, tiret, @.';
            }
            if ($pwErr = Auth::validatePassword((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''))) {
                $errors['password'] = $pwErr;
            }
            if (!$errors) {
                Batons\Db::run('INSERT INTO users (username, display_name, is_admin, created_at) VALUES (?, ?, 1, ?)',
                    [$username, $username, Time::nowUtc()]);
                $newId = Batons\Db::lastId();
                // Reprend les données de l'utilisateur local éventuel (passage du mode none au mode builtin).
                $local = Batons\Db::value('SELECT id FROM users WHERE username = ? AND id <> ?', [(string) Batons\Config::get('default_user'), $newId]);
                if ($local && !Batons\Db::value('SELECT 1 FROM categories WHERE user_id = ?', [$newId])) {
                    Batons\Db::run('UPDATE categories SET user_id = ? WHERE user_id = ?', [$newId, $local]);
                    Batons\Db::run('UPDATE groups SET user_id = ? WHERE user_id = ?', [$newId, $local]);
                }
                Auth::setPassword($newId, (string) $_POST['password']);
                Auth::attempt($username, (string) $_POST['password']);
                Http::flash('Compte administrateur créé. Bienvenue !');
                Http::redirect();
            }
        } else {
            $res = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
            if ($res === true) {
                Http::redirect();
            }
            $error = $res;
        }
    }
    Http::render('login', ['setup' => $setup, 'error' => $error, 'errors' => $errors, 'username' => $username],
        $setup ? 'Créer le premier compte' : 'Connexion');
}

if ($route === 'logout' && Http::isPost()) {
    Auth::logout();
    Http::redirect('login');
}

$uid = Auth::id();

/** Lit une valeur numérique saisie (accepte la virgule). */
$parseValue = static function (string $raw): ?float {
    $raw = str_replace([' ', ','], ['', '.'], trim($raw));
    return is_numeric($raw) ? (float) $raw : null;
};

/** Entier positif saisi, ou null. */
$posInt = static function (string $raw): ?int {
    return ctype_digit($raw) && (int) $raw > 0 && (int) $raw < 100000 ? (int) $raw : null;
};

/** Étape d'un jeu : start | finish | null. */
$milestone = static function (string $raw): ?string {
    return in_array($raw, ['start', 'finish'], true) ? $raw : null;
};

/** Note 1 à 10, ou null. */
$rating = static function (string $raw): ?int {
    return ctype_digit($raw) && (int) $raw >= 1 && (int) $raw <= 10 ? (int) $raw : null;
};

/** Catégorie de l'utilisateur ou 404. */
$category = static function (?int $id) use ($uid): array {
    return ($id !== null ? Categories::find($uid, $id) : null) ?? Http::notFound('Catégorie');
};

/** Bâton de l'utilisateur ou 404. */
$event = static function (?int $id) use ($uid): array {
    return ($id !== null ? Events::find($uid, $id) : null) ?? Http::notFound('Bâton');
};

switch ($route) {

    // --- Accueil : grille des catégories ---------------------------------------
    case 'home':
        Http::render('home', [
            'uid' => $uid,
            'categories' => Categories::listWithSummary($uid),
            'archivedCount' => count(Categories::listWithSummary($uid, true)),
        ]);

    // --- Ajout éclair : un tap = un bâton maintenant ----------------------------
    case 'tap':
        $c = $category(Http::intInput('id'));
        if ($c['kind'] !== 'simple') {
            Http::redirect('work_add', ['id' => $c['id']]); // une œuvre se choisit
        }
        if ($c['periods']) {
            // Option « périodes » : un tap commence la période, le suivant la termine.
            $open = Batons\Periods::open((int) $c['id']);
            $id = Events::add((int) $c['id'], null, null, null, $open ? 'finish' : 'start');
            if ($open) {
                $p = Batons\Periods::open((int) $c['id']) ?? Batons\Periods::list((int) $c['id'])[0];
                Http::flash('« ' . $c['name'] . ' » terminé : ' . Batons\Works::daysLabel((int) $p['days']) . '.', $id);
            } else {
                Http::flash('« ' . $c['name'] . ' » commencé.', $id);
            }
            Http::back();
        }
        $id = Events::add((int) $c['id']);
        Http::flash('Bâton ajouté à « ' . $c['name'] . ' ».', $id);
        Http::back();

    case 'undo':
        $ids = array_filter(array_map('intval', explode(',', Http::input('ids') ?: Http::input('id'))));
        $n = 0;
        foreach ($ids as $undoId) {
            if ($ev = Events::find($uid, $undoId)) { // seulement les bâtons de l'utilisateur
                Events::delete((int) $ev['id']);
                $n++;
            }
        }
        Http::flash($n > 1 ? 'Bâtons annulés.' : 'Bâton annulé.');
        Http::back();

    // --- Fiche catégorie ----------------------------------------------------------
    case 'category':
        $c = $category(Http::intInput('id'));
        $scale = in_array($_GET['scale'] ?? '', ['day', 'week', 'month'], true) ? $_GET['scale'] : 'day';
        $stats = Events::stats((int) $c['id'], (bool) $c['count_future']);
        $metric = ($_GET['metric'] ?? '') === 'sum' || (($_GET['metric'] ?? '') === '' && (int) $stats['with_value'] > 0) ? 'sum' : 'count';
        if ((int) $stats['with_value'] === 0) {
            $metric = 'count';
        }
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('category', [
            'c'      => $c,
            'stats'  => $stats,
            'series' => Events::series((int) $c['id'], $scale, (bool) $c['count_future']),
            'scale'  => $scale,
            'metric' => $metric,
            'events' => Events::forCategory((int) $c['id'], 51, $page * 50),
            'page'   => $page,
            'uid'    => $uid,
        ], $c['name']);

    // --- Bâtons : ajout détaillé, édition, suppression ----------------------------
    case 'event_add':
        $c = $category(Http::intInput('category_id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? Time::nowUtc();
        $id = Events::add((int) $c['id'], $at, $parseValue(Http::input('value')), Http::input('note'),
            $c['periods'] ? $milestone(Http::input('milestone')) : null);
        Http::flash('Bâton ajouté.', $id);
        Http::redirect('category', ['id' => $c['id']]);

    case 'event_edit':
        $ev = $event(Http::intInput('id'));
        Http::render('event_form', ['ev' => $ev], 'Modifier le bâton');

    case 'event_save':
        $ev = $event(Http::intInput('id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? $ev['occurred_at'];
        Events::update((int) $ev['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        if (!$ev['work_id'] && $ev['periods']) {
            Batons\Db::run('UPDATE events SET milestone = ? WHERE id = ?', [$milestone(Http::input('milestone')), $ev['id']]);
        }
        if ($ev['work_id']) {
            Events::updateWork((int) $ev['id'], $posInt(Http::input('season')), $posInt(Http::input('episode')),
                $posInt(Http::input('volume')), $rating(Http::input('rating')),
                in_array($ev['kind'], Batons\Works::TRACKED, true) ? $milestone(Http::input('milestone')) : null);
        }
        Http::flash('Bâton modifié.');
        Http::back('category', ['id' => $ev['category_id']]);

    case 'event_delete':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton supprimé.');
        Http::back('category', ['id' => $ev['category_id']]);

    // --- Catégories : création, édition, archivage, suppression, ordre -------------
    case 'category_new':
    case 'category_edit':
        $c = $route === 'category_edit' ? $category(Http::intInput('id'))
            : ['id' => null, 'name' => '', 'icon' => '', 'color' => '#c2410c', 'unit' => '', 'group_name' => '', 'archived' => 0, 'hidden' => 0, 'periods' => 0, 'count_future' => 0, 'kind' => in_array($_GET['kind'] ?? '', ['film', 'serie', 'livre', 'jeu'], true) ? $_GET['kind'] : 'simple'];
        Http::render('category_form', ['c' => $c, 'errors' => [], 'groups' => Categories::groupNames($uid)],
            $c['id'] ? 'Modifier « ' . $c['name'] . ' »' : 'Nouvelle catégorie');

    case 'category_save':
        $id = Http::intInput('id');
        if ($id !== null) {
            $category($id);
        }
        [$data, $errors] = Categories::validate($_POST);
        if ($data['hidden'] && !Privacy::hasPin($uid)) {
            $errors['hidden'] = 'Définis d\'abord un code de déverrouillage (menu du logo › Afficher les éléments masqués).';
        }
        if ($errors) {
            $c = ['id' => $id, 'name' => $data['name'], 'icon' => $data['icon'], 'color' => $data['color'],
                  'unit' => $data['unit'], 'group_name' => $data['group'], 'archived' => 0, 'hidden' => $data['hidden'] ? 1 : 0, 'kind' => $data['kind'], 'periods' => $data['periods'] ? 1 : 0];
            Http::render('category_form', ['c' => $c, 'errors' => $errors, 'groups' => Categories::groupNames($uid)], 'Catégorie');
        }
        if ($id !== null) {
            Categories::update($uid, $id, $data);
            if ($data['hidden'] && !Privacy::isUnlocked()) {
                Http::flash('Catégorie « ' . $data['name'] . ' » masquée.');
                Http::redirect();
            }
            Http::flash('Catégorie enregistrée.');
            Http::back('category', ['id' => $id]);
        }
        $id = Categories::create($uid, $data);
        Http::flash('Catégorie « ' . $data['name'] . ' » créée.');
        Http::redirect();

    case 'category_archive':
        $c = $category(Http::intInput('id'));
        $archive = !(bool) $c['archived'];
        Categories::setArchived($uid, (int) $c['id'], $archive);
        Http::flash($archive ? 'Catégorie archivée.' : 'Catégorie restaurée.');
        Http::redirect($archive ? 'home' : 'category', $archive ? [] : ['id' => $c['id']]);

    case 'category_delete':
        $c = $category(Http::intInput('id'));
        Categories::delete($uid, (int) $c['id']);
        Http::flash('Catégorie « ' . $c['name'] . ' » supprimée, avec ses bâtons.');
        Http::redirect();

    case 'category_move':
        $c = $category(Http::intInput('id'));
        Categories::move($uid, (int) $c['id'], Http::input('dir') === 'up' ? -1 : 1);
        Http::back('category_order');

    // --- Œuvres (catégories films / séries / livres) ------------------------------------
    case 'work_add':
        $c = $category(Http::intInput('id'));
        if ($c['kind'] === 'simple') {
            Http::redirect('category', ['id' => $c['id']]);
        }
        $q = Http::input('q');
        $results = $q !== '' ? Batons\Catalog::search($c['kind'], $q, $uid) : [];
        if (($_GET['partial'] ?? '') === '1') {
            $partial = true;
            require dirname(__DIR__) . '/views/_work_results.php';
            exit;
        }
        $continue = [];
        foreach (Batons\Works::inCategory($uid, (int) $c['id']) as $w) {
            if ($w['stopped_at']) {
                continue; // « J'arrête là »
            }
            $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
            if (in_array($w['kind'], Batons\Works::TRACKED, true)) {
                $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
                $explicitStart = (bool) Batons\Db::value("SELECT 1 FROM events WHERE work_id = ? AND category_id = ? AND milestone = 'start'",
                    [$w['id'], $c['id']]);
                if ($st['state'] === 'done' && !($w['kind'] === 'livre' && $next !== null)) {
                    $next = null; // terminé (sauf livre avec un tome suivant)
                } elseif ($st['state'] === 'playing' && !$explicitStart
                    && strtotime($w['last_at'] . ' UTC') < time() - 90 * 86400) {
                    $next = null; // jamais marqué « commencé » et rien depuis 3 mois : oublié
                } elseif ($st['state'] === 'playing' && $next === null) {
                    $next = ['reading' => true]; // en cours sans étape suivante (livre sans tome)
                }
            }
            if ($next !== null) {
                $continue[] = ['work' => $w, 'next' => $next];
            }
            if (count($continue) >= 30) {
                break;
            }
        }
        Http::render('work_add', ['c' => $c, 'q' => $q, 'results' => $results, 'continue' => $continue,
            'sources' => Batons\Catalog::sources($c['kind'], $uid)], Batons\Catalog::KINDS[$c['kind']] . ' : ajouter');

    case 'work_pick':
        $c = $category(Http::intInput('id'));
        $source = Http::input('source');
        $sid = Http::input('sid');
        if (!in_array($source, ['tmdb', 'tvmaze', 'gbooks', 'openlibrary', 'rawg'], true) || $sid === '') {
            Http::redirect('work_add', ['id' => $c['id']]);
        }
        $item = Batons\Catalog::details($source, $sid, $c['kind'], $uid) ?? [
            // Catalogue injoignable : on garde ce que la recherche avait renvoyé.
            'source' => $source, 'source_id' => $sid, 'title' => Http::input('title') ?: '(sans titre)',
            'subtitle' => Http::input('subtitle') ?: null, 'year' => $posInt(Http::input('year')), 'poster' => Http::input('poster') ?: null,
        ];
        $workId = Batons\Works::fromCatalog($uid, $c['kind'], $item);
        Http::redirect('work_log', ['id' => $c['id'], 'work' => $workId]);

    case 'work_manual':
        $c = $category(Http::intInput('id'));
        $title = Http::input('title');
        if ($title === '') {
            Http::redirect('work_add', ['id' => $c['id']]);
        }
        $workId = Batons\Works::manual($uid, $c['kind'], $title, Http::input('subtitle') ?: null);
        Http::redirect('work_log', ['id' => $c['id'], 'work' => $workId]);

    case 'work_log':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $last = Batons\Works::lastEvent((int) $w['id'], (int) $c['id']);
        if (Http::isPost()) {
            $at = Time::fromLocalInput(Http::input('occurred_at')) ?? Time::nowUtc();
            $evId = Events::addWork((int) $c['id'], $at, (int) $w['id'],
                $c['kind'] === 'serie' ? $posInt(Http::input('season')) : null,
                $c['kind'] === 'serie' ? $posInt(Http::input('episode')) : null,
                $c['kind'] === 'livre' ? $posInt(Http::input('volume')) : null,
                $rating(Http::input('rating')), Http::input('note'),
                in_array($c['kind'], Batons\Works::TRACKED, true) ? $milestone(Http::input('milestone')) : null);
            $ev = Events::find($uid, $evId);
            $label = trim($w['title'] . ' ' . Batons\Works::progress($ev['season'], $ev['episode'], $ev['volume'])
                . ['start' => ' : commencé', 'finish' => ' : terminé', '' => ''][$ev['milestone'] ?? '']);
            Http::flash('« ' . $label . ' » ajouté.', $evId);
            Http::redirect('work', ['id' => $c['id'], 'work' => $w['id']]);
        }
        Http::render('work_log', ['c' => $c, 'w' => $w, 'last' => $last,
            'next' => Batons\Works::next($uid, $w, $last), 'seasons' => Batons\Works::seasons($w)], $w['title']);

    case 'work':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $evs = Events::forWork((int) $w['id'], (int) $c['id']);
        $last = $evs[0] ?? null;
        Http::render('work', ['c' => $c, 'w' => $w, 'events' => $evs,
            'next' => Batons\Works::next($uid, $w, $last), 'last' => $last], $w['title']);

    case 'work_mark':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $m = $milestone(Http::input('milestone'));
        if ($m === null || !in_array($c['kind'], Batons\Works::TRACKED, true)) {
            Http::redirect('work', ['id' => $c['id'], 'work' => $w['id']]);
        }
        // Livre en plusieurs tomes : l'étape porte le numéro du tome en cours.
        $vol = $c['kind'] === 'livre' ? Batons\Works::currentVolume((int) $w['id'], (int) $c['id']) : null;
        if ($m === 'start' && $vol !== null) {
            $vol = null; // « Recommencer » un livre : on repart sans tome, la fiche permet d'en préciser un
        }
        $evId = Events::addWork((int) $c['id'], Time::nowUtc(), (int) $w['id'], null, null, $vol, null, null, $m);
        $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
        Http::flash($m === 'finish'
            ? '« ' . $w['title'] . ' » ' . ($c['kind'] === 'livre' ? 'lu' : 'terminé') . ' en ' . Batons\Works::daysLabel((int) $st['days']) . '. Bravo !'
            : '« ' . $w['title'] . ' » commencé aujourd\'hui.', $evId);
        Http::back('work', ['id' => $c['id'], 'work' => $w['id']]);

    case 'work_stop':
    case 'work_resume':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        Batons\Db::run('UPDATE works SET stopped_at = ? WHERE id = ? AND user_id = ?',
            [$route === 'work_stop' ? Time::nowUtc() : null, $w['id'], $uid]);
        Http::flash($route === 'work_stop'
            ? '« ' . $w['title'] . ' » ne sera plus proposé dans « Continuer ».'
            : '« ' . $w['title'] . ' » est de nouveau proposé dans « Continuer ».');
        Http::redirect('work', ['id' => $c['id'], 'work' => $w['id']]);

    case 'work_next':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
        if ($next === null) {
            Http::redirect('work_log', ['id' => $c['id'], 'work' => $w['id']]);
        }
        if ($c['kind'] === 'livre' && isset($next['volume'])) {
            // Tome suivant : le tome en cours est marqué lu, et le suivant commencé (même instant).
            $st = Batons\Works::status((int) $w['id'], (int) $c['id']);
            $cur = Batons\Works::currentVolume((int) $w['id'], (int) $c['id']);
            $now = Time::nowUtc();
            $msg = '';
            $undo = [];
            if ($st['state'] === 'playing' && $cur !== null) {
                // Fin du tome en cours une seconde avant le début du suivant (ordre garanti, jamais dans le futur).
                $undo[] = Events::addWork((int) $c['id'], gmdate('Y-m-d H:i:s', strtotime($now . ' UTC') - 1), (int) $w['id'], null, null, $cur, null, null, 'finish');
                $msg = 'Tome ' . $cur . ' lu, ';
            }
            $undo[] = $evId = Events::addWork((int) $c['id'], $now, (int) $w['id'],
                null, null, (int) $next['volume'], null, null, 'start');
            Http::flash('« ' . $w['title'] . ' » : ' . $msg . 'tome ' . $next['volume'] . ' commencé.', $undo);
            Http::back('category', ['id' => $c['id']]);
        }
        $evId = Events::addWork((int) $c['id'], Time::nowUtc(), (int) $w['id'],
            $next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null, null, null);
        Http::flash('« ' . trim($w['title'] . ' ' . Batons\Works::progress($next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null)) . ' » ajouté.', $evId);
        Http::back('category', ['id' => $c['id']]);

    case 'thumb':
        $u = (string) ($_GET['u'] ?? '');
        $bin = Batons\Works::thumb($u);
        if ($bin === null) {
            http_response_code(404);
            exit;
        }
        $mime = getimagesizefromstring($bin)['mime'] ?? 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        echo $bin;
        exit;

    case 'poster':
        $w = Batons\Works::find($uid, (int) Http::intInput('id'));
        // Pas d'affiche d'une œuvre qui n'apparaît que dans des catégories masquées (verrouillé).
        $visible = $w && (!Batons\Db::value('SELECT 1 FROM events WHERE work_id = ?', [$w['id']])
            || Batons\Db::value('SELECT 1 FROM events e JOIN categories c ON c.id = e.category_id
                LEFT JOIN groups g ON g.id = c.group_id WHERE e.work_id = ?' . Privacy::visibleSql(), [$w['id']]));
        $file = $w ? Batons\Works::posterFile((int) $w['id']) : '';
        if (!$visible || !$w['has_poster'] || !is_file($file)) {
            http_response_code(404);
            exit;
        }
        $mime = (getimagesize($file)['mime'] ?? 'image/jpeg');
        header('Content-Type: ' . (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg'));
        header('Cache-Control: private, max-age=604800');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;

    // --- Compte ----------------------------------------------------------------------
    case 'account':
    case 'account_password':
    case 'account_password_remove':
    case 'user_create':
    case 'user_delete':
        $me = Batons\Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        $isAdmin = (bool) $me['is_admin'] && Auth::has('builtin') && !Auth::has('header');
        $errors = [];
        $newUsername = '';
        if (Http::isPost()) {
            if ($route === 'account_password' && Auth::has('builtin')) {
                if (Auth::method() === 'builtin' && !password_verify((string) ($_POST['current'] ?? ''), (string) $me['password_hash'])) {
                    $errors['current'] = 'Mot de passe actuel incorrect.';
                } elseif ($err = Auth::validatePassword((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''))) {
                    $errors['password'] = $err;
                } else {
                    Auth::setPassword($uid, (string) $_POST['password']);
                    Http::flash($me['password_hash'] ? 'Mot de passe changé.' : 'Mot de passe défini : l\'accès direct est ouvert pour « ' . $me['username'] . ' ».');
                    Http::redirect('account');
                }
            } elseif ($route === 'account' && isset($_POST['rawg_key'])) {
                $k = trim((string) $_POST['rawg_key']);
                if ($k !== '' && !preg_match('/^[A-Za-z0-9]{20,64}$/', $k)) {
                    $errors['rawg_key'] = 'Clé invalide : colle la clé d\'API fournie par RAWG.';
                } else {
                    Batons\Settings::set($uid, 'rawg_key', $k);
                    Http::flash($k === '' ? 'Clé RAWG retirée.' : 'Clé RAWG enregistrée.');
                    Http::redirect('account');
                }
            } elseif ($route === 'account' && isset($_POST['tmdb_key'])) {
                $k = trim((string) $_POST['tmdb_key']);
                if ($k !== '' && !preg_match('/^[A-Za-z0-9._-]{20,400}$/', $k)) {
                    $errors['tmdb_key'] = 'Clé invalide : colle la « clé d\'API » ou le « jeton d\'accès en lecture » TMDB.';
                } else {
                    Batons\Settings::set($uid, 'tmdb_key', $k);
                    Http::flash($k === '' ? 'Clé TMDB retirée.' : 'Clé TMDB enregistrée.');
                    Http::redirect('account');
                }
            } elseif ($route === 'account_password_remove' && Auth::method() === 'header') {
                Batons\Db::run('UPDATE users SET password_hash = NULL WHERE id = ?', [$uid]);
                Http::flash('Mot de passe supprimé : l\'accès direct est fermé pour ce compte.');
                Http::redirect('account');
            } elseif ($route === 'user_create' && $isAdmin) {
                $newUsername = Http::input('username');
                if (!Auth::validUsername($newUsername)) {
                    $errors['new_username'] = '2 à 60 caractères : lettres, chiffres, point, tiret, @.';
                } elseif (Batons\Db::value('SELECT 1 FROM users WHERE username = ?', [$newUsername])) {
                    $errors['new_username'] = 'Cet identifiant existe déjà.';
                }
                $pw = (string) ($_POST['password'] ?? '');
                if ($err = Auth::validatePassword($pw, $pw)) {
                    $errors['new_password'] = $err;
                }
                if (!$errors) {
                    Batons\Db::run('INSERT INTO users (username, display_name, created_at) VALUES (?, ?, ?)', [$newUsername, $newUsername, Time::nowUtc()]);
                    Auth::setPassword(Batons\Db::lastId(), $pw);
                    Http::flash('Compte « ' . $newUsername . ' » créé.');
                    Http::redirect('account');
                }
            } elseif ($route === 'user_delete' && $isAdmin) {
                $target = (int) Http::intInput('id');
                if ($target !== $uid) {
                    Batons\Db::run('DELETE FROM users WHERE id = ?', [$target]);
                    Http::flash('Compte supprimé.');
                }
                Http::redirect('account');
            }
        }
        Http::render('account', [
            'me' => $me, 'method' => Auth::method(), 'errors' => $errors, 'newUsername' => $newUsername,
            'rawgOwn' => Batons\Settings::get($uid, 'rawg_key') !== null, 'rawgGlobal' => (string) Batons\Config::get('rawg_key') !== '',
            'tmdbOwn' => Batons\Settings::get($uid, 'tmdb_key') !== null, 'tmdbGlobal' => (string) Batons\Config::get('tmdb_key') !== '',
            'users' => $isAdmin ? Batons\Db::all('SELECT id, username, is_admin, created_at FROM users WHERE password_hash IS NOT NULL ORDER BY username') : null,
        ], 'Compte');

    // --- Éléments masqués : déverrouillage, code, passkeys ---------------------------
    case 'unlock':
        if (Privacy::isUnlocked()) {
            Http::redirect();
        }
        $error = null;
        if (Http::isPost()) {
            $res = Privacy::checkPin($uid, Http::input('pin'));
            if ($res === true) {
                Privacy::unlock();
                Http::flash('Éléments masqués visibles. Ils se reverrouillent après ' . intdiv(Privacy::timeout(), 60) . ' min d\'inactivité.');
                Http::redirect();
            }
            $error = $res;
        }
        Http::render('unlock', [
            'hasPin'      => Privacy::hasPin($uid),
            'hasPasskeys' => (bool) Batons\Db::value('SELECT 1 FROM passkeys WHERE user_id = ?', [$uid]),
            'blocked'     => Privacy::blockedFor($uid),
            'error'       => $error,
            'pinErrors'   => [],
        ], 'Éléments masqués');

    case 'pin_save':
        // Première définition du code (verrouillé, aucun code) ou changement (déverrouillé).
        $first = !Privacy::hasPin($uid);
        if (!$first && !Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        $pin = Http::input('pin');
        $errs = [];
        if (!Privacy::validPinFormat($pin)) {
            $errs['pin'] = 'Le code doit faire exactement ' . Privacy::PIN_LENGTH . ' chiffres.';
        } elseif ($pin !== Http::input('pin2')) {
            $errs['pin2'] = 'Les deux saisies ne correspondent pas.';
        }
        if ($errs) {
            if ($first) {
                Http::render('unlock', ['hasPin' => false, 'hasPasskeys' => false, 'blocked' => 0, 'error' => null, 'pinErrors' => $errs], 'Éléments masqués');
            }
            Http::render('privacy', Privacy::settings($uid) + ['pinErrors' => $errs], 'Confidentialité');
        }
        Privacy::setPin($uid, $pin);
        Privacy::unlock();
        Http::flash($first ? 'Code enregistré. Tu peux maintenant masquer des catégories et des groupes.' : 'Code modifié.');
        Http::redirect($first ? 'privacy' : 'privacy');

    case 'lock':
        Privacy::lock();
        Http::flash('Éléments masqués verrouillés.');
        Http::redirect();

    case 'privacy':
        if (!Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Http::render('privacy', Privacy::settings($uid) + ['pinErrors' => []], 'Confidentialité');

    case 'passkey_options':
        header('Content-Type: application/json; charset=utf-8');
        $mode = Http::input('mode');
        if ($mode === 'register') {
            if (!Privacy::isUnlocked()) {
                http_response_code(403);
                exit(json_encode(['error' => 'Déverrouille d\'abord avec ton code.']));
            }
            exit(json_encode(WebAuthn::registrationOptions(Auth::user()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        if (Privacy::blockedFor($uid) > 0) {
            http_response_code(429);
            exit(json_encode(['error' => 'Déverrouillage bloqué temporairement.']));
        }
        exit(json_encode(WebAuthn::authenticationOptions($uid), JSON_UNESCAPED_SLASHES));

    case 'passkey_register':
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!Privacy::isUnlocked()) {
                throw new RuntimeException('Déverrouille d\'abord avec ton code.');
            }
            WebAuthn::register($uid, $_POST, Http::input('name'));
            Http::flash('Appareil enregistré : tu peux maintenant déverrouiller avec Face ID / Touch ID.');
            exit(json_encode(['ok' => true]));
        } catch (Throwable $e) {
            http_response_code(400);
            exit(json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }

    case 'passkey_unlock':
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (Privacy::blockedFor($uid) > 0) {
                throw new RuntimeException('Déverrouillage bloqué temporairement.');
            }
            WebAuthn::verify($uid, $_POST);
            Privacy::unlock();
            Http::flash('Éléments masqués visibles. Ils se reverrouillent après ' . intdiv(Privacy::timeout(), 60) . ' min d\'inactivité.');
            exit(json_encode(['ok' => true]));
        } catch (Throwable $e) {
            http_response_code(400);
            exit(json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }

    case 'passkey_delete':
        if (!Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Batons\Db::run('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [Http::intInput('id'), $uid]);
        Http::flash('Appareil retiré.');
        Http::redirect('privacy');

    case 'group_hide':
        $hide = Http::input('hidden') === '1';
        if ($hide && !Privacy::hasPin($uid)) {
            Http::flash('Définis d\'abord un code de déverrouillage pour pouvoir masquer.');
            Http::redirect('unlock');
        }
        if (!$hide && !Privacy::isUnlocked()) {
            Http::redirect('unlock');
        }
        Privacy::setGroupHidden($uid, (int) Http::intInput('id'), $hide);
        Http::flash($hide ? 'Groupe masqué.' : 'Groupe de nouveau visible.');
        Http::back('category_order');

    case 'group_move':
        Categories::moveGroup($uid, (int) Http::intInput('id'), Http::input('dir') === 'up' ? -1 : 1);
        Http::back('category_order');

    case 'reorder':
        // Glisser-déposer : nouvel ordre complet (groupes, puis catégories par groupe). Réponse JSON.
        $groupOrder = array_filter(explode(',', Http::input('groups')), 'ctype_digit');
        $catOrder = [];
        foreach ((array) ($_POST['cats'] ?? []) as $gid => $list) {
            if (ctype_digit((string) $gid) && is_string($list)) {
                $catOrder[(int) $gid] = array_filter(explode(',', $list), 'ctype_digit');
            }
        }
        $ok = Categories::reorder($uid, $groupOrder, $catOrder);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($ok ? 200 : 409);
        echo json_encode(['ok' => $ok]);
        exit;

    case 'group_rename':
        $g = Categories::group($uid, (int) Http::intInput('id'));
        if ($g === null) {
            Http::notFound();
        }
        $name = trim(preg_replace('/\s+/u', ' ', Http::input('label')));
        $error = null;
        $conflict = null;
        if (Http::isPost() || isset($_GET['label'])) {
            if ($name === '') {
                $error = 'L\'intitulé est obligatoire.';
            } elseif (mb_strlen($name) > 80) {
                $error = '80 caractères maximum.';
            } elseif ($name !== $g['name']) {
                $conflict = Categories::groupNamed($uid, $name, (int) $g['id']);
                if ($conflict && $conflict['hidden'] && !Privacy::isUnlocked()) {
                    $error = 'Cet intitulé n\'est pas disponible.';
                    $conflict = null;
                }
            }
        }
        if (Http::isPost() && $error === null) {
            if ($conflict && Http::input('merge') === '1') {
                Categories::mergeGroups($uid, (int) $g['id'], (int) $conflict['id']);
                Http::flash('Groupes réunis dans « ' . $conflict['name'] . ' ».');
                Http::back('category_order');
            }
            if (!$conflict) {
                if ($name !== $g['name']) {
                    Categories::renameGroup($uid, (int) $g['id'], $name);
                    Http::flash('Groupe renommé en « ' . $name . ' ».');
                }
                Http::back('category_order');
            }
        }
        Http::render('group_rename', ['g' => $g, 'name' => $name !== '' ? $name : $g['name'],
            'error' => $error, 'conflict' => $conflict], 'Renommer le groupe');

    case 'category_order':
        Http::render('category_order', [
            'categories' => Categories::listWithSummary($uid),
            'groups'     => Categories::groups($uid),
        ], 'Réorganiser');

    case 'appearance':
        if (Http::isPost()) {
            Batons\Theme::save($uid, Http::input('tile_style'), Http::input('tile_grad'), Http::input('tile_shape'), Http::input('theme'));
            Http::flash('Apparence enregistrée.');
            Http::back('appearance');
        }
        Http::render('appearance', [
            'theme' => Batons\Theme::current($uid),
            'previewCats' => array_slice(Categories::listWithSummary($uid), 0, 4),
        ], 'Apparence');

    case 'menu':
        Http::render('menu', [], 'Menu');

    case 'archives':
        Http::render('archives', ['categories' => Categories::listWithSummary($uid, true)], 'Archives');

    // --- Chronologie ---------------------------------------------------------------
    case 'timeline':
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('timeline', ['events' => Events::timeline($uid, 101, $page * 100), 'page' => $page], 'Chronologie');

    // --- Import (Chronicling, ou export CSV de Bâtons) -----------------------------
    case 'import':
    case 'import_check':
        // Import en trois étapes : provenance -> fichiers -> vérification (rien n'est écrit) -> import.
        // Les fichiers envoyés sont gardés le temps de la confirmation dans data_dir/import/<jeton>.
        $importDir = Batons\Config::get('data_dir') . '/import';
        foreach (glob($importDir . '/*', GLOB_ONLYDIR) ?: [] as $old) {        // ménage : plus de 24 h
            if (filemtime($old) < time() - 86400) {
                array_map('unlink', glob($old . '/*') ?: []);
                @rmdir($old);
            }
        }
        $source = in_array($_GET['source'] ?? $_POST['source'] ?? '', ['chronicling', 'batons'], true) ? ($_GET['source'] ?? $_POST['source']) : null;
        $loadFiles = static function (string $dir): array {
            $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true) ?: [];
            $files = [];
            foreach ($meta as $stored => $name) {
                if (is_file($dir . '/' . $stored)) {
                    $files[$dir . '/' . $stored] = $name;
                }
            }
            return $files;
        };
        if ($route === 'import_check' && Http::isPost()) {
            $token = bin2hex(random_bytes(12));
            $dir = $importDir . '/' . $uid . '-' . $token;
            @mkdir($dir, 0700, true);
            $meta = [];
            foreach ((array) ($_FILES['files']['tmp_name'] ?? []) as $i => $tmp) {
                if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                    $stored = 'f' . count($meta) . '.csv';
                    if (move_uploaded_file($tmp, $dir . '/' . $stored)) {
                        $meta[$stored] = (string) $_FILES['files']['name'][$i];
                    }
                }
            }
            file_put_contents($dir . '/meta.json', json_encode($meta));
            $importer = new Batons\Importer($uid);
            if ($meta === []) {
                $importer->report['errors'][] = 'Aucun fichier reçu : choisis au moins un fichier CSV.';
            } else {
                $importer->importFiles($loadFiles($dir), true);
            }
            Http::render('import', ['step' => 'check', 'source' => $source, 'report' => $importer->report, 'token' => $token], 'Importer');
        }
        if ($route === 'import' && Http::isPost()) {
            $token = preg_replace('/[^0-9a-f]/', '', (string) Http::input('token'));
            $dir = $importDir . '/' . $uid . '-' . $token;
            $importer = new Batons\Importer($uid);
            $files = $token !== '' && is_dir($dir) ? $loadFiles($dir) : [];
            if ($files === []) {
                $importer->report['errors'][] = 'Les fichiers à importer ont expiré : recommence l\'import.';
            } else {
                $importer->importFiles($files);
            }
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
            Http::render('import', ['step' => 'done', 'source' => $source, 'report' => $importer->report], 'Importer');
        }
        Http::render('import', ['step' => $source ? 'files' : 'source', 'source' => $source, 'report' => null], 'Importer');

    // --- Recherche d'icônes (JSON, pour le sélecteur du formulaire) ----------------
    case 'icons':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');
        echo json_encode(Batons\Icons::search((string) ($_GET['q'] ?? '')), JSON_UNESCAPED_SLASHES);
        exit;

    // --- Export CSV ----------------------------------------------------------------
    case 'export':
        // Page de choix d'abord ; le fichier n'est produit qu'avec « download ».
        $all = Categories::listWithSummary($uid);
        $only = null;
        if (($_GET['what'] ?? 'all') === 'one') {
            $only = Categories::find($uid, Http::intInput('cat'));
        }
        if (!isset($_GET['download'])) {
            Http::render('export', ['categories' => array_merge($all, Categories::listWithSummary($uid, true)),
                'hiddenLocked' => !Privacy::isUnlocked() && Privacy::hiddenCategoryNames($uid) !== []], 'Exporter');
        }
        $slug = $only ? '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $only['name']) ?: 'categorie')), '-') : '';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="batons' . $slug . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['date_locale', 'date_utc', 'categorie', 'groupe', 'valeur', 'unite', 'note',
            'type', 'oeuvre', 'oeuvre_detail', 'annee', 'source', 'source_id', 'saison', 'episode', 'tome', 'note_sur_10', 'etape'], ';');
        $stmt = Events::allForExport($uid, $only ? (int) $only['id'] : null);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [Time::local($r['occurred_at'])->format('Y-m-d H:i'), $r['occurred_at'], $r['category'],
                $r['grp'], $r['value'], $r['unit'], $r['note'], $r['kind'], $r['work'], $r['work_subtitle'], $r['work_year'],
                $r['work_source'], $r['work_source_id'], $r['season'], $r['episode'], $r['volume'], $r['rating'],
                ['start' => 'debut', 'finish' => 'fin'][$r['milestone'] ?? ''] ?? ''], ';');
        }
        exit;

    default:
        Http::notFound();
}
