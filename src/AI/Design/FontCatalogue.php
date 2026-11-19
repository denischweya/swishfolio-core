<?php
/**
 * The fonts a custom design may use.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * A vetted list of self-hosted fonts: the woff2 files the template kits
 * already bundle in this plugin (assets/kits/<slug>/fonts) and the ones the
 * Swishfolio theme ships (assets/fonts). Nothing is loaded from a font CDN.
 *
 * A model only ever names a family from this list (the design schema's enum);
 * the @font-face rules and stacks are written here from these entries, so no
 * font URL in a custom design comes from model output.
 */
class FontCatalogue
{
    /**
     * family => [role, fallback stack, character, faces: [base, path, weight, style, stretch]]
     * role: serif | display | sans | mono. base: plugin | theme.
     */
    const FONTS = [
        // Serifs.
        'Fraunces'            => ['serif', 'Georgia,serif', 'soft, warm editorial serif with character; italic', [['plugin', 'assets/kits/restaurant/fonts/fraunces-roman.woff2', '300 700', 'normal'], ['plugin', 'assets/kits/restaurant/fonts/fraunces-italic.woff2', '300 700', 'italic']]],
        'Gloock'              => ['serif', 'Georgia,serif', 'high-contrast didone display serif, luxurious and dramatic; one weight (400)', [['plugin', 'assets/kits/chef/fonts/gloock.woff2', '400', 'normal']]],
        'Young Serif'         => ['serif', 'Georgia,serif', 'chunky, friendly old-style serif; one weight (400)', [['plugin', 'assets/kits/advisors/fonts/young-serif.woff2', '400', 'normal']]],
        'Newsreader'          => ['serif', 'Georgia,serif', 'refined newspaper serif, calm and literate; italic', [['plugin', 'assets/kits/law/fonts/newsreader.woff2', '200 800', 'normal'], ['plugin', 'assets/kits/law/fonts/newsreader-italic.woff2', '200 800', 'italic']]],
        'Literata'            => ['serif', 'Georgia,serif', 'bookish reading serif, trustworthy; italic', [['theme', 'assets/fonts/literata/Literata72pt-Regular.woff2', '400', 'normal'], ['theme', 'assets/fonts/literata/Literata72pt-RegularItalic.woff2', '400', 'italic'], ['theme', 'assets/fonts/literata/Literata72pt-SemiBold.woff2', '600', 'normal'], ['theme', 'assets/fonts/literata/Literata72pt-Bold.woff2', '700', 'normal']]],
        'Platypi'             => ['serif', 'Georgia,serif', 'sturdy contemporary serif with sharp wedges; italic', [['theme', 'assets/fonts/platypi/Platypi-VariableFont_wght.woff2', '300 800', 'normal'], ['theme', 'assets/fonts/platypi/Platypi-Italic-VariableFont_wght.woff2', '300 800', 'italic']]],
        'Vollkorn'            => ['serif', 'Georgia,serif', 'dark, hearty classic serif; italic', [['theme', 'assets/fonts/vollkorn/Vollkorn-VariableFont_wght.woff2', '400 900', 'normal'], ['theme', 'assets/fonts/vollkorn/Vollkorn-Italic-VariableFont_wght.woff2', '400 900', 'italic']]],
        'Roboto Slab'         => ['serif', 'Rockwell,Georgia,serif', 'slab serif, solid and mechanical', [['theme', 'assets/fonts/roboto-slab/RobotoSlab-VariableFont_wght.woff2', '100 900', 'normal']]],
        // Display sans.
        'Big Shoulders Display' => ['display', 'Impact,"Arial Narrow",sans-serif', 'tall condensed industrial display, loud at heavy weights', [['plugin', 'assets/kits/fitness/fonts/big-shoulders-var.woff2', '100 900', 'normal']]],
        'Syne'                => ['display', 'system-ui,sans-serif', 'wide, quirky art-school display, very expressive at 700-800', [['plugin', 'assets/kits/graphic/fonts/syne-var.woff2', '400 800', 'normal']]],
        'Bricolage Grotesque' => ['display', 'system-ui,sans-serif', 'characterful grotesk with ink traps, playful and modern', [['plugin', 'assets/kits/agency/fonts/bricolage-grotesque-var.woff2', '200 800', 'normal', '75% 100%']]],
        'Funnel Display'      => ['display', 'system-ui,sans-serif', 'smooth, rounded contemporary display', [['plugin', 'assets/kits/studio/fonts/funnel-display.woff2', '300 800', 'normal']]],
        'Space Grotesk'       => ['display', 'system-ui,sans-serif', 'techy grotesk with odd details, futuristic', [['theme', 'assets/fonts/space-grotesk/SpaceGrotesk-VariableFont_wght.woff2', '300 700', 'normal']]],
        'Archivo'             => ['display', 'system-ui,sans-serif', 'robust grotesk, bold and blunt, works heavy', [['plugin', 'assets/kits/hvac/fonts/archivo-var.woff2', '100 900', 'normal', '62% 125%']]],
        'Outfit'              => ['display', 'system-ui,sans-serif', 'clean geometric sans, round and friendly', [['theme', 'assets/fonts/outfit/Outfit-VariableFont_wght.woff2', '100 900', 'normal']]],
        // Text sans.
        'Hanken Grotesk'      => ['sans', 'system-ui,sans-serif', 'neutral modern grotesk; italic', [['plugin', 'assets/kits/photographer/fonts/hanken-grotesk-var.woff2', '100 900', 'normal'], ['plugin', 'assets/kits/photographer/fonts/hanken-grotesk-italic-var.woff2', '100 900', 'italic']]],
        'Inter Tight'         => ['sans', 'system-ui,sans-serif', 'tight, efficient UI sans', [['plugin', 'assets/kits/agency/fonts/inter-tight-var.woff2', '300 700', 'normal']]],
        'Jost'                => ['sans', '"Century Gothic",system-ui,sans-serif', 'geometric Futura-like sans, elegant; italic', [['plugin', 'assets/kits/chef/fonts/jost-var.woff2', '300 700', 'normal'], ['plugin', 'assets/kits/chef/fonts/jost-italic-var.woff2', '300 700', 'italic']]],
        'Plus Jakarta Sans'   => ['sans', 'system-ui,sans-serif', 'friendly, open modern sans', [['plugin', 'assets/kits/consultancy/fonts/plus-jakarta-sans.woff2', '200 800', 'normal']]],
        'Albert Sans'         => ['sans', 'system-ui,sans-serif', 'crisp Scandinavian sans; italic', [['plugin', 'assets/kits/fitness/fonts/albert-sans-var.woff2', '300 800', 'normal'], ['plugin', 'assets/kits/fitness/fonts/albert-sans-italic-var.woff2', '300 800', 'italic']]],
        'DM Sans'             => ['sans', 'system-ui,sans-serif', 'low-contrast geometric sans, soft; italic', [['plugin', 'assets/kits/graphic/fonts/dm-sans-var.woff2', '100 1000', 'normal'], ['plugin', 'assets/kits/graphic/fonts/dm-sans-italic-var.woff2', '100 1000', 'italic']]],
        'Instrument Sans'     => ['sans', 'system-ui,sans-serif', 'precise, slightly condensed sans', [['plugin', 'assets/kits/law/fonts/instrument-sans.woff2', '400 700', 'normal']]],
        'Schibsted Grotesk'   => ['sans', 'system-ui,sans-serif', 'sturdy newsy grotesk', [['plugin', 'assets/kits/logistics/fonts/schibsted-grotesk-var.woff2', '400 900', 'normal']]],
        'Onest'               => ['sans', 'system-ui,sans-serif', 'calm, rounded humanist sans', [['plugin', 'assets/kits/studio/fonts/onest.woff2', '100 900', 'normal']]],
        'Figtree'             => ['sans', 'system-ui,sans-serif', 'friendly, clear geometric sans; italic', [['plugin', 'assets/kits/accountant/fonts/figtree.woff2', '300 900', 'normal'], ['plugin', 'assets/kits/accountant/fonts/figtree-italic.woff2', '300 900', 'italic']]],
        'Manrope'             => ['sans', 'system-ui,sans-serif', 'modern semi-rounded sans', [['theme', 'assets/fonts/manrope/Manrope-VariableFont_wght.woff2', '200 800', 'normal']]],
        'Geist'               => ['sans', 'system-ui,sans-serif', 'minimal Swiss-style sans, technical', [['theme', 'assets/fonts/geist/Geist-VariableFont_wght.woff2', '100 900', 'normal']]],
        // Mono.
        'DM Mono'             => ['mono', 'ui-monospace,Menlo,monospace', 'soft monospace for labels', [['plugin', 'assets/kits/restaurant/fonts/dm-mono-400.woff2', '400', 'normal'], ['plugin', 'assets/kits/restaurant/fonts/dm-mono-500.woff2', '500', 'normal']]],
        'JetBrains Mono'      => ['mono', 'ui-monospace,Menlo,monospace', 'technical monospace, code feel', [['theme', 'assets/fonts/jetbrains-mono/JetBrainsMono-VariableFont_wght.woff2', '100 800', 'normal']]],
        'Fira Code'           => ['mono', 'ui-monospace,Menlo,monospace', 'friendly coding monospace', [['theme', 'assets/fonts/fira-code/FiraCode-VariableFont_wght.woff2', '300 700', 'normal']]],
    ];

