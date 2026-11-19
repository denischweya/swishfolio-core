<?php
/**
 * Recolours a kit's decorative raster art into another palette.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * A kit's decorative pictures (3D spheres, gradient meshes, type specimens,
 * abstract covers) are drawn in the kit's own colours. Under another palette
 * (a custom design, or the owner's colour wishes on a template) they would
 * clash, so they are recoloured pixel by pixel in OKLab, keeping every shape,
 * highlight and shadow:
 *
 *  - coloured pixels follow the kit colour nearest to them (by hue): they
 *    take that colour's replacement hue, their chroma scales with it and
 *    their lightness moves by the same step, so a lime sphere with its
 *    shading becomes a pink sphere with the same shading. A hue far from
 *    every kit colour (the violet in a lime kit's mesh) goes to the design's
 *    second accent, so the picture keeps two colours, both from the palette;
 *  - neutral pixels keep their lightness and take the change of the page
 *    and text colours as a tint (black stays dark, but plum-dark under a plum
 *    palette); greys and silvers stay grey;
 *  - in between, the two blend smoothly by chroma.
 *
 * The mapping is pure (mapRgb()) and baked into a colour lookup table, which
 * Imagick applies as a Hald CLUT (or GD pixel by pixel), so a picture is
 * recoloured in well under a second. SVG files are recoloured value by value.
 */
class ArtRecolour
{
    /** Below this OKLCH chroma a pixel is neutral. */
    const NEUTRAL = 0.035;

    /** Above this it is fully coloured. */
    const VIVID = 0.1;

    /** Hue gap (degrees) beyond which a colour is "other" (second accent). */
    const FAR = 55.0;

    /** @var array[] {k: [L,a,b], t: [L,a,b], kc, tc, kh, th} */
    private $refs = [];

    /** @var array|null Catch-all target {t: [L,a,b], tc, th}. */
    private $other;

    /** @var float[][] Neutral ends: kit dark, kit light, new dark, new light ([L,a,b]). */
    private $ends;

    /** @var string */
    private $key;

    /** @var bool */
    private $identity = true;

    /**
     * @param array  $pairs     Kit colour => replacement colour (#rrggbb each).
     * @param string $kitBase   Kit page background.
     * @param string $kitInk    Kit text colour.
     * @param string $newBase   New page background.
     * @param string $newInk    New text colour.
     * @param string $otherHue  Colour for hues far from every kit colour ('' for none).
     */
    public function __construct(array $pairs, $kitBase, $kitInk, $newBase, $newInk, $otherHue = '')
    {
        $kitBase = Color::hex($kitBase) ?: '#ffffff';
        $kitInk  = Color::hex($kitInk) ?: '#000000';
        $newBase = Color::hex($newBase) ?: $kitBase;
        $newInk  = Color::hex($newInk) ?: $kitInk;

        $sig = [];
        foreach ($pairs as $from => $to) {
            $from = Color::hex($from);
            $to   = Color::hex($to);
            if ($from === '' || $to === '' || Color::chroma($from) < self::NEUTRAL) {
                continue;
            }
            $k = Color::oklab($from);
            $t = Color::oklab($to);
            $this->refs[$from] = [
                'k'  => $k,
                't'  => $t,
                'kc' => self::c($k),
                'tc' => self::c($t),
                'kh' => self::h($k),
                'th' => self::h($t),
            ];
            $sig[] = $from . '>' . $to;
            if ($from !== $to) {
                $this->identity = false;
            }
        }

        $other = Color::hex($otherHue);
        if ($other !== '' && Color::chroma($other) >= self::NEUTRAL && $this->refs) {
            $o           = Color::oklab($other);
            $this->other = ['t' => $o, 'tc' => self::c($o), 'th' => self::h($o)];
            $sig[]       = 'other>' . $other;
            // Only a change when the kit's art has hues that would go there.
            $this->identity = false;
        }

        $kitDarkFirst = Color::lightness($kitBase) > Color::lightness($kitInk);
        $newDarkFirst = Color::lightness($newBase) > Color::lightness($newInk);
        $this->ends   = [
            Color::oklab($kitDarkFirst ? $kitInk : $kitBase),
            Color::oklab($kitDarkFirst ? $kitBase : $kitInk),
            Color::oklab($newDarkFirst ? $newInk : $newBase),
            Color::oklab($newDarkFirst ? $newBase : $newInk),
        ];
        if (($kitDarkFirst ? $kitInk : $kitBase) !== ($newDarkFirst ? $newInk : $newBase) || ($kitDarkFirst ? $kitBase : $kitInk) !== ($newDarkFirst ? $newBase : $newInk)) {
            $this->identity = false;
        }
        $sig[] = 'ends>' . $kitBase . $kitInk . $newBase . $newInk;

        sort($sig);
        $this->key = substr(md5(implode('|', $sig) . '|v2'), 0, 10);
    }

