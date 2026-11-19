<?php
declare(strict_types=1);

namespace Batons;

/** Histogramme SVG généré côté serveur : aucune dépendance JS. */
final class Chart
{
    /**
     * @param array  $series  sortie de Events::series()
     * @param string $metric  'count' ou 'sum'
     */
    public static function bars(array $series, string $metric, string $color, ?string $unit = null): string
    {
        $w = 640; $h = 200; $padL = 8; $padR = 8; $padT = 22; $padB = 26;
        $n = max(1, count($series));
        $max = 0.0;
        foreach ($series as $p) {
            $max = max($max, (float) $p[$metric]);
        }
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $slot = $plotW / $n;
        $barW = max(2.0, $slot * 0.72);
        $labelEvery = $n > 16 ? (int) ceil($n / 10) : 1;

        $svg = sprintf('<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="Histogramme">', $w, $h);
        // ligne de base
        $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="chart-axis"/>', $padL, $padT + $plotH, $w - $padR, $padT + $plotH);

        foreach ($series as $i => $p) {
            $v = (float) $p[$metric];
            $bh = $max > 0 ? ($v / $max) * $plotH : 0;
            $x = $padL + $i * $slot + ($slot - $barW) / 2;
            $y = $padT + $plotH - $bh;
            $fmt = self::num($v) . ($metric === 'sum' && $unit ? ' ' . $unit : '');
            $title = e($p['label'] . ' : ' . $fmt);
            if ($v > 0) {
                $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="%s"><title>%s</title></rect>',
                    $x, $y, $barW, max(1.5, $bh), e($color), $title);
                if ($n <= 16) {
                    $svg .= sprintf('<text x="%.1f" y="%.1f" class="chart-value">%s</text>', $x + $barW / 2, $y - 5, e(self::num($v)));
                }
            } else {
                $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="1.5" class="chart-empty"><title>%s</title></rect>',
                    $x, $padT + $plotH - 1.5, $barW, $title);
            }
            if ($i % $labelEvery === 0 || $i === $n - 1) {
                $svg .= sprintf('<text x="%.1f" y="%d" class="chart-label">%s</text>', $x + $barW / 2, $h - 8, e($p['label']));
            }
        }
        if ($max > 0 && $n > 16) {
            $svg .= sprintf('<text x="%d" y="14" class="chart-max">max %s</text>', $padL, e(self::num($max)));
        }
        return $svg . '</svg>';
    }

    public static function num(float|int|string|null $v): string
    {
        $v = (float) $v;
        if (abs($v - round($v)) < 0.005) {
            return number_format($v, 0, ',', ' ');
        }
        return rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',');
    }
}
