<?php
/**
 * Pexels API photo search.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Pexels API guidelines (https://www.pexels.com/api/documentation/): show a
 * prominent link to Pexels and credit the photographer where possible.
 * PhotoCredits prints "Photo by <photographer> on Pexels", both linked, on
 * every page that shows the photo. There is no download endpoint to call.
 *
 * Auth is the bare key in the Authorization header. The media library copy is
 * src.original resized by the Pexels CDN (w=1600).
 */
class PexelsPhotos extends AbstractStockPhotos
{
    const API = 'https://api.pexels.com/v1';

    public function slug()
    {
        return 'pexels';
    }

    protected function orientation($orientation)
    {
        return in_array($orientation, ['portrait', 'square'], true) ? $orientation : 'landscape';
    }

    public function search($query, $orientation, $count, $timeout)
    {
        $this->error = '';
        $decoded     = $this->getJson(self::API . '/search?' . http_build_query([
            'query'       => (string) $query,
            'per_page'    => max(1, min(10, (int) $count)),
            'orientation' => $this->orientation($orientation),
        ], '', '&', PHP_QUERY_RFC3986), ['Authorization' => $this->key], $timeout);

        $out = [];
        foreach ($decoded !== null && isset($decoded['photos']) ? (array) $decoded['photos'] : [] as $r) {
            $photo = $this->normalise($r);
            if ($photo !== null) {
                $out[] = $photo;
            }
        }

        return $out;
    }

    public function trackUse(array $photo, $timeout)
    {
        // Pexels asks for attribution only.
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

        $original = self::str(isset($r['src']['original']) ? $r['src']['original'] : '');
        $thumb    = self::str(isset($r['src']['medium']) ? $r['src']['medium'] : '');
        $author   = self::str(isset($r['photographer']) ? $r['photographer'] : '');

        if ($original === '' || $thumb === '' || $author === '' || strpos($original, 'https://') !== 0 || strpos($thumb, 'https://') !== 0) {
            return null;
        }

        return [
            'provider'   => 'pexels',
            'id'         => self::str($r['id']),
            'thumb'      => $thumb,
            'download'   => self::withArgs($original, ['auto' => 'compress', 'cs' => 'tinysrgb', 'w' => 1600]),
            'hotlink'    => '',
            'alt'        => self::str(isset($r['alt']) ? $r['alt'] : ''),
            'author'     => $author,
            'authorUrl'  => self::str(isset($r['photographer_url']) ? $r['photographer_url'] : ''),
            'pageUrl'    => self::str(isset($r['url']) ? $r['url'] : ''),
            'sourceName' => 'Pexels',
            'sourceUrl'  => 'https://www.pexels.com',
            'track'      => '',
        ];
    }
}