    /**
     * From a kit palette and the palette now in use, slug by slug (theme.json
     * slugs: base, contrast, accent-1...). The custom design writes its
     * palette under the kit's slugs too, so this serves both paths.
     *
     * @param array  $kitPalette  slug => hex (the kit as shipped).
     * @param array  $sitePalette slug => hex (now).
     * @param string $otherHue    The design's second accent, or '' to pick one.
     *
     * @return self
     */
    public static function fromPalettes(array $kitPalette, array $sitePalette, $otherHue = '')
    {
        $pairs = [];
        foreach ($kitPalette as $slug => $hex) {
            if (isset($sitePalette[$slug]) && Color::hex($hex) !== '' && Color::hex($sitePalette[$slug]) !== '') {
                $pairs[Color::hex($hex)] = Color::hex($sitePalette[$slug]);
            }
        }

        $kitBase = isset($kitPalette['base']) ? $kitPalette['base'] : '#ffffff';
        $kitInk  = isset($kitPalette['contrast']) ? $kitPalette['contrast'] : '#000000';
        $newBase = isset($sitePalette['base']) ? $sitePalette['base'] : $kitBase;
        $newInk  = isset($sitePalette['contrast']) ? $sitePalette['contrast'] : $kitInk;

        // Without a named second accent: the most vivid new colour of a hue
        // clearly apart from the main replacement, if the palette changed at all.
        if (Color::hex($otherHue) === '' && $pairs && array_keys($pairs) !== array_values($pairs)) {
            $main = '';
            $best = 0.0;
            foreach ($pairs as $from => $to) {
                if ($to !== $from && Color::chroma($to) > $best) {
                    $best = Color::chroma($to);
                    $main = $to;
                }
            }
            $otherBest = 0.0;
            foreach ($pairs as $to) {
                $gap = $main === '' ? 0 : self::gap(Color::hue($to), Color::hue($main));
                if ($to !== $main && Color::chroma($to) >= 0.06 && $gap > 30 && Color::chroma($to) > $otherBest) {
                    $otherBest = Color::chroma($to);
                    $otherHue  = $to;
                }
            }
        }

        return new self($pairs, $kitBase, $kitInk, $newBase, $newInk, Color::hex($otherHue) !== '' ? $otherHue : '');
    }

    /**
     * @return bool Whether nothing would change.
     */
    public function isIdentity()
    {
        return $this->identity;
    }

    /**
     * @return string Short hash of the mapping (file names of recoloured art).
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * @param int $r 0..255.
     * @param int $g 0..255.
     * @param int $b 0..255.
     *
     * @return int[] [r, g, b] 0..255.
     */
    public function mapRgb($r, $g, $b)
    {
        return self::labToRgb($this->mapLab(self::rgbToLab($r, $g, $b)));
    }

    /**
     * @param string $hex #rrggbb.
     *
     * @return string #rrggbb.
     */
    public function mapHex($hex)
    {
        $rgb = Color::rgb(Color::hex($hex) ?: '#000000');

        return Color::fromRgb($this->mapRgb($rgb[0], $rgb[1], $rgb[2]));
    }

