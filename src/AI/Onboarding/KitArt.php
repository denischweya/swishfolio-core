<?php
/**
 * Keeps a kit's decorative art in the site's palette.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Onboarding;

use SwishfolioCore\AI\Design\ArtRecolour;
use SwishfolioCore\AI\Design\Color;
use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;

/**
 * Kit assets are either photos (people, places, work) or decorative art
 * (abstract shapes, gradients, 3D renders, type specimens), as kit.json
 * "assets.<name>.kind" says. Photos are never touched. When the site's
 * palette is no longer the kit's own (a custom design, or the owner's colour
 * wishes swapped the template's colours), each decorative picture the site
 * shows is replaced by a copy recoloured into the site palette
 * (ArtRecolour), written once per palette under uploads/swish-art/<kit>/.
 *
 * rewrite() swaps the URLs in block markup (the kit's shipped file, or an
 * earlier recoloured copy, becomes the copy for the current palette, or the
 * shipped file again when the palette is back to the kit's). covers() does
 * the same for sample projects whose cover is a decorative kit image.
 */
class KitArt
{
    const DIR    = 'swish-art';
    const TOOL   = 'swishfolio/kit-art';
    const OPTION = 'swish_custom_design';

    /**
     * @param Kit $kit Kit.
     *
     * @return string[] Names of the kit's decorative assets.
     */
    public static function decorative(Kit $kit)
    {
        $out = [];
        foreach ($kit->getAssets() as $name => $asset) {
            if (is_array($asset) && isset($asset['kind']) && 'decorative' === $asset['kind']) {
                $out[] = (string) $name;
            }
        }

        return $out;
    }

    /**
     * @param Kit $kit Kit.
     *
     * @return array slug => hex, the kit's palette as shipped.
     */
    public static function kitPalette(Kit $kit)
    {
        $design = $kit->getDesignFile();

        return self::palette(isset($design['globalStyles']['settings']['color']['palette']['theme']) ? (array) $design['globalStyles']['settings']['color']['palette']['theme'] : []);
    }

    /**
     * @param array $entries theme.json palette entries.
     *
     * @return array slug => hex.
     */
    public static function palette(array $entries)
    {
        $out = [];
        foreach ($entries as $entry) {
            if (isset($entry['slug'], $entry['color']) && Color::hex($entry['color']) !== '') {
                $out[(string) $entry['slug']] = Color::hex($entry['color']);
            }
        }

        return $out;
    }

    /**
     * The recolouring for this site's palette now, or null when the art may
     * stay as shipped (the palette is the kit's own, or the kit has none).
     *
     * @param Kit        $kit         Kit.
     * @param array|null $sitePalette slug => hex, or null to read the site's.
     * @param string     $other       Second accent of a custom design ('' to read it).
     *
     * @return ArtRecolour|null
     */
    public static function recolourer(Kit $kit, array $sitePalette = null, $other = '')
    {
        if (! self::decorative($kit)) {
            return null;
        }
        if ($sitePalette === null) {
            if (function_exists('wp_clean_theme_json_cache')) {
                wp_clean_theme_json_cache();
            }
            $sitePalette = function_exists('wp_get_global_settings') ? self::palette((array) wp_get_global_settings(['color', 'palette', 'theme'])) : [];
        }
        if ($other === '' && function_exists('get_option')) {
            $custom = get_option(self::OPTION);
            if (is_array($custom) && isset($custom['kit'], $custom['tokens']['palette']['accent2']) && $custom['kit'] === $kit->getSlug()) {
                $other = (string) $custom['tokens']['palette']['accent2'];
            }
        }

        $kitPalette = self::kitPalette($kit);
        if (! $kitPalette || ! $sitePalette || array_intersect_key($sitePalette, $kitPalette) == array_intersect_key($kitPalette, $sitePalette)) {
            return null;
        }

        $recolour = ArtRecolour::fromPalettes($kitPalette, $sitePalette, $other);

        return $recolour->isIdentity() ? null : $recolour;
    }

    /**
     * Pure apart from writing the recoloured files: markup with every
     * decorative kit picture in the given palette.
     *
     * @param string           $markup   Block markup.
     * @param Kit              $kit      Kit.
     * @param ArtRecolour|null $recolour Null: back to the shipped files.
     *
     * @return string
     */
    public static function rewrite($markup, Kit $kit, ArtRecolour $recolour = null)
    {
        $markup = (string) $markup;
        $base   = self::uploads();

        foreach (self::decorative($kit) as $name) {
            $original = $kit->assetUrl($name);
            if ($original === null) {
                continue;
            }
            $ext    = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $prefix = $base !== null ? $base['url'] . '/' . self::DIR . '/' . $kit->getSlug() . '/' . $name . '-' : null;

            // Plain, and with JSON-escaped slashes (block comment attributes).
            foreach ([false, true] as $escaped) {
                $esc  = static function ($s) use ($escaped) {
                    return preg_quote($escaped ? str_replace('/', '\\/', $s) : $s, '#');
                };
                $find = '#' . $esc($original) . ($prefix !== null ? '|' . $esc($prefix) . '[0-9a-f]{10}\.' . preg_quote($ext, '#') : '') . '#';
                if (! preg_match($find, $markup)) {
                    continue;
                }
                $to = $recolour !== null ? self::variant($kit, $name, $recolour) : null;
                $to = $to !== null ? $to : $original;
                $markup = (string) preg_replace_callback($find, static function () use ($to, $escaped) {
                    return $escaped ? str_replace('/', '\\/', $to) : $to;
                }, $markup);
            }
        }

        return $markup;
    }

