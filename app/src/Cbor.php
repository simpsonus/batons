<?php
declare(strict_types=1);

namespace Batons;

/**
 * Décodeur CBOR minimal (RFC 8949), suffisant pour WebAuthn :
 * entiers, chaînes d'octets, textes, tableaux, tables, booléens/null. Pas de longueurs indéfinies, de flottants
 * ni d'étiquettes (refusés par une exception). Vérifié avec les exemples de l'annexe A de la RFC (tests/unit.php).
 */
final class Cbor
{
    /** Décode une valeur ; $end reçoit la position qui suit la valeur. */
    public static function decode(string $data, ?int &$end = null): mixed
    {
        $pos = 0;
        $value = self::item($data, $pos, 0);
        $end = $pos;
        return $value;
    }

    private static function item(string $d, int &$p, int $depth): mixed
    {
        if ($depth > 16 || $p >= strlen($d)) {
            throw new \RuntimeException('CBOR invalide.');
        }
        $b = ord($d[$p++]);
        $major = $b >> 5;
        $info = $b & 0x1f;
        $n = self::length($d, $p, $info);

        switch ($major) {
            case 0: return $n;
            case 1: return -1 - $n;
            case 2:
            case 3:
                if ($p + $n > strlen($d)) {
                    throw new \RuntimeException('CBOR tronqué.');
                }
                $s = substr($d, $p, $n);
                $p += $n;
                return $s;
            case 4:
                $out = [];
                for ($i = 0; $i < $n; $i++) {
                    $out[] = self::item($d, $p, $depth + 1);
                }
                return $out;
            case 5:
                $out = [];
                for ($i = 0; $i < $n; $i++) {
                    $k = self::item($d, $p, $depth + 1);
                    if (!is_int($k) && !is_string($k)) {
                        throw new \RuntimeException('CBOR : clé de table non prise en charge.');
                    }
                    $out[$k] = self::item($d, $p, $depth + 1);
                }
                return $out;
            case 7:
                return match ($info) { 20 => false, 21 => true, 22, 23 => null,
                    default => throw new \RuntimeException('CBOR : valeur simple non prise en charge.') };
            default:
                throw new \RuntimeException('CBOR : type non pris en charge.');
        }
    }

    private static function length(string $d, int &$p, int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        $size = match ($info) { 24 => 1, 25 => 2, 26 => 4, 27 => 8,
            default => throw new \RuntimeException('CBOR : longueur indéfinie non prise en charge.') };
        if ($p + $size > strlen($d)) {
            throw new \RuntimeException('CBOR tronqué.');
        }
        $bytes = substr($d, $p, $size);
        $p += $size;
        $n = match ($size) { 1 => ord($bytes), 2 => unpack('n', $bytes)[1], 4 => unpack('N', $bytes)[1], 8 => unpack('J', $bytes)[1] };
        if ($n < 0) {
            throw new \RuntimeException('CBOR : entier trop grand.');   // ≥ 2^63 : hors des entiers PHP
        }
        return $n;
    }
}
