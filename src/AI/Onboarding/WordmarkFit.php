<?php
/**
 * Fits a kit's giant footer wordmark to the owner's business name.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Some kits end the footer on the brand name set enormous and on one line
 * (white-space:nowrap, a font size in tens of vw), sized for a short demo
 * name such as "Lumbre." A longer name ("Grind Lagos Roasters.") runs off
 * the page and is clipped. CSS cannot measure text, so the size is worked
 * out from the name's length: the element gets an inline font-size of
 *
 *   min(<the kit's own size>, calc(min(100vw - 3rem, 84rem) / (chars * 0.56)))
 *
 * which keeps the kit's size whenever the name fits and shrinks it just
 * enough when it does not. 0.56em is a generous average glyph width (wide
 * display faces included), so the name always fits rather than nearly fits.
 * Re-running recomputes it. Pure.
 */
class WordmarkFit
{
    /** Average glyph width, in em, with room to spare. */
    const GLYPH = 0.56;

    const MARKER = '--sf-fit';

    /**
     * Giant one-line type in a kit's CSS: top-level rules for a single
     * class with white-space:nowrap and a display-sized font.
     *
     * @param string $css Kit CSS.
     *
     * @return array<string,string> class => font-size value.
     */
    public static function sizes($css)
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) $css);
        // Top level only: drop at-rule blocks (media queries hold the narrow variants).
        $flat = '';
        $depth = 0;
        $len   = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $ch = $css[$i];
            if ($ch === '@') {
                $open = strpos($css, '{', $i);
                if ($open === false) {
                    break;
                }
                $d = 0;
                for ($j = $open; $j < $len; $j++) {
                    $d += $css[$j] === '{' ? 1 : ($css[$j] === '}' ? -1 : 0);
                    if ($d === 0) {
                        break;
                    }
                }
                $i = $j;
                continue;
            }
            $flat .= $ch;
        }

        $out = [];
        if (preg_match_all('/(?:^|(?<=\}))\s*\.([a-z0-9_-]+)\s*\{([^}]*)\}/i', $flat, $m, PREG_SET_ORDER)) {
            foreach ($m as $rule) {
                if (! preg_match('/white-space\s*:\s*nowrap/i', $rule[2])) {
                    continue;
                }
                // font-size, or the size inside a "font" shorthand (400 clamp(...)/.8 serif).
                if (! preg_match('/font-size\s*:\s*([^;]+)/i', $rule[2], $fs)
                    && ! preg_match('/(?:^|;)\s*font\s*:\s*(?:[a-z0-9-]+\s+)*?((?:clamp|min|max)\([^)]*\)|\d*\.?\d+(?:rem|vw|px|em))\s*\//i', $rule[2], $fs)) {
                    continue;
                }
                $size = trim($fs[1]);
                $big  = false;
                if (preg_match_all('/(\d*\.?\d+)(vw|rem)\b/', $size, $n, PREG_SET_ORDER)) {
                    foreach ($n as $v) {
                        $big = $big || ($v[2] === 'vw' ? (float) $v[1] >= 12 : (float) $v[1] >= 9);
                    }
                }
                if ($big && strpos($size, 'var(') === false) {
                    $out[$rule[1]] = $size;
                }
            }
        }

        return $out;
    }

    /**
     * @param string $markup Header or footer markup.
     * @param array  $sizes  sizes().
     *
     * @return string
     */
    public static function apply($markup, array $sizes)
    {
        if (! $sizes) {
            return (string) $markup;
        }
        $classes = implode('|', array_map(static function ($c) {
            return preg_quote($c, '/');
        }, array_keys($sizes)));

        return (string) preg_replace_callback('/<(p|div|span|h[1-6]|a)\b([^>]*\bclass="[^"]*\b(' . $classes . ')\b[^"]*"[^>]*)>(.*?)<\/\1>/s', static function ($m) use ($sizes) {
            $chars = mb_strlen(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[4]), ENT_QUOTES, 'UTF-8'))));
            if ($chars < 1) {
                return $m[0];
            }
            $fit   = 'font-size:min(' . $sizes[$m[3]] . ',calc(min(100vw - 3rem,84rem) / ' . rtrim(rtrim(number_format($chars * self::GLYPH, 2, '.', ''), '0'), '.') . '));' . self::MARKER . ':' . $chars;
            $attrs = $m[2];
            if (preg_match('/\sstyle="([^"]*)"/', $attrs, $st)) {
                // Drop an earlier fit, keep anything else.
                $rest  = trim((string) preg_replace('/font-size:min\([^;]*\);' . preg_quote(self::MARKER, '/') . ':\d+;?/', '', $st[1]), '; ');
                $attrs = str_replace($st[0], ' style="' . $fit . ($rest !== '' ? ';' . $rest : '') . '"', $attrs);
            } else {
                $attrs .= ' style="' . $fit . '"';
            }

            return '<' . $m[1] . $attrs . '>' . $m[4] . '</' . $m[1] . '>';
        }, (string) $markup);
    }

    /**
     * The current site's kit wordmark sizes (empty when its kit has none).
     *
     * @return array
     */
    public static function forSite()
    {
        $library = \SwishfolioCore\AI\Generation\PatternLibrary::shipped();
        $slug    = $library->current();
        $kit     = $slug !== null ? $library->kit($slug) : null;
        if ($kit === null) {
            return [];
        }
        $design = $kit->getDesignFile();

        return self::sizes(isset($design['globalStyles']['styles']['css']) ? (string) $design['globalStyles']['styles']['css'] : '');
    }
}