    /**
     * @return string[] Every family name.
     */
    public static function families()
    {
        return array_keys(self::FONTS);
    }

    /**
     * @param string $family Family.
     *
     * @return bool
     */
    public static function has($family)
    {
        return isset(self::FONTS[(string) $family]);
    }

    /**
     * @param string $family Family.
     *
     * @return string serif|display|sans|mono or ''.
     */
    public static function role($family)
    {
        return self::has($family) ? self::FONTS[$family][0] : '';
    }

    /**
     * @param string $family Family.
     *
     * @return string CSS stack, e.g. "Gloock",Georgia,serif.
     */
    public static function stack($family)
    {
        if (! self::has($family)) {
            return 'system-ui,sans-serif';
        }

        return '"' . $family . '",' . self::FONTS[$family][1];
    }

    /**
     * @param string $family Family.
     *
     * @return int[] [min, max] weight the files cover.
     */
    public static function weightRange($family)
    {
        $min = 1000;
        $max = 0;
        foreach (self::has($family) ? self::FONTS[$family][3] : [] as $face) {
            $parts = array_map('intval', explode(' ', $face[2]));
            $min   = min($min, $parts[0]);
            $max   = max($max, end($parts));
        }

        return $min <= $max ? [$min, $max] : [400, 400];
    }

