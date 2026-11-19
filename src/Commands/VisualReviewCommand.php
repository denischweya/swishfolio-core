<?php
/**
 * WP-CLI: the visual review (polish) pass after a site goes live.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Commands;

use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\ModelRouter;
use SwishfolioCore\AI\ProviderException;
use SwishfolioCore\AI\ProviderRegistry;
use SwishfolioCore\AI\Review\ReviewSite;
use SwishfolioCore\AI\Review\ScreenshotRenderer;
use SwishfolioCore\AI\Review\VisualReview;
use SwishfolioCore\AI\Review\VisualReviewConfig;
use SwishfolioCore\AI\Review\VisualReviewQueue;
use SwishfolioCore\AI\Settings;
use SwishfolioCore\AI\UsageLogger;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\Journal\WpdbJournalRepository;
use SwishfolioCore\Tools\ToolContext;

/**
 * Why a WP-CLI command run by system cron, not WP-Cron: the pass renders with
 * headless Chrome (15 to 30 s) and waits on a vision model (30 to 90 s).
 * WP-Cron runs inside a PHP-FPM worker reached over HTTP, and the pool has
 * five; holding one for two minutes per new site would starve the network.
 * The CLI process has no request timeout and no worker to hold, and flock in
 * the cron line keeps one pass at a time. Each site runs in its own wp-cli
 * process (--url), so switch_to_blog() never mixes two sites' theme state.
 *
 *     * * * * * www-data flock -n /tmp/swish-vr.lock wp --path=/var/www/swishfolio.com swishfolio visual-review run-due
 *
 * ## EXAMPLES
 *
 *     wp swishfolio visual-review status
 *     wp swishfolio visual-review run-due --max=2
 *     wp --url=https://shop.example.com swishfolio visual-review run --keep=/tmp/vr
 */
class VisualReviewCommand
{
    /**
     * Run the passes that are due (one wp-cli process per site).
     *
     * ## OPTIONS
     *
     * [--max=<n>]
     * : Most sites this run. Default 3.
     *
     * @subcommand run-due
     *
     * @param array $args  Positional.
     * @param array $assoc Options.
     *
     * @return void
     */
    public function run_due($args, $assoc)
    {
        $max = isset($assoc['max']) ? max(1, (int) $assoc['max']) : 3;

        foreach (VisualReviewQueue::due(time(), $max) as $item) {
            $blog = (int) $item['blog'];
            $site = get_site($blog);
            if (! $site) {
                VisualReviewQueue::remove($blog);
                continue;
            }
            $url = get_home_url($blog, '/');
            \WP_CLI::log(sprintf('blog %d %s', $blog, $url));

            $out = \WP_CLI::runcommand('swishfolio visual-review run --queued --url=' . escapeshellarg($url), [
                'launch'     => true,
                'exit_error' => false,
                'return'     => 'all',
            ]);

            \WP_CLI::log(trim($out->stdout));
            if ((int) $out->return_code !== 0) {
                \WP_CLI::warning(sprintf('blog %d: %s', $blog, trim($out->stderr)));
                VisualReviewQueue::retry($blog);
            }
        }
    }

    /**
     * Run the pass on this site (--url) now.
     *
     * ## OPTIONS
     *
     * [--queued]
     * : Take this site's queue entry (and remove it when done).
     *
     * [--keep=<dir>]
     * : Keep the screenshots in this directory.
     *
     * [--owner=<id>]
     * : User the writes are attributed to (default: the queued owner or the site admin).
     *
     * @param array $args  Positional.
     * @param array $assoc Options.
     *
     * @return void
     */
    public function run($args, $assoc)
    {
        $blog = get_current_blog_id();
        $item = VisualReviewQueue::get($blog);

        if (! empty($assoc['queued']) && $item === null) {
            \WP_CLI::log('nothing queued for this site');
            return;
        }

        $item  = $item !== null ? $item : ['user' => 0, 'kit' => '', 'front' => '', 'nav' => [], 'pages' => []];
        $owner = isset($assoc['owner']) ? (int) $assoc['owner'] : (int) $item['user'];
        if ($owner <= 0 || ! is_user_member_of_blog($owner, $blog)) {
            $admins = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);
            $owner  = $admins ? (int) $admins[0] : 0;
        }
        if ($owner <= 0) {
            \WP_CLI::error('no owner to attribute the writes to');
        }
        wp_set_current_user($owner);

        // The pass's result is journaled either way; the queue entry is done.
        if (! empty($assoc['queued'])) {
            VisualReviewQueue::remove($blog);
        }

        $result = $this->pass($item, $owner, isset($assoc['keep']) ? (string) $assoc['keep'] : null);

