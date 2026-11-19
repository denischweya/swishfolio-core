<?php
/**
 * Checks the text-on-background pairs a compiled design really shows.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * DesignTokens::enforceContrast() makes the palette's own pairs readable,
 * but a kit's CSS puts colours together in ways the palette cannot see: a
 * call-to-action card on a dark gradient whose email link turns into the
 * accent (pink on plum), an eyebrow in the accent on an inverse band, links
 * on alternate surface bands. After the kit CSS is recoloured and the
 * overrides are written, this reads the result and appends rules that move
 * the failing colours just far enough to reach WCAG AA (4.5:1 for text,
 * 3:1 for headings):
 *
 *  - a "band" is any rule with an opaque background colour (or a gradient
 *    of them: every stop must pass), excluding buttons and hover states;
 *  - inside a band (its selector, its class, or BEM parts of its block):
 *    the kit's own link rules are asserted over the page-wide link colour,
 *    and every text colour that fails is nudged;
 *  - running-text links in a band that would show the page link colour
 *    (the accent) get the band's own link colour, or a nudged accent.
 *
 * Pure. contrast() and fix() are the helpers everything goes through.
 */
class ContrastGuard
{
    const LINK = ':not(.wp-block-button__link):not(.wp-element-button)';

    /**
     * @param string   $fg  Text colour #rrggbb.
     * @param string[] $bgs Background colour(s) #rrggbb (a gradient's stops).
     *
     * @return float The worst contrast over the backgrounds.
     */
    public static function contrast($fg, array $bgs)
    {
        $min = 21.0;
        foreach ($bgs as $bg) {
            $min = min($min, Color::contrast($fg, $bg));
        }

        return $min;
    }

    /**
     * The colour nearest $fg that reads on every background: $fg itself if it
     * already does, else nudged (hue kept) towards black or white.
     *
     * @param string   $fg    Text colour.
     * @param string[] $bgs   Background colour(s).
     * @param float    $ratio Contrast wanted.
     *
     * @return string #rrggbb.
     */
    public static function fix($fg, array $bgs, $ratio = Color::AA)
    {
        $fg = Color::hex($fg);
        if ($fg === '' || ! $bgs || self::contrast($fg, $bgs) >= $ratio) {
            return $fg;
        }
        $c = $fg;
        for ($i = 0; $i < 4 && self::contrast($c, $bgs) < $ratio; $i++) {
            $worst = $bgs[0];
            foreach ($bgs as $bg) {
                if (Color::contrast($c, $bg) < Color::contrast($c, $worst)) {
                    $worst = $bg;
                }
            }
            $c = Color::ensureContrast($c, $worst, $ratio);
        }
        if (self::contrast($c, $bgs) >= $ratio) {
            return $c;
        }

        return self::contrast('#ffffff', $bgs) >= self::contrast('#000000', $bgs) ? '#ffffff' : '#000000';
    }

