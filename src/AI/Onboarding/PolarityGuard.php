<?php
/**
 * Keeps a light-to-dark (or dark-to-light) colour change readable.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Templates pair their neutrals: dark ink on a light page, white type on a
 * dark band or a dark photo overlay. When a design change turns the page
 * background from light to dark, swapping only some neutrals leaves pairs on
 * the same side (white type on a now light overlay, grey text on a now dark
 * page). So once the base colour changes side, every neutral in the design
 * changes side too: the model's own choice where it already did, otherwise
 * the same hue with its lightness mirrored. Accents keep the model's choice.
 */
class PolarityGuard
{
    /** Relative luminance above which a colour counts as light. */
    const LIGHT = 0.18;

    /** Below this chroma (0..1) a colour is a neutral, not an accent. */
    const NEUTRAL = 0.2;

    /**
     * @param array    $swaps List of {from, to} hex pairs.
     * @param string[] $used  Hex colours the design uses.
     * @param string   $base  The page background colour (theme "base").
     *
     * @return array List of {from, to}.
     */
    public static function complete(array $swaps, array $used, $base)
    {
        $map = [];
        foreach ($swaps as $swap) {
            $from = self::hex(isset($swap['from']) ? $swap['from'] : '');
            $to   = self::hex(isset($swap['to']) ? $swap['to'] : '');
            if ($from !== '' && $to !== '') {
                $map[$from] = $to;
            }
        }

        $base = self::hex($base);
        if ($base === '' || ! isset($map[$base]) || self::isLight($map[$base]) === self::isLight($base)) {
            return $swaps;
        }

        foreach (array_unique(array_merge([$base], array_map([self::class, 'hex'], $used))) as $color) {
            if ($color === '' || self::chroma($color) >= self::NEUTRAL) {
                continue;
            }
            if (! isset($map[$color]) || self::isLight($map[$color]) === self::isLight($color)) {
                $map[$color] = self::mirror($color);
            }
        }

        $out = [];
        foreach ($map as $from => $to) {
            if ($from !== $to) {
                $out[] = ['from' => $from, 'to' => $to];
            }
        }

        return $out;
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return bool
     */
    public static function isLight($hex)
    {
        list($r, $g, $b) = self::rgb($hex);
        $f = static function ($c) {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        };

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b) > self::LIGHT;
    }

    /**
     * Same hue and saturation, lightness mirrored (#f4f0ea becomes a warm near black).
     *
     * @param string $hex #rrggbb.
     *
     * @return string
     */
    public static function mirror($hex)
    {
        list($r, $g, $b) = array_map(static function ($c) {
            return $c / 255;
        }, self::rgb($hex));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;
        $d   = $max - $min;
        $h   = 0.0;
        $s   = 0.0;

        if ($d > 0) {
            $s = $d / (1 - abs(2 * $l - 1));
            if ($max === $r) {
                $h = fmod(($g - $b) / $d, 6);
            } elseif ($max === $g) {
                $h = ($b - $r) / $d + 2;
            } else {
                $h = ($r - $g) / $d + 4;
            }
            $h *= 60;
        }

        $l = 1 - $l;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        $h = $h < 0 ? $h + 360 : $h;

        if ($h < 60) {
            $p = [$c, $x, 0];
        } elseif ($h < 120) {
            $p = [$x, $c, 0];
        } elseif ($h < 180) {
            $p = [0, $c, $x];
        } elseif ($h < 240) {
            $p = [0, $x, $c];
        } elseif ($h < 300) {
            $p = [$x, 0, $c];
        } else {
            $p = [$c, 0, $x];
        }

        return sprintf('#%02x%02x%02x', ...array_map(static function ($v) use ($m) {
            return (int) max(0, min(255, round(($v + $m) * 255)));
        }, $p));
    }

    private static function chroma($hex)
    {
        list($r, $g, $b) = self::rgb($hex);

        return (max($r, $g, $b) - min($r, $g, $b)) / 255;
    }

    private static function rgb($hex)
    {
        return sscanf(self::hex($hex), '#%02x%02x%02x');
    }

    private static function hex($value)
    {
        $value = strtolower(trim((string) $value));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : '';
    }
}