    /**
     * @param int    $weight Wanted weight.
     * @param string $family Family.
     *
     * @return int Nearest weight the family has (no faux bold).
     */
    public static function clampWeight($weight, $family)
    {
        list($min, $max) = self::weightRange($family);

        return (int) max($min, min($max, (int) $weight));
    }

    /**
     * The catalogue for a prompt: one line per family.
     *
     * @return string
     */
    public static function describe()
    {
        $lines = [];
        foreach (self::FONTS as $family => $f) {
            list($min, $max) = self::weightRange($family);
            $lines[] = sprintf('%s (%s, weights %s): %s', $family, $f[0], $min === $max ? $min : $min . '-' . $max, $f[2]);
        }

        return implode("\n", $lines);
    }

    /**
     * The @font-face rules for the given families.
     *
     * @param string[] $families   Families.
     * @param string   $pluginBase This plugin's URL (trailing slash optional).
     * @param string   $themeBase  The theme's URL.
     *
     * @return string
     */
    public static function fontFaces(array $families, $pluginBase, $themeBase)
    {
        $bases = ['plugin' => rtrim((string) $pluginBase, '/') . '/', 'theme' => rtrim((string) $themeBase, '/') . '/'];
        $css   = '';

        foreach (array_unique($families) as $family) {
            if (! self::has($family)) {
                continue;
            }
            foreach (self::FONTS[$family][3] as $face) {
                $css .= '@font-face{font-family:"' . $family . '";src:url(' . $bases[$face[0]] . $face[1] . ') format("woff2");font-weight:' . $face[2] . ';font-style:' . $face[3]
                    . (isset($face[4]) ? ';font-stretch:' . $face[4] : '') . ';font-display:swap}' . "\n";
            }
        }

        return $css;
    }

    /**
     * Every font file URL a design may reference (for the sanitiser).
     *
     * @param string $pluginBase Plugin URL.
     * @param string $themeBase  Theme URL.
     *
     * @return string[]
     */
    public static function urls($pluginBase, $themeBase)
    {
        $bases = ['plugin' => rtrim((string) $pluginBase, '/') . '/', 'theme' => rtrim((string) $themeBase, '/') . '/'];
        $out   = [];
        foreach (self::FONTS as $f) {
            foreach ($f[3] as $face) {
                $out[] = $bases[$face[0]] . $face[1];
            }
        }

        return $out;
    }

    /**
     * Relative file paths, for the "every file exists" test.
     *
     * @return array[] [base, path]
     */
    public static function files()
    {
        $out = [];
        foreach (self::FONTS as $f) {
            foreach ($f[3] as $face) {
                $out[] = [$face[0], $face[1]];
            }
        }

        return $out;
    }
}