    /**
     * Rules that fix what fails in a compiled stylesheet.
     *
     * @param string $css    Compiled CSS (kit rules and overrides).
     * @param array  $tokens Valid tokens.
     *
     * @return string Extra rules ('' when everything reads).
     */
    public static function css($css, array $tokens)
    {
        $css   = (string) preg_replace('#/\*.*?\*/#s', '', (string) $css);
        $rules = [];
        if (preg_match_all('/([^{};]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER)) {
            foreach ($m as $r) {
                $sel = trim($r[1]);
                if ($sel === '' || $sel[0] === '@' || stripos($sel, 'font-face') !== false) {
                    continue;
                }
                $rules[] = ['sel' => $sel, 'decls' => self::declarations($r[2])];
            }
        }

        // Custom properties (last one wins, as in the cascade for body-level vars).
        $vars = [];
        foreach ($rules as $rule) {
            foreach ($rule['decls'] as $prop => $value) {
                if (strpos($prop, '--') === 0) {
                    $vars[$prop] = $value;
                }
            }
        }

        $accent = $tokens['links'] === 'plain' ? '' : $tokens['palette']['accent'];
        $out    = [];

        foreach ($rules as $band) {
            $stops = self::background($band['decls'], $vars);
            if (! $stops) {
                continue;
            }
            foreach (self::split($band['sel']) as $scope) {
                if (preg_match('/::?(before|after|hover|focus|active|placeholder|selection)\b|button|btn|wp-element-button/i', $scope)) {
                    continue;
                }
                $keys     = self::keys($scope);
                // Only a band that sets its own text colour (a real contrasting
                // card) claims its BEM siblings; a media wrapper does not.
                if ($keys && ! isset($band['decls']['color'])) {
                    $keys['block'] = '';
                }
                $linkFrom = '';

                // The page itself (body, main) is the palette's pair, already
                // checked; what sits on it may be over a photo.
                foreach ($keys ? $rules : [] as $rule) {
                    if ($rule === $band || ! isset($rule['decls']['color']) || self::background($rule['decls'], $vars)) {
                        continue;
                    }
                    foreach (self::split($rule['sel']) as $sel) {
                        if (preg_match('/::?(hover|focus|active|before|after|placeholder|selection)\b|button|btn|wp-element-button|aria-hidden/i', $sel) || ! self::inside($sel, $scope, $keys)) {
                            continue;
                        }
                        $colour = self::resolve($rule['decls']['color'], $vars);
                        if ($colour === null) {
                            continue;
                        }
                        $isLink = (bool) preg_match('/(^|[\s>+~(,])a(?=$|[\s:.\[)])/', $sel);
                        $ratio  = CssCompiler::isHeadingSelector($sel) ? Color::AA_LARGE : Color::AA;
                        $fixed  = self::fix($colour, $stops, $ratio);
                        if ($isLink) {
                            $linkFrom = $linkFrom !== '' ? $linkFrom : $fixed;
                            // The kit's own link colour, asserted over the page-wide one.
                            $out[] = ':root ' . $sel . self::LINK . '{color:' . $fixed . '}';
                        } elseif ($fixed !== $colour) {
                            $out[] = ':root ' . $sel . '{color:' . $fixed . '}';
                        }
                    }
                }

                // Running-text links that would show the accent on this band.
                if ($accent !== '' && self::contrast($accent, $stops) < Color::AA) {
                    $colour = $linkFrom !== '' ? $linkFrom : self::fix($accent, $stops);
                    $out[]  = ':root ' . $scope . ' :is(p,li) a' . self::LINK . '{color:' . $colour . '}';
                }
            }
        }

        $out = array_values(array_unique($out));

        return $out ? "\n/* contrast guard */\n" . implode("\n", $out) . "\n" : '';
    }

    /**
     * @return array prop => value (lower-case props, last wins).
     */
    private static function declarations($body)
    {
        $out = [];
        foreach (explode(';', (string) $body) as $decl) {
            if (strpos($decl, ':') === false) {
                continue;
            }
            list($prop, $value) = array_map('trim', explode(':', $decl, 2));
            $out[strtolower($prop)] = trim((string) preg_replace('/!\s*important\s*$/i', '', $value));
        }

        return $out;
    }

    /**
     * @return string[] Opaque background colours of a rule ([] for none / see-through / image).
     */
    private static function background(array $decls, array $vars)
    {
        foreach (['background-color', 'background', 'background-image'] as $prop) {
            if (! isset($decls[$prop])) {
                continue;
            }
            $value = self::expand($decls[$prop], $vars);
            if (stripos($value, 'url(') !== false) {
                return [];
            }
            if (! preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $value, $m)) {
                continue;
            }
            $stops = [];
            foreach ($m[0] as $raw) {
                $c = Color::parseCss($raw);
                if ($c === null || $c['alpha'] < 0.9) {
                    return [];
                }
                $stops[] = $c['hex'];
            }

            return array_values(array_unique($stops));
        }

        return [];
    }

    /**
     * @return string|null A colour value resolved to #rrggbb (opaque), or null.
     */
    private static function resolve($value, array $vars)
    {
        $c = Color::parseCss(trim(self::expand($value, $vars)));

        return $c === null || $c['alpha'] < 0.9 ? null : $c['hex'];
    }

    /**
     * var(--x) replaced by its value (twice, for one level of indirection).
     */
    private static function expand($value, array $vars)
    {
        for ($i = 0; $i < 3; $i++) {
            $value = (string) preg_replace_callback('/var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*([^()]*))?\)/i', static function ($m) use ($vars) {
                $key = strtolower($m[1]);

                return isset($vars[$key]) ? $vars[$key] : (isset($m[2]) ? $m[2] : '');
            }, (string) $value);
        }

        return $value;
    }

    /**
     * @return string[] Classes that mark something as part of the band: its
     *                  last class, and the BEM block of it ("ym-cta" of "ym-cta__card").
     */
    private static function keys($scope)
    {
        if (! preg_match_all('/\.([a-z0-9_-]+)/i', $scope, $m)) {
            return [];
        }
        $last = end($m[1]);
        $keys = ['class' => $last, 'block' => ''];
        if (strpos($last, '__') !== false) {
            $keys['block'] = substr($last, 0, strpos($last, '__'));
        }

        return $keys;
    }

    /**
     * Whether a selector styles something inside the band.
     */
    private static function inside($sel, $scope, array $keys)
    {
        if (strpos($sel, $scope . ' ') === 0) {
            return true;
        }
        if (! $keys) {
            return false;
        }
        $class = preg_quote($keys['class'], '/');
        if (preg_match('/\.' . $class . '(?:__[a-z0-9_-]+)?[^\s,]*\s+\S/i', $sel) || preg_match('/\.' . $class . '__[a-z0-9_-]/i', $sel)) {
            return true;
        }
        if ($keys['block'] !== '') {
            $block = preg_quote($keys['block'], '/');

            // The block's other parts (ym-cta__direct beside ym-cta__card), not the outer wrapper alone.
            return (bool) preg_match('/\.' . $block . '__(?!' . preg_quote(substr($keys['class'], strlen($keys['block']) + 2), '/') . '\b)[a-z0-9_-]+/i', $sel);
        }

        return false;
    }

    /**
     * Top-level comma split (not inside :is() or :not()).
     *
     * @return string[]
     */
    private static function split($selector)
    {
        $out   = [];
        $depth = 0;
        $cur   = '';
        $len   = strlen($selector);
        for ($i = 0; $i < $len; $i++) {
            $ch = $selector[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($ch === ',' && $depth === 0) {
                $out[] = trim($cur);
                $cur   = '';
                continue;
            }
            $cur .= $ch;
        }
        $out[] = trim($cur);

        return array_values(array_filter($out, 'strlen'));
    }
}
