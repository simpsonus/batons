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
        $id = Events::add((int) $c['id']);
        Http::flash('Bâton ajouté à « ' . $c['name'] . ' ».', $id);
        Http::back();

    case 'undo':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton annulé.');
        Http::back();

    // --- Fiche catégorie ----------------------------------------------------------
    case 'category':
        $c = $category(Http::intInput('id'));
        $scale = in_array($_GET['scale'] ?? '', ['day', 'week', 'month'], true) ? $_GET['scale'] : 'day';
        $stats = Events::stats((int) $c['id']);
        $metric = ($_GET['metric'] ?? '') === 'sum' || (($_GET['metric'] ?? '') === '' && (int) $stats['with_value'] > 0) ? 'sum' : 'count';
        if ((int) $stats['with_value'] === 0) {
            $metric = 'count';
        }
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('category', [
            'c'      => $c,
            'stats'  => $stats,
            'series' => Events::series((int) $c['id'], $scale),
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
        $id = Events::add((int) $c['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        Http::flash('Bâton ajouté.', $id);
        Http::redirect('category', ['id' => $c['id']]);

    case 'event_edit':
        $ev = $event(Http::intInput('id'));
        Http::render('event_form', ['ev' => $ev], 'Modifier le bâton');

    case 'event_save':
        $ev = $event(Http::intInput('id'));
        $at = Time::fromLocalInput(Http::input('occurred_at')) ?? $ev['occurred_at'];
        Events::update((int) $ev['id'], $at, $parseValue(Http::input('value')), Http::input('note'));
        if ($ev['work_id']) {
            Events::updateWork((int) $ev['id'], $posInt(Http::input('season')), $posInt(Http::input('episode')),
                $posInt(Http::input('volume')), $rating(Http::input('rating')));
        }
        Http::flash('Bâton modifié.');
        Http::redirect('category', ['id' => $ev['category_id']]);

    case 'event_delete':
        $ev = $event(Http::intInput('id'));
        Events::delete((int) $ev['id']);
        Http::flash('Bâton supprimé.');
        Http::back('category', ['id' => $ev['category_id']]);

    // --- Catégories : création, édition, archivage, suppression, ordre -------------
    case 'category_new':
    case 'category_edit':
        $c = $route === 'category_edit' ? $category(Http::intInput('id'))
            : ['id' => null, 'name' => '', 'icon' => '', 'color' => '#c2410c', 'unit' => '', 'group_name' => '', 'archived' => 0, 'hidden' => 0, 'kind' => in_array($_GET['kind'] ?? '', ['film', 'serie', 'livre'], true) ? $_GET['kind'] : 'simple'];
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
                  'unit' => $data['unit'], 'group_name' => $data['group'], 'archived' => 0, 'hidden' => $data['hidden'] ? 1 : 0, 'kind' => $data['kind']];
            Http::render('category_form', ['c' => $c, 'errors' => $errors, 'groups' => Categories::groupNames($uid)], 'Catégorie');
        }
        if ($id !== null) {
            Categories::update($uid, $id, $data);
            if ($data['hidden'] && !Privacy::isUnlocked()) {
                Http::flash('Catégorie « ' . $data['name'] . ' » masquée.');
                Http::redirect();
            }
            Http::flash('Catégorie enregistrée.');
            Http::redirect('category', ['id' => $id]);
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
            $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
            if ($next !== null) {
                $continue[] = ['work' => $w, 'next' => $next];
            }
            if (count($continue) >= 8) {
                break;
            }
        }
        Http::render('work_add', ['c' => $c, 'q' => $q, 'results' => $results, 'continue' => $continue,
            'sources' => Batons\Catalog::sources($c['kind'], $uid)], Batons\Catalog::KINDS[$c['kind']] . ' : ajouter');

    case 'work_pick':
        $c = $category(Http::intInput('id'));
        $source = Http::input('source');
        $sid = Http::input('sid');
        if (!in_array($source, ['tmdb', 'tvmaze', 'gbooks', 'openlibrary'], true) || $sid === '') {
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
                $rating(Http::input('rating')), Http::input('note'));
            $ev = Events::find($uid, $evId);
            $label = trim($w['title'] . ' ' . Batons\Works::progress($ev['season'], $ev['episode'], $ev['volume']));
            Http::flash('« ' . $label . ' » ajouté.', $evId);
            Http::redirect('category', ['id' => $c['id']]);
        }
        Http::render('work_log', ['c' => $c, 'w' => $w, 'last' => $last,
            'next' => Batons\Works::next($uid, $w, $last), 'seasons' => Batons\Works::seasons($w)], $w['title']);

    case 'work_next':
        $c = $category(Http::intInput('id'));
        $w = Batons\Works::find($uid, (int) Http::intInput('work')) ?? Http::notFound('Œuvre');
        $next = Batons\Works::next($uid, $w, Batons\Works::lastEvent((int) $w['id'], (int) $c['id']));
        if ($next === null) {
            Http::redirect('work_log', ['id' => $c['id'], 'work' => $w['id']]);
        }
        $evId = Events::addWork((int) $c['id'], Time::nowUtc(), (int) $w['id'],
            $next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null, null, null);
        Http::flash('« ' . $w['title'] . ' ' . Batons\Works::progress($next['season'] ?? null, $next['episode'] ?? null, $next['volume'] ?? null) . ' » ajouté.', $evId);
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

    case 'category_order':
        Http::render('category_order', [
            'categories' => Categories::listWithSummary($uid),
            'groups'     => Categories::groups($uid),
        ], 'Réorganiser');

    case 'archives':
        Http::render('archives', ['categories' => Categories::listWithSummary($uid, true)], 'Archives');

    // --- Chronologie ---------------------------------------------------------------
    case 'timeline':
        $page = max(0, (int) ($_GET['page'] ?? 0));
        Http::render('timeline', ['events' => Events::timeline($uid, 101, $page * 100), 'page' => $page], 'Chronologie');

    // --- Import (Chronicling, ou export CSV de Bâtons) -----------------------------
    case 'import':
        if (!Http::isPost()) {
            Http::render('import', ['report' => null], 'Importer');
        }
        $files = [];
        foreach ((array) ($_FILES['files']['tmp_name'] ?? []) as $i => $tmp) {
            if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                $files[$tmp] = (string) $_FILES['files']['name'][$i];
            }
        }
        $importer = new Batons\Importer($uid);
        if ($files === []) {
            $importer->report['errors'][] = 'Aucun fichier reçu.';
        } else {
            $importer->importFiles($files);
        }
        Http::render('import', ['report' => $importer->report], 'Importer');

    // --- Recherche d'icônes (JSON, pour le sélecteur du formulaire) ----------------
    case 'icons':
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');
        echo json_encode(Batons\Icons::search((string) ($_GET['q'] ?? '')), JSON_UNESCAPED_SLASHES);
        exit;

    // --- Export CSV ----------------------------------------------------------------
    case 'export':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="batons-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        fputcsv($out, ['date_locale', 'date_utc', 'categorie', 'groupe', 'valeur', 'unite', 'note',
            'type', 'oeuvre', 'oeuvre_detail', 'annee', 'source', 'source_id', 'saison', 'episode', 'tome', 'note_sur_10'], ';');
        $stmt = Events::allForExport($uid);
        while ($r = $stmt->fetch()) {
            fputcsv($out, [Time::local($r['occurred_at'])->format('Y-m-d H:i'), $r['occurred_at'], $r['category'],
                $r['grp'], $r['value'], $r['unit'], $r['note'], $r['kind'], $r['work'], $r['work_subtitle'], $r['work_year'],
                $r['work_source'], $r['work_source_id'], $r['season'], $r['episode'], $r['volume'], $r['rating']], ';');
        }
        exit;

    default:
        Http::notFound();
}
