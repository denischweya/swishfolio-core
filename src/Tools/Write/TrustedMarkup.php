<?php
/**
 * Lets an exact, already-vetted body through kses on save.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

/**
 * Template pages are network-authored block markup full of inline SVG icons,
 * which wp_kses_post() strips. A site owner on multisite has no
 * unfiltered_html, so every save they cause runs kses: rewriting a template's
 * copy, and later undoing that rewrite from the audit log, would both leave
 * the page without its icons.
 *
 * RewriteCopyTool changes only sanitised text slots and kses-filtered new
 * sections, so its output is safe by construction, and the body it replaces
 * was already on the post. Each such body is recorded here by hash against the
 * post; a save whose unsanitised content hashes to one of them keeps that
 * content. This permits no new markup: only bodies that have already been on
 * this post, or that the tool built. Revisions are matched against their
 * parent, which is how the undo (wp_restore_post_revision) stays exact.
 */
class TrustedMarkup
{
    const META = '_swish_trusted_markup';
    const KEEP = 12;

    /** @var array<string,bool> Hashes of bodies server code vouched for in this request. */
    private static $vouched = [];

    /**
     * @return void
     */
    public static function register()
    {
        add_filter('wp_insert_post_data', [self::class, 'filter'], 99, 4);
    }

    /**
     * @param int    $postId  Post.
     * @param string $content Body to trust (unslashed).
     *
     * @return void
     */
    public static function allow($postId, $content)
    {
        $hashes   = (array) get_post_meta((int) $postId, self::META, true);
        $hashes[] = md5((string) $content);
        update_post_meta((int) $postId, self::META, array_slice(array_values(array_unique(array_filter($hashes))), -self::KEEP));
    }

    /**
     * Vouch for one exact body for the rest of this request, on any post,
     * including one not created yet. For bodies server code composed itself
     * from vetted markup and sanitised copy (generation from a kit). Nothing
     * from a request can add to this set; it is never stored.
     *
     * @param string $content Body to trust (unslashed).
     *
     * @return void
     */
    public static function vouch($content)
    {
        self::$vouched[md5((string) $content)] = true;
    }

    /**
     * @param string $content Body (unslashed).
     *
     * @return bool Whether vouch() was called for exactly this body in this request.
     */
    public static function isVouched($content)
    {
        return isset(self::$vouched[md5((string) $content)]);
    }

    /**
     * @param array $data        Sanitised, slashed post data.
     * @param array $postarr     Sanitised post array.
     * @param array $unsanitized Slashed, unsanitised post array.
     * @param bool  $update      Whether this is an update.
     *
     * @return array
     */
    public static function filter($data, $postarr, $unsanitized = [], $update = false)
    {
        if (! isset($unsanitized['post_content']) || ! is_string($unsanitized['post_content'])) {
            return $data;
        }

        if (self::isVouched(wp_unslash($unsanitized['post_content']))) {
            $data['post_content'] = $unsanitized['post_content'];

            return $data;
        }

        $owner = 0;

        if (isset($data['post_type']) && 'revision' === $data['post_type']) {
            $owner = isset($data['post_parent']) ? (int) $data['post_parent'] : 0;
        } elseif (! empty($postarr['ID'])) {
            $owner = (int) $postarr['ID'];
        }

        if ($owner <= 0) {
            return $data;
        }

        $hashes = (array) get_post_meta($owner, self::META, true);

        if ($hashes && in_array(md5(wp_unslash($unsanitized['post_content'])), $hashes, true)) {
            $data['post_content'] = $unsanitized['post_content'];
        }

        return $data;
    }
}
