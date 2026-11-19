<?php
/**
 * Compiles a custom design's tokens into CSS for one structural kit.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * Two layers, both built only from validated tokens:
 *
 * 1. The kit's own CSS (template-authored, from the plugin's kit files),
 *    rewritten value by value: every colour through ColorRemap, every font
 *    stack to the custom display / body / label font, radii to the custom
 *    shape, shadows, border widths, heading weight, case, tracking and size,
 *    label case, and section spacing. The kit's @font-face rules are dropped
 *    and @import is never kept. Selectors and layout stay, so the kit's
 *    sections still work; its look does not survive.
 * 2. An override layer: custom properties (--sf-*), base type and colour,
 *    buttons, links, cards, section treatment, image treatment and
 *    decoration, written from enumerated options with selectors scoped to
 *    the page (body ...).
 *
 * sanitize() is the last step for anything this class returns: it refuses
 * url() other than the catalogue's font files and inline SVG data, and any
 * @import, expression(), javascript:, behavior or a closing style tag.
 */
class CssCompiler
{
    /** Marker at the top of a compiled stylesheet. */
    const MARKER = '/* swish-custom-design */';

    /** @var array */
    private $tokens;

    /** @var ColorRemap */
    private $remap;

    /** @var string */
    private $kitDisplay;

    /** @var string */
    private $kitBody;

    /** @var string */
    private $prefix;

    /** @var string */
    private $pluginBase;

    /** @var string */
    private $themeBase;

