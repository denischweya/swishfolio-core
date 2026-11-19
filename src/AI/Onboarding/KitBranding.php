<?php
/**
 * Puts the owner's logo where a kit header shows its wordmark.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

/**
 * Kit headers draw the demo brand themselves: most as a link to "/" with a
 * class ending in -logo or -brand holding an inline SVG mark and the name
 * (<a class="lu-brand" href="/" aria-label="Lumbre home">…</a>); a few use
 * the core/site-title block. Without a logo upload the wordmark text is
 * rewritten with the rest of the header copy (TemplateCustomiser), and the
 * site title already carries the business name. With a logo, this swaps the
 * brand link's contents for the logo image, or the site-title block for the
 * site-logo block (the site_logo option is set alongside).
 *
 * Pure.
 */
class KitBranding
{
    const BRAND = '#(<a\b[^>]*\bclass="[^"]*\b[a-z]{1,4}-(?:logo|brand)\b[^"]*"[^>]*>)(.*?)(</a>)#s';

    /**
     * @param string $header Header part markup.
     *
     * @return bool Whether the header has a brand link or site-title block to swap.
     */
    public static function hasBrand($header)
    {
        return (bool) preg_match(self::BRAND, (string) $header) || strpos((string) $header, '<!-- wp:site-title') !== false || strpos((string) $header, '<!-- wp:site-logo') !== false;
    }

    /**
     * @param string $header Header part markup.
     * @param string $url    Logo URL.
     * @param string $name   Business name (alt text).
     *
     * @return string
     */
    public static function withLogo($header, $url, $name)
    {
        $header = (string) $header;
        $alt    = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $src    = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');

        if ($src === '') {
            return $header;
        }

        if (preg_match(self::BRAND, $header)) {
            return (string) preg_replace_callback(self::BRAND, static function ($m) use ($src, $alt) {
                $open = preg_replace('/\baria-label="[^"]*"/', 'aria-label="' . $alt . ' home"', $m[1]);
                $open = preg_replace('/\bclass="([^"]*)"/', 'class="$1 swish-has-logo"', $open, 1);

                return $open . '<img class="swish-logo" src="' . $src . '" alt="' . $alt . '" style="display:block;height:2.75rem;width:auto;max-width:13rem;object-fit:contain">' . $m[3];
            }, $header, 1);
        }

        if (strpos($header, '<!-- wp:site-logo') !== false) {
            return $header;
        }

        return (string) preg_replace('#<!-- wp:site-title(?:\s+(\{.*?\}))?\s*/-->#s', '<!-- wp:site-logo {"width":180,"shouldSyncIcon":false} /-->', $header, 1);
    }
}
