<?php
/**
 * The Swishfolio theme's style variations as custom design starting points.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * Each preset is one style variation of the theme (themes/swishfolio/styles/
 * <file>) written as a DesignTokens seed, by hand and once, so the mapping is
 * deterministic and never depends on a model:
 *
 * - palette roles come from the variation's own palette slugs (base is the
 *   background, contrast the text, and so on); a unit test checks every seed
 *   colour is still in the variation's JSON;
 * - fonts are the variation's families, all in FontCatalogue and so served
 *   from the theme's own woff2 files; a monospace body (Terminal) gets a
 *   technical sans instead, since DesignTokens refuses a mono body;
 * - radius, borders, shadow, buttons, links, cards, sections and decoration
 *   are the nearest enumerated option to the variation's styles.
 *
 * tokens() always passes the seed through DesignTokens::validate(), so the
 * same contrast enforcement as a model's design applies. With a preset and no
 * prompt the wizard compiles straight from it (no AI call, no credits); with
 * a prompt the seed goes to the model to modify (CustomDesigner).
 */
class StylePresets
{
    /**
     * slug => [label, variation file, summary, preview, seed].
     * preview: what the wizard's mini card draws, straight from the variation
     * (its own button colours and shape, before any contrast nudge).
     */
    const PRESETS = [
        'default'       => [
            'label'   => 'Default',
            'file'    => '00-default.json',
            'summary' => 'Minimal and light: near white pages, soft grey surfaces, Manrope headings over Geist text, one calm blue accent and gently rounded corners.',
            'preview' => ['bg' => '#fbfbfa', 'text' => '#18181b', 'button' => '#18181b', 'buttonText' => '#fbfbfa', 'border' => '#18181b', 'accent' => '#3563cc', 'radius' => '8px', 'borderWidth' => 1, 'shadow' => 'none'],
            'seed'    => [
                'name'            => 'Default',
                'palette'         => ['background' => '#fbfbfa', 'surface' => '#f3f4f1', 'text' => '#18181b', 'muted' => '#5c6170', 'accent' => '#3563cc', 'accent2' => '#18181b', 'soft' => '#e4e6e1', 'inverse' => '#18181b'],
                'fonts'           => ['display' => 'Manrope', 'body' => 'Geist', 'label' => 'Geist'],
                'headingWeight'   => 700,
                'headingCase'     => 'none',
                'headingTracking' => 'tight',
                'headingScale'    => 1.0,
                'labelStyle'      => 'plain',
                'radius'          => 'subtle',
                'borderWidth'     => 1,
                'shadow'          => 'none',
                'buttons'         => 'solid',
                'links'           => 'underline',
                'cards'           => 'bordered',
                'sections'        => 'flat',
                'spacing'         => 'regular',
                'imageStyle'      => 'natural',
                'imageFrame'      => 'none',
                'decor'           => 'none',
            ],
        ],
        'neo-brutalist' => [
            'label'   => 'Neo Brutalist',
            'file'    => '01-neo-brutalist.json',
            'summary' => 'Loud and graphic: cream pages, heavy uppercase headings, thick black borders, hard offset shadows and coral, yellow and lilac colour.',
            'preview' => ['bg' => '#fffdf5', 'text' => '#000000', 'button' => '#1040c0', 'buttonText' => '#ffffff', 'border' => '#000000', 'accent' => '#ff6b6b', 'radius' => '0', 'borderWidth' => 2, 'shadow' => '4px 4px 0 #000000'],
            'seed'    => [
                'name'            => 'Neo Brutalist',
                'palette'         => ['background' => '#fffdf5', 'surface' => '#ffd93d', 'text' => '#000000', 'muted' => '#121212', 'accent' => '#1040c0', 'accent2' => '#ff6b6b', 'soft' => '#c4b5fd', 'inverse' => '#000000'],
                'fonts'           => ['display' => 'Outfit', 'body' => 'Space Grotesk', 'label' => 'Space Grotesk'],
                'headingWeight'   => 900,
                'headingCase'     => 'uppercase',
                'headingTracking' => 'tight',
                'headingScale'    => 1.25,
                'labelStyle'      => 'uppercase',
                'radius'          => 'none',
                'borderWidth'     => 2,
                'shadow'          => 'hard',
                'buttons'         => 'hard-shadow',
                'links'           => 'underline',
                'cards'           => 'raised',
                'sections'        => 'alternate',
                'spacing'         => 'regular',
                'imageStyle'      => 'natural',
                'imageFrame'      => 'offset',
                'decor'           => 'none',
            ],
        ],
        'swiss'         => [
            'label'   => 'Swiss',
            'file'    => '02-swiss.json',
            'summary' => 'International style: white space, black uppercase Manrope headings, square corners, ruled sections and one signal red.',
            'preview' => ['bg' => '#ffffff', 'text' => '#000000', 'button' => '#000000', 'buttonText' => '#ffffff', 'border' => '#000000', 'accent' => '#ff3000', 'radius' => '0', 'borderWidth' => 2, 'shadow' => 'none'],
            'seed'    => [
                'name'            => 'Swiss',
                'palette'         => ['background' => '#ffffff', 'surface' => '#f2f2f2', 'text' => '#000000', 'muted' => '#666666', 'accent' => '#ff3000', 'accent2' => '#ff3000', 'soft' => '#e5e5e5', 'inverse' => '#000000'],
                'fonts'           => ['display' => 'Manrope', 'body' => 'Manrope', 'label' => 'Manrope'],
                'headingWeight'   => 800,
                'headingCase'     => 'uppercase',
                'headingTracking' => 'tight',
                'headingScale'    => 1.3,
                'labelStyle'      => 'uppercase',
                'radius'          => 'none',
                'borderWidth'     => 2,
                'shadow'          => 'none',
                'buttons'         => 'solid',
                'links'           => 'underline',
                'cards'           => 'flat',
                'sections'        => 'bordered',
                'spacing'         => 'airy',
                'imageStyle'      => 'natural',
                'imageFrame'      => 'none',
                'decor'           => 'grid',
            ],
        ],
        'sasha'         => [
            'label'   => 'Sasha',
            'file'    => '03-sasha.json',
            'summary' => 'Warm and editorial: cream and sand tones, huge tight Manrope headings, Geist text, mono labels, pill buttons and a terracotta accent.',
            'preview' => ['bg' => '#f3ede2', 'text' => '#1a1613', 'button' => '#1a1613', 'buttonText' => '#f3ede2', 'border' => '#1a1613', 'accent' => '#c8703a', 'radius' => '999px', 'borderWidth' => 0, 'shadow' => 'none'],
            'seed'    => [
                'name'            => 'Sasha',
                'palette'         => ['background' => '#f3ede2', 'surface' => '#ece4d4', 'text' => '#1a1613', 'muted' => '#4a423b', 'accent' => '#c8703a', 'accent2' => '#c8703a', 'soft' => '#e8d8be', 'inverse' => '#161311'],
                'fonts'           => ['display' => 'Manrope', 'body' => 'Geist', 'label' => 'JetBrains Mono'],
                'headingWeight'   => 800,
                'headingCase'     => 'none',
                'headingTracking' => 'tight',
                'headingScale'    => 1.4,
                'labelStyle'      => 'uppercase',
                'radius'          => 'pill',
                'borderWidth'     => 1,
                'shadow'          => 'soft',
                'buttons'         => 'solid',
                'links'           => 'plain',
                'cards'           => 'filled',
                'sections'        => 'flat',
                'spacing'         => 'airy',
                'imageStyle'      => 'natural',
                'imageFrame'      => 'none',
                'decor'           => 'none',
            ],
        ],
        'terminal'      => [
            'label'   => 'Terminal',
            'file'    => '09-terminal.json',
            'summary' => 'A green screen console: near black pages, phosphor green Fira Code headings and labels, amber highlights, sharp outlined buttons.',
            'preview' => ['bg' => '#0a0a0a', 'text' => '#33ff00', 'button' => 'transparent', 'buttonText' => '#33ff00', 'border' => '#33ff00', 'accent' => '#ffb000', 'radius' => '0', 'borderWidth' => 1, 'shadow' => 'none'],
            'seed'    => [
                'name'            => 'Terminal',
                'palette'         => ['background' => '#0a0a0a', 'surface' => '#111111', 'text' => '#33ff00', 'muted' => '#00ccff', 'accent' => '#33ff00', 'accent2' => '#ffb000', 'soft' => '#1f521f', 'inverse' => '#1f521f'],
                // Fira Code everywhere in the variation; DesignTokens refuses a
                // mono body, so running text gets Geist, a technical sans.
                'fonts'           => ['display' => 'Fira Code', 'body' => 'Geist', 'label' => 'Fira Code'],
                'headingWeight'   => 700,
                'headingCase'     => 'uppercase',
                'headingTracking' => 'wide',
                'headingScale'    => 1.1,
                'labelStyle'      => 'uppercase',
                'radius'          => 'none',
                'borderWidth'     => 1,
                'shadow'          => 'none',
                'buttons'         => 'outline',
                'links'           => 'accent',
                'cards'           => 'bordered',
                'sections'        => 'bordered',
                'spacing'         => 'compact',
                'imageStyle'      => 'grayscale',
                'imageFrame'      => 'border',
                'decor'           => 'lines',
            ],
        ],
    ];

