<?php
/**
 * The design system a model writes for a custom design, and its validation.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * A custom design is a small set of tokens, every one either a colour or a
 * choice from an enumerated list, never CSS. schema() is what the model must
 * answer in; validate() turns any decoded reply (or anything stored and read
 * back) into a complete, safe token set: unknown keys dropped, bad values
 * replaced by defaults, numbers clamped, fonts limited to FontCatalogue, and
 * every colour pair the site will show checked against WCAG AA and nudged
 * until it passes.
 */
class DesignTokens
{
    const PALETTE = ['background', 'surface', 'text', 'muted', 'accent', 'accent2', 'soft', 'inverse'];

    const ENUMS = [
        'headingCase'     => ['none', 'uppercase'],
        'headingTracking' => ['tight', 'normal', 'wide'],
        'labelStyle'      => ['uppercase', 'plain', 'italic'],
        'radius'          => ['none', 'subtle', 'rounded', 'soft', 'pill'],
        'shadow'          => ['none', 'soft', 'hard'],
        'buttons'         => ['solid', 'outline', 'underline', 'hard-shadow'],
        'links'           => ['underline', 'accent', 'plain'],
        'cards'           => ['flat', 'bordered', 'raised', 'filled'],
        'sections'        => ['flat', 'alternate', 'bordered'],
        'spacing'         => ['compact', 'regular', 'airy'],
        'imageStyle'      => ['natural', 'grayscale', 'warm', 'cool', 'high-contrast'],
        'imageFrame'      => ['none', 'border', 'shadow', 'offset'],
        'decor'           => ['none', 'lines', 'grid', 'dots', 'blobs', 'stripes'],
    ];

    const DEFAULTS = [
        'name'            => 'Custom',
        'summary'         => '',
        'palette'         => [
            'background' => '#ffffff',
            'surface'    => '#f3f3f1',
            'text'       => '#141414',
            'muted'      => '#555555',
            'accent'     => '#1f4fd8',
            'accent2'    => '#c2410c',
            'soft'       => '#e8eefc',
            'inverse'    => '#141414',
        ],
        'fonts'           => ['display' => 'Bricolage Grotesque', 'body' => 'Hanken Grotesk', 'label' => 'Hanken Grotesk'],
        'headingWeight'   => 700,
        'headingCase'     => 'none',
        'headingTracking' => 'tight',
        'headingScale'    => 1.0,
        'labelStyle'      => 'uppercase',
        'radius'          => 'rounded',
        'borderWidth'     => 1,
        'shadow'          => 'none',
        'buttons'         => 'solid',
        'links'           => 'accent',
        'cards'           => 'bordered',
        'sections'        => 'flat',
        'spacing'         => 'regular',
        'imageStyle'      => 'natural',
        'imageFrame'      => 'none',
        'decor'           => 'none',
    ];

