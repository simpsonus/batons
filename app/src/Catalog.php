<?php
declare(strict_types=1);

namespace Batons;

/**
 * Catalogues d'œuvres, interrogés côté serveur :
 *  - films  : TMDB (clé requise) ;
 *  - séries : TMDB (clé) sinon TVmaze (sans clé, titres surtout anglais) ;
 *  - livres : Google Books, sinon Open Library.
 *
 * Chaque résultat : ['source', 'source_id', 'title', 'subtitle', 'year', 'poster' (URL ou null)].
 * Les détails d'une série ajoutent 'seasons' => [numéro => nombre d'épisodes] (hors saison 0).
 */
final class Catalog
{
    public const KINDS = ['film' => 'Films', 'serie' => 'Séries', 'livre' => 'Livres', 'jeu' => 'Jeux vidéo'];

    private const TMDB = 'https://api.themoviedb.org/3';
    private const TMDB_IMG = 'https://image.tmdb.org/t/p/w185';

    /** Sources utilisées pour ce type, dans l'ordre (pour l'affichage des crédits). */
    public static function sources(string $kind, int $userId): array
    {
        $tmdb = Settings::tmdbKey($userId) !== '';
        return match ($kind) {
            'film'  => $tmdb ? ['TMDB'] : [],
            'serie' => $tmdb ? ['TMDB'] : ['TVmaze'],
            'livre' => ['Google Books', 'Open Library'],
            'jeu'   => Settings::rawgKey($userId) !== '' ? ['RAWG'] : [],
            default => [],
        };
    }

