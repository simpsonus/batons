<?php
declare(strict_types=1);

namespace Batons;

/** Œuvres (films, séries, livres) et progression (épisode, tome). */
final class Works
{
    /** Hôtes autorisés pour le téléchargement des affiches (évite de suivre n'importe quelle URL). */
    private const POSTER_HOSTS = ['image.tmdb.org', 'static.tvmaze.com', 'books.google.com',
        'books.googleusercontent.com', 'covers.openlibrary.org', 'media.rawg.io'];

    public static function find(int $userId, int $id): ?array
    {
        return Db::one('SELECT * FROM works WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** Œuvre issue d'un catalogue : retrouvée si déjà connue, sinon créée (avec affiche et saisons). */
    public static function fromCatalog(int $userId, string $kind, array $item): int
    {
        $id = Db::value('SELECT id FROM works WHERE user_id = ? AND source = ? AND source_id = ?',
            [$userId, $item['source'], $item['source_id']]);
        if ($id !== null) {
            if (isset($item['seasons'])) {
                self::saveSeasons((int) $id, $item['seasons']);
            }
            return (int) $id;
        }
        Db::run(
            'INSERT INTO works (user_id, kind, source, source_id, title, subtitle, year, meta, meta_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $kind, $item['source'], $item['source_id'], mb_substr($item['title'], 0, 200),
             $item['subtitle'] ? mb_substr($item['subtitle'], 0, 200) : null, $item['year'],
             isset($item['seasons']) ? json_encode($item['seasons']) : null,
             isset($item['seasons']) ? Time::nowUtc() : null, Time::nowUtc()]
        );
        $id = Db::lastId();
        if (!empty($item['poster'])) {
            self::downloadPoster($id, $item['poster']);
        }
        return $id;
    }

    /** Œuvre saisie à la main (hors catalogue). */
    public static function manual(int $userId, string $kind, string $title, ?string $subtitle = null, ?int $year = null): int
    {
        $title = mb_substr(trim($title), 0, 200);
        $sid = hash('sha1', $kind . '|' . mb_strtolower($title) . '|' . mb_strtolower((string) $subtitle));
        $id = Db::value('SELECT id FROM works WHERE user_id = ? AND source = ? AND source_id = ?', [$userId, 'manual', $sid]);
        if ($id !== null) {
            return (int) $id;
        }
        Db::run('INSERT INTO works (user_id, kind, source, source_id, title, subtitle, year, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $kind, 'manual', $sid, $title, $subtitle ?: null, $year, Time::nowUtc()]);
        return Db::lastId();
    }

    /** Œuvres ayant au moins un bâton dans la catégorie, de la plus récemment vue à la plus ancienne. */
    public static function inCategory(int $userId, int $categoryId): array
    {
        return Db::all(
            'SELECT w.*, MAX(e.occurred_at) AS last_at, COUNT(e.id) AS n, AVG(e.rating) AS avg_rating
               FROM works w JOIN events e ON e.work_id = w.id
              WHERE e.category_id = ? AND w.user_id = ?
              GROUP BY w.id ORDER BY last_at DESC',
            [$categoryId, $userId]
        );
    }

    /** Dernier bâton (le plus récent) de l'œuvre dans la catégorie. */
    public static function lastEvent(int $workId, int $categoryId): ?array
    {
        return Db::one('SELECT * FROM events WHERE work_id = ? AND category_id = ? ORDER BY occurred_at DESC, id DESC LIMIT 1',
            [$workId, $categoryId]);
    }

    /** Saisons connues [numéro => nb d'épisodes]. */
    public static function seasons(array $work): array
    {
        $s = $work['meta'] ? json_decode($work['meta'], true) : null;
        $out = [];
        foreach (is_array($s) ? $s : [] as $n => $count) {
            $out[(int) $n] = (int) $count;
        }
        ksort($out);
        return $out;
    }

    private static function saveSeasons(int $workId, array $seasons): void
    {
        if ($seasons) {
            Db::run('UPDATE works SET meta = ?, meta_at = ? WHERE id = ?', [json_encode($seasons), Time::nowUtc(), $workId]);
        }
    }

    /**
     * Étape suivante : ['season' => s, 'episode' => e] (série), ['volume' => n] (livre), ou null (rien à proposer).
     * Pour une série arrivée au bout des épisodes connus, les saisons sont rafraîchies (au plus une fois par jour).
     */
    public static function next(int $userId, array $work, ?array $last): ?array
    {
        if ($work['kind'] === 'jeu') {
            return ['session' => true]; // une partie de plus (le jeu terminé est écarté par l'appelant)
        }
        if ($work['kind'] === 'livre') {
            // Dernier tome renseigné (le tout dernier bâton peut être un « Lu » sans tome).
            $vol = $last ? ($last['volume'] ?: Db::value(
                'SELECT volume FROM events WHERE work_id = ? AND category_id = ? AND volume IS NOT NULL
                  ORDER BY occurred_at DESC, id DESC LIMIT 1', [$last['work_id'], $last['category_id']])) : null;
            return $vol ? ['volume' => (int) $vol + 1] : null;
        }
        if ($work['kind'] !== 'serie') {
            return null;
        }
        if (!$last || !$last['season'] || !$last['episode']) {
            return ['season' => 1, 'episode' => 1];
        }
        $s = (int) $last['season'];
        $e = (int) $last['episode'];
        $seasons = self::seasons($work);
        if (!$seasons) {
            return ['season' => $s, 'episode' => $e + 1];
        }
        $step = self::stepIn($seasons, $s, $e);
        if ($step === null && $work['source'] !== 'manual'
            && (!$work['meta_at'] || strtotime($work['meta_at'] . ' UTC') < time() - 86400)) {
            $d = Catalog::details($work['source'], $work['source_id'], 'serie', $userId);
            if ($d && !empty($d['seasons'])) {
                self::saveSeasons((int) $work['id'], $d['seasons']);
                $step = self::stepIn($d['seasons'], $s, $e);
            } else {
                Db::run('UPDATE works SET meta_at = ? WHERE id = ?', [Time::nowUtc(), $work['id']]);
            }
        }
        return $step;
    }

    private static function stepIn(array $seasons, int $s, int $e): ?array
    {
        if (isset($seasons[$s]) && $e < $seasons[$s]) {
            return ['season' => $s, 'episode' => $e + 1];
        }
        foreach ($seasons as $n => $count) {
            if ($n > $s && $count > 0) {
                return ['season' => $n, 'episode' => 1];
            }
        }
        if (!isset($seasons[$s])) {
            return ['season' => $s, 'episode' => $e + 1]; // saison inconnue du catalogue : on avance quand même
        }
        return null; // à jour
    }

    /**
     * Début, fin et durée d'une œuvre dans une catégorie (jeux vidéo).
     * Début = dernier « Commencé » (sinon premier bâton) ; fin = dernier « Terminé » postérieur au début.
     * Renvoie ['state' => none|playing|done, 'start' => utc|null, 'finish' => utc|null, 'days' => int|null].
     */
    public static function status(int $workId, int $categoryId): array
    {
        $rows = Db::all('SELECT occurred_at, milestone FROM events WHERE work_id = ? AND category_id = ? ORDER BY occurred_at, id',
            [$workId, $categoryId]);
        if (!$rows) {
            return ['state' => 'none', 'start' => null, 'finish' => null, 'days' => null];
        }
        $start = null;
        $finish = null;
        foreach ($rows as $r) {
            if ($r['milestone'] === 'start') {
                $start = $r['occurred_at'];
                $finish = null; // nouvelle partie complète (on recommence le jeu)
            } elseif ($r['milestone'] === 'finish') {
                $finish = $r['occurred_at'];
            }
        }
        $start ??= $rows[0]['occurred_at'];
        if ($finish !== null && $finish < $start) {
            $finish = null;
        }
        $end = Time::local($finish ?? Time::nowUtc())->setTime(0, 0);
        $days = (int) Time::local($start)->setTime(0, 0)->diff($end)->days;
        return ['state' => $finish ? 'done' : 'playing', 'start' => $start, 'finish' => $finish, 'days' => $days];
    }

    /** Types d'œuvre suivis avec un début et une fin. */
    public const TRACKED = ['jeu', 'livre'];

    /** Libellés des étapes selon le type : [bâton ordinaire, début, fin, nom au pluriel]. */
    public static function milestoneLabels(string $kind): array
    {
        return $kind === 'livre'
            ? ['' => 'Une séance de lecture', 'start' => 'Le début du livre', 'finish' => 'La fin du livre', 'plural' => 'livres', 'done' => 'Lu']
            : ['' => 'Une partie', 'start' => 'Le début du jeu', 'finish' => 'La fin du jeu', 'plural' => 'jeux', 'done' => 'Terminé'];
    }

    /** Tome du dernier « Commencé » d'un livre (le tome en cours ou le dernier lu), ou null. */
    public static function currentVolume(int $workId, int $categoryId): ?int
    {
        $v = Db::value("SELECT volume FROM events WHERE work_id = ? AND category_id = ? AND milestone = 'start'
                         ORDER BY occurred_at DESC, id DESC LIMIT 1", [$workId, $categoryId]);
        return $v !== null ? (int) $v : null;
    }

    /**
     * Lecture tome par tome : pour chaque tome ayant un « Commencé », [volume, start, finish|null, days].
     * Du plus récent au plus ancien.
     */
    public static function volumes(int $workId, int $categoryId): array
    {
        $rows = Db::all("SELECT occurred_at, milestone, volume FROM events
                          WHERE work_id = ? AND category_id = ? AND milestone IN ('start', 'finish') AND volume IS NOT NULL
                          ORDER BY occurred_at, id", [$workId, $categoryId]);
        $byVol = [];
        foreach ($rows as $r) {
            $v = (int) $r['volume'];
            if ($r['milestone'] === 'start') {
                $byVol[$v] = ['volume' => $v, 'start' => $r['occurred_at'], 'finish' => null];
            } elseif (isset($byVol[$v]) && $byVol[$v]['finish'] === null) {
                $byVol[$v]['finish'] = $r['occurred_at'];
            }
        }
        foreach ($byVol as &$p) {
            $end = Time::local($p['finish'] ?? Time::nowUtc())->setTime(0, 0);
            $p['days'] = (int) Time::local($p['start'])->setTime(0, 0)->diff($end)->days;
        }
        unset($p);
        usort($byVol, static fn($a, $b) => [$b['start'], $b['volume']] <=> [$a['start'], $a['volume']]);
        return $byVol;
    }

    /** « 47 jours », « 1 jour », « le jour même » */
    public static function daysLabel(int $days): string
    {
        return $days === 0 ? 'le jour même' : $days . ' jour' . ($days > 1 ? 's' : '');
    }

    /** « S02E05 », « Tome 3 » ou '' */
    public static function progress(?int $season, ?int $episode, ?int $volume): string
    {
        if ($season && $episode) {
            return sprintf('S%02dE%02d', $season, $episode);
        }
        if ($season) {
            return 'Saison ' . $season;
        }
        if ($volume) {
            return 'Tome ' . $volume;
        }
        return '';
    }

    /** Libellé lisible pour les lecteurs d'écran : « saison 2, épisode 5 ». */
    public static function progressLong(?int $season, ?int $episode, ?int $volume): string
    {
        if ($season && $episode) {
            return "saison $season, épisode $episode";
        }
        if ($season) {
            return "saison $season";
        }
        return $volume ? "tome $volume" : '';
    }

    // --- Affiches (copiées localement : pas de requête vers les catalogues depuis le navigateur) ---

    public static function posterFile(int $workId): string
    {
        return Config::get('data_dir') . '/posters/' . $workId . '.img';
    }

    /** URL d'image autorisée (https et hôte d'un catalogue connu). */
    public static function allowedImageUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return parse_url($url, PHP_URL_SCHEME) === 'https' && in_array($host, self::POSTER_HOSTS, true);
    }

    /** Télécharge une image d'un catalogue et vérifie que c'en est bien une. */
    public static function fetchImage(string $url): ?string
    {
        if (!self::allowedImageUrl($url)) {
            return null;
        }
        $bin = Net::binary($url);
        if ($bin === null || !($info = @getimagesizefromstring($bin))
            || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        return $bin;
    }

    /**
     * Vignette d'un résultat de recherche, relayée par le serveur (le navigateur ne contacte pas
     * les catalogues) et gardée 7 jours dans data_dir/thumbs.
     */
    public static function thumb(string $url): ?string
    {
        if (!self::allowedImageUrl($url)) {
            return null;
        }
        $dir = Config::get('data_dir') . '/thumbs';
        $file = $dir . '/' . hash('sha256', $url) . '.img';
        if (is_file($file) && filemtime($file) > time() - 7 * 86400) {
            return (string) file_get_contents($file);
        }
        $bin = self::fetchImage($url);
        if ($bin !== null && (is_dir($dir) || @mkdir($dir, 0775, true))) {
            @file_put_contents($file, $bin);
        }
        return $bin;
    }

    private static function downloadPoster(int $workId, string $url): void
    {
        $bin = self::fetchImage($url);
        if ($bin === null) {
            return;
        }
        $dir = dirname(self::posterFile($workId));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return;
        }
        if (@file_put_contents(self::posterFile($workId), $bin) !== false) {
            Db::run('UPDATE works SET has_poster = 1 WHERE id = ?', [$workId]);
        }
    }
}
