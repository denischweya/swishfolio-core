<?php
/**
 * Unsplash API photo search.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Unsplash API guidelines this follows
 * (https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines):
 *
 * - Hotlinking: every image URL used is one the API returned under
 *   photo.urls, and Unsplash photos are never copied into the media library.
 *   The vision check looks at urls.small; the page shows urls.regular with
 *   Imgix sizing params (w=1600) in place.
 * - Downloads: using a photo on a site counts as a download, so trackUse()
 *   calls links.download_location (authenticated) for every photo placed.
 * - Attribution: photographer and Unsplash, linked, with
 *   utm_source=<app>&utm_medium=referral on every link back (PhotoCredits
 *   prints it on each page that shows the photo).
 *
 * Auth is the "Client-ID <access key>" header, never a query arg.
 */
class UnsplashPhotos extends AbstractStockPhotos
{
    const API = 'https://api.unsplash.com';

    /** @var string utm_source value: the application name registered with Unsplash. */
    private $app;

    /**
     * @param string $key Access key.
     * @param string $app Application name for the utm_source referral param.
     */
    public function __construct($key, $app = 'swishfolio')
    {
        parent::__construct($key);
        $this->app = sanitize_key((string) $app) !== '' ? sanitize_key((string) $app) : 'swishfolio';
    }

    public function slug()
    {
        return 'unsplash';
    }

    protected function orientation($orientation)
    {
        return $orientation === 'portrait' ? 'portrait' : ($orientation === 'square' ? 'squarish' : 'landscape');
    }

    public function search($query, $orientation, $count, $timeout)
    {
        $this->error = '';
        $decoded     = $this->getJson(self::API . '/search/photos?' . http_build_query([
            'query'          => (string) $query,
            'per_page'       => max(1, min(10, (int) $count)),
            'orientation'    => $this->orientation($orientation),
            'content_filter' => 'high',
        ], '', '&', PHP_QUERY_RFC3986), $this->headers(), $timeout);

        $out = [];
        foreach ($decoded !== null && isset($decoded['results']) ? (array) $decoded['results'] : [] as $r) {
            $photo = $this->normalise($r);
            if ($photo !== null) {
                $out[] = $photo;
            }
        }

        return $out;
    }

    public function trackUse(array $photo, $timeout)
    {
        if (! empty($photo['track']) && strpos((string) $photo['track'], self::API . '/') === 0) {
            $this->getJson((string) $photo['track'], $this->headers(), $timeout);
        }
    }

    /**
     * @param mixed $r One search result.
     *
     * @return array|null Normalised photo.
     */
    public function normalise($r)
    {
        if (! is_array($r) || self::str(isset($r['id']) ? $r['id'] : '') === '') {
            return null;
        }

        $raw    = self::str(isset($r['urls']['regular']) ? $r['urls']['regular'] : '');
        $thumb  = self::str(isset($r['urls']['small']) ? $r['urls']['small'] : '');
        $author = self::str(isset($r['user']['name']) ? $r['user']['name'] : '');

        if ($raw === '' || $thumb === '' || $author === '' || strpos($raw, 'https://') !== 0 || strpos($thumb, 'https://') !== 0) {
            return null;
        }

        $utm = ['utm_source' => $this->app, 'utm_medium' => 'referral'];

        return [
            'provider'   => 'unsplash',
            'id'         => self::str($r['id']),
            'thumb'      => $thumb,
            'download'   => '',
            'hotlink'    => self::withArgsReplaced($raw, ['w' => 1600, 'fit' => 'max', 'q' => 80, 'fm' => 'jpg']),
            'alt'        => self::str(isset($r['alt_description']) ? $r['alt_description'] : (isset($r['description']) ? $r['description'] : '')),
            'author'     => $author,
            'authorUrl'  => self::withArgs(self::str(isset($r['user']['links']['html']) ? $r['user']['links']['html'] : ''), $utm),
            'pageUrl'    => self::withArgs(self::str(isset($r['links']['html']) ? $r['links']['html'] : ''), $utm),
            'sourceName' => 'Unsplash',
            'sourceUrl'  => self::withArgs('https://unsplash.com/', $utm),
            'track'      => self::str(isset($r['links']['download_location']) ? $r['links']['download_location'] : ''),
        ];
    }

    /**
     * @return array
     */
    private function headers()
    {
        return ['Authorization' => 'Client-ID ' . $this->key, 'Accept-Version' => 'v1'];
    }
}
