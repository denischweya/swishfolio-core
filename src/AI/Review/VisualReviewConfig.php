<?php
/**
 * Network settings for the visual review pass.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

/**
 * One network option (written by swishfolio-extra's "Visual review" tab in
 * Network Admin > Settings > Swishfolio AI):
 *
 *   mode     auto | on | off. "auto" (the default) means on only when a Chrome
 *            binary is found, so a server without Chrome never tries.
 *   chrome   Path to Chrome / chrome-headless-shell / Chromium ('' = look in
 *            the usual places, see CHROME_CANDIDATES).
 *   hostMap  Resolve the sites' hostnames to this IP or host while rendering
 *            ('' = normal DNS). On production behind Cloudflare: 127.0.0.1.
 *
 * Node and the renderer's node_modules live in RENDER_DIR by default (see
 * seed/server/install-visual-review.sh); the swishfolio_visual_review_paths
 * filter or the SWISH_VR_NODE / SWISH_VR_MODULES constants override them.
 */
class VisualReviewConfig
{
    const OPTION     = 'swishfolio_visual_review';
    const RENDER_DIR = '/opt/swish-render';

    /** Tried in order when no Chrome path is set. */
    const CHROME_CANDIDATES = [
        '/opt/swish-render/chrome-headless-shell/current/chrome-headless-shell',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/google-chrome',
    ];

    /** @var array|null */
    private $stored;

    /** @var callable */
    private $isExecutable;

    /**
     * @param array|null    $stored       Stored option (null: read it).
     * @param callable|null $isExecutable Path => bool (tests).
     */
    public function __construct($stored = null, $isExecutable = null)
    {
        $this->stored       = $stored;
        $this->isExecutable = $isExecutable !== null ? $isExecutable : static function ($path) {
            return $path !== '' && @is_file($path) && @is_executable($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        };
    }

    /**
     * @return array{mode:string,chrome:string,hostMap:string}
     */
    public function stored()
    {
        $raw = $this->stored !== null ? $this->stored : (function_exists('get_site_option') ? get_site_option(self::OPTION, []) : []);
        $raw = is_array($raw) ? $raw : [];

        $mode = isset($raw['mode']) ? (string) $raw['mode'] : 'auto';

        return [
            'mode'    => in_array($mode, ['auto', 'on', 'off'], true) ? $mode : 'auto',
            'chrome'  => isset($raw['chrome']) ? trim((string) $raw['chrome']) : '',
            'hostMap' => isset($raw['hostMap']) ? trim((string) $raw['hostMap']) : '',
        ];
    }

    /**
     * Pure: a stored value from the settings form.
     *
     * @param array $in Posted fields (vr_mode, vr_chrome, vr_host_map).
     *
     * @return array
     */
    public static function sanitize(array $in)
    {
        $mode   = isset($in['vr_mode']) ? (string) $in['vr_mode'] : 'auto';
        $chrome = isset($in['vr_chrome']) ? trim((string) $in['vr_chrome']) : '';
        $host   = isset($in['vr_host_map']) ? trim((string) $in['vr_host_map']) : '';

        return [
            'mode'    => in_array($mode, ['auto', 'on', 'off'], true) ? $mode : 'auto',
            // An absolute path of ordinary characters, nothing a shell could read.
            'chrome'  => preg_match('#^/[A-Za-z0-9._/+-]{1,250}$#', $chrome) ? $chrome : '',
            'hostMap' => preg_match('/^[A-Za-z0-9.:-]{1,100}$/', $host) ? $host : '',
        ];
    }

    /**
     * @return string The Chrome binary to use, or '' when none is found.
     */
    public function chromePath()
    {
        $stored = $this->stored();
        $check  = $this->isExecutable;

        if ($stored['chrome'] !== '') {
            return call_user_func($check, $stored['chrome']) ? $stored['chrome'] : '';
        }

        foreach (self::CHROME_CANDIDATES as $path) {
            if (call_user_func($check, $path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @return bool Whether the pass should run at all.
     */
    public function enabled()
    {
        $mode = $this->stored()['mode'];

        if ($mode === 'off') {
            return false;
        }

        // "on" without Chrome still cannot render; the pass then skips (journaled).
        return $mode === 'on' || $this->chromePath() !== '';
    }

    /**
     * @return array{node:string,modules:string,script:string}
     */
    public function paths()
    {
        $node = defined('SWISH_VR_NODE') ? (string) SWISH_VR_NODE : '';
        if ($node === '') {
            $check = $this->isExecutable;
            foreach ([self::RENDER_DIR . '/node/bin/node', '/usr/bin/node', '/usr/local/bin/node', '/usr/local/n/bin/node'] as $candidate) {
                if (call_user_func($check, $candidate)) {
                    $node = $candidate;
                    break;
                }
            }
        }

        $paths = [
            'node'    => $node,
            'modules' => defined('SWISH_VR_MODULES') ? (string) SWISH_VR_MODULES : self::RENDER_DIR,
            'script'  => (defined('SFCORE_PLUGIN_DIR') ? SFCORE_PLUGIN_DIR : dirname(__DIR__, 3) . '/') . 'bin/visual-review/render.mjs',
        ];

        /**
         * Filters where the visual review renderer runs from.
         *
         * @param array $paths node (binary), modules (dir with node_modules/puppeteer-core), script.
         */
        return function_exists('apply_filters') ? (array) apply_filters('swishfolio_visual_review_paths', $paths) : $paths;
    }

    /**
     * @return string IP or host the sites resolve to while rendering ('' = DNS).
     */
    public function hostMap()
    {
        return $this->stored()['hostMap'];
    }
}
