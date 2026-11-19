<?php
/**
 * Light design changes: swap colours and pick heading / body fonts.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * set-style-variation is the only other write to global styles, and it
 * replaces the whole design. Template sites need something lighter: "make the
 * accent deep navy", "a warmer serif for headings". A template keeps its look
 * in its user global styles (palette, element styles) and in its custom CSS,
 * whose own custom properties hold literal hex values, so a palette edit alone
 * would change nothing visible.
 *
 * So the input is closed and the CSS is ours, not the model's:
 *   colorSwaps  exact hex to hex, applied to every string in the user global
 *               styles (palette, element styles, custom CSS, including the
 *               rgb() triplets of that colour). A swap whose source is in the
 *               theme palette but not yet overridden adds the override.
 *   headingFont / bodyFont  one of the font families the site already loads
 *               (theme.json presets, or a family the custom CSS declares with
 *               @font-face), written as element styles plus a marked CSS block
 *               that wins over the template's own font rules.
 *
 * Nothing free-form reaches the stylesheet, which is why the result may be
 * saved past the global-styles kses filter (it would otherwise drop the
 * template's custom CSS, which an owner without edit_css cannot write). The
 * prior body is captured as a revision, so the run's undo restores it exactly.
 */
class SetDesignTokensTool extends AbstractWriteTool
{
    const MARK_OPEN  = '/* swish:design-tokens */';
    const MARK_CLOSE = '/* /swish:design-tokens */';
    const MAX_SWAPS  = 24;

    public function getName()
    {
        return 'swishfolio/set-design-tokens';
    }

