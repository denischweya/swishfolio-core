<?php
/**
 * The photo credit line on pages that show imported stock photos.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Photos;

/**
 * Unsplash asks that a displayed photo credits the photographer and Unsplash,
 * linked (with utm referral params); Pexels asks for a link to Pexels and a
 * photographer credit. One small line at the very end of a page that shows
 * such photos does both: "Photos: Jane Doe on Unsplash, Sam Lee on Pexels".
 * Pages without imported stock photos print nothing.
 */
class PhotoCredits
{
    /** Page meta: credits of hotlinked photos on the page (Unsplash), key "provider:id". */
    const META = '_swish_photo_credits';

    /**
     * @return void
     */
    public static function register()
    {
        add_action('wp_footer', [self::class, 'render'], 5);
    }

    /**
     * @return void
     */
    public static function render()
    {
        if (! is_singular()) {
            return;
        }

        $postId  = get_queried_object_id();
        $content = (string) get_post_field('post_content', $postId);
        $credits = self::hotlinked((array) get_post_meta($postId, self::META, true), $content);
        foreach (self::attachmentIds($content) as $id) {
            $credit = get_post_meta($id, PhotoSideloader::META, true);
            if (is_array($credit) && ! empty($credit['author']) && ! empty($credit['source'])) {
                $credits[$credit['provider'] . ':' . $credit['id']] = $credit;
            }
        }

        echo self::html(array_values($credits)); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in html().
    }

    /**
     * Pure: the stored hotlink credits whose photo is still on the page
     * (matched by the image's path, so a changed query string still counts).
     *
     * @param array  $stored  "provider:id" => credit with 'url'.
     * @param string $content Post content.
     *
     * @return array "provider:id" => credit.
     */
    public static function hotlinked(array $stored, $content)
    {
        $out = [];
        foreach ($stored as $key => $credit) {
            $path = is_array($credit) && ! empty($credit['url']) ? (string) parse_url((string) $credit['url'], PHP_URL_PATH) : '';
            $host = is_array($credit) && ! empty($credit['url']) ? (string) parse_url((string) $credit['url'], PHP_URL_HOST) : '';
            if ($path !== '' && $path !== '/' && strpos((string) $content, $host . $path) !== false && ! empty($credit['author'])) {
                $out[(string) $key] = $credit;
            }
        }

        return $out;
    }

    /**
     * Pure: attachment ids an image block's markup refers to.
     *
     * @param string $content Post content.
     *
     * @return int[]
     */
    public static function attachmentIds($content)
    {
        preg_match_all('/wp-image-(\d+)|<!-- wp:[a-z0-9\/-]+ \{[^}]*?"id":(\d+)/', (string) $content, $m);
        $ids = array_filter(array_map('intval', array_merge($m[1], $m[2])));

        return array_values(array_unique($ids));
    }

    /**
     * Pure: the credit line.
     *
     * @param array[] $credits PhotoSideloader::creditMeta() values.
     *
     * @return string HTML ('' for none).
     */
    public static function html(array $credits)
    {
        if (! $credits) {
            return '';
        }

        $link = static function ($text, $url) {
            return $url !== '' ? '<a href="' . esc_url($url) . '" rel="noopener nofollow">' . esc_html($text) . '</a>' : esc_html($text);
        };

        $parts = [];
        foreach ($credits as $c) {
            $parts[] = sprintf(
                /* translators: 1: photographer, 2: photo library. */
                __('%1$s on %2$s', 'swishfolio-core'),
                $link((string) $c['author'], isset($c['authorUrl']) ? (string) $c['authorUrl'] : ''),
                $link((string) $c['source'], isset($c['sourceUrl']) ? (string) $c['sourceUrl'] : '')
            );
        }

        return '<p class="swish-photo-credits" style="margin:0;padding:8px 16px;font-size:12px;line-height:1.4;text-align:center;opacity:.7">'
            . esc_html__('Photos:', 'swishfolio-core') . ' ' . implode(', ', $parts) . '</p>';
    }
}
