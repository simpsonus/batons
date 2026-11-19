<?php
declare(strict_types=1);

namespace Batons;

/**
 * Périodes d'une catégorie simple (option « périodes ») : un bâton « début », puis un bâton « fin ».
 * Une seule période ouverte à la fois : un nouveau « début » pendant une période ouverte est ignoré
 * pour le calcul (la période continue), une « fin » sans période ouverte aussi.
 */
final class Periods
{
    /** Périodes de la catégorie, de la plus récente à la plus ancienne : [start, finish|null, days, start_id, finish_id]. */
    public static function list(int $categoryId): array
    {
        $rows = Db::all("SELECT id, occurred_at, milestone FROM events
                          WHERE category_id = ? AND milestone IN ('start', 'finish') ORDER BY occurred_at, id", [$categoryId]);
        $out = [];
        $open = null;
        foreach ($rows as $r) {
            if ($r['milestone'] === 'start' && $open === null) {
                $open = $r;
            } elseif ($r['milestone'] === 'finish' && $open !== null) {
                $out[] = self::make($open, $r);
                $open = null;
            }
        }
        if ($open !== null) {
            $out[] = self::make($open, null);
        }
        return array_reverse($out);
    }

    private static function make(array $start, ?array $finish): array
    {
        $end = Time::local($finish['occurred_at'] ?? Time::nowUtc())->setTime(0, 0);
        return [
            'start' => $start['occurred_at'], 'finish' => $finish['occurred_at'] ?? null,
            'days' => (int) Time::local($start['occurred_at'])->setTime(0, 0)->diff($end)->days,
            'start_id' => (int) $start['id'], 'finish_id' => $finish ? (int) $finish['id'] : null,
        ];
    }

    /** Période en cours, ou null. */
    public static function open(int $categoryId): ?array
    {
        $list = self::list($categoryId);
        return ($list && $list[0]['finish'] === null) ? $list[0] : null;
    }

    /** Statistiques des périodes terminées : [count, avg, max]. */
    public static function stats(array $periods): array
    {
        $done = array_column(array_filter($periods, static fn($p) => $p['finish'] !== null), 'days');
        return [
            'count' => count($done),
            'avg' => $done ? (int) round(array_sum($done) / count($done)) : null,
            'max' => $done ? max($done) : null,
        ];
    }

    /** Jours locaux ('Y-m-d') couverts par les périodes, pour le calendrier. */
    public static function coveredDays(array $periods): array
    {
        $days = [];
        foreach ($periods as $p) {
            $d = Time::local($p['start'])->setTime(0, 0);
            $end = Time::local($p['finish'] ?? Time::nowUtc())->setTime(0, 0);
            for ($i = 0; $d <= $end && $i < 3660; $i++, $d = $d->modify('+1 day')) {
                $days[$d->format('Y-m-d')] = true;
            }
        }
        return $days;
    }
}
