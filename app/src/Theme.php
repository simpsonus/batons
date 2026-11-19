<?php
declare(strict_types=1);

namespace Batons;

/**
 * Apparence des tuiles de l'accueil (réglage par utilisateur) et couleurs dérivées d'une catégorie,
 * calculées pour garder un contraste AA (4,5:1) entre le texte et le fond, quelle que soit la couleur choisie.
 */
final class Theme
{
    public const STYLES = ['lisere' => 'Liseré', 'plein' => 'Plein', 'degrade' => 'Dégradé', 'coin' => 'Coin', 'teinte' => 'Teinté', 'pastille' => 'Pastille'];
    public const GRADS = ['diag' => 'Diagonal', 'vert' => 'Vertical', 'lum' => 'Lumière', 'deux' => 'Deux teintes', 'pastel' => 'Pastel', 'voile' => 'Voile latéral'];
    public const THEMES = ['auto' => 'Automatique', 'clair' => 'Clair', 'sombre' => 'Sombre'];
    public const SHAPES = ['arr' => 'Arrondie', 'car' => 'Carrée', 'tres' => 'Très arrondie', 'un' => 'Un coin arrondi', 'casse' => 'Coin cassé'];

    private const WHITE = [255, 255, 255];
    private const INK = [28, 25, 23];      // --ink du mode clair

    /** Réglage courant de l'utilisateur, validé : [style, grad, shape]. */
    public static function current(int $userId): array
    {
        $pick = static fn(?string $v, array $allowed, string $def): string => isset($allowed[$v ?? '']) ? $v : $def;
        return [
            'style' => $pick(Settings::get($userId, 'tile_style'), self::STYLES, 'lisere'),
            'grad'  => $pick(Settings::get($userId, 'tile_grad'), self::GRADS, 'diag'),
            'shape' => $pick(Settings::get($userId, 'tile_shape'), self::SHAPES, 'arr'),
            'theme' => $pick(Settings::get($userId, 'theme'), self::THEMES, 'auto'),
        ];
    }

    public static function save(int $userId, string $style, string $grad, string $shape, string $theme = 'auto'): void
    {
        Settings::set($userId, 'theme', isset(self::THEMES[$theme]) && $theme !== 'auto' ? $theme : null);
        Settings::set($userId, 'tile_style', isset(self::STYLES[$style]) && $style !== 'lisere' ? $style : null);
        Settings::set($userId, 'tile_grad', isset(self::GRADS[$grad]) && $grad !== 'diag' ? $grad : null);
        Settings::set($userId, 'tile_shape', isset(self::SHAPES[$shape]) && $shape !== 'arr' ? $shape : null);
    }

    /**
     * Palette proposée à la création d'une catégorie : hex => [nom, rangée].
     * Toutes les couleurs restent lisibles en texte grâce aux variantes calculées (baseVars / tileVars).
     */
    public static function palette(): array
    {
        $hues = [
            'Rouge' => ['#ef4444', '#dc2626', '#991b1b'], 'Framboise' => ['#f43f5e', '#e11d48', '#9f1239'],
            'Rose' => ['#ec4899', '#db2777', '#9d174d'], 'Fuchsia' => ['#d946ef', '#c026d3', '#86198f'],
            'Violet' => ['#a855f7', '#7c3aed', '#5b21b6'], 'Indigo' => ['#818cf8', '#4f46e5', '#3730a3'],
            'Bleu' => ['#60a5fa', '#2563eb', '#1e40af'], 'Ciel' => ['#38bdf8', '#0284c7', '#075985'],
            'Cyan' => ['#22d3ee', '#0891b2', '#155e75'], 'Sarcelle' => ['#2dd4bf', '#0d9488', '#115e59'],
            'Émeraude' => ['#34d399', '#059669', '#065f46'], 'Vert' => ['#4ade80', '#16a34a', '#166534'],
            'Olive' => ['#a3e635', '#65a30d', '#3f6212'], 'Jaune' => ['#facc15', '#ca8a04', '#854d0e'],
            'Ambre' => ['#fbbf24', '#d97706', '#92400e'], 'Orange' => ['#fb923c', '#ea580c', '#9a3412'],
        ];
        $out = [];
        foreach (['Vives' => [0, ' vif'], 'Moyennes' => [1, ''], 'Profondes' => [2, ' profond']] as $row => [$i, $suffix]) {
            foreach ($hues as $name => $shades) {
                $out[$shades[$i]] = [$name . $suffix, $row];
            }
        }
        $more = [
            'Neutres' => ['#a8a29e' => 'Gris clair', '#78716c' => 'Gris', '#57534e' => 'Gris foncé', '#44403c' => 'Taupe',
                          '#94a3b8' => 'Ardoise claire', '#64748b' => 'Ardoise', '#475569' => 'Ardoise foncée', '#1e293b' => 'Nuit'],
            'Classiques' => ['#c2410c' => 'Orange brûlé', '#b91c1c' => 'Rouge brique', '#be185d' => 'Rose framboise',
                             '#1d4ed8' => 'Bleu roi', '#0e7490' => 'Bleu canard', '#047857' => 'Vert sapin',
                             '#4d7c0f' => 'Vert olive', '#a16207' => 'Ocre'],
        ];
        foreach ($more as $row => $cols) {
            foreach ($cols as $hex => $name) {
                $out[$hex] ??= [$name, $row];
            }
        }
        return $out;
    }

