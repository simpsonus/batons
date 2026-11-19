<?php
declare(strict_types=1);

namespace Batons;

use DateTimeImmutable;
use DateTimeZone;

/** Dépôt des bâtons (événements). Toutes les requêtes vérifient l'appartenance via la catégorie. */
final class Events
{
    public static function add(int $categoryId, ?string $occurredAtUtc = null, ?float $value = null, ?string $note = null,
                               ?string $milestone = null): int
    {
        Db::run(
            'INSERT INTO events (category_id, occurred_at, value, note, milestone, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$categoryId, $occurredAtUtc ?? Time::nowUtc(), $value, $note === '' ? null : $note,
             in_array($milestone, ['start', 'finish'], true) ? $milestone : null, Time::nowUtc()]
        );
        $id = Db::lastId();
        Db::run('UPDATE works SET stopped_at = NULL WHERE id = ? AND stopped_at IS NOT NULL', [$workId]); // reprise
        return $id;
    }

    /** Bâton d'une catégorie « œuvre ». */
    public static function addWork(int $categoryId, string $atUtc, int $workId, ?int $season, ?int $episode,
                                   ?int $volume, ?int $rating, ?string $note, ?string $milestone = null): int
    {
        Db::run(
            'INSERT INTO events (category_id, occurred_at, work_id, season, episode, volume, rating, note, milestone, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $atUtc, $workId, $season, $episode, $volume, $rating, $note === '' ? null : $note,
             in_array($milestone, ['start', 'finish'], true) ? $milestone : null, Time::nowUtc()]
        );
        return Db::lastId();
    }

    public static function updateWork(int $id, ?int $season, ?int $episode, ?int $volume, ?int $rating, ?string $milestone = null): void
    {
        Db::run('UPDATE events SET season = ?, episode = ?, volume = ?, rating = ?, milestone = ? WHERE id = ?',
            [$season, $episode, $volume, $rating, in_array($milestone, ['start', 'finish'], true) ? $milestone : null, $id]);
    }

    public static function find(int $userId, int $id): ?array
    {
        return Db::one(
            'SELECT e.*, c.name AS category_name, c.icon, c.color, c.unit, c.kind, c.periods,
                    w.title AS work_title, w.kind AS work_kind
               FROM events e JOIN categories c ON c.id = e.category_id
               LEFT JOIN works w ON w.id = e.work_id
               LEFT JOIN groups g ON g.id = c.group_id
              WHERE e.id = ? AND c.user_id = ?' . Privacy::visibleSql(),
            [$id, $userId]
        );
    }

    public static function update(int $id, string $occurredAtUtc, ?float $value, ?string $note): void
    {
        Db::run('UPDATE events SET occurred_at = ?, value = ?, note = ? WHERE id = ?',
            [$occurredAtUtc, $value, $note === '' ? null : $note, $id]);
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM events WHERE id = ?', [$id]);
    }

    public static function forCategory(int $categoryId, int $limit = 50, int $offset = 0): array
    {
        return Db::all(
            'SELECT e.*, w.title AS work_title, w.id AS work_ref FROM events e LEFT JOIN works w ON w.id = e.work_id
              WHERE e.category_id = ? ORDER BY e.occurred_at DESC, e.id DESC LIMIT ? OFFSET ?',
            [$categoryId, $limit, $offset]
        );
    }

    /** Bâtons d'une œuvre dans une catégorie, du plus récent au plus ancien. */
    public static function forWork(int $workId, int $categoryId): array
    {
        return Db::all(
            'SELECT e.*, w.title AS work_title FROM events e JOIN works w ON w.id = e.work_id
              WHERE e.work_id = ? AND e.category_id = ? ORDER BY e.occurred_at DESC, e.id DESC',
            [$workId, $categoryId]
        );
    }

    /** Derniers bâtons toutes catégories confondues. */
    public static function timeline(int $userId, int $limit = 100, int $offset = 0): array
    {
        return Db::all(
            'SELECT e.*, c.name AS category_name, c.icon, c.color, c.unit, c.kind, w.title AS work_title,
                    CASE WHEN c.hidden = 1 OR g.hidden = 1 THEN 1 ELSE 0 END AS is_hidden
               FROM events e JOIN categories c ON c.id = e.category_id
               LEFT JOIN groups g ON g.id = c.group_id
               LEFT JOIN works w ON w.id = e.work_id
              WHERE c.user_id = ?' . Privacy::visibleSql() . '
              ORDER BY e.occurred_at DESC, e.id DESC LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );
    }

    /** Tous les bâtons d'un utilisateur, pour l'export CSV. */
    public static function allForExport(int $userId): \PDOStatement
    {
        return Db::run(
            'SELECT e.occurred_at, c.name AS category, g.name AS grp, e.value, c.unit, e.note, c.kind,
                    w.title AS work, w.subtitle AS work_subtitle, w.year AS work_year, w.source AS work_source,
                    w.source_id AS work_source_id, e.season, e.episode, e.volume, e.rating, e.milestone
               FROM events e JOIN categories c ON c.id = e.category_id
               LEFT JOIN groups g ON g.id = c.group_id
               LEFT JOIN works w ON w.id = e.work_id
              WHERE c.user_id = ?' . Privacy::visibleSql() . ' ORDER BY e.occurred_at',
            [$userId]
        );
    }

    /** Chiffres clés d'une catégorie. */
    public static function stats(int $categoryId): array
    {
        $row = Db::one(
            'SELECT COUNT(*) AS total, SUM(value) AS total_value, COUNT(value) AS with_value,
                    MIN(occurred_at) AS first_at, MAX(occurred_at) AS last_at
               FROM events WHERE category_id = ?',
            [$categoryId]
        );
        $periods = [];
        foreach (['today', 'week', 'month', 'year'] as $p) {
            [$from, $to] = Time::periodUtc($p);
            $periods[$p] = Db::one(
                'SELECT COUNT(*) AS n, SUM(value) AS v FROM events
                  WHERE category_id = ? AND occurred_at >= ? AND occurred_at < ?',
                [$categoryId, $from, $to]
            );
        }
        $avgInterval = null;
        if ((int) $row['total'] > 1) {
            $span = strtotime($row['last_at'] . ' UTC') - strtotime($row['first_at'] . ' UTC');
            $avgInterval = $span / ((int) $row['total'] - 1);
        }
        return $row + ['periods' => $periods, 'avg_interval' => $avgInterval];
    }

    /**
     * Nombre de bâtons par jour local ('Y-m-d' => n) depuis le lundi d'il y a $weeks semaines,
     * et ce lundi de départ. Sert à la vue calendrier.
     */
    public static function daily(int $categoryId, int $weeks = 52): array
    {
        $tz = Time::tz();
        $start = (new DateTimeImmutable('now', $tz))->modify('monday this week')->setTime(0, 0)->modify('-' . ($weeks - 1) . ' weeks');
        $fromUtc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $counts = [];
        foreach (Db::all('SELECT occurred_at FROM events WHERE category_id = ? AND occurred_at >= ?', [$categoryId, $fromUtc]) as $r) {
            $d = Time::local($r['occurred_at'])->format('Y-m-d');
            $counts[$d] = ($counts[$d] ?? 0) + 1;
        }
        return [$start, $counts];
    }

    /**
     * Série pour le graphique, regroupée dans le fuseau local.
     * $scale : 'day' (30 derniers jours), 'week' (12 semaines), 'month' (12 mois).
     * Renvoie une liste de ['label' => …, 'count' => int, 'sum' => float].
     */
    public static function series(int $categoryId, string $scale = 'day'): array
    {
        $tz = Time::tz();
        $now = new DateTimeImmutable('now', $tz);
        [$n, $start, $step, $keyFmt] = match ($scale) {
            'week'  => [12, $now->modify('monday this week')->setTime(0, 0)->modify('-11 weeks'), '+1 week', 'o-W'],
            'month' => [12, $now->modify('first day of this month')->setTime(0, 0)->modify('-11 months'), '+1 month', 'Y-m'],
            default => [30, $now->setTime(0, 0)->modify('-29 days'), '+1 day', 'Y-m-d'],
        };

        $buckets = [];
        $d = $start;
        for ($i = 0; $i < $n; $i++) {
            $label = match ($scale) {
                'week'  => Time::shortDate($d),
                'month' => mb_substr(['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) $d->format('n') - 1], 0, 5),
                default => $d->format('j'),
            };
            $buckets[$d->format($keyFmt)] = ['label' => $label, 'date' => $d, 'count' => 0, 'sum' => 0.0];
            $d = $d->modify($step);
        }

        $fromUtc = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $rows = Db::all('SELECT occurred_at, value FROM events WHERE category_id = ? AND occurred_at >= ?', [$categoryId, $fromUtc]);
        foreach ($rows as $r) {
            $key = Time::local($r['occurred_at'])->format($keyFmt);
            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
                $buckets[$key]['sum'] += (float) ($r['value'] ?? 0);
            }
        }
        return array_values($buckets);
    }
}
