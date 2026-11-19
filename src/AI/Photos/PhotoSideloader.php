<?php
/**
 * Imports a picked stock photo into the site's media library.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Pexels photos only: Unsplash requires hotlinking, so its photos stay on
 * images.unsplash.com and their credit lives on the page (PhotoCredits::META).
 *
 * Downloads the photo at the size the provider's CDN was asked for (about
 * 1600px wide), only from that provider's image host, and makes it a normal
 * attachment the owner can replace, with alt text and the credit the
 * library's terms ask for stored as attachment meta (META). PhotoCredits
 * reads that meta to print the credit on pages that show the photo.
 */
class PhotoSideloader
{
    const META      = '_swish_photo_credit';
    const MAX_BYTES = 12582912;

    /** Image hosts of the providers whose photos may be copied (Unsplash photos are hotlinked, never copied). */
    const HOSTS = [
        'pexels' => ['images.pexels.com'],
    ];

    /**
     * @param array  $photo   Normalised photo (StockPhotoProvider).
     * @param string $alt     Alt text.
     * @param int    $timeout Seconds.
     *
     * @throws \RuntimeException When it cannot be imported.
     *
     * @return array {id, url}
     */
    public function sideload(array $photo, $alt, $timeout)
    {
        if (! self::allowedUrl($photo)) {
            throw new \RuntimeException('Not a ' . (string) $photo['provider'] . ' image URL.');
        }
        if (! current_user_can('upload_files')) {
            throw new \RuntimeException('This user cannot upload files.');
        }

        $response = wp_safe_remote_get((string) $photo['download'], ['timeout' => max(1, (int) $timeout), 'limit_response_size' => self::MAX_BYTES, 'redirection' => 2]);
        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            throw new \RuntimeException('Image download returned HTTP ' . (int) wp_remote_retrieve_response_code($response) . '.');
        }

        $type = strtolower(trim(explode(';', (string) wp_remote_retrieve_header($response, 'content-type'))[0]));
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$type] ?? '';
        $body = (string) wp_remote_retrieve_body($response);
        if ($ext === '' || $body === '') {
            throw new \RuntimeException('The download was not an image.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $name = sanitize_file_name($photo['provider'] . '-' . $photo['id'] . '.' . $ext);
        $tmp  = wp_tempnam($name);
        if (! $tmp || false === file_put_contents($tmp, $body)) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            throw new \RuntimeException('Could not write a temporary file.');
        }

        $credit = self::creditMeta($photo);
        $id     = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], 0, self::creditLine($credit));
        if (is_wp_error($id)) {
            @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
            throw new \RuntimeException($id->get_error_message());
        }

        update_post_meta((int) $id, '_wp_attachment_image_alt', sanitize_text_field((string) $alt));
        update_post_meta((int) $id, self::META, $credit);

        $url = wp_get_attachment_image_url((int) $id, 'full');

        return ['id' => (int) $id, 'url' => $url ? (string) $url : (string) wp_get_attachment_url((int) $id)];
    }

    /**
     * Pure: whether the photo's download URL is on its provider's image host, over https.
     *
     * @param array $photo Photo.
     *
     * @return bool
     */
    public static function allowedUrl(array $photo)
    {
        $url   = isset($photo['download']) ? (string) $photo['download'] : '';
        $hosts = isset($photo['provider'], self::HOSTS[$photo['provider']]) ? self::HOSTS[$photo['provider']] : [];

        return strpos($url, 'https://') === 0 && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), $hosts, true);
    }

    /**
     * Pure: the attribution stored on the attachment.
     *
     * @param array $photo Normalised photo.
     *
     * @return array {provider, id, author, authorUrl, pageUrl, source, sourceUrl}
     */
    public static function creditMeta(array $photo)
    {
        $url = static function ($key) use ($photo) {
            return isset($photo[$key]) && strpos((string) $photo[$key], 'https://') === 0 ? esc_url_raw((string) $photo[$key]) : '';
        };

        return [
            'provider'  => sanitize_key(isset($photo['provider']) ? (string) $photo['provider'] : ''),
            'id'        => sanitize_text_field(isset($photo['id']) ? (string) $photo['id'] : ''),
            'author'    => sanitize_text_field(isset($photo['author']) ? (string) $photo['author'] : ''),
            'authorUrl' => $url('authorUrl'),
            'pageUrl'   => $url('pageUrl'),
            'source'    => sanitize_text_field(isset($photo['sourceName']) ? (string) $photo['sourceName'] : ''),
            'sourceUrl' => $url('sourceUrl'),
        ];
    }

    /**
     * @param array $credit creditMeta().
     *
     * @return string "Photo by Jane Doe on Unsplash".
     */
    public static function creditLine(array $credit)
    {
        /* translators: 1: photographer, 2: photo library (Unsplash, Pexels). */
        return sprintf(__('Photo by %1$s on %2$s', 'swishfolio-core'), $credit['author'], $credit['source']);
    }
}