    /**
     * @param array      $tokens     DesignTokens::validate() output.
     * @param ColorRemap $remap      Kit colour mapping.
     * @param string     $kitDisplay Kit heading family (first name of its stack).
     * @param string     $kitBody    Kit body family.
     * @param string     $prefix     Kit class prefix (lu-), may be ''.
     * @param string     $pluginBase This plugin's URL.
     * @param string     $themeBase  Theme URL.
     */
    public function __construct(array $tokens, ColorRemap $remap, $kitDisplay, $kitBody, $prefix, $pluginBase, $themeBase)
    {
        $this->tokens     = $tokens;
        $this->remap      = $remap;
        $this->kitDisplay = strtolower(trim((string) $kitDisplay));
        $this->kitBody    = strtolower(trim((string) $kitBody));
        $this->prefix     = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $prefix));
        $this->pluginBase = (string) $pluginBase;
        $this->themeBase  = (string) $themeBase;
    }

    /**
     * @param string $kitCss The kit's custom CSS.
     *
     * @return string Sanitised stylesheet.
     */
    public function compile($kitCss)
    {
        $f   = $this->tokens['fonts'];
        $css = self::MARKER . "\n"
            . FontCatalogue::fontFaces([$f['display'], $f['body'], $f['label']], $this->pluginBase, $this->themeBase)
            . $this->transform((string) $kitCss) . "\n"
            . $this->overrides();

        return $this->sanitize($css);
    }

    /* ------------------------------------------------------------------
     * Layer 1: the kit's CSS, rewritten.
     * ---------------------------------------------------------------- */

    /**
     * @param string $css Kit CSS.
     *
     * @return string
     */
    public function transform($css)
    {
        $css = preg_replace('#/\*.*?\*/#s', '', (string) $css);
        $this->findBands((string) $css);

        return $this->block($css, '');
    }

    /** @var array<string,string> Kit custom property => hex. */
    private $kitVars = [];

    /** @var string[] Class names of the kit's inverse bands (when the design flips). */
    private $bands = [];

    /**
     * Inverse bands: a light kit's dark footer or section (background in the
     * kit's text colour, words in its page colour). Under a design the other
     * way round they would come out as the one light block on a dark site,
     * which reads as a colour nobody mapped. So under a flipped design a band
     * stays a band in the design's own terms: the raised surface colour with
     * the design's text, and inside it (its rule and every rule naming its
     * class or its __parts) page and text colours trade places.
     *
     * @param string $css Kit CSS, comments removed.
     *
     * @return void
     */
    private function findBands($css)
    {
        $this->kitVars = [];
        $this->bands   = [];
        if (preg_match_all('/(--[a-z0-9_-]+)\s*:\s*(#[0-9a-fA-F]{3,8})\b/', $css, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                $this->kitVars[strtolower($v[1])] = Color::hex($v[2]);
            }
        }
        if (! $this->remap->flips()) {
            return;
        }
        if (preg_match_all('/([^{};]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER)) {
            foreach ($rules as $rule) {
                if (! preg_match('/(?:^|;)\s*background(?:-color)?\s*:\s*([^;]+)/', $rule[2], $bg) || ! preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/', $rule[2], $fg)) {
                    continue;
                }
                if ($this->roleOf($bg[1]) === 'text' && in_array($this->roleOf($fg[1], true), ['background', 'surface', 'soft'], true)
                    && preg_match('/\.([a-z0-9-]+)\s*$/i', trim($rule[1]), $cls)) {
                    $this->bands[] = $cls[1];
                }
            }
        }
        $this->bands = array_values(array_unique($this->bands));
    }

    /**
     * @param string $value       A colour value: a literal or var(--kit-var).
     * @param bool   $translucent Whether a see-through colour counts (text may be).
     *
     * @return string Its kit role, or ''.
     */
    private function roleOf($value, $translucent = false)
    {
        $value = trim((string) $value);
        if (preg_match('/^var\((--[a-z0-9_-]+)\)$/i', $value, $m)) {
            $value = isset($this->kitVars[strtolower($m[1])]) ? $this->kitVars[strtolower($m[1])] : '';
        }
        $c = Color::parseCss($value);

        return $c === null || ($c['alpha'] < 1 && ! $translucent) ? '' : $this->remap->role($c['hex']);
    }

    /**
     * @param string $selector Selector.
     *
     * @return bool Whether it styles an inverse band or something inside one.
     */
    private function inBand($selector)
    {
        foreach ($this->bands as $band) {
            if (preg_match('/\.' . preg_quote($band, '/') . '(?![a-z0-9-])|\.' . preg_quote($band, '/') . '__/i', (string) $selector)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inside an un-inverted band: page and text colours trade places, and the
     * band's own background is the design's surface.
     *
     * @param string $prop     Property.
     * @param string $value    Original value.
     * @param string $selector Selector.
     *
     * @return string|null The rewritten value, or null to use the normal path.
     */
    private function bandValue($prop, $value, $selector)
    {
        if (! $this->bands || ! $this->inBand($selector)) {
            return null;
        }
        $p = $this->tokens['palette'];
        if (($prop === 'background' || $prop === 'background-color') && $this->roleOf($value) === 'text') {
            return 'var(--sf-surface,' . $p['surface'] . ')';
        }
        $vars  = $this->kitVars;
        $remap = $this->remap;
        $swap  = static function ($hex) use ($remap, $p) {
            $role = $remap->role($hex);

            return $role === 'background' ? $p['text'] : ($role === 'text' ? $p['surface'] : ($role === 'muted' ? $p['muted'] : null));
        };
        $out = (string) preg_replace_callback('/var\((--[a-z0-9_-]+)\)/i', static function ($m) use ($vars, $swap) {
            $hex = isset($vars[strtolower($m[1])]) ? $vars[strtolower($m[1])] : '';
            $to  = $hex !== '' ? $swap($hex) : null;

            return $to === null ? $m[0] : $to;
        }, (string) $value);
        $out = (string) preg_replace_callback('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', static function ($m) use ($swap) {
            $c  = Color::parseCss($m[0]);
            $to = $c === null ? null : $swap($c['hex']);

            return $to === null ? $m[0] : Color::toCss($to, $c['alpha']);
        }, $out);

        return $out === $value ? null : $out;
    }

    /**
     * Rules at one nesting level.
     *
     * @param string $css    Rules.
     * @param string $parent Enclosing at-rule prelude ('' at top level).
     *
     * @return string
     */
    private function block($css, $parent)
    {
        $out = '';
        $len = strlen($css);
        $i   = 0;

        while ($i < $len) {
            $open = $this->find($css, '{', $i);
            $semi = $this->find($css, ';', $i);

            // A statement at-rule (@import, @charset) before the next block: dropped.
            if ($semi !== false && ($open === false || $semi < $open)) {
                $i = $semi + 1;
                continue;
            }
            if ($open === false) {
                break;
            }

            $prelude = trim(substr($css, $i, $open - $i));
            $close   = $this->matching($css, $open);
            $body    = substr($css, $open + 1, $close - $open - 1);
            $i       = $close + 1;
            $at      = strtolower((string) preg_replace('/^@(-webkit-)?([a-z-]+).*$/s', '$2', $prelude));

            if ($prelude === '') {
                continue;
            }
            if ($prelude[0] === '@') {
                if (in_array($at, ['media', 'supports', 'container', 'layer', 'keyframes'], true)) {
                    $inner = $this->block($body, $prelude);
                    if (trim($inner) !== '') {
                        $out .= $prelude . '{' . $inner . '}' . "\n";
                    }
                }
                // @font-face, @import, @page and the rest: dropped.
                continue;
            }

            $decls = $this->declarations($body, $prelude, strpos($parent, 'keyframes') !== false);
            if ($decls !== '') {
                $out .= $prelude . '{' . $decls . '}' . "\n";
            }
        }

        return $out;
    }

    /**
     * @param string $body     Declarations.
     * @param string $selector Rule selector.
     * @param bool   $frames   Inside @keyframes.
     *
     * @return string
     */
    private function declarations($body, $selector, $frames)
    {
        $out = [];

        foreach ($this->split($body, ';') as $decl) {
            $colon = strpos($decl, ':');
            if ($colon === false) {
                continue;
            }
            $prop  = strtolower(trim(substr($decl, 0, $colon)));
            $value = trim(substr($decl, $colon + 1));
            if ($prop === '' || $value === '' || ! preg_match('/^(--)?[a-z0-9-]+$/', $prop)) {
                continue;
            }
            $value = $this->value($prop, $value, $selector, $frames);
            if ($value !== '') {
                $out[] = $prop . ':' . $value;
            }
        }

        return implode(';', $out);
    }

    /**
     * One declaration's value, rewritten.
     *
     * @param string $prop     Property.
     * @param string $value    Value.
     * @param string $selector Selector.
     * @param bool   $frames   Inside @keyframes.
     *
     * @return string
     */
    public function value($prop, $value, $selector = '', $frames = false)
    {
        $t         = $this->tokens;
        $important = '';
        if (preg_match('/\s*!important\s*$/i', $value)) {
            $important = '!important';
            $value     = preg_replace('/\s*!important\s*$/i', '', $value);
        }

        $heading = self::isHeadingSelector($selector);
        $label   = self::isLabelSelector($selector);
        $custom  = strpos($prop, '--') === 0;

        if ($prop === 'font-family' || ($custom && $this->isFontStack($value)) || $prop === 'font') {
            $value = $this->fonts($value, $heading && ! $custom, $label);
        }

        $band  = $frames ? null : $this->bandValue($prop, $value, $selector);
        $value = $band !== null ? $band : $this->colors($value, $prop, self::isScrim($selector, $prop));

        if (! $frames) {
            if (preg_match('/^border(-(top|bottom)-(left|right))?-radius$/', $prop) || ($custom && preg_match('/(-r|-r-(sm|md|lg|xl)|radius[a-z-]*|-arch|-round[a-z]*)$/', $prop))) {
                $value = $this->radius($value);
            } elseif ($prop === 'clip-path' && preg_match('/^(inset\(.*?\bround\s+)([^)]*)(\).*)$/s', $value, $cm)) {
                // An inset clip drawn to the kit's corners follows this design's.
                $value = $cm[1] . $this->radius(trim($cm[2])) . $cm[3];
            } elseif (($prop === 'box-shadow' && strpos($selector, ':focus') === false) || ($custom && preg_match('/shadow/', $prop))) {
                $value = $this->shadow($value);
            } elseif (preg_match('/^(border(-(top|right|bottom|left|block|inline)(-(start|end))?)?|outline)$/', $prop) || $prop === 'border-width') {
                $value = $this->borderWidth($value);
            } elseif ($prop === 'font-weight' && $heading) {
                $value = (string) $t['headingWeight'];
            } elseif ($prop === 'letter-spacing' && $heading) {
                $value = ['tight' => '-.03em', 'normal' => '0', 'wide' => '.04em'][$t['headingTracking']];
            } elseif ($prop === 'text-transform' && $heading && ! $label) {
                $value = $t['headingCase'];
            } elseif ($prop === 'text-transform' && $label && $value === 'uppercase' && $t['labelStyle'] !== 'uppercase') {
                $value = 'none';
            } elseif ($prop === 'font-style' && $label && $t['labelStyle'] === 'italic') {
                $value = 'italic';
            } elseif ($prop === 'font-size' && $heading) {
                $value = self::scale($value, $this->headingFactor($value));
            } elseif (($prop === 'white-space' || $prop === 'text-wrap') && $heading && $value === 'nowrap') {
                // A no-wrap title was fitted to the kit's face and copy; this
                // design's face and the owner's words must be free to wrap.
                $value = $prop === 'white-space' ? 'normal' : 'balance';
            } elseif ($custom && preg_match('/^--[a-z0-9]*-?(sec|section|secy|space|spacing|gutter)(-[a-z0-9]+)?$/', $prop)) {
                $value = self::scale($value, ['compact' => 0.72, 'regular' => 1.0, 'airy' => 1.3][$t['spacing']]);
            }
        }

        return $value === '' ? '' : $value . $important;
    }

    /**
     * Colour literals: hex, rgb(a), white/black keywords, and %23hex in
     * inline SVG data.
     *
     * @param string $value Value.
     * @param string $prop  Property.
     *
     * @return string
     */
    public function colors($value, $prop = '', $scrim = false)
    {
        $remap = $this->remap;
        $dark  = $this->darkest();

        $value = (string) preg_replace_callback('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', static function ($m) use ($remap, $scrim, $dark) {
            $c = Color::parseCss($m[0]);
            if ($c === null) {
                return $m[0];
            }
            // A translucent dark wash over a photo stays a dark wash, whichever
            // way round the design is (light text is drawn over it).
            if ($scrim && $c['alpha'] < 1 && Color::lightness($c['hex']) < 0.35) {
                return Color::toCss($dark, $c['alpha']);
            }

            return Color::toCss($remap->map($c['hex']), $c['alpha']);
        }, $value);

        $value = (string) preg_replace_callback('/%23([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', static function ($m) use ($remap) {
            return '%23' . substr($remap->map('#' . $m[1]), 1);
        }, $value);

        if (preg_match('/(color|background|border|outline|fill|stroke|shadow|^--)/', $prop)) {
            $value = (string) preg_replace_callback('/(?<![\w-])(white|black)(?![\w-])/i', static function ($m) use ($remap) {
                return $remap->map(strtolower($m[1]) === 'white' ? '#ffffff' : '#000000');
            }, $value);
        }

        return $value;
    }

    /**
     * @param string $value   Font stack or font shorthand.
     * @param bool   $heading Rule targets headings.
     * @param bool   $label   Rule targets labels.
     *
     * @return string
     */
    private function fonts($value, $heading, $label)
    {
        // A theme font preset (var(--wp--preset--font-family--geist)) is a family by name.
        if (preg_match('/^var\(--wp--preset--font-family--([a-z0-9-]+)\)$/', trim($value), $m)) {
            $value = '"' . str_replace('-', ' ', $m[1]) . '",sans-serif';
        } elseif (strpos($value, 'var(') !== false && ! preg_match('/["\']/', $value) && ! $heading) {
            return $value;
        }
        $f    = $this->tokens['fonts'];
        $role = $this->fontRole($value);
        if ($heading) {
            $role = preg_match('/mono/i', $value) ? 'label' : 'display';
        } elseif ($label && $role === 'body') {
            $role = 'label';
        }

        $stack = FontCatalogue::stack($f[$role]);

        // "font" shorthand: replace only the family part (after the size).
        if (preg_match('/^(.*?\d[\w.%]*(?:\/[\w.%]+)?\s+)(.+)$/', $value, $m) && ! preg_match('/^["\']/', $value) && preg_match('/\d/', $m[1])) {
            return $m[1] . $stack;
        }

        return $stack;
    }

    /**
     * @param string $stack Font stack.
     *
     * @return string display|body|label
     */
    private function fontRole($stack)
    {
        $first = strtolower(trim((string) preg_replace('/^[\s"\']*([^,"\']+).*$/s', '$1', $stack)));

        if (preg_match('/mono/', $stack)) {
            return 'label';
        }
        if ($first !== '' && $first === $this->kitDisplay && $first !== $this->kitBody) {
            return 'display';
        }
        if ($first !== '' && $first === $this->kitBody) {
            return 'body';
        }
        // Another kit family: a serif is a display face, anything else a label face.
        return preg_match('/(^|,)\s*(ui-)?serif\s*$/', $stack) || preg_match('/georgia|times/i', $stack) ? 'display' : 'label';
    }

    private function isFontStack($value)
    {
        return (bool) preg_match('/(^|,)\s*(sans-serif|serif|monospace|system-ui|ui-sans-serif|ui-serif|ui-monospace)\s*$/i', $value);
    }

    /**
     * @param string $value Radius value.
     *
     * @return string
     */
    public function radius($value)
    {
        $style = $this->tokens['radius'];
        if (strpos($value, 'var(') !== false || strpos($value, 'calc(') !== false || $value === 'inherit') {
            return $value;
        }
        $base  = ['none' => '0', 'subtle' => '4px', 'rounded' => '10px', 'soft' => '18px', 'pill' => '14px'][$style];
        $large = ['none' => '0', 'subtle' => '6px', 'rounded' => '18px', 'soft' => '30px', 'pill' => '26px'][$style];
        $parts = preg_split('/\s+/', trim(str_replace('/', ' / ', $value)));
        $nums  = array_filter($parts, static function ($p) {
            return $p !== '/' && (float) $p > 0;
        });

        if (! $nums) {
            return $value;
        }
        // Mixed zero and large corners (arches, tabs): one even shape instead.
        $mixed = count($nums) < count(array_filter($parts, static function ($p) {
            return $p !== '/';
        }));

        if ($style === 'none') {
            return '0';
        }

        $px = 0;
        foreach ($nums as $p) {
            $px = max($px, self::px($p));
        }
        if (! $mixed && (strpos($value, '%') !== false || $px >= 90)) {
            // Circles and pills stay round (no square avatars on a soft design).
            return $style === 'subtle' && strpos($value, '%') === false ? $large : $value;
        }
        if ($mixed) {
            return $large;
        }

        return $px <= 9 ? $base : $large;
    }

    /**
     * @param string $value box-shadow value.
     *
     * @return string
     */
    private function shadow($value)
    {
        if (strtolower(trim($value)) === 'none' || strpos($value, 'var(') === 0) {
            return $value;
        }

        return $this->shadowValue();
    }

    private function shadowValue()
    {
        switch ($this->tokens['shadow']) {
            case 'hard':
                return '6px 6px 0 ' . $this->tokens['palette']['text'];
            case 'soft':
                return '0 18px 40px -18px ' . Color::toCss($this->tokens['palette']['text'], 0.28) . ',0 2px 6px ' . Color::toCss($this->tokens['palette']['text'], 0.06);
            default:
                return 'none';
        }
    }

    /**
     * @param string $value border / outline value.
     *
     * @return string
     */
    private function borderWidth($value)
    {
        $w = (int) $this->tokens['borderWidth'];
        if ($w <= 1) {
            return $value;
        }

        return (string) preg_replace('/(?<![\d.])(1px|1\.5px|\.5px|0\.5px)(?=\s|$)/', $w . 'px', $value);
    }

    /**
     * Multiply lengths in a value (rem, em, px, vw, vh).
     *
     * @param string $value  Value.
     * @param float  $factor Factor.
     *
     * @return string
     */
    public static function scale($value, $factor)
    {
        return (string) preg_replace_callback('/(-?\d*\.?\d+)(rem|em|px|vw|vh|vmin|vmax|cqi)\b/', static function ($m) use ($factor) {
            return rtrim(rtrim(number_format((float) $m[1] * $factor, 3, '.', ''), '0'), '.') . $m[2];
        }, (string) $value);
    }

    /**
     * The heading scale for one size: display sizes (hero and column titles,
     * fitted to the kit's own face and layout) never grow, and shrink for uppercase, which
     * sets about a fifth wider.
     *
     * @param string $value A font-size value.
     *
     * @return float
     */
    private function headingFactor($value)
    {
        $factor  = (float) $this->tokens['headingScale'];
        $display = false;
        if (preg_match_all('/(\d*\.?\d+)(rem|em|px|vw|cqi)\b/', (string) $value, $m, PREG_SET_ORDER)) {
            foreach ($m as $n) {
                $display = $display || (in_array($n[2], ['vw', 'cqi'], true) ? (float) $n[1] >= 4.5 : self::px($n[1] . $n[2]) >= 64);
            }
        }
        if (! $display) {
            return $factor;
        }

        return min($factor, 1.0) * ($this->tokens['headingCase'] === 'uppercase' ? 0.8 : 1.0);
    }

    private static function px($length)
    {
        $n = (float) $length;
        if (preg_match('/rem|em$/', $length)) {
            return $n * 16;
        }

        return $n;
    }

    /**
     * @param string $selector Selector.
     *
     * @return bool Targets headings or heading-like display text.
     */
    public static function isHeadingSelector($selector)
    {
        return (bool) preg_match('/(^|[\s>+~,(])h[1-6]\b|-h[1-6]\b|title|heading|headline|display|__big|serif-xl/i', (string) $selector);
    }

    /**
     * @param string $selector Selector.
     *
     * @return bool Targets small labels (kickers, eyebrows, tags).
     */
    public static function isLabelSelector($selector)
    {
        return (bool) preg_match('/label|kicker|eyebrow|overline|tag\b|meta|caps|mono|badge|chip/i', (string) $selector);
    }

    /**
     * A background painted over a photo by a pseudo-element or an overlay
     * element (a gradient scrim under a caption or title).
     *
     * @param string $selector Selector.
     * @param string $prop     Property.
     *
     * @return bool
     */
    public static function isScrim($selector, $prop)
    {
        return strpos((string) $prop, 'background') === 0 && (bool) preg_match('/::?(after|before)\b|scrim|overlay|shade|veil/i', (string) $selector);
    }

    /**
     * The darker of this design's page and text colours.
     *
     * @return string
     */
    private function darkest()
    {
        $p = $this->tokens['palette'];

        return Color::lightness($p['background']) < Color::lightness($p['text']) ? $p['background'] : $p['text'];
    }

    /**
     * The lighter of this design's page and text colours (text over photos).
     *
     * @return string
     */
    private function lightest()
    {
        $p = $this->tokens['palette'];

        return Color::lightness($p['background']) >= Color::lightness($p['text']) ? $p['background'] : $p['text'];
    }

    /* ------------------------------------------------------------------
     * Layer 2: overrides from enumerated options.
     * ---------------------------------------------------------------- */

    /**
     * @return string
     */
    public function overrides()
    {
        $t  = $this->tokens;
        $p  = $t['palette'];
        $d  = $t['derived'];
        $f  = $t['fonts'];
        $bw = max(1, (int) $t['borderWidth']);
        $r  = ['none' => '0', 'subtle' => '4px', 'rounded' => '10px', 'soft' => '18px', 'pill' => '999px'][$t['radius']];
        $rc = ['none' => '0', 'subtle' => '6px', 'rounded' => '16px', 'soft' => '28px', 'pill' => '26px'][$t['radius']];
        $px = $this->prefix;
        $c  = [];

        $c[] = 'body{--sf-bg:' . $p['background'] . ';--sf-surface:' . $p['surface'] . ';--sf-text:' . $p['text'] . ';--sf-muted:' . $p['muted']
            . ';--sf-accent:' . $p['accent'] . ';--sf-accent-2:' . $p['accent2'] . ';--sf-soft:' . $p['soft'] . ';--sf-inverse:' . $p['inverse']
            . ';--sf-on-accent:' . $d['onAccent'] . ';--sf-on-inverse:' . $d['onInverse'] . ';--sf-line:' . $d['line'] . ';--sf-line-strong:' . $d['lineStrong']
            . ';--sf-display:' . FontCatalogue::stack($f['display']) . ';--sf-body:' . FontCatalogue::stack($f['body']) . ';--sf-label:' . FontCatalogue::stack($f['label'])
            . ';--sf-radius:' . $r . ';--sf-radius-card:' . $rc . ';--sf-border:' . $bw . 'px;--sf-shadow:' . $this->shadowValue()
            . ';background:var(--sf-bg);color:var(--sf-text);font-family:var(--sf-body)}';
        $c[] = 'body ::selection{background:var(--sf-accent);color:var(--sf-on-accent)}';
        // The platform credit sits on whatever band the kit's footer ends on.
        $c[] = 'body .sf-credit a{color:inherit;text-decoration:underline}';

        $heading = 'body :is(h1,h2,h3,h4,h5,h6)';
        $c[]     = $heading . '{overflow-wrap:break-word;font-family:var(--sf-display);font-weight:' . $t['headingWeight'] . ';text-transform:' . $t['headingCase']
            . ';letter-spacing:' . ['tight' => '-.03em', 'normal' => '0', 'wide' => '.04em'][$t['headingTracking']] . '}';
        // A kit's accent-coloured italic inside headings becomes the new accent, upright when headings shout.
        $c[] = $heading . ' em{color:var(--sf-accent)' . ($t['headingCase'] === 'uppercase' ? ';font-style:normal' : '') . '}';

        // Buttons.
        $btn = 'body :is(.wp-block-button__link,.wp-element-button)';
        $c[] = $btn . '{font-family:var(--sf-label);border-radius:var(--sf-radius);border:var(--sf-border) solid var(--sf-accent);'
            . ($t['labelStyle'] === 'uppercase' ? 'text-transform:uppercase;letter-spacing:.08em;' : 'text-transform:none;letter-spacing:0;') . 'box-shadow:none}';
        switch ($t['buttons']) {
            case 'outline':
                $c[] = $btn . '{background:transparent;color:inherit;border-color:currentColor}';
                $c[] = $btn . ':hover{background:var(--sf-accent);color:var(--sf-on-accent);border-color:var(--sf-accent)}';
                break;
            case 'underline':
                $c[] = $btn . '{background:transparent;color:inherit;border-width:0 0 var(--sf-border);border-color:currentColor;border-radius:0;padding-inline:0}';
                $c[] = $btn . ':hover{color:var(--sf-accent);border-color:var(--sf-accent)}';
                break;
            case 'hard-shadow':
                $c[] = $btn . '{background:var(--sf-accent);color:var(--sf-on-accent);border-color:var(--sf-text);box-shadow:4px 4px 0 var(--sf-text);transition:transform .15s ease,box-shadow .15s ease}';
                $c[] = $btn . ':hover{background:var(--sf-accent);color:var(--sf-on-accent);border-color:var(--sf-text);transform:translate(2px,2px);box-shadow:2px 2px 0 var(--sf-text)}';
                break;
            default:
                $c[] = $btn . '{background:var(--sf-accent);color:var(--sf-on-accent)}';
                $c[] = $btn . ':hover{background:var(--sf-text);color:var(--sf-bg);border-color:var(--sf-text)}';
        }
        // On inverse bands the button keeps reading.
        $c[] = 'body :is([class*="dark"],[class*="inverse"],[class*="night"]) :is(.wp-block-button__link,.wp-element-button){border-color:currentColor}';

        // Links in running text.
        $prose = 'body main :is(p,li) a:not(.wp-block-button__link)';
        if ($t['links'] === 'underline') {
            $c[] = $prose . '{text-decoration:underline;text-decoration-thickness:' . max(1, $bw) . 'px;text-underline-offset:.2em}';
        } elseif ($t['links'] === 'accent') {
            $c[] = $prose . '{color:var(--sf-accent);text-decoration:underline;text-decoration-color:currentColor;text-underline-offset:.2em}';
        }

        // Cards: block-level elements whose class names say card, tile or panel (not their __parts).
        $card = 'body main :is([class*="card"],[class*="tile"],[class*="panel"]):not([class*="__"]):not(img):not(figure)';
        switch ($t['cards']) {
            case 'bordered':
                $c[] = $card . '{border:var(--sf-border) solid var(--sf-line-strong);border-radius:var(--sf-radius-card);box-shadow:none}';
                break;
            case 'raised':
                $c[] = $card . '{border:0;border-radius:var(--sf-radius-card);box-shadow:' . ($t['shadow'] === 'none' ? '0 16px 36px -20px ' . Color::toCss($p['text'], 0.3) : 'var(--sf-shadow)') . '}';
                break;
            case 'filled':
                $c[] = $card . '{background:var(--sf-soft);color:var(--sf-text);border:0;border-radius:var(--sf-radius-card);box-shadow:none}';
                break;
            default:
                $c[] = $card . '{box-shadow:none;border-radius:var(--sf-radius-card)}';
        }

        // Section rhythm: the page's top-level bands.
        $bands = 'body main .wp-block-post-content>:is(section,.wp-block-group)';
        if ($t['sections'] === 'alternate') {
            $c[] = $bands . ':nth-child(even):not([class*="dark"]):not([class*="inverse"]):not([class*="night"]):not([class*="hero"]){background-color:var(--sf-surface)}';
        } elseif ($t['sections'] === 'bordered') {
            $c[] = $bands . '+:is(section,.wp-block-group){border-top:var(--sf-border) solid var(--sf-text)}';
        }

        // Images.
        $img     = 'body main :is(.wp-block-image,.wp-block-cover,.wp-block-media-text,figure) img';
        $filters = ['grayscale' => 'grayscale(1) contrast(1.08)', 'warm' => 'sepia(.25) saturate(1.1)', 'cool' => 'saturate(.85) hue-rotate(-8deg) brightness(1.02)', 'high-contrast' => 'contrast(1.25) saturate(1.15)'];
        if (isset($filters[$t['imageStyle']])) {
            $c[] = $img . '{filter:' . $filters[$t['imageStyle']] . '}';
        }
        $frame = 'body main .wp-block-image img';
        if ($t['imageFrame'] === 'border') {
            $c[] = $frame . '{border:var(--sf-border) solid var(--sf-text);box-sizing:border-box}';
        } elseif ($t['imageFrame'] === 'shadow') {
            $c[] = $frame . '{box-shadow:0 24px 50px -24px ' . Color::toCss($p['text'], 0.45) . '}';
        } elseif ($t['imageFrame'] === 'offset') {
            $c[] = $frame . '{box-shadow:10px 10px 0 var(--sf-accent)}';
        }

        // Decoration: the kit's own motifs go; this design's (if any) come in.
        // A title drawn a second time over a photo (a "ghost" heading, always
        // the same words as the title) stays: without it the title runs under
        // the photo. It is drawn in the light colour, over the dark wash.
        $kitDecor = 'body :is([class*="marquee"],[class*="ticker"]) [aria-hidden="true"],body [aria-hidden="true"]:is([class*="ghost"],[class*="blob"],[class*="squiggle"],[class*="orb"],[class*="deco"],[class*="ring"]):not([class*="title"]):not([class*="heading"])';
        $c[]      = $kitDecor . '{display:none!important}';
        $c[]      = 'body [aria-hidden="true"][class*="ghost"]:is([class*="title"],[class*="heading"]){color:' . $this->lightest() . ';font-family:var(--sf-display);font-weight:' . $t['headingWeight'] . ';text-transform:' . $t['headingCase']
            . ';letter-spacing:' . ['tight' => '-.03em', 'normal' => '0', 'wide' => '.04em'][$t['headingTracking']] . '}';
        $c[]      = 'body :is([class*="marquee"],[class*="ticker"]) *{animation:none!important}';
        $c[]      = 'body :is([class*="kicker"],[class*="eyebrow"])::before{background:currentColor;border-radius:0;width:' . ($t['decor'] === 'lines' ? '2rem' : '.5rem') . ';height:' . ($t['decor'] === 'lines' ? max(2, $bw) . 'px' : '.5rem') . '}';
        if ($t['decor'] === 'none') {
            $c[] = 'body :is([class*="kicker"],[class*="eyebrow"])::before{display:none}';
        }

        $hero = 'body main .wp-block-post-content>:first-child';
        switch ($t['decor']) {
            case 'grid':
                $c[] = $hero . '{background-image:linear-gradient(var(--sf-line) 1px,transparent 1px),linear-gradient(90deg,var(--sf-line) 1px,transparent 1px);background-size:56px 56px}';
                break;
            case 'dots':
                $c[] = $hero . '{background-image:radial-gradient(var(--sf-line-strong) 1.2px,transparent 1.6px);background-size:22px 22px}';
                break;
            case 'blobs':
                $c[] = $hero . '{background-image:radial-gradient(circle at 88% 12%,var(--sf-soft) 0,var(--sf-soft) 13rem,transparent 13.1rem),radial-gradient(circle at 6% 92%,' . Color::toCss($p['accent2'], 0.22) . ' 0,' . Color::toCss($p['accent2'], 0.22) . ' 9rem,transparent 9.1rem)}';
                break;
            case 'stripes':
                $c[] = $hero . '{border-top:14px solid transparent;border-image:repeating-linear-gradient(90deg,var(--sf-accent) 0 28px,var(--sf-text) 28px 56px) 14}';
                break;
            case 'lines':
                $c[] = $hero . '{border-bottom:var(--sf-border) solid var(--sf-text)}';
                break;
        }

        // Header and footer bands follow the palette.
        $c[] = 'body header.wp-block-template-part{border-bottom:' . ($t['sections'] === 'bordered' || $t['decor'] === 'lines' ? 'var(--sf-border) solid var(--sf-text)' : '0') . '}';
        if ($px !== '') {
            $c[] = 'body .' . $px . 'sec{scroll-margin-top:6rem}';
        }

        return implode("\n", $c) . "\n" . HeadingSafety::css();
    }

    /* ------------------------------------------------------------------
     * Sanitiser.
     * ---------------------------------------------------------------- */

    /**
     * @param string $css Compiled CSS.
     *
     * @throws \UnexpectedValueException When something unsafe is present.
     *
     * @return string
     */
    public function sanitize($css)
    {
        return self::sanitizeCss($css, FontCatalogue::urls($this->pluginBase, $this->themeBase));
    }

    /**
     * @param string   $css     CSS.
     * @param string[] $allowed Exact URLs url() may reference.
     *
     * @throws \UnexpectedValueException When something unsafe is present.
     *
     * @return string
     */
    public static function sanitizeCss($css, array $allowed)
    {
        $css = (string) $css;

        if (preg_match('#@import|expression\s*\(|javascript:|vbscript:|(?<![\w-])behavior\s*:|-moz-binding|</\s*style|<\s*/?\s*script#i', $css, $m)) {
            throw new \UnexpectedValueException('Unsafe CSS: ' . $m[0]);
        }
        // Escapes could spell any of the above; only a content string may hold one ('\2013').
        $outsideContent = (string) preg_replace('/content:\s*(["\'])(?:\\\\[0-9a-fA-F]{1,6}\s?|[^"\'\\\\<>])*\1/', 'content:""', $css);
        if (strpos($outsideContent, '\\') !== false) {
            throw new \UnexpectedValueException('Unsafe CSS: escape outside a content string.');
        }

        if (preg_match_all('/url\(\s*([^)]*?)\s*\)/i', $css, $all)) {
            foreach ($all[1] as $ref) {
                $ref = trim($ref, " \t\"'");
                if (strpos($ref, 'data:image/svg+xml,') === 0 && ! preg_match('/script|on[a-z]+=|href|<foreign/i', rawurldecode($ref))) {
                    continue;
                }
                if (! in_array($ref, $allowed, true)) {
                    throw new \UnexpectedValueException('Unsafe CSS url(): ' . $ref);
                }
            }
        }

        $open = substr_count($css, '{');
        if ($open !== substr_count($css, '}')) {
            throw new \UnexpectedValueException('Unbalanced CSS.');
        }

        return $css;
    }

    /* ------------------------------------------------------------------
     * Parsing helpers (strings and parentheses aware).
     * ---------------------------------------------------------------- */

    private function find($css, $char, $from)
    {
        $len   = strlen($css);
        $quote = '';
        $depth = 0;
        for ($i = $from; $i < $len; $i++) {
            $ch = $css[$i];
            if ($quote !== '') {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
            } elseif ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($ch === $char && $depth === 0) {
                return $i;
            } elseif ($ch === '{' || $ch === '}') {
                if ($char === ';') {
                    return false;
                }
            }
        }

        return false;
    }

    private function matching($css, $open)
    {
        $len   = strlen($css);
        $depth = 0;
        $quote = '';
        for ($i = $open; $i < $len; $i++) {
            $ch = $css[$i];
            if ($quote !== '') {
                if ($ch === '\\') {
                    $i++;
                } elseif ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $len - 1;
    }

    private function split($body, $sep)
    {
        $out   = [];
        $buf   = '';
        $quote = '';
        $depth = 0;
        $len   = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($quote !== '') {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $body[++$i];
                } elseif ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
            } elseif ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($ch === $sep && $depth === 0) {
                $out[] = $buf;
                $buf   = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = $buf;
        }

        return $out;
    }
}