    public static function search(string $kind, string $q, int $userId): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $key = Settings::tmdbKey($userId);
        return match ($kind) {
            'film'  => $key !== '' ? self::tmdbSearch('movie', $q, $key) : [],
            'serie' => $key !== '' ? self::tmdbSearch('tv', $q, $key) : self::tvmazeSearch($q),
            'livre' => self::googleBooksSearch($q) ?: self::openLibrarySearch($q),
            'jeu'   => ($rk = Settings::rawgKey($userId)) !== '' ? self::rawgSearch($q, $rk) : [],
            default => [],
        };
    }

    /** Détails d'une œuvre (null si introuvable ou catalogue injoignable). */
    public static function details(string $source, string $id, string $kind, int $userId): ?array
    {
        $key = Settings::tmdbKey($userId);
        return match ($source) {
            'tmdb'        => $key !== '' ? self::tmdbDetails($kind === 'serie' ? 'tv' : 'movie', $id, $key) : null,
            'tvmaze'      => self::tvmazeDetails($id),
            'gbooks'      => self::googleBooksDetails($id),
            'openlibrary' => self::openLibraryDetails($id),
            'rawg'        => ($rk = Settings::rawgKey($userId)) !== '' ? self::rawgDetails($id, $rk) : null,
            default       => null,
        };
    }

    // --- TMDB ----------------------------------------------------------------------------

    /** Clé v3 (32 caractères) en paramètre, ou jeton v4 (« eyJ… ») en en-tête Bearer. */
    private static function tmdbGet(string $path, array $params, string $key, int $ttl = 86400): ?array
    {
        $headers = [];
        if (strlen($key) > 40) {
            $headers[] = 'Authorization: Bearer ' . $key;
        } else {
            $params['api_key'] = $key;
        }
        $params['language'] = 'fr-FR';
        return Net::json(self::TMDB . $path . '?' . http_build_query($params), $headers, $ttl);
    }

    private static function tmdbSearch(string $type, string $q, string $key): array
    {
        $data = self::tmdbGet("/search/$type", ['query' => $q, 'include_adult' => 'false'], $key, 3600);
        $out = [];
        foreach (array_slice($data['results'] ?? [], 0, 12) as $r) {
            $out[] = self::tmdbItem($type, $r);
        }
        return $out;
    }

    private static function tmdbDetails(string $type, string $id, string $key): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        $r = self::tmdbGet("/$type/$id", [], $key, 7 * 86400);
        if (!$r || !isset($r['id'])) {
            return null;
        }
        $item = self::tmdbItem($type, $r);
        if ($type === 'tv') {
            $seasons = [];
            foreach ($r['seasons'] ?? [] as $s) {
                if ((int) ($s['season_number'] ?? 0) > 0 && (int) ($s['episode_count'] ?? 0) > 0) {
                    $seasons[(int) $s['season_number']] = (int) $s['episode_count'];
                }
            }
            ksort($seasons);
            $item['seasons'] = $seasons;
        }
        return $item;
    }

    private static function tmdbItem(string $type, array $r): array
    {
        $title = $type === 'tv' ? ($r['name'] ?? '') : ($r['title'] ?? '');
        $orig = $type === 'tv' ? ($r['original_name'] ?? '') : ($r['original_title'] ?? '');
        $date = $type === 'tv' ? ($r['first_air_date'] ?? '') : ($r['release_date'] ?? '');
        return [
            'source' => 'tmdb', 'source_id' => (string) $r['id'],
            'title' => $title,
            'subtitle' => ($orig !== '' && $orig !== $title) ? $orig : null,
            'year' => preg_match('/^\d{4}/', (string) $date, $m) ? (int) $m[0] : null,
            'poster' => !empty($r['poster_path']) ? self::TMDB_IMG . $r['poster_path'] : null,
        ];
    }

    // --- TVmaze (séries, sans clé) ------------------------------------------------------------

    private static function tvmazeSearch(string $q): array
    {
        $data = Net::json('https://api.tvmaze.com/search/shows?' . http_build_query(['q' => $q]), [], 3600) ?? [];
        $out = [];
        foreach (array_slice($data, 0, 12) as $r) {
            if (isset($r['show']['id'])) {
                $out[] = self::tvmazeItem($r['show']);
            }
        }
        return $out;
    }

    private static function tvmazeDetails(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        $show = Net::json("https://api.tvmaze.com/shows/$id", [], 7 * 86400);
        if (!$show || !isset($show['id'])) {
            return null;
        }
        $item = self::tvmazeItem($show);
        $seasons = [];
        foreach (Net::json("https://api.tvmaze.com/shows/$id/episodes", [], 86400) ?? [] as $ep) {
            $s = (int) ($ep['season'] ?? 0);
            if ($s > 0 && isset($ep['number'])) {
                $seasons[$s] = max($seasons[$s] ?? 0, (int) $ep['number']);
            }
        }
        ksort($seasons);
        $item['seasons'] = $seasons;
        return $item;
    }

    private static function tvmazeItem(array $s): array
    {
        return [
            'source' => 'tvmaze', 'source_id' => (string) $s['id'],
            'title' => (string) ($s['name'] ?? ''),
            'subtitle' => null,
            'year' => preg_match('/^\d{4}/', (string) ($s['premiered'] ?? ''), $m) ? (int) $m[0] : null,
            'poster' => $s['image']['medium'] ?? null,
        ];
    }

    // --- Jeux vidéo : RAWG (clé gratuite ; lien vers RAWG obligatoire là où ses données s'affichent) ---

    private static function rawgSearch(string $q, string $key): array
    {
        $data = Net::json('https://api.rawg.io/api/games?' . http_build_query([
            'search' => $q, 'page_size' => 12, 'key' => $key,
        ]), [], 3600);
        $out = [];
        foreach ($data['results'] ?? [] as $r) {
            if (isset($r['id'])) {
                $out[] = self::rawgItem($r);
            }
        }
        return $out;
    }

    private static function rawgDetails(string $id, string $key): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        $r = Net::json("https://api.rawg.io/api/games/$id?" . http_build_query(['key' => $key]), [], 7 * 86400);
        return ($r && isset($r['id'])) ? self::rawgItem($r) : null;
    }

    private static function rawgItem(array $r): array
    {
        $platforms = array_filter(array_map(static fn($p) => $p['platform']['name'] ?? null, array_slice($r['platforms'] ?? [], 0, 3)));
        return [
            'source' => 'rawg', 'source_id' => (string) $r['id'],
            'title' => (string) ($r['name'] ?? ''),
            'subtitle' => $platforms ? implode(', ', $platforms) : null,
            'year' => preg_match('/^\d{4}/', (string) ($r['released'] ?? ''), $m) ? (int) $m[0] : null,
            'poster' => $r['background_image'] ?? null,
        ];
    }

    // --- Livres ---------------------------------------------------------------------------------

    private static function googleBooksSearch(string $q): array
    {
        $data = Net::json('https://www.googleapis.com/books/v1/volumes?' . http_build_query([
            'q' => $q, 'maxResults' => 12, 'printType' => 'books', 'langRestrict' => 'fr',
        ]), [], 3600);
        $out = [];
        foreach ($data['items'] ?? [] as $r) {
            $out[] = self::googleBooksItem($r);
        }
        return $out;
    }

    private static function googleBooksDetails(string $id): ?array
    {
        if (!preg_match('/^[\w-]{4,40}$/', $id)) {
            return null;
        }
        $r = Net::json('https://www.googleapis.com/books/v1/volumes/' . $id, [], 7 * 86400);
        return ($r && isset($r['id'])) ? self::googleBooksItem($r) : null;
    }

    private static function googleBooksItem(array $r): array
    {
        $v = $r['volumeInfo'] ?? [];
        $poster = $v['imageLinks']['thumbnail'] ?? $v['imageLinks']['smallThumbnail'] ?? null;
        return [
            'source' => 'gbooks', 'source_id' => (string) $r['id'],
            'title' => trim(($v['title'] ?? '') . (isset($v['subtitle']) ? ' — ' . $v['subtitle'] : '')),
            'subtitle' => isset($v['authors']) ? implode(', ', array_slice($v['authors'], 0, 3)) : null,
            'year' => preg_match('/^\d{4}/', (string) ($v['publishedDate'] ?? ''), $m) ? (int) $m[0] : null,
            'poster' => $poster ? preg_replace('/^http:/', 'https:', $poster) : null,
        ];
    }

    private static function openLibrarySearch(string $q): array
    {
        $data = Net::json('https://openlibrary.org/search.json?' . http_build_query([
            'q' => $q, 'limit' => 12, 'fields' => 'key,title,author_name,first_publish_year,cover_i',
        ]), [], 3600);
        $out = [];
        foreach ($data['docs'] ?? [] as $r) {
            $out[] = self::openLibraryItem($r);
        }
        return $out;
    }

    private static function openLibraryDetails(string $id): ?array
    {
        if (!preg_match('/^OL\d+W$/', $id)) {
            return null;
        }
        $r = Net::json("https://openlibrary.org/works/$id.json", [], 7 * 86400);
        if (!$r || empty($r['title'])) {
            return null;
        }
        return [
            'source' => 'openlibrary', 'source_id' => $id, 'title' => (string) $r['title'], 'subtitle' => null,
            'year' => null,
            'poster' => !empty($r['covers'][0]) ? 'https://covers.openlibrary.org/b/id/' . (int) $r['covers'][0] . '-M.jpg' : null,
        ];
    }

    private static function openLibraryItem(array $r): array
    {
        return [
            'source' => 'openlibrary', 'source_id' => basename((string) ($r['key'] ?? '')),
            'title' => (string) ($r['title'] ?? ''),
            'subtitle' => isset($r['author_name']) ? implode(', ', array_slice($r['author_name'], 0, 3)) : null,
            'year' => isset($r['first_publish_year']) ? (int) $r['first_publish_year'] : null,
            'poster' => !empty($r['cover_i']) ? 'https://covers.openlibrary.org/b/id/' . (int) $r['cover_i'] . '-M.jpg' : null,
        ];
    }
}
