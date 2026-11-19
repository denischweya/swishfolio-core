<?php
/**
 * Maps every colour of a template kit onto a custom design's palette.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * A kit's CSS and markup use dozens of literal colours (its palette, tints of
 * it, white on buttons, translucent hairlines). Each is classified by the
 * role it plays in the kit, measured against the kit's own page background
 * and text colour, and replaced by the custom palette's colour for that role:
 *
 *   near the page background   -> background
 *   a step towards the text    -> surface (alternate bands, hairlines, cards)
 *   half way                   -> muted (secondary text)
 *   near the text colour       -> text (also dark bands, which then carry the
 *                                 page colour as text: an inverse band)
 *   dark and coloured          -> inverse
 *   vivid                      -> accent or accent2, by hue, keeping how much
 *                                 lighter or darker the kit's tint was
 *
 * The position is measured on OKLab lightness between the kit's background
 * and text, so it works whichever way round either design is (a light kit
 * into a dark design flips cleanly). Alpha is kept. Pure.
 */
class ColorRemap
{
    /** OKLCH chroma above which a kit colour is an accent, not a neutral. */
    const VIVID = 0.075;

    /** @var string */
    private $base;

    /** @var string */
    private $ink;

    /** @var array[] kit accents: [hex, role] */
    private $anchors = [];

    /** @var array */
    private $palette;

    /** @var array<string,string> */
    private $cache = [];

    /**
     * @param array  $kitPalette slug => hex of the kit's theme palette.
     * @param string $kitBase    Kit page background.
     * @param string $kitInk     Kit body text.
     * @param array  $palette    Validated custom palette (DesignTokens).
     */
    public function __construct(array $kitPalette, $kitBase, $kitInk, array $palette)
    {
        $this->base    = Color::hex($kitBase) ?: '#ffffff';
        $this->ink     = Color::hex($kitInk) ?: '#000000';
        $this->palette = $palette;

        // The kit's vivid colours, most vivid first: the first is its main
        // accent, the next of a clearly different hue its second.
        $vivid = [];
        foreach ($kitPalette as $hex) {
            $hex = Color::hex($hex);
            if ($hex !== '' && Color::chroma($hex) >= self::VIVID && Color::lightness($hex) >= 0.4) {
                $vivid[$hex] = Color::chroma($hex);
            }
        }
        arsort($vivid);
        foreach (array_keys($vivid) as $hex) {
            if (! $this->anchors) {
                $this->anchors[] = [$hex, 'accent'];
            } elseif (count($this->anchors) === 1 && self::hueGap(Color::hue($hex), Color::hue($this->anchors[0][0])) > 40) {
                $this->anchors[] = [$hex, 'accent2'];
            }
        }
    }

    /**
     * @param string $hex Kit colour #rrggbb.
     *
     * @return string Custom design colour #rrggbb.
     */
    public function map($hex)
    {
        $hex = Color::hex($hex);
        if ($hex === '') {
            return '';
        }
        if (! isset($this->cache[$hex])) {
            $this->cache[$hex] = $this->compute($hex);
        }

        return $this->cache[$hex];
    }

    /**
     * Whether the design is the other way round from the kit (a light kit
     * under a dark design, or the reverse).
     *
     * @return bool
     */
    public function flips()
    {
        return (Color::lightness($this->base) > 0.5) !== (Color::lightness($this->palette['background']) > 0.5);
    }

    /**
     * @param string $hex Kit colour.
     *
     * @return string Role name (palette key).
     */
    public function role($hex)
    {
        $hex = Color::hex($hex);
        $lb  = Color::lightness($this->base);
        $lt  = Color::lightness($this->ink);
        $l   = Color::lightness($hex);
        $t   = abs($lt - $lb) < 0.05 ? ($l > 0.5 ? 0.0 : 1.0) : ($l - $lb) / ($lt - $lb);
        $c   = Color::chroma($hex);

        if ($hex === $this->base) {
            return 'background';
        }
        if ($hex === $this->ink) {
            return 'text';
        }
        if ($c >= self::VIVID) {
            // Deep and coloured (oxblood, forest, navy bands): an inverse band.
            if ($l < 0.42 && $t > 0.55) {
                return 'inverse';
            }
            if ($t > 0.12 && $t < 0.95 || $l >= 0.4) {
                return $this->nearestAnchor($hex);
            }
        }
        if ($t <= 0.03) {
            return 'background';
        }
        if ($t <= 0.32) {
            return $c >= 0.03 ? 'soft' : 'surface';
        }
        if ($t <= 0.78) {
            return 'muted';
        }

        return $c >= 0.04 && $l < 0.42 ? 'inverse' : 'text';
    }

    /**
     * @param string $hex Kit colour.
     *
     * @return string
     */
    private function compute($hex)
    {
        $role = $this->role($hex);
        $to   = $this->palette[$role];

        // Tints and shades of an accent keep their offset from the anchor.
        if (($role === 'accent' || $role === 'accent2') && $this->anchors) {
            foreach ($this->anchors as $anchor) {
                if ($anchor[1] === $role) {
                    $delta = Color::lightness($hex) - Color::lightness($anchor[0]);
                    // A light kit's tint for its dark bands is a shade in a dark design.
                    if ((Color::lightness($this->base) > 0.5) !== (Color::lightness($this->palette['background']) > 0.5)) {
                        $delta = -$delta;
                    }
                    if (abs($delta) > 0.02) {
                        return Color::withLightness($to, Color::lightness($to) + $delta);
                    }
                }
            }
        }

        return $to;
    }

    private function nearestAnchor($hex)
    {
        if (! $this->anchors) {
            return 'accent';
        }
        $best = $this->anchors[0];
        $gap  = 999;
        foreach ($this->anchors as $anchor) {
            $g = self::hueGap(Color::hue($hex), Color::hue($anchor[0]));
            if ($g < $gap) {
                $gap  = $g;
                $best = $anchor;
            }
        }

        return $best[1];
    }

    private static function hueGap($a, $b)
    {
        $d = abs($a - $b);

        return $d > 180 ? 360 - $d : $d;
    }
}