    /**
     * @return string[] Preset slugs, in display order.
     */
    public static function slugs()
    {
        return array_keys(self::PRESETS);
    }

    /**
     * @param string $slug Slug.
     *
     * @return bool
     */
    public static function has($slug)
    {
        return is_string($slug) && isset(self::PRESETS[$slug]);
    }

    /**
     * The raw seed (before validation), e.g. for the model's starting point.
     *
     * @param string $slug Slug.
     *
     * @return array|null
     */
    public static function seed($slug)
    {
        if (! self::has($slug)) {
            return null;
        }
        $p = self::PRESETS[$slug];

        return array_merge($p['seed'], ['summary' => $p['summary']]);
    }

    /**
     * Complete, valid tokens for a preset, contrast enforced (DesignTokens::validate).
     *
     * @param string $slug Slug.
     *
     * @return array|null
     */
    public static function tokens($slug)
    {
        $seed = self::seed($slug);

        return $seed === null ? null : DesignTokens::validate($seed);
    }

    /**
     * What the wizard's preset cards draw: label, summary, the variation's own
     * colours and button shape, and font stacks from FontCatalogue.
     *
     * @return array[]
     */
    public static function previews()
    {
        $out = [];
        foreach (self::PRESETS as $slug => $p) {
            $f     = $p['seed']['fonts'];
            $out[] = array_merge($p['preview'], [
                'slug'          => $slug,
                'label'         => $p['label'],
                'summary'       => $p['summary'],
                'display'       => FontCatalogue::stack($f['display']),
                'body'          => FontCatalogue::stack($f['body']),
                'labelFont'     => FontCatalogue::stack($f['label']),
                'headingWeight' => $p['seed']['headingWeight'],
                'headingCase'   => $p['seed']['headingCase'],
                'labelCase'     => $p['seed']['labelStyle'] === 'uppercase' ? 'uppercase' : 'none',
            ]);
        }

        return $out;
    }

    /**
     * Every font family the presets use (for the wizard's @font-face rules).
     *
     * @return string[]
     */
    public static function families()
    {
        $all = [];
        foreach (self::PRESETS as $p) {
            $all = array_merge($all, array_values($p['seed']['fonts']));
        }

        return array_values(array_unique($all));
    }
}
