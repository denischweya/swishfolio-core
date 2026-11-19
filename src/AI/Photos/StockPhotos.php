<?php
/**
 * Which stock photo libraries this site may search.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Keys come only from the network (swishfolio-extra's "Photos" tab, through
 * the swishfolio_stock_photo_credentials filter); a site has no way to set
 * one. No key, no providers: photo sourcing is off and the images stage does
 * exactly what it did before.
 */
class StockPhotos
{
    /**
     * @return StockPhotoProvider[] Unsplash first, then Pexels; [] when off.
     */
    public static function providers()
    {
        /**
         * Filters the stock photo API keys (server side only, never sent to a browser).
         *
         * @param array $credentials {unsplash: string, pexels: string, app: string (Unsplash utm_source)}.
         */
        $creds = apply_filters('swishfolio_stock_photo_credentials', []);
        $creds = is_array($creds) ? $creds : [];
        $out   = [];

        if (! empty($creds['unsplash']) && is_string($creds['unsplash'])) {
            $out[] = new UnsplashPhotos($creds['unsplash'], isset($creds['app']) ? (string) $creds['app'] : 'swishfolio');
        }
        if (! empty($creds['pexels']) && is_string($creds['pexels'])) {
            $out[] = new PexelsPhotos($creds['pexels']);
        }

        return $out;
    }

    /**
     * @return bool Whether photo sourcing is on.
     */
    public static function enabled()
    {
        return self::providers() !== [];
    }
}