        \WP_CLI::log(wp_json_encode([
            'status'   => $result['status'],
            'reason'   => $result['reason'],
            'ms'       => isset($result['ms']) ? $result['ms'] : 0,
            'renderMs' => isset($result['renderMs']) ? $result['renderMs'] : 0,
            'usage'    => $result['usage'],
            'issues'   => $result['issues'],
            'applied'  => $result['applied'],
            'rejected' => array_map(static function ($r) {
                return ['fix' => $r['fix'], 'reason' => $r['reason']];
            }, $result['rejected']),
            'shots'    => $result['shots'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Queue this site (--url) as if it had just gone live.
     *
     * ## OPTIONS
     *
     * [--now]
     * : Due immediately.
     *
     * @param array $args  Positional.
     * @param array $assoc Options.
     *
     * @return void
     */
    public function enqueue($args, $assoc)
    {
        $state = get_option('swish_onboarding');
        $run   = is_array($state) && isset($state['build']['runId']) ? (string) $state['build']['runId'] : '';
        $admin = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);

        if (! VisualReviewQueue::enqueueBuild($run, $admin ? (int) $admin[0] : 0)) {
            \WP_CLI::error('visual review is turned off (or no Chrome found)');
        }
        if (! empty($assoc['now'])) {
            $queue = VisualReviewQueue::all();
            $queue[get_current_blog_id()]['at'] = time();
            update_site_option(VisualReviewQueue::OPTION, $queue);
        }
        \WP_CLI::success('queued');
    }

    /**
     * Settings, what was found on this server, and the queue.
     *
     * @return void
     */
    public function status()
    {
        $config = new VisualReviewConfig();
        $paths  = $config->paths();
        \WP_CLI::log(wp_json_encode([
            'stored'    => $config->stored(),
            'enabled'   => $config->enabled(),
            'chrome'    => $config->chromePath(),
            'node'      => $paths['node'],
            'modules'   => $paths['modules'] . (is_dir($paths['modules'] . '/node_modules/puppeteer-core') ? ' (puppeteer-core found)' : ' (puppeteer-core MISSING)'),
            'script'    => $paths['script'],
            'queue'     => VisualReviewQueue::all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array       $item  Queue item.
     * @param int         $owner User.
     * @param string|null $keep  Screenshot dir.
     *
     * @return array VisualReview::run() result.
     */
    private function pass(array $item, $owner, $keep)
    {
        $config   = new VisualReviewConfig();
        $settings = new Settings(new \SwishfolioCore\AI\Credentials\WordPressCredentialStore(new \SwishfolioCore\Services\EncryptionService()));
        $router   = new ModelRouter($settings);
        $library  = PatternLibrary::shipped();
        $kit      = $item['kit'] !== '' ? $library->kit((string) $item['kit']) : null;

        $context = ToolContext::forUser($owner);
        $context->setConfirmed(true);
        $journal = new MutationJournal(new WpdbJournalRepository());

        $kitPages = array_filter(array_map('intval', (array) $item['pages']), static function ($id) {
            return $id > 0 && 'publish' === get_post_status($id);
        });
        $site = new ReviewSite($kit, self::reviewedPages($item, $kitPages), $kitPages, $journal, $context);

        $provider = null;
        if (! empty($settings->get()['enabled'])) {
            try {
                $provider = (new ProviderRegistry($settings))->configured();
            } catch (ProviderException $e) {
                $provider = null;
            }
        }

        $state = get_option('swish_onboarding');
        $brief = [];
        if (is_array($state)) {
            $brief[] = trim((string) (isset($state['business']['name']) ? $state['business']['name'] : get_bloginfo('name')));
            $brief[] = trim((string) (isset($state['business']['brief']) ? mb_substr((string) $state['business']['brief'], 0, 1200) : ''));
            if (! empty($state['designWishes'])) {
                $brief[] = 'Design wishes: ' . mb_substr((string) $state['designWishes'], 0, 400);
            }
        } else {
            $brief[] = (string) get_bloginfo('name') . '. ' . (string) get_bloginfo('description');
        }

        $timeout = static function () {
            return 300;
        };
        add_filter('swishfolio_ai_http_timeout', $timeout);

        $review = (new VisualReview($config, new ScreenshotRenderer($config), $site, $provider, $router->modelFor(ModelRouter::KIND_VISUAL_REVIEW), $router->modelFor(ModelRouter::KIND_VISUAL_REVIEW_COPY), new UsageLogger()))
            ->withBrief(implode("\n", array_filter($brief)));
        if ($keep !== null) {
            $review->keepShotsIn($keep);
        }

        try {
            return $review->run();
        } finally {
            remove_filter('swishfolio_ai_http_timeout', $timeout);
        }
    }

    /**
     * Home plus one other key page: the first page in the build's menu that is
     * live, else any other live page of the build.
     *
     * @param array $item     Queue item.
     * @param array $kitPages Kit page key => post ID (live).
     *
     * @return array[]
     */
    private static function reviewedPages(array $item, array $kitPages)
    {
        $front = (int) get_option('page_on_front');
        if ($front <= 0 && $item['front'] !== '' && isset($kitPages[$item['front']])) {
            $front = $kitPages[$item['front']];
        }
        if ($front <= 0 || 'publish' !== get_post_status($front)) {
            return [];
        }

        $pages = [['key' => 'home', 'kitKey' => (string) array_search($front, $kitPages, true), 'postId' => $front, 'title' => get_the_title($front), 'url' => home_url('/'), 'front' => true]];

        $order = array_merge((array) $item['nav'], array_keys($kitPages));
        foreach ($order as $key) {
            if (isset($kitPages[$key]) && $kitPages[$key] !== $front) {
                $id      = $kitPages[$key];
                $pages[] = ['key' => 'page', 'kitKey' => (string) $key, 'postId' => $id, 'title' => get_the_title($id), 'url' => (string) get_permalink($id), 'front' => false];
                break;
            }
        }

        return $pages;
    }
}