    /** Attribut data-theme de <html> (vide : on suit l'appareil). */
    public static function htmlAttr(array $t): string
    {
        return match ($t['theme'] ?? 'auto') {
            'clair' => ' data-theme="light"',
            'sombre' => ' data-theme="dark"',
            default => '',
        };
    }

    /**
     * Variables de base de toute zone colorée par une catégorie : --c, --c-ink-l (couleur assez foncée pour du
     * texte sur fond clair), --c-solid / --c-on (fond de bouton et son texte, contraste ≥ 4,5:1).
     */
    public static function baseVars(string $hex): string
    {
        $c = self::rgb($hex);
        [$solid, $on] = self::solid($c);
        $inkL = self::toward($c, [0, 0, 0], [247, 244, 238]);   // --bg clair, le plus foncé des fonds clairs
        return '--c: ' . self::hex($c) . '; --c-ink-l: ' . self::hex($inkL) . '; --c-solid: ' . self::hex($solid) . '; --c-on: ' . self::hex($on) . ';';
    }

    /** Fond « plein » et couleur du texte posé dessus. */
    private static function solid(array $c): array
    {
        if (self::contrast($c, self::WHITE) >= 4.5) {
            return [$c, self::WHITE];
        }
        if (self::luminance($c) >= 0.30) {
            return [$c, self::INK];
        }
        return [self::toward($c, [0, 0, 0], self::WHITE), self::WHITE];
    }

    /** Classes à poser sur <body>. */
    public static function bodyClasses(array $t): string
    {
        return 'tiles-' . $t['style'] . ' grad-' . $t['grad'] . ' shape-' . $t['shape'];
    }

    /** Variables CSS d'une tuile (à mettre dans son attribut style, après --c). */
    public static function tileVars(string $hex): string
    {
        $c = self::rgb($hex);
        [$solid, $on] = self::solid($c);
        $white = $on === self::WHITE;
        $deep = $white ? self::mix($solid, [0, 0, 0], 0.45) : self::toward(self::mix($solid, [0, 0, 0], 0.15), self::WHITE, self::INK);
        $glow = $white ? self::toward(self::mix($c, self::WHITE, 0.25), [0, 0, 0], self::WHITE) : self::mix($solid, self::WHITE, 0.3);
        $hue2 = self::rotate($solid, 40);
        $hue2 = $white ? self::toward($hue2, [0, 0, 0], self::WHITE) : self::toward($hue2, self::WHITE, self::INK);
        $pastel = self::mix($c, self::WHITE, 0.65);
        $pastel2 = self::mix($c, self::WHITE, 0.90);
        $pastelInk = self::toward($c, [0, 0, 0], $pastel);
        $tint = self::mix($c, [255, 253, 249], 0.88);   // fond « Teinté » du mode clair (12 % sur --surface)
        $tintInk = self::toward($c, [0, 0, 0], $tint);

        $vars = ['ink-l' => self::toward($c, [0, 0, 0], [247, 244, 238]), 'solid' => $solid, 'on' => $on, 'deep' => $deep, 'glow' => $glow, 'hue2' => $hue2,
                 'pastel' => $pastel, 'pastel2' => $pastel2, 'pastel-ink' => $pastelInk, 'tint-ink' => $tintInk];
        $out = '';
        foreach ($vars as $k => $v) {
            $out .= '--c-' . $k . ': ' . self::hex($v) . '; ';
        }
        return rtrim($out);
    }

    /** Ratio de contraste WCAG entre deux couleurs (utile aussi aux tests). */
    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = 'c2410c';
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(static fn($v) => (int) round(max(0, min(255, $v))), $c));
    }

    private static function luminance(array $c): float
    {
        $f = static function (float $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
    }

    /** Mélange : $t = part de $b (0 = $a, 1 = $b). */
    private static function mix(array $a, array $b, float $t): array
    {
        return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t];
    }

    /** Pousse $c vers $target (noir ou blanc) jusqu'à un contraste de 4,5:1 avec $against. */
    private static function toward(array $c, array $target, array $against): array
    {
        for ($t = 0.0; $t <= 1.0; $t += 0.03) {
            $x = self::mix($c, $target, $t);
            if (self::contrast($x, $against) >= 4.6) {
                return $x;
            }
        }
        return $target;
    }

    /** Rotation de teinte (degrés), saturation et luminosité conservées. */
    private static function rotate(array $c, float $deg): array
    {
        [$r, $g, $b] = [$c[0] / 255, $c[1] / 255, $c[2] / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            return $c;
        }
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        } * 60;
        $h = fmod($h + $deg, 360);
        $C = (1 - abs(2 * $l - 1)) * $s;
        $X = $C * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $C / 2;
        [$r1, $g1, $b1] = match (true) {
            $h < 60 => [$C, $X, 0], $h < 120 => [$X, $C, 0], $h < 180 => [0, $C, $X],
            $h < 240 => [0, $X, $C], $h < 300 => [$X, 0, $C], default => [$C, 0, $X],
        };
        return [($r1 + $m) * 255, ($g1 + $m) * 255, ($b1 + $m) * 255];
    }
}
