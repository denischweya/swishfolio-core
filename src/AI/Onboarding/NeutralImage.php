<?php
/**
 * A quiet stand-in for a kit photo that does not fit the business.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\Design\Color;

/**
 * When the owner has no photo for a spot and none of the kit's photos suits
 * their business (a coffee shop on a restaurant kit would otherwise show
 * octopus and steak), the spot gets an abstract duotone drawn from the site's
 * own palette instead: a soft field with one large shape in the accent. No
 * image generation: a few rectangles and circles drawn with GD, saved once
 * per palette as a normal media library image the owner can replace.
 */
class NeutralImage
{
    const OPTION = 'swish_neutral_image';
    const WIDTH  = 1200;
    const HEIGHT = 1500;

    /**
     * The site palette's background, text and accent (theme.json slugs).
     *
     * @return array {base, ink, accent}
     */
    public static function palette()
    {
        $theme = [];
        if (function_exists('wp_get_global_settings')) {
            foreach ((array) wp_get_global_settings(['color', 'palette', 'theme']) as $entry) {
                if (isset($entry['slug'], $entry['color']) && Color::hex($entry['color']) !== '') {
                    $theme[(string) $entry['slug']] = Color::hex($entry['color']);
                }
            }
        }

        return [
            'base'   => isset($theme['base']) ? $theme['base'] : '#f3f1ec',
            'ink'    => isset($theme['contrast']) ? $theme['contrast'] : '#1a1a1a',
            'accent' => isset($theme['accent-1']) ? $theme['accent-1'] : '#c0502a',
        ];
    }

    /**
     * Pure: the shapes of the stand-in, as colours and geometry.
     *
     * @param array $p {base, ink, accent}.
     *
     * @return array {field, band, disc, ring}
     */
    public static function plan(array $p)
    {
        return [
            // A step from the page colour towards the text: reads as a picture area.
            'field' => Color::mix($p['base'], $p['ink'], 0.14),
            'band'  => Color::mix($p['base'], $p['ink'], 0.24),
            'disc'  => Color::mix($p['accent'], $p['base'], 0.15),
            'ring'  => Color::mix($p['base'], $p['ink'], 0.4),
        ];
    }

    /**
     * The attachment for the current palette, made on first use.
     *
     * @return array|null {id, url}
     */
    public static function attachment()
    {
        $palette = self::palette();
        $key     = md5(wp_json_encode($palette));
        $stored  = get_option(self::OPTION);

        if (is_array($stored) && $stored['key'] === $key && get_post_type((int) $stored['id']) === 'attachment') {
            $url = wp_get_attachment_image_url((int) $stored['id'], 'large');
            if ($url) {
                return ['id' => (int) $stored['id'], 'url' => $url];
            }
        }

        if (! function_exists('imagecreatetruecolor') || ! current_user_can('upload_files')) {
            return null;
        }

        $bytes = self::png(self::plan($palette));
        if ($bytes === '') {
            return null;
        }

        $upload = wp_upload_bits('swish-placeholder-' . substr($key, 0, 8) . '.png', null, $bytes);
        if (! empty($upload['error'])) {
            return null;
        }

        $id = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title'     => __('Placeholder image', 'swishfolio-core'),
            'post_status'    => 'inherit',
        ], $upload['file']);
        if (! $id || is_wp_error($id)) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, '_wp_attachment_image_alt', '');
        update_option(self::OPTION, ['key' => $key, 'id' => (int) $id], false);

        $url = wp_get_attachment_image_url($id, 'large');

        return $url ? ['id' => (int) $id, 'url' => $url] : null;
    }

    /**
     * @param array $shapes plan().
     *
     * @return string PNG bytes.
     */
    private static function png(array $shapes)
    {
        $w   = self::WIDTH;
        $h   = self::HEIGHT;
        $img = imagecreatetruecolor($w, $h);
        $col = static function ($hex) use ($img) {
            $hex = ltrim($hex, '#');

            return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        };

        imagefilledrectangle($img, 0, 0, $w, $h, $col($shapes['field']));
        imagefilledrectangle($img, 0, (int) ($h * 0.68), $w, $h, $col($shapes['band']));
        imagefilledellipse($img, (int) ($w * 0.62), (int) ($h * 0.42), (int) ($w * 0.78), (int) ($w * 0.78), $col($shapes['disc']));
        imagesetthickness($img, 6);
        imageellipse($img, (int) ($w * 0.3), (int) ($h * 0.7), (int) ($w * 0.46), (int) ($w * 0.46), $col($shapes['ring']));

        ob_start();
        imagepng($img, null, 9);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }
}
