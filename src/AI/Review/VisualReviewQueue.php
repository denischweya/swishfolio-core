<?php
/**
 * Sites waiting for their visual review.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

use SwishfolioCore\REST\GenerationController;

/**
 * One network option, blog ID => item. A site goes in when it goes live
 * (swishfolio-extra's go-live calls enqueueBuild(), a single option write),
 * and comes out when `wp swishfolio visual-review run-due`, started by system
 * cron, has run its pass. Nothing here renders or calls a provider.
 *
 * Item: {blog, user, runId, kit, front, pages: {kitKey => postId}, at (due), tries}
 */
class VisualReviewQueue
{
    const OPTION    = 'swishfolio_visual_review_queue';
    const DELAY     = 120;
    const RETRY     = 600;
    const MAX_TRIES = 2;
    const MAX_ITEMS = 500;

    /**
     * Queue the current site's finished build.
     *
     * @param string $runId  Generation run.
     * @param int    $userId Owner the build ran as.
     *
     * @return bool Whether it was queued.
     */
    public static function enqueueBuild($runId, $userId)
    {
        if (! (new VisualReviewConfig())->enabled()) {
            return false;
        }

        $meta  = get_transient(GenerationController::RUN_META_PREFIX . preg_replace('/[^a-f0-9]/', '', (string) $runId));
        $kit   = is_array($meta) && ! empty($meta['kit']) ? (string) $meta['kit'] : '';
        $pages = [];
        if (is_array($meta)) {
            foreach ((array) $meta['pages'] as $page) {
                $pages[(string) $page['key']] = (int) $page['postId'];
            }
        }
        if ($kit === '') {
            $applied = get_option('swish_kit');
            $kit     = is_array($applied) && ! empty($applied['slug']) ? (string) $applied['slug'] : '';
        }

        $item = [
            'blog'  => (int) get_current_blog_id(),
            'user'  => (int) $userId,
            'runId' => (string) $runId,
            'kit'   => $kit,
            'front' => is_array($meta) && isset($meta['front']) ? (string) $meta['front'] : '',
            'nav'   => is_array($meta) && isset($meta['navigation']) ? array_values(array_map('strval', (array) $meta['navigation'])) : [],
            'pages' => $pages,
            'at'    => time() + self::DELAY,
            'tries' => 0,
        ];

        $queue               = self::all();
        $queue[$item['blog']] = $item;
        update_site_option(self::OPTION, array_slice($queue, -self::MAX_ITEMS, null, true));

        return true;
    }

    /**
     * @return array blog => item.
     */
    public static function all()
    {
        $queue = get_site_option(self::OPTION, []);

        return is_array($queue) ? $queue : [];
    }

    /**
     * @param int $blog Blog.
     *
     * @return array|null
     */
    public static function get($blog)
    {
        $queue = self::all();

        return isset($queue[(int) $blog]) ? $queue[(int) $blog] : null;
    }

    /**
     * @param int $now Time.
     * @param int $max Most items.
     *
     * @return array[] Due items, oldest first.
     */
    public static function due($now, $max)
    {
        $due = array_filter(self::all(), static function ($item) use ($now) {
            return is_array($item) && (int) $item['at'] <= $now;
        });
        uasort($due, static function ($a, $b) {
            return (int) $a['at'] - (int) $b['at'];
        });

        return array_slice(array_values($due), 0, max(1, (int) $max));
    }

    /**
     * @param int $blog Blog.
     *
     * @return void
     */
    public static function remove($blog)
    {
        $queue = self::all();
        unset($queue[(int) $blog]);
        update_site_option(self::OPTION, $queue);
    }

    /**
     * The run itself failed (not the pass's own skip): try once more later.
     *
     * @param int $blog Blog.
     *
     * @return void
     */
    public static function retry($blog)
    {
        $queue = self::all();
        if (! isset($queue[(int) $blog])) {
            return;
        }
        $queue[(int) $blog]['tries']++;
        $queue[(int) $blog]['at'] = time() + self::RETRY;
        if ($queue[(int) $blog]['tries'] >= self::MAX_TRIES) {
            unset($queue[(int) $blog]);
        }
        update_site_option(self::OPTION, $queue);
    }
}
