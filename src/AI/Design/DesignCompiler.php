<?php
/**
 * Turns a custom design into a site's global styles and recoloured markup.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * Input: the structural kit's design file (Kit::getDesignFile(), the
 * template's global styles as shipped in this plugin, never the site's
 * current styles, so a re-run starts from the same source) and validated
 * tokens. Output: a complete user global styles document (theme.json shape)
 * with the palette, typography, element styles and the compiled CSS, and a
 * markup rewriter for the colours templates hard-code in block attributes
 * and inline SVG (header logos, map strokes). Pure apart from the base URLs
 * passed in.
 */
class DesignCompiler
{
    /** @var array */
    private $tokens;

    /** @var array */
    private $design;

    /** @var string */
    private $prefix;

    /** @var ColorRemap */
    private $remap;

    /** @var CssCompiler */
    private $css;

    /**
     * @param array  $tokens     DesignTokens::validate().
     * @param array  $design     Kit::getDesignFile(): {variation, globalStyles, fonts}.
     * @param string $prefix     Kit class prefix.
     * @param string $pluginBase Plugin URL.
     * @param string $themeBase  Theme URL.
     */
    public function __construct(array $tokens, array $design, $prefix, $pluginBase, $themeBase)
    {
        $this->tokens = $tokens;
        $this->design = $design;
        $this->prefix = (string) $prefix;

        $gs      = isset($design['globalStyles']) && is_array($design['globalStyles']) ? $design['globalStyles'] : [];
        $palette = [];
        foreach (isset($gs['settings']['color']['palette']['theme']) ? (array) $gs['settings']['color']['palette']['theme'] : [] as $entry) {
            if (isset($entry['slug'], $entry['color']) && Color::hex($entry['color']) !== '') {
                $palette[(string) $entry['slug']] = Color::hex($entry['color']);
            }
        }
        $base = isset($palette['base']) ? $palette['base'] : self::presetColor(isset($gs['styles']['color']['background']) ? $gs['styles']['color']['background'] : '', $palette, '#ffffff');
        $ink  = isset($palette['contrast']) ? $palette['contrast'] : self::presetColor(isset($gs['styles']['color']['text']) ? $gs['styles']['color']['text'] : '', $palette, '#111111');

        $this->remap = new ColorRemap($palette, $base, $ink, $tokens['palette']);
        $this->css   = new CssCompiler(
            $tokens,
            $this->remap,
            self::firstFamily(isset($gs['styles']['elements']['heading']['typography']['fontFamily']) ? $gs['styles']['elements']['heading']['typography']['fontFamily'] : ''),
            self::firstFamily(isset($gs['styles']['typography']['fontFamily']) ? $gs['styles']['typography']['fontFamily'] : ''),
            $prefix,
            $pluginBase,
            $themeBase
        );
    }

    /**
     * @return ColorRemap
     */
    public function remap()
    {
        return $this->remap;
    }

    /**
     * The user global styles document.
     *
     * @return array {version, isGlobalStylesUserThemeJSON, settings, styles}
     */
    public function globalStyles()
    {
        $t  = $this->tokens;
        $gs = isset($this->design['globalStyles']) && is_array($this->design['globalStyles']) ? $this->design['globalStyles'] : [];

        $settings = isset($gs['settings']) && is_array($gs['settings']) ? $gs['settings'] : [];
        $styles   = isset($gs['styles']) && is_array($gs['styles']) ? $gs['styles'] : [];

        // Palette: every kit slug (classes like has-accent-4-color stay valid), recoloured.
        $theme = [];
        foreach (isset($settings['color']['palette']['theme']) ? (array) $settings['color']['palette']['theme'] : [] as $entry) {
            if (! isset($entry['slug'], $entry['color'])) {
                continue;
            }
            $mapped  = Color::hex($entry['color']) !== '' ? $this->remap->map($entry['color']) : (string) $entry['color'];
            $theme[] = ['slug' => (string) $entry['slug'], 'name' => self::paletteName($this->remap->role(Color::hex($entry['color']) ?: '#000000'), (string) $entry['slug']), 'color' => $mapped];
        }
        if (! $theme) {
            foreach (['base' => 'background', 'contrast' => 'text', 'accent-1' => 'accent', 'accent-2' => 'accent2', 'accent-3' => 'inverse', 'accent-4' => 'muted', 'accent-5' => 'surface', 'accent-6' => 'soft'] as $slug => $role) {
                $theme[] = ['slug' => $slug, 'name' => self::paletteName($role, $slug), 'color' => $t['palette'][$role]];
            }
        }
        $settings['color']['palette']['theme'] = $theme;
        // Gradients and duotones may hold kit colours too.
        foreach (['gradients', 'duotone'] as $key) {
            if (isset($settings['color'][$key]['theme'])) {
                $settings['color'][$key]['theme'] = json_decode($this->css->colors(self::json($settings['color'][$key]['theme'])), true) ?: [];
            }
        }

        // First the kit's own values: block and element styles may carry kit
        // literals; recolour and refont them, then set this design's on top.
        // Block-level style values (blocks.*) may carry kit literals: recolour, refont.
        if (isset($styles['blocks'])) {
            $styles['blocks'] = $this->recolourTree($styles['blocks']);
        }
        foreach (['elements', 'spacing', 'border'] as $key) {
            if (isset($styles[$key])) {
                $styles[$key] = $this->recolourTree($styles[$key]);
            }
        }

        // Typography: the custom fonts by name (font presets of the kit are left unused).
        $display = FontCatalogue::stack($t['fonts']['display']);
        $body    = FontCatalogue::stack($t['fonts']['body']);
        unset($settings['typography']['fontFamilies']);

        $styles['color']['background']       = 'var:preset|color|base';
        $styles['color']['text']             = 'var:preset|color|contrast';
        $styles['typography']['fontFamily']  = $body;
        $styles['elements']['heading']['typography']['fontFamily']    = $display;
        $styles['elements']['heading']['typography']['fontWeight']    = (string) $t['headingWeight'];
        $styles['elements']['heading']['typography']['textTransform'] = $t['headingCase'];
        $styles['elements']['heading']['typography']['letterSpacing'] = ['tight' => '-0.03em', 'normal' => '0', 'wide' => '0.04em'][$t['headingTracking']];
        unset($styles['elements']['heading']['color']);
        // The kit's h1..h6 and heading block styles carry its own face and
        // weight (and outrank elements.heading): give them this design's.
        $headingType = $styles['elements']['heading']['typography'];
        $headingKeys = ['fontFamily', 'fontWeight', 'textTransform', 'letterSpacing'];
        if (isset($styles['blocks']['core/heading']['typography'])) {
            foreach ($headingKeys as $prop) {
                $styles['blocks']['core/heading']['typography'][$prop] = $headingType[$prop];
            }
        }
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $h) {
            if (isset($styles['elements'][$h]['typography'])) {
                foreach ($headingKeys as $prop) {
                    $styles['elements'][$h]['typography'][$prop] = $headingType[$prop];
                }
            }
        }
        $styles['elements']['link']['color']['text'] = $t['links'] === 'plain' ? 'inherit' : 'var(--sf-accent, currentColor)';
        $styles['elements']['button']['color']       = ['background' => $t['palette']['accent'], 'text' => $t['derived']['onAccent']];
        $styles['elements']['button']['border']['radius'] = ['none' => '0', 'subtle' => '4px', 'rounded' => '10px', 'soft' => '18px', 'pill' => '999px'][$t['radius']];
        $styles['elements']['button']['typography']['fontFamily'] = FontCatalogue::stack($t['fonts']['label']);

