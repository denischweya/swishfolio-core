<?php
/**
 * One stock photo library (Unsplash, Pexels).
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Every provider answers in one normalised photo shape, so the sourcing,
 * vision check, sideload and credits never look at a provider's own JSON:
 *
 *     provider   string  Provider slug.
 *     id         string  Provider's photo id.
 *     thumb      string  Small hotlinked image (the vision check looks at it).
 *     download   string  The image at about 1600px wide, for the media library ('' when hotlinked).
 *     hotlink    string  The image at about 1600px wide, used in place on the page ('' when imported).
 *     alt        string  The library's own description ('' when none).
 *     author     string  Photographer's name.
 *     authorUrl  string  Photographer's profile (with any referral params the terms ask for).
 *     pageUrl    string  The photo's page on the library.
 *     sourceName string  "Unsplash" / "Pexels".
 *     sourceUrl  string  The library's home page (with referral params).
 *     track      string  URL to call when the photo is used ('' when the terms ask for none).
 *
 * HTTP goes through wp_safe_remote_get() so the pre_http_request filter can
 * stand in for the network in tests. Keys are sent in headers and never
 * appear in a URL, an error message or a log line.
 */
interface StockPhotoProvider
{
    /**
     * @return string Slug ('unsplash', 'pexels').
     */
    public function slug();

    /**
     * @param string $query       Short search query.
     * @param string $orientation landscape|portrait|square.
     * @param int    $count       Results wanted.
     * @param int    $timeout     Seconds.
     *
     * @return array[] Normalised photos; [] on no results or any error (see lastError()).
     */
    public function search($query, $orientation, $count, $timeout);

    /**
     * Tell the library the photo was used, where its terms ask for it.
     *
     * @param array $photo   Normalised photo.
     * @param int   $timeout Seconds.
     *
     * @return void
     */
    public function trackUse(array $photo, $timeout);

    /**
     * @return string Why the last search returned nothing ('' when it simply had no results).
     */
    public function lastError();
}
