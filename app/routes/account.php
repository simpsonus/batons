<?php
declare(strict_types=1);

/*
 * Routes — Compte, comptes utilisateurs ; Apparence ; pages du Menu.
 * Inclus par public/index.php, dans sa portée : $route, $uid et les aides ($category, $event, $parseValue…)
 * y sont disponibles. Chaque route se termine par un rendu, une redirection ou exit.
 */

use Batons\Auth;
use Batons\Categories;
use Batons\Events;
use Batons\Http;
use Batons\Privacy;
use Batons\Time;
use Batons\WebAuthn;

switch ($route) {
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


    case 'appearance':
        if (Http::isPost()) {
            Batons\Theme::save($uid, Http::input('tile_style'), Http::input('tile_grad'), Http::input('tile_shape'), Http::input('theme'));
            Batons\Settings::set($uid, 'flash_auto', Http::input('flash_auto') === '0' ? '0' : '1');
            Http::flash('Apparence enregistrée.');
            Http::back('appearance');
        }
        Http::render('appearance', [
            'theme' => Batons\Theme::current($uid),
            'flashAuto' => Batons\Settings::get($uid, 'flash_auto', '1') === '1',
            'previewCats' => array_slice(Categories::listWithSummary($uid), 0, 4),
        ], 'Apparence');

    case 'menu':
        Http::render('menu', [], 'Menu');

    case 'archives':
        Http::render('archives', ['categories' => Categories::listWithSummary($uid, true)], 'Archives');
}
