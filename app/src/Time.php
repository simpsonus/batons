<?php
declare(strict_types=1);

namespace Batons;

use DateTimeImmutable;
use DateTimeZone;

/** Conversions UTC (stockage) <-> fuseau local (affichage, saisie) et formats lisibles. */
final class Time
{
    private static ?DateTimeZone $tz = null;

    public static function tz(): DateTimeZone
    {
        return self::$tz ??= new DateTimeZone((string) Config::get('timezone'));
    }

    public static function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** 'Y-m-d H:i:s' UTC -> DateTimeImmutable dans le fuseau local. */
    public static function local(string $utc): DateTimeImmutable
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(self::tz());
    }

    /** Saisie locale (input datetime-local 'Y-m-d\TH:i') -> chaîne UTC de stockage. */
    public static function fromLocalInput(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable(str_replace('T', ' ', $input), self::tz());
        } catch (\Exception) {
            return null;
        }
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Valeur pour un input datetime-local. */
    public static function toLocalInput(?string $utc = null): string
    {
        $dt = $utc === null ? new DateTimeImmutable('now', self::tz()) : self::local($utc);
        return $dt->format('Y-m-d\TH:i');
    }

    /** Bornes UTC [début, fin[ d'une période locale ('today', 'week', 'month', 'year'). */
    public static function periodUtc(string $period): array
    {
        $now = new DateTimeImmutable('now', self::tz());
        $start = match ($period) {
            'today' => $now->setTime(0, 0),
            'week'  => $now->modify('monday this week')->setTime(0, 0),
            'month' => $now->modify('first day of this month')->setTime(0, 0),
            'year'  => $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0),
        };
        // Fin = fin de la période (et non « maintenant ») : un bâton daté de plus tard aujourd'hui
        // (une livraison prévue à 10 h 30…) compte bien dans aujourd'hui, la semaine, le mois et l'année.
        $end = $start->modify(match ($period) {
            'today' => '+1 day', 'week' => '+1 week', 'month' => '+1 month', 'year' => '+1 year',
        });
        $utc = new DateTimeZone('UTC');
        return [$start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    /** Début (UTC) des N derniers jours, aujourd'hui compris : minuit local il y a N-1 jours. */
    public static function lastDaysUtc(int $days): string
    {
        $start = (new DateTimeImmutable('now', self::tz()))->setTime(0, 0)->modify('-' . max(0, $days - 1) . ' days');
        return $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Bornes UTC [début, fin[ d'un intervalle de jours locaux 'Y-m-d' (fin incluse). Null si une date est invalide. */
    public static function daysUtc(string $from, string $to): ?array
    {
        $parse = static function (string $d): ?DateTimeImmutable {
            $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d, self::tz());
            return $x && $x->format('Y-m-d') === $d ? $x : null;
        };
        $a = $parse($from);
        $b = $parse($to);
        if (!$a || !$b) {
            return null;
        }
        if ($b < $a) {
            [$a, $b] = [$b, $a];
        }
        $utc = new DateTimeZone('UTC');
        return [$a->setTimezone($utc)->format('Y-m-d H:i:s'), $b->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    /** « à l'instant », « il y a 3 h », « il y a 2 j »… */
    public static function ago(?string $utc): string
    {
        if ($utc === null) {
            return 'jamais';
        }
        $diff = time() - (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
        $future = $diff < 0;
        $s = abs($diff);
        $txt = match (true) {
            $s < 60       => null,
            $s < 3600     => intdiv($s, 60) . ' min',
            $s < 86400    => intdiv($s, 3600) . ' h',
            $s < 86400*60 => intdiv($s, 86400) . ' j',
            $s < 86400*730=> intdiv($s, 86400*30) . ' mois',
            default       => intdiv($s, 86400*365) . ' ans',
        };
        if ($txt === null) {
            return "à l'instant";
        }
        return $future ? "dans $txt" : "il y a $txt";
    }

    /** Durée lisible en secondes -> « 3 j 4 h ». */
    public static function duration(float $seconds): string
    {
        $s = (int) round($seconds);
        if ($s < 3600) {
            return max(1, intdiv($s, 60)) . ' min';
        }
        if ($s < 86400) {
            $h = intdiv($s, 3600);
            $m = intdiv($s % 3600, 60);
            return $h . ' h' . ($m ? ' ' . $m . ' min' : '');
        }
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        return $d . ' j' . ($h ? ' ' . $h . ' h' : '');
    }

    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** « jeudi 19 novembre 2026 » */
    public static function longDate(DateTimeImmutable $d): string
    {
        return self::DAYS[(int) $d->format('N') - 1] . ' ' . $d->format('j') . ' ' . self::MONTHS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** « 19 nov. » */
    public static function shortDate(DateTimeImmutable $d): string
    {
        $short = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return $d->format('j') . ' ' . $short[(int) $d->format('n') - 1];
    }
}