    /**
     * @param float[] $p OKLab pixel.
     *
     * @return float[] OKLab.
     */
    public function mapLab(array $p)
    {
        $c = self::c($p);

        // Neutral: keep lightness, carry the page / text tint change.
        list($kd, $kl, $nd, $nl) = $this->ends;
        $span = $kl[0] - $kd[0];
        $s    = abs($span) < 0.05 ? ($p[0] > 0.5 ? 1.0 : 0.0) : max(0.0, min(1.0, ($p[0] - $kd[0]) / $span));
        $da   = ($nd[1] - $kd[1]) * (1 - $s) + ($nl[1] - $kl[1]) * $s;
        $db   = ($nd[2] - $kd[2]) * (1 - $s) + ($nl[2] - $kl[2]) * $s;
        // A faint tint of its own fades as the pixel turns coloured, so the
        // edge of a recoloured shape carries no fringe of the old hue.
        $keep    = 1 - self::smooth(0.006, 0.03, $c);
        $neutral = [$p[0], $p[1] * $keep + $da, $p[2] * $keep + $db];

        $w = self::smooth(self::NEUTRAL, self::VIVID, $c);
        if ($w <= 0 || ! $this->refs) {
            return $neutral;
        }

        $h = self::h($p);

        // Weights by hue gap to each kit colour (soft nearest).
        $sum    = 0.0;
        $acc    = [0.0, 0.0, 0.0];
        $near   = 360.0;
        foreach ($this->refs as $ref) {
            $gap  = self::gap($h, $ref['kh']);
            $near = min($near, $gap);
            $wt   = 1 / pow(max(2.0, $gap), 4);
            $out  = self::follow($p, $c, $h, $ref['k'], $ref['kc'], $ref['t'], $ref['tc'], $ref['th'], $ref['kh']);
            $acc[0] += $out[0] * $wt;
            $acc[1] += $out[1] * $wt;
            $acc[2] += $out[2] * $wt;
            $sum    += $wt;
        }
        $coloured = [$acc[0] / $sum, $acc[1] / $sum, $acc[2] / $sum];

        if ($this->other !== null) {
            $f = self::smooth(self::FAR - 15, self::FAR + 15, $near);
            if ($f > 0) {
                $o   = $this->other;
                // A far hue keeps its own lightness and chroma, in the new hue.
                $tc  = min(0.32, $c * max(0.6, min(1.6, $o['tc'] / 0.15)));
                $l   = $p[0] + ($o['t'][0] - 0.65) * 0.5;
                $alt = [max(0.0, min(1.0, $l)), $tc * cos(deg2rad($o['th'])), $tc * sin(deg2rad($o['th']))];
                $coloured = self::lerp($coloured, $alt, $f);
            }
        }

        return self::lerp($neutral, $coloured, $w);
    }

    /**
     * A pixel following one kit colour to its replacement.
     */
    private static function follow(array $p, $c, $h, array $k, $kc, array $t, $tc, $th, $kh)
    {
        // Lightness scales piecewise ([0, kit] -> [0, new], [kit, 1] -> [new, 1]),
        // so shading towards black stays as deep as the black around it.
        $l = $p[0] <= $k[0]
            ? ($k[0] > 0.001 ? $p[0] * $t[0] / $k[0] : $t[0])
            : $t[0] + ($p[0] - $k[0]) * (1 - $t[0]) / max(0.001, 1 - $k[0]);
        $l = max(0.0, min(1.0, $l));
        // The replacement may be a neutral (a vivid kit colour turned grey): chroma follows.
        $ratio = $kc > 0.001 ? $tc / $kc : 1.0;
        $cc    = max(0.0, min(0.32, $c * $ratio));
        // The hue turns by the kit colour's turn (a pixel on the kit colour
        // lands on the replacement; one beside it stays beside it).
        $hh = $h + self::signedGap($th, $kh);

        return [$l, $cc * cos(deg2rad($hh)), $cc * sin(deg2rad($hh))];
    }