    /**
     * JSON Schema for the model's reply.
     *
     * @return array
     */
    public static function schema()
    {
        $hex   = ['type' => 'string', 'description' => 'Hex colour, #rrggbb.'];
        $fonts = ['type' => 'string', 'enum' => FontCatalogue::families()];
        $props = [
            'name'    => ['type' => 'string', 'description' => 'A short evocative name for this design system (one or two words).'],
            'summary' => ['type' => 'string', 'description' => 'One sentence describing the look, for the owner. No dashes.'],
            'palette' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => self::PALETTE,
                'properties'           => array_fill_keys(self::PALETTE, $hex),
            ],
            'fonts'   => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['display', 'body', 'label'],
                'properties'           => ['display' => $fonts, 'body' => $fonts, 'label' => $fonts],
            ],
            'headingWeight' => ['type' => 'integer', 'description' => '100 to 900.'],
            'headingScale'  => ['type' => 'number', 'description' => '0.8 (restrained) to 1.4 (huge headings). 1 is the layout default.'],
            'borderWidth'   => ['type' => 'integer', 'description' => '0 to 4 (pixels) for card, section and button borders.'],
        ];
        foreach (self::ENUMS as $key => $values) {
            $props[$key] = ['type' => 'string', 'enum' => $values];
        }

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array_keys($props),
            'properties'           => $props,
        ];
    }

    /**
     * @param mixed $raw Decoded model reply or stored tokens.
     *
     * @return array Complete, valid tokens with contrast enforced.
     */
    public static function validate($raw)
    {
        $raw = is_array($raw) ? $raw : [];
        $d   = self::DEFAULTS;
        $out = [];

        $out['name']    = \SwishfolioCore\AI\Copy\Dashes::text(self::text(isset($raw['name']) ? $raw['name'] : '', 40)) ?: $d['name'];
        $out['summary'] = \SwishfolioCore\AI\Copy\Dashes::text(self::text(isset($raw['summary']) ? $raw['summary'] : '', 240));

        $palette = isset($raw['palette']) && is_array($raw['palette']) ? $raw['palette'] : [];
        foreach (self::PALETTE as $key) {
            $hex                  = Color::hex(isset($palette[$key]) ? $palette[$key] : '');
            $out['palette'][$key] = $hex !== '' ? $hex : $d['palette'][$key];
        }

        $fonts = isset($raw['fonts']) && is_array($raw['fonts']) ? $raw['fonts'] : [];
        foreach (['display', 'body', 'label'] as $key) {
            $family             = isset($fonts[$key]) ? (string) $fonts[$key] : '';
            $out['fonts'][$key] = FontCatalogue::has($family) ? $family : $d['fonts'][$key];
        }
        // A monospace body is unreadable at length.
        if (FontCatalogue::role($out['fonts']['body']) === 'mono') {
            $out['fonts']['body'] = $d['fonts']['body'];
        }

        $weight               = isset($raw['headingWeight']) && is_numeric($raw['headingWeight']) ? (int) $raw['headingWeight'] : $d['headingWeight'];
        $weight               = (int) (round(max(100, min(900, $weight)) / 100) * 100);
        $out['headingWeight'] = FontCatalogue::clampWeight($weight, $out['fonts']['display']);

        $scale               = isset($raw['headingScale']) && is_numeric($raw['headingScale']) ? (float) $raw['headingScale'] : $d['headingScale'];
        $out['headingScale'] = round(max(0.8, min(1.4, $scale)), 2);

        $border             = isset($raw['borderWidth']) && is_numeric($raw['borderWidth']) ? (int) $raw['borderWidth'] : $d['borderWidth'];
        $out['borderWidth'] = max(0, min(4, $border));

        foreach (self::ENUMS as $key => $values) {
            $value     = isset($raw[$key]) ? (string) $raw[$key] : '';
            $out[$key] = in_array($value, $values, true) ? $value : $d[$key];
        }

        $out['palette'] = self::enforceContrast($out['palette']);
        $out['derived'] = self::derive($out['palette']);

        return $out;
    }

    /**
     * Every pair the compiled design shows as text on a background reaches AA.
     *
     * @param array $p Palette (valid hex values).
     *
     * @return array
     */
    public static function enforceContrast(array $p)
    {
        // Body text gets headroom (7:1) so lighter mixes of it still read.
        $p['text']    = Color::ensureContrast($p['text'], $p['background'], 7.0);
        $p['surface'] = self::ensureBackground($p['surface'], $p['text'], Color::AA);
        $p['soft']    = self::ensureBackground($p['soft'], $p['text'], Color::AA);
        $p['muted']   = Color::ensureContrast($p['muted'], $p['background'], Color::AA);
        $p['muted']   = Color::ensureContrast($p['muted'], $p['surface'], Color::AA);
        $p['accent']  = Color::ensureContrast($p['accent'], $p['background'], Color::AA);
        $p['accent2'] = Color::ensureContrast($p['accent2'], $p['background'], Color::AA_LARGE);
        // Inverse bands carry the page background colour as their text.
        $p['inverse'] = Color::ensureContrast($p['inverse'], $p['background'], Color::AA);
        // Kits set accent words on their inverse bands too: large text, 3:1,
        // without giving up AA on the page. Best effort: a light design's dark
        // accent and dark band may leave no room (kits use a light tint there).
        $p['accent'] = self::accentOnBoth($p['accent'], $p['background'], $p['inverse']);

        return $p;
    }

    /**
     * @param string $accent     Accent (AA on the background already).
     * @param string $background Page background.
     * @param string $inverse    Inverse band.
     *
     * @return string
     */
    private static function accentOnBoth($accent, $background, $inverse)
    {
        if (Color::contrast($accent, $inverse) >= Color::AA_LARGE) {
            return $accent;
        }
        $away = Color::luminance($inverse) > 0.18 ? '#000000' : '#ffffff';
        for ($i = 1; $i <= 25; $i++) {
            $next = Color::mix($accent, $away, $i * 0.03);
            if (Color::contrast($next, $background) < Color::AA) {
                break;
            }
            if (Color::contrast($next, $inverse) >= Color::AA_LARGE) {
                return $next;
            }
        }

        return $accent;
    }

    /**
     * Colours compiled from the palette, never chosen by the model.
     *
     * @param array $p Palette.
     *
     * @return array onAccent, onAccent2, onInverse, line, lineStrong.
     */
    public static function derive(array $p)
    {
        return [
            'onAccent'   => Color::readableOn($p['accent'], [$p['background'], $p['text'], '#ffffff', '#000000']),
            'onAccent2'  => Color::readableOn($p['accent2'], [$p['background'], $p['text'], '#ffffff', '#000000']),
            'onInverse'  => Color::readableOn($p['inverse'], [$p['background'], $p['text'], '#ffffff', '#000000']),
            'line'       => Color::mix($p['background'], $p['text'], 0.18),
            'lineStrong' => Color::mix($p['background'], $p['text'], 0.55),
        ];
    }

    /**
     * Move a background (not the text) until the text reads on it.
     */
    private static function ensureBackground($bg, $text, $ratio)
    {
        return Color::contrast($bg, $text) >= $ratio ? $bg : Color::ensureContrast($bg, $text, $ratio);
    }

    private static function text($value, $max)
    {
        $value = is_scalar($value) ? (string) $value : '';
        $value = function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value));
        // Copy rule: no long dashes in anything the owner reads.
        $value = str_replace(["\u{2014}", "\u{2013}"], [', ', ' to '], $value);

        return mb_substr($value, 0, $max);
    }
}
