<?php
/**
 * The small block of polish CSS a custom design may carry.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * The custom-design model may add a few rules of its own (section rhythm,
 * button shape, a heading treatment) on top of the compiled tokens. That CSS
 * comes from a model, so it is held to a strict subset before it is stored
 * in the site's global styles:
 *
 *  - at most MAX_BYTES; plain rules only (no at-rules, no nesting, no
 *    comments kept, no escapes, no markup);
 *  - every selector names one of the kit's classes (its prefix, e.g. .oz-)
 *    and is scoped under body;
 *  - only the properties in PROPS; colours only as the design's own
 *    variables (var(--sf-*)), currentColor, transparent or inherit, so the
 *    palette and its contrast checks stay in charge;
 *  - values from a small character set, functions only from FUNCS: no
 *    url(), image-set(), expression() or attr().
 *
 * Anything that looks like an injection attempt rejects the whole block (an
 * empty string comes back); a rule or declaration that is merely outside the
 * subset is dropped. The result still goes through CssCompiler::sanitizeCss().
 */
class PolishCss
{
    const MAX_BYTES = 4096;

    const PROPS = [
        'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'margin-block', 'margin-inline',
        'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right', 'padding-block', 'padding-inline',
        'gap', 'row-gap', 'column-gap',
        'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-width', 'border-style', 'border-color',
        'border-radius', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius',
        'outline', 'outline-offset',
        'font-size', 'font-weight', 'font-style', 'letter-spacing', 'line-height', 'text-transform', 'text-align',
        'text-decoration', 'text-decoration-line', 'text-decoration-thickness', 'text-decoration-color', 'text-underline-offset',
        'max-width', 'min-height', 'aspect-ratio', 'object-fit',
        'color', 'background-color', 'box-shadow', 'opacity', 'transition',
    ];

    /** Properties whose value is a colour: design variables only. */
    const COLOR_PROPS = ['color', 'background-color', 'border-color', 'text-decoration-color'];

    const FUNCS = ['var', 'calc', 'clamp', 'min', 'max', 'cubic-bezier'];

    /** Bare words a value may hold (besides property names, for transition). */
    const KEYWORDS = [
        'solid', 'dashed', 'dotted', 'double', 'none', 'inset', 'auto', 'currentcolor', 'transparent', 'inherit', 'initial', 'unset',
        'underline', 'overline', 'line-through', 'wavy', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear', 'all', 'transform',
        'normal', 'bold', 'bolder', 'lighter', 'italic', 'uppercase', 'lowercase', 'capitalize', 'left', 'right', 'center', 'justify',
        'start', 'end', 'cover', 'contain', 'fill', 'scale-down', 'from-font',
    ];

