<?php
declare(strict_types=1);

namespace Batons;

/**
 * Authentification enfichable. BATONS_AUTH contient un ou plusieurs modes, séparés par des virgules :
 *
 *  - none    : un seul utilisateur local, sans connexion (usage perso sur un réseau de confiance).
 *  - header  : identité fournie par un reverse proxy dans un en-tête HTTP (Ingress de Home Assistant,
 *              Authelia, Authentik, forward-auth…). L'en-tête n'est accepté que des IP de
 *              BATONS_TRUSTED_PROXIES : ailleurs il est ignoré.
 *  - builtin : comptes et mots de passe gérés par Bâtons (page de connexion).
 *
 * Combinaison typique : « header,builtin ». Les requêtes passant par le proxy de confiance utilisent son
 * identité ; les autres (accès direct par un port, autre reverse proxy) passent par la page de connexion.
 * Un même utilisateur retrouve ses données des deux côtés : l'identifiant est le même (users.username),
 * il suffit de lui définir un mot de passe depuis la page « Compte ».
 */
final class Auth
{
    public const MAX_FAILURES = 5;
    public const BLOCK_MINUTES = 15;
    public const IP_MAX_FAILURES = 20;   // par IP et par quart d'heure, tous identifiants confondus

    private static ?array $user = null;
    private static ?string $method = null;

    /** Routes accessibles sans être connecté. */
    public const PUBLIC_ROUTES = ['login', 'setup', 'health'];

    public static function modes(): array
    {
        $modes = array_filter(array_map('trim', explode(',', strtolower((string) Config::get('auth')))));
        foreach ($modes as $m) {
            if (!in_array($m, ['none', 'header', 'builtin'], true)) {
                self::deny(500, "Mode d'authentification inconnu : « $m ».");
            }
        }
        return $modes ?: ['none'];
    }

    public static function has(string $mode): bool
    {
        return in_array($mode, self::modes(), true);
    }

    /** Comment l'utilisateur courant est authentifié : none | header | builtin (null si personne). */
    public static function method(): ?string
    {
        self::resolve();
        return self::$method;
    }

    /** Utilisateur courant ; s'il n'y en a pas, redirige vers la connexion (ou refuse). */
    public static function user(): array
    {
        self::resolve();
        if (self::$user !== null) {
            return self::$user;
        }
        if (self::has('builtin')) {
            Http::redirect(self::needsSetup() ? 'setup' : 'login');
        }
        self::deny(401, "Identité absente : l'en-tête « " . Config::get('auth_header') . " » n'a pas été transmis par un proxy de confiance.");
    }

    public static function id(): int
    {
        return (int) self::user()['id'];
    }

    /** Identifie la requête sans rediriger. */
    private static function resolve(): void
    {
        if (self::$method !== null || self::$user !== null) {
            return;
        }
        // 1. Proxy de confiance avec en-tête d'identité.
        if (self::has('header') && self::fromTrustedProxy()) {
            $username = self::header((string) Config::get('auth_header'));
            if ($username !== null && $username !== '') {
                $nameHeader = (string) Config::get('auth_name_header');
                self::$user = self::findOrCreate($username, $nameHeader !== '' ? self::header($nameHeader) : null);
                self::$method = 'header';
                return;
            }
        }
        // 2. Session ouverte par la page de connexion.
        if (self::has('builtin') && isset($_SESSION['auth_user'])) {
            $u = Db::one('SELECT * FROM users WHERE id = ? AND password_hash IS NOT NULL', [(int) $_SESSION['auth_user']]);
            if ($u !== null && hash_equals((string) ($_SESSION['auth_pw'] ?? ''), self::passwordStamp($u))) {
                self::$user = $u;
                self::$method = 'builtin';
                return;
            }
            unset($_SESSION['auth_user'], $_SESSION['auth_pw']);
        }
        // 3. Utilisateur unique sans connexion.
        if (self::has('none') && !self::has('builtin')) {
            self::$user = self::findOrCreate((string) Config::get('default_user'), null);
            self::$method = 'none';
        }
    }

    private static function findOrCreate(string $username, ?string $display): array
    {
        $user = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($user === null) {
            Db::run('INSERT INTO users (username, display_name, created_at) VALUES (?, ?, ?)',
                [$username, $display, gmdate('Y-m-d H:i:s')]);
            return Db::one('SELECT * FROM users WHERE id = ?', [Db::lastId()]);
        }
        if ($display !== null && $display !== $user['display_name']) {
            Db::run('UPDATE users SET display_name = ? WHERE id = ?', [$display, $user['id']]);
            $user['display_name'] = $display;
        }
        return $user;
    }

