<?php
declare(strict_types=1);

namespace Batons;

/**
 * Authentification enfichable.
 *
 *  - none   : un seul utilisateur local, pas de login (usage perso, réseau de confiance).
 *  - header : l'identité est fournie par un reverse proxy dans un en-tête HTTP
 *             (Home Assistant Ingress, Authelia, Authentik, Traefik forward-auth, Caddy…).
 *  - builtin: comptes et mots de passe gérés par Bâtons — à venir.
 */
final class Auth
{
    private static ?array $user = null;

    /** Renvoie l'utilisateur courant (créé à la volée si besoin) ou stoppe avec 401/403. */
    public static function user(): array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $mode = (string) Config::get('auth');
        switch ($mode) {
            case 'none':
                $username = (string) Config::get('default_user');
                $display = null;
                break;

            case 'header':
                self::checkTrustedProxy();
                $username = self::header((string) Config::get('auth_header'));
                $nameHeader = (string) Config::get('auth_name_header');
                $display = $nameHeader !== '' ? self::header($nameHeader) : null;
                if ($username === null || $username === '') {
                    self::deny(401, "Identité absente : l'en-tête « " . Config::get('auth_header') . " » n'a pas été transmis par le proxy.");
                }
                break;

            default:
                self::deny(500, "Mode d'authentification inconnu : « $mode ».");
        }

        $user = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        if ($user === null) {
            Db::run('INSERT INTO users (username, display_name, created_at) VALUES (?, ?, ?)',
                [$username, $display, gmdate('Y-m-d H:i:s')]);
            $user = Db::one('SELECT * FROM users WHERE id = ?', [Db::lastId()]);
        } elseif ($display !== null && $display !== $user['display_name']) {
            Db::run('UPDATE users SET display_name = ? WHERE id = ?', [$display, $user['id']]);
            $user['display_name'] = $display;
        }

        return self::$user = $user;
    }

    public static function id(): int
    {
        return (int) self::user()['id'];
    }

    private static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $_SERVER[$key] ?? null;
        return $v === null ? null : trim((string) $v);
    }

    private static function checkTrustedProxy(): void
    {
        $list = array_filter(array_map('trim', explode(',', (string) Config::get('trusted_proxies'))));
        if ($list === []) {
            return;
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($remote, $list, true)) {
            self::deny(403, 'Requête refusée : elle ne provient pas d\'un proxy de confiance.');
        }
    }

    private static function deny(int $code, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
