<?php
/**
 * Pure colour maths for custom designs.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * Hex parsing, WCAG contrast, OKLab lightness and chroma, mixing, and the
 * nudge that moves a foreground colour until it reads on a background.
 * Every function is pure and works on #rrggbb strings (lower case out).
 */
class Color
{
    /** WCAG 2.x AA for normal text. */
    const AA = 4.5;

    /** WCAG 2.x AA for large text and UI parts. */
    const AA_LARGE = 3.0;

    /**
     * @param mixed $value #rgb, #rrggbb or #rrggbbaa (alpha dropped).
     *
     * @return string #rrggbb or '' when not a hex colour.
     */
    public static function hex($value)
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])?$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $value, $m)) {
            return '#' . $m[1];
        }

        return '';
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return int[] [r, g, b] 0..255.
     */
    public static function rgb($hex)
    {
        $hex = self::hex($hex);

        return $hex === '' ? [0, 0, 0] : array_map('intval', sscanf($hex, '#%02x%02x%02x'));
    }

    /**
     * @param float[] $rgb 0..255 each (clamped, rounded).
     *
     * @return string #rrggbb.
     */
    public static function fromRgb(array $rgb)
    {
        return vsprintf('#%02x%02x%02x', array_map(static function ($c) {
            return (int) max(0, min(255, round((float) $c)));
        }, array_slice(array_values($rgb), 0, 3)));
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return float WCAG relative luminance 0..1.
     */
    public static function luminance($hex)
    {
        $lin = array_map([self::class, 'toLinear'], self::rgb($hex));

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    /**
     * @param string $a #rrggbb.
     * @param string $b #rrggbb.
     *
     * @return float WCAG contrast ratio 1..21.
     */
    public static function contrast($a, $b)
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return float[] OKLab [L, a, b].
     */
    public static function oklab($hex)
    {
        list($r, $g, $b) = array_map([self::class, 'toLinear'], self::rgb($hex));

        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

        $l = self::cbrt($l);
        $m = self::cbrt($m);
        $s = self::cbrt($s);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    /**
     * @param float[] $lab OKLab [L, a, b].
     *
     * @return string #rrggbb (clipped to sRGB).
     */
    public static function fromOklab(array $lab)
    {
        list($L, $a, $b) = $lab;

        $l = pow($L + 0.3963377774 * $a + 0.2158037573 * $b, 3);
        $m = pow($L - 0.1055613458 * $a - 0.0638541728 * $b, 3);
        $s = pow($L - 0.0894841775 * $a - 1.2914855480 * $b, 3);

        $rgb = [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];

        return self::fromRgb(array_map(static function ($c) {
            $c = max(0.0, min(1.0, $c));
            return 255 * ($c <= 0.0031308 ? 12.92 * $c : 1.055 * pow($c, 1 / 2.4) - 0.055);
        }, $rgb));
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return float OKLab lightness 0..1.
     */
    public static function lightness($hex)
    {
        return self::oklab($hex)[0];
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return float OKLCH chroma (0 grey, ~0.3 vivid).
     */
    public static function chroma($hex)
    {
        $lab = self::oklab($hex);

        return sqrt($lab[1] * $lab[1] + $lab[2] * $lab[2]);
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return float OKLCH hue in degrees 0..360.
     */
    public static function hue($hex)
    {
        $lab = self::oklab($hex);
        $h   = rad2deg(atan2($lab[2], $lab[1]));

        return $h < 0 ? $h + 360 : $h;
    }

    /**
     * @param string $hex #rrggbb.
     * @param float  $l   New OKLab lightness.
     *
     * @return string Same hue and chroma ratio at that lightness.
     */
    public static function withLightness($hex, $l)
    {
        $lab = self::oklab($hex);

        return self::fromOklab([max(0.0, min(1.0, (float) $l)), $lab[1], $lab[2]]);
    }

    /**
     * @param string $a #rrggbb.
     * @param string $b #rrggbb.
     * @param float  $t 0 = a, 1 = b.
     *
     * @return string
     */
    public static function mix($a, $b, $t)
    {
        $t  = max(0.0, min(1.0, (float) $t));
        $ca = self::rgb($a);
        $cb = self::rgb($b);

        return self::fromRgb([
            $ca[0] + ($cb[0] - $ca[0]) * $t,
            $ca[1] + ($cb[1] - $ca[1]) * $t,
            $ca[2] + ($cb[2] - $ca[2]) * $t,
        ]);
    }

    /**
     * Move $fg towards black or white (whichever is away from $bg) in small
     * steps until it reaches $ratio against $bg. Keeps the hue as far as
     * possible; ends at black or white when nothing else is enough.
     *
     * @param string $fg    Foreground #rrggbb.
     * @param string $bg    Background #rrggbb.
     * @param float  $ratio Contrast to reach.
     *
     * @return string
     */
    public static function ensureContrast($fg, $bg, $ratio = self::AA)
    {
        $fg = self::hex($fg);
        $bg = self::hex($bg);

        if ($fg === '' || $bg === '' || self::contrast($fg, $bg) >= $ratio) {
            return $fg;
        }

        // Towards the pole that gives the most room against this background.
        $poles = self::contrast('#000000', $bg) >= self::contrast('#ffffff', $bg) ? ['#000000', '#ffffff'] : ['#ffffff', '#000000'];

        foreach ($poles as $pole) {
            for ($t = 0.04; $t <= 1.0001; $t += 0.04) {
                $c = self::mix($fg, $pole, $t);
                if (self::contrast($c, $bg) >= $ratio) {
                    return $c;
                }
            }
        }

        return $poles[0];
    }

    /**
     * @param string   $bg         Background #rrggbb.
     * @param string[] $candidates Preferred first.
     * @param float    $ratio      Contrast wanted.
     *
     * @return string The first candidate that reads on $bg, else the best one.
     */
    public static function readableOn($bg, array $candidates, $ratio = self::AA)
    {
        $best      = '';
        $bestRatio = 0.0;

        foreach ($candidates as $c) {
            $c = self::hex($c);
            if ($c === '') {
                continue;
            }
            $r = self::contrast($c, $bg);
            if ($r >= $ratio) {
                return $c;
            }
            if ($r > $bestRatio) {
                $best      = $c;
                $bestRatio = $r;
            }
        }

        return $best !== '' ? $best : '#000000';
    }

    /**
     * Parse a CSS colour literal: hex, rgb()/rgba() (comma or space syntax).
     *
     * @param string $value Literal.
     *
     * @return array|null {hex, alpha} or null.
     */
    public static function parseCss($value)
    {
        $value = trim((string) $value);
        $hex   = self::hex($value);

        if ($hex !== '') {
            $alpha = 1.0;
            if (preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})$/i', $value, $m)) {
                $alpha = hexdec($m[1]) / 255;
            } elseif (preg_match('/^#[0-9a-f]{3}([0-9a-f])$/i', $value, $m)) {
                $alpha = hexdec($m[1] . $m[1]) / 255;
            }
            return ['hex' => $hex, 'alpha' => $alpha];
        }

        if (preg_match('/^rgba?\(\s*([\d.]+%?)[\s,]+([\d.]+%?)[\s,]+([\d.]+%?)(?:\s*[,\/]\s*([\d.]+%?))?\s*\)$/i', $value, $m)) {
            $channel = static function ($v) {
                return substr($v, -1) === '%' ? (float) $v * 2.55 : (float) $v;
            };
            $alpha = isset($m[4]) && $m[4] !== '' ? (substr($m[4], -1) === '%' ? (float) $m[4] / 100 : (float) $m[4]) : 1.0;

            return ['hex' => self::fromRgb([$channel($m[1]), $channel($m[2]), $channel($m[3])]), 'alpha' => max(0.0, min(1.0, $alpha))];
        }

        return null;
    }

    /**
     * @param string $hex   #rrggbb.
     * @param float  $alpha 0..1.
     *
     * @return string Hex when opaque, else rgba().
     */
    public static function toCss($hex, $alpha = 1.0)
    {
        if ($alpha >= 0.999) {
            return $hex;
        }
        list($r, $g, $b) = self::rgb($hex);

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim(rtrim(number_format(max(0.0, $alpha), 3, '.', ''), '0'), '.') ?: '0');
    }

    private static function toLinear($c)
    {
        $c /= 255;

        return $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    }

    private static function cbrt($x)
    {
        return $x < 0 ? -pow(-$x, 1 / 3) : pow($x, 1 / 3);
    }
}
