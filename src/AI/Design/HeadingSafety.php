<?php
/**
 * Headings that never overflow their column.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

/**
 * Template headings are fitted to the demo's short copy, some on one line
 * (white-space:nowrap) at sizes in tens of vw. An owner's longer words must
 * wrap instead of running out of their column or under a photo, at every
 * width: wrapping and hyphenation always, breaking anywhere only as the last
 * resort on narrow screens. A decorative copy of a title (a "ghost" drawn
 * again over a photo, aria-hidden) wraps by the same rules, so its lines stay
 * on the real title's; the fit wrapper a long title gets (HeadlineFit) comes
 * with them. Appended to a kit's styles (KitDesign) and to a compiled custom
 * design (CssCompiler), after the kit's own rules.
 */
class HeadingSafety
{
    const MARKER = '/* swish-heading-safety */';

    /**
     * @return string CSS.
     */
    public static function css()
    {
        // Separate selectors: :is() takes its most specific argument's weight.
        $h = 'body :is(h1,h2,h3,h4,h5,h6),body [aria-hidden="true"][class*="ghost"]:is([class*="title"],[class*="heading"])';

        return self::MARKER . "\n"
            . $h . '{overflow-wrap:break-word;hyphens:auto;min-width:0;white-space:normal!important;text-wrap:balance}' . "\n"
            . \SwishfolioCore\AI\Generation\HeadlineFit::css() . "\n"
            . '@media (max-width:781px){' . $h . ',body .' . \SwishfolioCore\AI\Generation\HeadlineFit::CLASS_NAME . '{overflow-wrap:anywhere}}' . "\n";
    }

    /**
     * Add the layer to a global styles JSON document (once).
     *
     * @param string $json Global styles post content.
     *
     * @return string
     */
    public static function addTo($json)
    {
        $doc = json_decode((string) $json, true);
        if (! is_array($doc)) {
            return (string) $json;
        }
        $css = isset($doc['styles']['css']) ? (string) $doc['styles']['css'] : '';
        if (strpos($css, self::MARKER) !== false) {
            return (string) $json;
        }
        $doc['styles']['css'] = rtrim($css) . "\n" . self::css();

        return (string) (function_exists('wp_json_encode') ? wp_json_encode($doc) : json_encode($doc));
    }
}