    /**
     * @param mixed  $css    The model's CSS.
     * @param string $prefix Kit class prefix (oz-). Without one nothing is kept.
     *
     * @return string Safe CSS, or '' when nothing (or something unsafe) was given.
     */
    public static function clean($css, $prefix)
    {
        $css    = is_string($css) ? $css : '';
        $prefix = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $prefix));

        if (trim($css) === '' || $prefix === '' || strlen($css) > self::MAX_BYTES) {
            return '';
        }

        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        // Anything that can load, run or escape: the whole block goes.
        if (preg_match('#@|url\s*\(|image-set|expression|javascript:|vbscript:|data:|behavior|-moz-binding|\\\\|<|&|attr\s*\(|/\*|\*/|position\s*:\s*(fixed|sticky)#i', $css)) {
            return '';
        }

        $out   = [];
        $len   = strlen($css);
        $pos   = 0;
        while ($pos < $len) {
            $open = strpos($css, '{', $pos);
            if ($open === false) {
                if (trim(substr($css, $pos)) !== '') {
                    return '';
                }
                break;
            }
            $close = strpos($css, '}', $open);
            if ($close === false) {
                return '';
            }
            $selector = trim(substr($css, $pos, $open - $pos));
            $body     = substr($css, $open + 1, $close - $open - 1);
            $pos      = $close + 1;
            // Nesting or a stray brace.
            if (strpos($body, '{') !== false || strpos($selector, '}') !== false) {
                return '';
            }

            $selector = self::selector($selector, $prefix);
            $decls    = self::declarations($body);
            if ($selector !== '' && $decls !== '') {
                $out[] = $selector . '{' . $decls . '}';
            }
        }

        $result = implode("\n", $out);
        if ($result === '') {
            return '';
        }

        try {
            return CssCompiler::sanitizeCss($result, []);
        } catch (\UnexpectedValueException $e) {
            return '';
        }
    }

    /**
     * @return string The selector list scoped under body, or '' when any part is not the kit's.
     */
    private static function selector($selector, $prefix)
    {
        if ($selector === '' || strlen($selector) > 300 || ! preg_match('/^[a-z0-9_\-.\s>+~:(),*\[\]="]+$/i', $selector)) {
            return '';
        }
        $parts = [];
        foreach (explode(',', $selector) as $part) {
            $part = trim((string) preg_replace('/\s+/', ' ', $part));
            // Must name a kit class; html / :root never.
            if ($part === '' || ! preg_match('/\.' . preg_quote($prefix, '/') . '[a-z0-9_-]/i', $part) || preg_match('/(^|[\s>+~])(html|:root)\b/i', $part)) {
                return '';
            }
            $parts[] = preg_match('/^body[\s.:\[]/i', $part) ? $part : 'body ' . $part;
        }

        return implode(',', $parts);
    }

    /**
     * @return string Declarations within the subset (others dropped).
     */
    private static function declarations($body)
    {
        $out = [];
        foreach (explode(';', (string) $body) as $decl) {
            if (trim($decl) === '') {
                continue;
            }
            if (strpos($decl, ':') === false) {
                continue;
            }
            list($prop, $value) = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! in_array($prop, self::PROPS, true) || $value === '' || strlen($value) > 200) {
                continue;
            }
            $important = (bool) preg_match('/!\s*important\s*$/i', $value);
            $value     = trim((string) preg_replace('/!\s*important\s*$/i', '', $value));
            if (! preg_match('/^[a-z0-9#%.,\s()\-+*\/]+$/i', $value)) {
                continue;
            }
            if (preg_match_all('/([a-z-]+)\s*\(/i', $value, $fn)) {
                foreach ($fn[1] as $name) {
                    if (! in_array(strtolower($name), self::FUNCS, true)) {
                        continue 2;
                    }
                }
            }
            // Only the design's own custom properties.
            if (preg_match_all('/var\(\s*([^),\s]+)/i', $value, $vars)) {
                foreach ($vars[1] as $var) {
                    if (! preg_match('/^--sf-[a-z0-9-]+$/', $var)) {
                        continue 2;
                    }
                }
            }
            if (! self::colourSafe($prop, $value)) {
                continue;
            }
            $out[] = $prop . ':' . $value . ($important ? '!important' : '');
        }

        return implode(';', $out);
    }

    /**
     * Colours only as design variables: no literal hex, rgb() or names.
     */
    private static function colourSafe($prop, $value)
    {
        if (preg_match('/#[0-9a-f]{3,8}\b|\b(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\s*\(/i', $value)) {
            return false;
        }
        if (in_array($prop, self::COLOR_PROPS, true)) {
            return (bool) preg_match('/^(var\(--sf-[a-z0-9-]+\)|currentcolor|transparent|inherit)$/i', $value);
        }

        // Every bare word is a known keyword (so no named colour hides in a shorthand).
        $bare = (string) preg_replace('/var\([^)]*\)/i', ' ', $value);
        if (preg_match_all('/(?<![\w.#-])([a-z][a-z-]*)(?![a-z-]|\s*\()/i', $bare, $words)) {
            foreach ($words[1] as $word) {
                if (! in_array(strtolower($word), self::KEYWORDS, true) && ! in_array(strtolower($word), self::PROPS, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