    /**
     * The lookup table: a Hald CLUT image's pixels (level N: N^2 steps per
     * channel, red fastest), as bytes RGBRGB...
     *
     * @param int $level Hald level (2..8).
     *
     * @return string
     */
    public function haldPixels($level = 6)
    {
        $n    = $level * $level;
        $out  = '';
        $step = 255 / ($n - 1);
        for ($b = 0; $b < $n; $b++) {
            for ($g = 0; $g < $n; $g++) {
                for ($r = 0; $r < $n; $r++) {
                    $rgb  = $this->mapRgb((int) round($r * $step), (int) round($g * $step), (int) round($b * $step));
                    $out .= chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]);
                }
            }
        }

        return $out;
    }

    /**
     * Writes a recoloured copy of an image.
     *
     * @param string $src  Source file (jpg, png, webp or svg).
     * @param string $dest Destination file (same type).
     *
     * @return bool
     */
    public function recolourFile($src, $dest)
    {
        $ext = strtolower(pathinfo((string) $src, PATHINFO_EXTENSION));
        if (! is_readable($src)) {
            return false;
        }

        if ($ext === 'svg') {
            $svg = (string) file_get_contents($src);
            $map = $this;
            $svg = (string) preg_replace_callback('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', static function ($m) use ($map) {
                return $map->mapHex($m[0]);
            }, $svg);

            return false !== file_put_contents($dest, $svg);
        }

        if (class_exists('\Imagick')) {
            try {
                $level = 6;
                $side  = $level * $level * $level;
                $clut  = new \Imagick();
                $clut->newImage($side, $side, 'black');
                $clut->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                $clut->importImagePixels(0, 0, $side, $side, 'RGB', \Imagick::PIXEL_CHAR, array_values(unpack('C*', $this->haldPixels($level))));
                $img = new \Imagick($src);
                $img->haldClutImage($clut);
                $img->stripImage();
                if (in_array($ext, ['jpg', 'jpeg'], true)) {
                    $img->setImageCompressionQuality(84);
                }
                $ok = $img->writeImage($dest);
                $img->clear();
                $clut->clear();

                return (bool) $ok && is_file($dest);
            } catch (\Exception $e) {
                // Fall through to GD.
            }
        }

        return $this->recolourGd($src, $dest, $ext);
    }

    /**
     * GD: every pixel through a 5-bit lookup table.
     */
    private function recolourGd($src, $dest, $ext)
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }
        $img = @imagecreatefromstring((string) file_get_contents($src)); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if (! $img) {
            return false;
        }
        imagepalettetotruecolor($img);
        $w   = imagesx($img);
        $h   = imagesy($img);
        $lut = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c   = imagecolorat($img, $x, $y);
                $key = (($c >> 19) & 31) << 10 | (($c >> 11) & 31) << 5 | (($c >> 3) & 31);
                if (! isset($lut[$key])) {
                    $rgb        = $this->mapRgb((($key >> 10) & 31) * 8 + 4, (($key >> 5) & 31) * 8 + 4, ($key & 31) * 8 + 4);
                    $lut[$key]  = ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2];
                }
                imagesetpixel($img, $x, $y, ($c & 0x7f000000) | $lut[$key]);
            }
        }
        if ($ext === 'png') {
            imagesavealpha($img, true);
            $ok = imagepng($img, $dest);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            $ok = imagewebp($img, $dest, 84);
        } else {
            $ok = imagejpeg($img, $dest, 84);
        }
        imagedestroy($img);

        return (bool) $ok;
    }

    /* ------------------------------------------------------------------ */

    private static function rgbToLab($r, $g, $b)
    {
        $lin = static function ($v) {
            $v /= 255;

            return $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };
        $r = $lin($r);
        $g = $lin($g);
        $b = $lin($b);
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        $l = $l > 0 ? pow($l, 1 / 3) : 0.0;
        $m = $m > 0 ? pow($m, 1 / 3) : 0.0;
        $s = $s > 0 ? pow($s, 1 / 3) : 0.0;

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    private static function labToRgb(array $lab)
    {
        list($L, $a, $b) = $lab;
        $l = pow($L + 0.3963377774 * $a + 0.2158037573 * $b, 3);
        $m = pow($L - 0.1055613458 * $a - 0.0638541728 * $b, 3);
        $s = pow($L - 0.0894841775 * $a - 1.2914855480 * $b, 3);
        $out = [];
        foreach ([
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ] as $c) {
            $c     = max(0.0, min(1.0, $c));
            $out[] = (int) round(255 * ($c <= 0.0031308 ? 12.92 * $c : 1.055 * pow($c, 1 / 2.4) - 0.055));
        }

        return $out;
    }

    private static function c(array $lab)
    {
        return sqrt($lab[1] * $lab[1] + $lab[2] * $lab[2]);
    }

    private static function h(array $lab)
    {
        $h = rad2deg(atan2($lab[2], $lab[1]));

        return $h < 0 ? $h + 360 : $h;
    }

    private static function gap($a, $b)
    {
        $d = abs($a - $b);

        return $d > 180 ? 360 - $d : $d;
    }

    private static function signedGap($a, $b)
    {
        $d = fmod($a - $b + 540, 360) - 180;

        return $d;
    }

    private static function smooth($e0, $e1, $x)
    {
        $t = max(0.0, min(1.0, ($x - $e0) / ($e1 - $e0)));

        return $t * $t * (3 - 2 * $t);
    }

    private static function lerp(array $a, array $b, $t)
    {
        return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t];
    }
}
