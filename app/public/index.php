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

// En-têtes de sécurité. Pas de « frame-ancestors » : Bâtons doit pouvoir s'afficher dans le cadre d'un
// tableau de bord (Home Assistant Ingress…). style-src 'unsafe-inline' : couleurs des catégories en attribut style.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
    . "script-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Sauvegarde du jour, après l'envoi de la page.
register_shutdown_function(static function (): void {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    Batons\Backup::maybeRun();
});

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


// Routes, rangées par domaine dans app/routes/*.php. Une route non trouvée finit en 404.
foreach (['categories', 'events', 'works', 'account', 'privacy', 'data'] as $routeFile) {
    require dirname(__DIR__) . '/routes/' . $routeFile . '.php';
}
Http::notFound();
