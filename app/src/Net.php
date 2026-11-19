<?php
declare(strict_types=1);

namespace Batons;

/**
 * Petit client HTTP pour les catalogues (films, séries, livres), avec cache en base.
 *
 * Pour les tests hors ligne : BATONS_HTTP_FIXTURES=/chemin/fixtures.php, un fichier qui renvoie
 * un tableau [motif regex => réponse JSON (chaîne)] ; aucune requête réseau n'est alors faite.
 */
final class Net
{
    private static ?array $fixtures = null;

    /** GET JSON ; null en cas d'échec (réseau, statut, JSON invalide). $ttl en secondes (0 = pas de cache). */
    public static function json(string $url, array $headers = [], int $ttl = 86400): ?array
    {
        $body = self::get($url, $headers, $ttl);
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /** GET brut (images) ; null en cas d'échec. Pas de cache en base. */
    public static function binary(string $url, int $maxBytes = 2_000_000): ?string
    {
        if (self::fixtureMode()) {
            return null;
        }
        $body = self::fetch($url, [], 8);
        return ($body !== null && strlen($body) <= $maxBytes) ? $body : null;
    }

    private static function get(string $url, array $headers, int $ttl): ?string
    {
        if (self::fixtureMode()) {
            foreach (self::$fixtures as $pattern => $response) {
                if (preg_match($pattern, $url)) {
                    return $response;
                }
            }
            return null;
        }
        // La clé de cache ignore les secrets (clé d'API dans l'URL, en-têtes).
        $key = hash('sha256', preg_replace('/([?&])api_key=[^&]*/', '$1', $url));
        if ($ttl > 0) {
            $row = Db::one('SELECT body, fetched_at FROM http_cache WHERE key = ?', [$key]);
            if ($row !== null && (int) $row['fetched_at'] > time() - $ttl) {
                return $row['body'];
            }
        }
        $body = self::fetch($url, $headers, 6);
        if ($body !== null && $ttl > 0) {
            Db::run('DELETE FROM http_cache WHERE key = ? OR fetched_at < ?', [$key, time() - 30 * 86400]);
            Db::run('INSERT INTO http_cache (key, body, fetched_at) VALUES (?, ?, ?)', [$key, $body, time()]);
        }
        return $body;
    }

    private static function fetch(string $url, array $headers, int $timeout): ?string
    {
        $headers[] = 'User-Agent: Batons/' . VERSION . ' (application auto-hebergee)';
        $headers[] = 'Accept: application/json, image/*';
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 3,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1]; // le dernier statut (après redirections)
            }
        }
        return ($status >= 200 && $status < 300) ? $body : null;
    }

    private static function fixtureMode(): bool
    {
        $file = getenv('BATONS_HTTP_FIXTURES');
        if (!$file) {
            return false;
        }
        self::$fixtures ??= is_file($file) ? (require $file) : [];
        return true;
    }
}