    /**
     * The URL of a decorative asset recoloured for a palette, made on first use.
     *
     * @param Kit         $kit      Kit.
     * @param string      $name     Asset name.
     * @param ArtRecolour $recolour Recolouring.
     *
     * @return string|null
     */
    public static function variant(Kit $kit, $name, ArtRecolour $recolour)
    {
        $file = self::variantFile($kit, $name, $recolour);

        return $file === null ? null : $file['url'];
    }

    /**
     * @return array|null {path, url}
     */
    private static function variantFile(Kit $kit, $name, ArtRecolour $recolour)
    {
        $src  = $kit->assetPath($name);
        $base = self::uploads();
        if ($src === null || ! is_readable($src) || $base === null) {
            return null;
        }
        $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $rel  = self::DIR . '/' . $kit->getSlug() . '/' . $name . '-' . $recolour->key() . '.' . $ext;
        $path = $base['dir'] . '/' . $rel;

        if (! is_file($path)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p(dirname($path));
            }
            if (! $recolour->recolourFile($src, $path)) {
                return null;
            }
        }

        return ['path' => $path, 'url' => $base['url'] . '/' . $rel];
    }

    /**
     * Sample projects (SampleProjects) whose cover is a decorative kit image:
     * the cover becomes the copy for this palette (its own media item).
     *
     * @param Kit              $kit      Kit.
     * @param ArtRecolour|null $recolour Null: back to the kit's own cover.
     * @param MutationJournal  $journal  Journal.
     * @param ToolContext      $context  Run.
     *
     * @return int Covers changed.
     */
    public static function covers(Kit $kit, $recolour, MutationJournal $journal, ToolContext $context)
    {
        $names = self::decorative($kit);
        if (! $names || ! function_exists('get_posts')) {
            return 0;
        }

        $changed = 0;
        foreach (get_posts([
            'post_type'      => SampleProjects::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => SampleProjects::META, // phpcs:ignore WordPress.DB.SlowDBQuery
        ]) as $projectId) {
            $thumb = (int) get_post_thumbnail_id($projectId);
            $asset = $thumb > 0 ? (string) get_post_meta($thumb, SampleProjects::ASSET, true) : '';
            if (! preg_match('#^' . preg_quote($kit->getSlug(), '#') . '/([a-z0-9-]+)(?:@[0-9a-f]{10})?$#', $asset, $m) || ! in_array($m[1], $names, true) || ! current_user_can('edit_post', $projectId)) {
                continue;
            }
            $want = $kit->getSlug() . '/' . $m[1] . ($recolour !== null ? '@' . $recolour->key() : '');
            if ($want === $asset) {
                continue;
            }
            $id = self::coverAttachment($kit, $m[1], $want, $recolour, $journal, $context);
            if ($id > 0 && $id !== $thumb) {
                $journal->updateMeta($context, self::TOOL, (int) $projectId, '_thumbnail_id', $id, __('Recoloured a sample project cover for your palette', 'swishfolio-core'));
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @return int Attachment ID, 0 when it could not be made.
     */
    private static function coverAttachment(Kit $kit, $name, $key, $recolour, MutationJournal $journal, ToolContext $context)
    {
        $found = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => SampleProjects::ASSET, // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
            'no_found_rows'  => true,
        ]);
        if ($found) {
            return (int) $found[0];
        }
        // Back to the shipped cover: SampleProjects imports it once; without it, nothing to do.
        if ($recolour === null) {
            return 0;
        }

        $file = self::variantFile($kit, $name, $recolour);
        if ($file === null) {
            return 0;
        }
        $type = wp_check_filetype($file['path']);
        $id   = wp_insert_attachment([
            'post_mime_type' => $type['type'] ? $type['type'] : 'image/jpeg',
            'post_title'     => __('Sample project image', 'swishfolio-core'),
            'post_status'    => 'inherit',
        ], $file['path']);
        if (is_wp_error($id) || ! $id) {
            return 0;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file['path']));
        update_post_meta($id, '_wp_attachment_image_alt', __('Sample project image', 'swishfolio-core'));
        update_post_meta($id, SampleProjects::ASSET, $key);
        update_post_meta($id, SampleProjects::META, '1');
        $journal->recordCreated($context, self::TOOL, (int) $id, __('Added a sample project image in your palette', 'swishfolio-core'));

        return (int) $id;
    }

    /**
     * @return array|null {dir, url} of this site's uploads.
     */
    private static function uploads()
    {
        if (! function_exists('wp_upload_dir')) {
            return null;
        }
        $u = wp_upload_dir(null, false);

        return empty($u['basedir']) ? null : ['dir' => untrailingslashit($u['basedir']), 'url' => untrailingslashit($u['baseurl'])];
    }
}
