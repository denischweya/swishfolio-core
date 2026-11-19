<?php
/**
 * The catalogue of kits page generation is allowed to compose with.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * Discovers the kits under src/AI/Generation/Kits/<slug>/kit.json, one per
 * template site, and decides which one a generation run uses.
 *
 * This is also the entry point for the template matcher and the kit design
 * applier: PatternLibrary::shipped()->kits() lists every kit and ->kit($slug)
 * returns one, with its design reference (Kit::getDesign(), getDesignFile()).
 */
class PatternLibrary
{
    /** Option the kit design applier records the applied template in ({slug, ...}). */
    const APPLIED_OPTION = 'swish_kit';

    /** Used when nothing else names a kit. */
    const DEFAULT_KIT = 'designer';

    /** @var string[] */
    private $dirs;

    /** @var Kit[]|null */
    private $kits = null;

    /**
     * @param string|string[] $dirs Directories holding one sub-directory per kit.
     */
    public function __construct($dirs)
    {
        $this->dirs = array_values((array) $dirs);
    }

    /**
     * @return self The kits shipped with the plugin.
     */
    public static function shipped()
    {
        return new self(SFCORE_PLUGIN_DIR . 'src/AI/Generation/Kits');
    }

    /**
     * @return Kit[] Keyed by slug.
     */
    public function kits()
    {
        if ($this->kits !== null) {
            return $this->kits;
        }

        $this->kits = [];

        foreach ($this->dirs as $dir) {
            foreach ((array) glob(rtrim($dir, '/') . '/*/kit.json') as $file) {
                $kit = Kit::fromDir(dirname($file));

                if ($kit !== null && ! isset($this->kits[$kit->getSlug()])) {
                    $this->kits[$kit->getSlug()] = $kit;
                }
            }
        }

        ksort($this->kits);

        return $this->kits;
    }

    /**
     * @param string $slug Kit slug (the template's slug).
     *
     * @return Kit|null
     */
    public function kit($slug)
    {
        $kits = $this->kits();
        $slug = (string) $slug;

        return isset($kits[$slug]) ? $kits[$slug] : null;
    }

    /**
     * @param string $slug Kit slug.
     *
     * @return bool
     */
    public function has($slug)
    {
        return $this->kit($slug) !== null;
    }

    /**
     * @return array[] Kit::describeForMatching() for every kit, keyed by slug.
     */
    public function describeKits()
    {
        return array_map(static function (Kit $kit) {
            return $kit->describeForMatching();
        }, $this->kits());
    }

    /**
     * The kit a generation run composes with. This is the hook:
     *
     *  1. the kit the request names (the onboarding passes the matcher's pick);
     *  2. otherwise the kit whose design this site already has: the design
     *     applier's record, else the template CSS or style variation found in
     *     the site's global styles (a template site, or a copy of one);
     *  3. the `swishfolio_ai_generation_kit` filter, which sees that candidate
     *     and may replace it (a template matcher plugs in here);
     *  4. otherwise DEFAULT_KIT.
     *
     * @param string $requested Kit slug from the request, or ''.
     * @param array  $brief     The generation brief, for the filter.
     * @param string $scope     "site" or "page".
     *
     * @return Kit|null Null only when no kit ships at all.
     */
    public function resolve($requested = '', array $brief = [], $scope = 'site')
    {
        $slug = $this->has($requested) ? (string) $requested : $this->current();

        if (function_exists('apply_filters')) {
            /**
             * Filters the kit (template slug) generation composes with.
             *
             * @param string|null $slug  Requested or current kit, or null when the site has none.
             * @param array       $brief Instruction and site facts.
             * @param string      $scope "site" or "page".
             */
            $filtered = apply_filters('swishfolio_ai_generation_kit', $slug, $brief, $scope);
            $slug     = $this->has($filtered) ? (string) $filtered : $slug;
        }

        if ($slug === null || ! $this->has($slug)) {
            $slug = $this->has(self::DEFAULT_KIT) ? self::DEFAULT_KIT : key($this->kits());
        }

        return $slug === null ? null : $this->kit($slug);
    }

    /**
     * The kit this site's design comes from, if it can be told.
     *
     * @return string|null
     */
    public function current()
    {
        $applied = function_exists('get_option') ? get_option(self::APPLIED_OPTION) : null;

        if (is_array($applied) && isset($applied['slug']) && $this->has($applied['slug'])) {
            return (string) $applied['slug'];
        }

        return $this->detect($this->siteCss(), $this->siteVariation());
    }

    /**
     * Recognise a kit from a site's custom CSS (each template's CSS has its own
     * class prefix) or, for templates styled by a theme variation alone, from
     * the variation's title.
     *
     * @param string $css       The site's global styles custom CSS.
     * @param string $variation Active style variation title, or ''.
     *
     * @return string|null
     */
    public function detect($css, $variation = '')
    {
        $byVariation = [];

        foreach ($this->kits() as $slug => $kit) {
            $design      = $kit->getDesign();
            $fingerprint = isset($design['fingerprint']) ? (string) $design['fingerprint'] : '';

            if ($fingerprint !== '' && strpos((string) $css, $fingerprint) !== false) {
                return $slug;
            }

            if (empty($design['fingerprint']) && ! empty($design['variation']) && $design['variation'] !== 'Default') {
                $byVariation[(string) $design['variation']] = $slug;
            }
        }

        return $variation !== '' && isset($byVariation[$variation]) ? $byVariation[$variation] : null;
    }

    /**
     * @return string
     */
    private function siteCss()
    {
        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return '';
        }

        $data = \WP_Theme_JSON_Resolver::get_user_data();
        $raw  = is_object($data) && method_exists($data, 'get_raw_data') ? $data->get_raw_data() : [];

        return isset($raw['styles']['css']) ? (string) $raw['styles']['css'] : '';
    }

    /**
     * @return string
     */
    private function siteVariation()
    {
        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return '';
        }

        $data = \WP_Theme_JSON_Resolver::get_user_data();
        $raw  = is_object($data) && method_exists($data, 'get_raw_data') ? $data->get_raw_data() : [];
        $css  = isset($raw['styles']['css']) ? (string) $raw['styles']['css'] : '';

        // A variation's own css carries --style-mode:<slug>.
        if (! preg_match('/--style-mode:\s*([a-z0-9-]+)/', $css, $m)) {
            return '';
        }

        foreach ((array) \WP_Theme_JSON_Resolver::get_style_variations() as $variation) {
            if (isset($variation['title']) && sanitize_title($variation['title']) === $m[1]) {
                return (string) $variation['title'];
            }
        }

        return '';
    }
}