        $styles['css'] = $this->css->compile(isset($styles['css']) ? (string) $styles['css'] : '');

        return [
            'version'                     => 3,
            'isGlobalStylesUserThemeJSON' => true,
            'settings'                    => $settings,
            'styles'                      => $styles,
        ];
    }

    /**
     * Hard-coded colours in block markup: block comment attributes and the
     * style / fill / stroke / stop-color attributes they render to, mapped
     * with the same function so a block's comment and its HTML stay equal
     * (the editor would otherwise call the block invalid).
     *
     * @param string $markup Block markup.
     *
     * @return string
     */
    public function recolourMarkup($markup)
    {
        $remap = $this->remap;
        $hex   = static function ($m) use ($remap) {
            $c = Color::parseCss($m[0]);

            return $c === null ? $m[0] : Color::toCss($remap->map($c['hex']), $c['alpha']);
        };

        // Block comments: "#rrggbb" string values only.
        $markup = (string) preg_replace_callback('/<!-- wp:[a-z0-9\/-]+ \{.*?\} \/?-->/s', static function ($m) use ($remap) {
            return (string) preg_replace_callback('/"(#[0-9a-fA-F]{3,8})"/', static function ($h) use ($remap) {
                return '"' . $remap->map($h[1]) . '"';
            }, $m[0]);
        }, (string) $markup);

        // HTML attributes that carry colours.
        return (string) preg_replace_callback('/\b(style|fill|stroke|stop-color|color|flood-color)="([^"]*)"/i', static function ($m) use ($hex) {
            return $m[1] . '="' . preg_replace_callback('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $hex, $m[2]) . '"';
        }, $markup);
    }

    /**
     * @param mixed $node Style subtree.
     *
     * @return mixed
     */
    private function recolourTree($node)
    {
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                if ($k === 'fontFamily' && is_string($v)) {
                    $node[$k] = strpos($v, 'heading') !== false ? FontCatalogue::stack($this->tokens['fonts']['display']) : FontCatalogue::stack($this->tokens['fonts']['body']);
                    continue;
                }
                $node[$k] = $this->recolourTree($v);
            }

            return $node;
        }
        if (is_string($node)) {
            return $this->css->colors($node, 'color');
        }

        return $node;
    }

    private static function json($data)
    {
        return (string) (function_exists('wp_json_encode') ? wp_json_encode($data) : json_encode($data));
    }

    private static function presetColor($value, array $palette, $fallback)
    {
        if (preg_match('/^var:preset\|color\|([a-z0-9-]+)$/', (string) $value, $m) && isset($palette[$m[1]])) {
            return $palette[$m[1]];
        }

        return Color::hex($value) ?: $fallback;
    }

    private static function firstFamily($stack)
    {
        $stack = (string) $stack;
        if (preg_match('/^var:preset\|font-family\|([a-z0-9-]+)$/', $stack, $m)) {
            return str_replace('-', ' ', $m[1]);
        }

        return trim((string) preg_replace('/^[\s"\']*([^,"\']+).*$/s', '$1', $stack));
    }

    private static function paletteName($role, $slug)
    {
        $names = ['background' => 'Background', 'surface' => 'Surface', 'text' => 'Text', 'muted' => 'Muted text', 'accent' => 'Accent', 'accent2' => 'Second accent', 'soft' => 'Soft', 'inverse' => 'Inverse'];

        return isset($names[$role]) ? $names[$role] . ' (' . $slug . ')' : $slug;
    }
}