    public function getTitle()
    {
        return __('Adjust colours and fonts', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Swap colours used by the site design (exact hex to hex) and choose heading and body fonts from '
            . 'the families the site already loads. Changes the live design and must be confirmed.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_PUBLISHED;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'colorSwaps'  => [
                    'type'     => 'array',
                    'maxItems' => self::MAX_SWAPS,
                    'default'  => [],
                    'items'    => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['from', 'to'],
                        'properties'           => [
                            'from' => ['type' => 'string', 'maxLength' => 7],
                            'to'   => ['type' => 'string', 'maxLength' => 7],
                        ],
                    ],
                ],
                'headingFont' => ['type' => 'string', 'maxLength' => 80, 'default' => ''],
                'bodyFont'    => ['type' => 'string', 'maxLength' => 80, 'default' => ''],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            $this->forbid(__('You cannot change the site design.', 'swishfolio-core'));
        }

        $postId = self::globalStylesPostId();

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            $this->forbid(__('You cannot edit this site\'s global styles.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId = self::globalStylesPostId();
        $prior  = (string) get_post_field('post_content', $postId);
        $config = json_decode($prior, true);
        $config = is_array($config) ? $config : ['version' => 3, 'isGlobalStylesUserThemeJSON' => true];

        $result = self::transform($config, $input, self::themePalette(), self::fontOptions($config));

        if ($result['changes'] === []) {
            return ['applied' => false, 'changes' => []];
        }

        $json = wp_json_encode($result['config'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        TrustedMarkup::allow($postId, $prior);
        TrustedMarkup::allow($postId, $json);

        $this->journal->updatePost(
            $context,
            $this->getName(),
            wp_slash(['ID' => $postId, 'post_content' => $json]),
            __('Adjusted the site colours and fonts', 'swishfolio-core')
        );

        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        }

        return ['applied' => true, 'changes' => $result['changes']];
    }

    /**
     * Pure: the new config and a human list of what changed.
     *
     * @param array $config       User global styles config.
     * @param array $input        Validated input.
     * @param array $themePalette Theme palette entries (slug, color, name).
     * @param array $fonts        name|slug => CSS font stack.
     *
     * @return array{config: array, changes: string[]}
     */
    public static function transform(array $config, array $input, array $themePalette, array $fonts)
    {
        $changes = [];
        $swaps   = [];

        foreach (isset($input['colorSwaps']) ? (array) $input['colorSwaps'] : [] as $swap) {
            $from = self::hex(isset($swap['from']) ? $swap['from'] : '');
            $to   = self::hex(isset($swap['to']) ? $swap['to'] : '');

            if ($from !== '' && $to !== '' && $from !== $to) {
                $swaps[$from] = $to;
            }
        }

        if ($swaps) {
            // A theme palette colour the user styles do not override yet.
            $palette = isset($config['settings']['color']['palette']['theme']) ? (array) $config['settings']['color']['palette']['theme'] : null;

            if ($palette === null) {
                foreach ($themePalette as $entry) {
                    if (isset($entry['color']) && isset($swaps[self::hex($entry['color'])])) {
                        $config['settings']['color']['palette']['theme'] = array_values($themePalette);
                        break;
                    }
                }
            }

            $before = wp_json_encode($config);
            $config = self::swapColors($config, $swaps);

            if (wp_json_encode($config) !== $before) {
                foreach ($swaps as $from => $to) {
                    $changes[] = sprintf('%s → %s', $from, $to);
                }
            }
        }

        $css   = isset($config['styles']['css']) ? (string) $config['styles']['css'] : '';
        $rules = [];

        foreach (['headingFont' => 'heading', 'bodyFont' => 'body'] as $key => $role) {
            $name = isset($input[$key]) ? trim((string) $input[$key]) : '';

            if ($name === '' || ! isset($fonts[$name])) {
                continue;
            }

            $stack = $fonts[$name];
            $value = strpos($stack, 'var(--wp--preset--font-family--') === 0
                ? 'var:preset|font-family|' . substr($stack, 31, -1)
                : $stack;

            if ($role === 'heading') {
                $config['styles']['elements']['heading']['typography']['fontFamily'] = $value;
                $rules[] = 'body :is(h1,h2,h3,h4,h5,h6){font-family:' . $stack . ' !important}';
            } else {
                $config['styles']['typography']['fontFamily'] = $value;
                $rules[] = 'body,body :is(p,li,dd,dt,td,th,blockquote,figcaption,label,button,input,select,textarea){font-family:' . $stack . ' !important}';
            }

            $changes[] = sprintf('%s font: %s', $role === 'heading' ? 'Heading' : 'Body', $name);
        }

        if ($rules) {
            $previous = self::markedRules($css);
            // Keep a font chosen earlier for the other role.
            foreach ($previous as $rule) {
                $isHeading = strpos($rule, 'body :is(h1') === 0;
                $wanted    = $isHeading ? 'headingFont' : 'bodyFont';
                if (empty($input[$wanted]) || ! isset($fonts[trim((string) $input[$wanted])])) {
                    $rules[] = $rule;
                }
            }

            $css = trim(self::stripMarked($css)) . "\n" . self::MARK_OPEN . "\n" . implode("\n", $rules) . "\n" . self::MARK_CLOSE;
            $config['styles']['css'] = $css;
        }

        return ['config' => $config, 'changes' => $changes];
    }

    /**
     * @param mixed $value Config node.
     * @param array $swaps from => to (lowercase #rrggbb).
     *
     * @return mixed
     */
    private static function swapColors($value, array $swaps)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::swapColors($v, $swaps);
            }

            return $value;
        }

        if (! is_string($value) || strpos($value, '#') === false && stripos($value, 'rgb') === false) {
            return $value;
        }

        // One pass, so a colour swapped to another swapped colour is not
        // swapped twice; #fff counts as #ffffff.
        $value = preg_replace_callback('/#([0-9a-f]{6}|[0-9a-f]{3})(?![0-9a-f])/i', static function ($m) use ($swaps) {
            $hex = self::hex($m[0]);

            return isset($swaps[$hex]) ? $swaps[$hex] : $m[0];
        }, $value);

        return preg_replace_callback('/(rgba?\(\s*)(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?=\s*[,)])/i', static function ($m) use ($swaps) {
            $hex = sprintf('#%02x%02x%02x', min(255, (int) $m[2]), min(255, (int) $m[3]), min(255, (int) $m[4]));
            if (! isset($swaps[$hex])) {
                return $m[0];
            }
            list($r, $g, $b) = sscanf($swaps[$hex], '#%02x%02x%02x');

            return $m[1] . $r . ',' . $g . ',' . $b;
        }, $value);
    }

    /**
     * @param mixed $value Candidate colour.
     *
     * @return string Lowercase #rrggbb or ''.
     */
    public static function hex($value)
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : '';
    }

    /**
     * @param string $css Custom CSS.
     *
     * @return string[] Rules inside our marked block.
     */
    private static function markedRules($css)
    {
        $open = strpos($css, self::MARK_OPEN);
        $close = strpos($css, self::MARK_CLOSE);

        if ($open === false || $close === false || $close < $open) {
            return [];
        }

        $body = substr($css, $open + strlen(self::MARK_OPEN), $close - $open - strlen(self::MARK_OPEN));

        return array_values(array_filter(array_map('trim', explode("\n", $body))));
    }

    /**
     * @param string $css Custom CSS.
     *
     * @return string
     */
    private static function stripMarked($css)
    {
        return preg_replace('#\s*' . preg_quote(self::MARK_OPEN, '#') . '.*?' . preg_quote(self::MARK_CLOSE, '#') . '#s', '', $css);
    }

    /**
     * Fonts the site already loads: name or slug => CSS stack.
     *
     * @param array $config User global styles config.
     *
     * @return array
     */
    public static function fontOptions(array $config)
    {
        $fonts = [];

        $families = function_exists('wp_get_global_settings') ? (array) wp_get_global_settings(['typography', 'fontFamilies']) : [];

        foreach ($families as $origin => $list) {
            if ($origin === 'default') {
                continue;
            }
            foreach ((array) $list as $family) {
                if (is_array($family) && ! empty($family['slug']) && ! empty($family['name'])) {
                    $fonts[(string) $family['name']] = 'var(--wp--preset--font-family--' . sanitize_title((string) $family['slug']) . ')';
                }
            }
        }

        $css = isset($config['styles']['css']) ? (string) $config['styles']['css'] : '';

        if (preg_match_all('/@font-face\s*\{[^}]*font-family\s*:\s*["\']?([A-Za-z0-9 _-]{2,60})["\']?\s*[;}]/i', $css, $m)) {
            foreach (array_unique($m[1]) as $name) {
                $name = trim($name);
                if (! isset($fonts[$name])) {
                    $fonts[$name] = '"' . $name . '",ui-sans-serif,system-ui,sans-serif';
                }
            }
        }

        return $fonts;
    }

    /**
     * @return array Theme palette entries.
     */
    public static function themePalette()
    {
        $palette = function_exists('wp_get_global_settings') ? (array) wp_get_global_settings(['color', 'palette']) : [];

        return isset($palette['theme']) ? array_values((array) $palette['theme']) : [];
    }

    /**
     * Colours the design uses most, for the prompt: hex => uses.
     *
     * @param array $config User global styles config.
     *
     * @return array
     */
    public static function usedColors(array $config)
    {
        $haystack = wp_json_encode($config);

        foreach (self::themePalette() as $entry) {
            $haystack .= ' ' . (isset($entry['color']) ? $entry['color'] : '');
        }

        preg_match_all('/#[0-9a-fA-F]{6}(?![0-9a-fA-F])/', (string) $haystack, $m);

        $counts = array_count_values(array_map('strtolower', $m[0]));
        arsort($counts);

        return array_slice($counts, 0, 24, true);
    }

    /**
     * @return int
     */
    public static function globalStylesPostId()
    {
        return class_exists('\WP_Theme_JSON_Resolver') ? (int) \WP_Theme_JSON_Resolver::get_user_global_styles_post_id() : 0;
    }
}