    // --- Mode builtin : connexion -------------------------------------------------------

    /** Mode builtin seul et aucun compte avec mot de passe : il faut créer le premier compte. */
    public static function needsSetup(): bool
    {
        return self::has('builtin') && !self::has('header')
            && !Db::value('SELECT 1 FROM users WHERE password_hash IS NOT NULL');
    }

    /** Tente une connexion ; renvoie true ou un message d'erreur (volontairement vague). */
    public static function attempt(string $username, string $password): true|string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $since = gmdate('Y-m-d H:i:s', time() - self::BLOCK_MINUTES * 60);
        Db::run('DELETE FROM login_failures WHERE at < ?', [$since]);
        if ((int) Db::value('SELECT COUNT(*) FROM login_failures WHERE ip = ?', [$ip]) >= self::IP_MAX_FAILURES) {
            return 'Trop de tentatives depuis cette adresse. Réessaie dans ' . self::BLOCK_MINUTES . ' min.';
        }
        $u = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($u !== null && $u['password_blocked_until'] && strtotime($u['password_blocked_until'] . ' UTC') > time()) {
            return 'Compte temporairement bloqué après plusieurs échecs. Réessaie plus tard.';
        }
        $ok = $u !== null && $u['password_hash'] !== null && password_verify($password, $u['password_hash']);
        if (!$ok) {
            if ($u === null || $u['password_hash'] === null) {
                password_verify($password, '$2y$10$kgsRVSz.wnCcQIyHoOtm6OpBpQFYjjFY/Yu0PmEEv95FrrnENaQVS'); // même durée qu'un vrai essai
            }
            Db::run('INSERT INTO login_failures (ip, at) VALUES (?, ?)', [$ip, Time::nowUtc()]);
            if ($u !== null) {
                $f = (int) $u['password_failures'] + 1;
                Db::run('UPDATE users SET password_failures = ?, password_blocked_until = ? WHERE id = ?', [
                    $f >= self::MAX_FAILURES ? 0 : $f,
                    $f >= self::MAX_FAILURES ? gmdate('Y-m-d H:i:s', time() + self::BLOCK_MINUTES * 60) : null,
                    $u['id'],
                ]);
            }
            return 'Identifiant ou mot de passe incorrect.';
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            self::setPassword((int) $u['id'], $password);
            $u = Db::one('SELECT * FROM users WHERE id = ?', [$u['id']]);
        }
        Db::run('UPDATE users SET password_failures = 0, password_blocked_until = NULL WHERE id = ?', [$u['id']]);
        session_regenerate_id(true);
        $_SESSION['auth_user'] = (int) $u['id'];
        $_SESSION['auth_pw'] = self::passwordStamp($u);
        unset($_SESSION['unlocked']); // les éléments masqués restent verrouillés à la connexion
        self::$user = $u;
        self::$method = 'builtin';
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
        self::$method = null;
    }

    public static function setPassword(int $userId, string $password): void
    {
        Db::run('UPDATE users SET password_hash = ?, password_failures = 0, password_blocked_until = NULL WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $userId]);
        // Garde la session courante valide après un changement de son propre mot de passe.
        if (($_SESSION['auth_user'] ?? null) === $userId) {
            $_SESSION['auth_pw'] = self::passwordStamp(Db::one('SELECT * FROM users WHERE id = ?', [$userId]));
        }
    }

    /** Empreinte du mot de passe courant : changer le mot de passe déconnecte les autres sessions. */
    private static function passwordStamp(array $u): string
    {
        return hash('sha256', (string) $u['password_hash'] . '|' . $u['id']);
    }

    public static function validatePassword(string $pw, string $confirm): ?string
    {
        if (mb_strlen($pw) < 10) {
            return 'Au moins 10 caractères.';
        }
        if ($pw !== $confirm) {
            return 'Les deux saisies ne correspondent pas.';
        }
        return null;
    }

    public static function validUsername(string $u): bool
    {
        return (bool) preg_match('/^[\p{L}\p{N}._@-]{2,60}$/u', $u);
    }

    // --- Outils --------------------------------------------------------------------------

    private static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $_SERVER[$key] ?? null;
        return $v === null ? null : trim((string) $v);
    }

    /** La requête vient-elle d'un proxy de confiance ? (liste vide = tous, seulement si header est le seul mode) */
    private static function fromTrustedProxy(): bool
    {
        $list = array_filter(array_map('trim', explode(',', (string) Config::get('trusted_proxies'))));
        if ($list === []) {
            return self::modes() === ['header'];
        }
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', $list, true);
    }

    private static function deny(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
