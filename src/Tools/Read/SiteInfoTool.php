<?php
/**
 * Reports what kind of site this is.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Tools\ToolContext;

/**
 * The orientation call. Without it the model has to infer the site's shape from
 * whatever the user happened to mention, which is how generated pages end up
 * referring to post types the site does not have and style variations the theme
 * does not ship.
 */
class SiteInfoTool extends AbstractReadTool
{
    const PROJECT_POST_TYPE = 'sfcore_project';

    /** @var callable|null Returns the list of variation configs. */
    private $variationsResolver;

    /**
     * @param callable|null $variationsResolver Variation list resolver, for tests.
     */
    public function __construct($variationsResolver = null)
    {
        $this->variationsResolver = $variationsResolver;
    }

    public function getName()
    {
        return 'swishfolio/site-info';
    }

    public function getTitle()
    {
        return __('Site info', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Read the site\'s name, tagline, locale, active theme, the style variations the theme ships, '
            . 'which post types exist, how the front page is configured, and how many pages, posts and projects '
            . 'there are. Call this first when you do not already know what the site contains.';
    }

    public function execute(array $input, ToolContext $context)
    {
        return [
            'siteId'          => $context->getSiteId(),
            'name'            => get_bloginfo('name'),
            'tagline'         => get_bloginfo('description'),
            'url'             => home_url('/'),
            'locale'          => get_locale(),
            'theme'           => $this->theme(),
            'styleVariations' => $this->variationTitles(),
            'postTypes'       => $this->postTypes(),
            'frontPage'       => $this->frontPage(),
            'counts'          => $this->counts(),
        ];
    }

    /**
     * @return array
     */
    private function theme()
    {
        if (! function_exists('wp_get_theme')) {
            return ['name' => '', 'stylesheet' => ''];
        }

        $theme = wp_get_theme();

        return [
            'name'       => (string) $theme->get('Name'),
            'stylesheet' => (string) $theme->get_stylesheet(),
        ];
    }

    /**
     * @return string[]
     */
    private function variationTitles()
    {
        $variations = [];

        if ($this->variationsResolver !== null) {
            $variations = (array) call_user_func($this->variationsResolver);
        } elseif (class_exists('\WP_Theme_JSON_Resolver')) {
            $variations = (array) \WP_Theme_JSON_Resolver::get_style_variations();
        }

        $titles = [];

        foreach ($variations as $variation) {
            if (is_array($variation) && isset($variation['title'])) {
                $titles[] = (string) $variation['title'];
            }
        }

        return $titles;
    }

    /**
     * Only the types a tool can actually address. Listing every registered type
     * would invite calls against revisions and menu items.
     *
     * @return array[]
     */
    private function postTypes()
    {
        $out = [];

        foreach (['page', 'post', self::PROJECT_POST_TYPE] as $slug) {
            $object = get_post_type_object($slug);

            if (null === $object) {
                continue;
            }

            $out[] = [
                'slug'  => $slug,
                'label' => isset($object->labels->name) ? (string) $object->labels->name : $slug,
            ];
        }

        return $out;
    }

    /**
     * @return array
     */
    private function frontPage()
    {
        $frontId = (int) get_option('page_on_front');
        $blogId  = (int) get_option('page_for_posts');

        return [
            'showOnFront' => (string) get_option('show_on_front'),
            'frontPageId' => $frontId > 0 ? $frontId : null,
            'frontPage'   => $frontId > 0 ? (string) get_the_title($frontId) : null,
            'blogPageId'  => $blogId > 0 ? $blogId : null,
        ];
    }

    /**
     * @return array
     */
    private function counts()
    {
        $out = [];

        foreach (['page', 'post', self::PROJECT_POST_TYPE] as $slug) {
            if (null === get_post_type_object($slug)) {
                continue;
            }

            $counts = function_exists('wp_count_posts') ? wp_count_posts($slug) : null;

            $out[$slug] = [
                'publish' => is_object($counts) && isset($counts->publish) ? (int) $counts->publish : 0,
                'draft'   => is_object($counts) && isset($counts->draft) ? (int) $counts->draft : 0,
            ];
        }

        return $out;
    }
}
