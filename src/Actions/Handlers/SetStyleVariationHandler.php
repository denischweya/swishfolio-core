<?php
/**
 * Switches the site to one of the theme's shipped style variations.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Reskinning through a variation the theme already ships is the whole of what
 * generation is allowed to do to global styles. Writing an arbitrary theme.json
 * patch is deliberately absent: this install already carries customised
 * templates and a customised footer part, and an overwrite there has no undo.
 *
 * WP_Theme_JSON_Resolver::get_style_variations() returns no slug for any
 * variation, only a title, so a title is the only identifier available. Titles
 * are translatable, so a sanitize_title comparison is accepted too — that makes
 * "swiss-international" work when "Swiss International" has been localised.
 *
 * The two core lookups are injectable so this can be tested without a theme.
 */
class SetStyleVariationHandler implements ServerActionHandlerInterface
{
    const TYPE = 'set_style_variation';

    /** @var callable|null Returns the list of variation configs. */
    private $variationsResolver;

    /** @var callable|null Returns the user global styles post ID. */
    private $globalStylesIdResolver;

    /**
     * @param callable|null $variationsResolver     Variation list resolver.
     * @param callable|null $globalStylesIdResolver Global styles post ID resolver.
     */
    public function __construct($variationsResolver = null, $globalStylesIdResolver = null)
    {
        $this->variationsResolver     = $variationsResolver;
        $this->globalStylesIdResolver = $globalStylesIdResolver;
    }

    public function getType()
    {
        return self::TYPE;
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot change the site design.', 'swishfolio-core')
            );
        }

        $postId = $this->globalStylesPostId();

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot edit this site\'s global styles.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $action, ExecutionContext $context)
    {
        $title     = isset($action['title']) ? (string) $action['title'] : '';
        $variation = $this->findVariation($title);

        if ($variation === null) {
            throw new ExecutionException(
                self::TYPE,
                'unknown_variation',
                sprintf(
                    /* translators: %s: style variation title. */
                    __('"%s" is not a style variation this theme ships.', 'swishfolio-core'),
                    $title
                )
            );
        }

        $config = [
            'version'                     => isset($variation['version']) ? (int) $variation['version'] : 3,
            'isGlobalStylesUserThemeJSON' => true,
        ];

        foreach (['settings', 'styles'] as $section) {
            if (isset($variation[$section]) && is_array($variation[$section])) {
                $config[$section] = $variation[$section];
            }
        }

        $postId = $this->globalStylesPostId();

        // Slashed: wp_json_encode escapes forward slashes as \/, and an
        // unslashed wp_update_post would strip those backslashes and leave
        // invalid JSON in the global styles post.
        $result = wp_update_post(
            wp_slash([
                'ID'           => $postId,
                'post_content' => wp_json_encode($config),
            ]),
            true
        );

        if (is_wp_error($result) || (int) $result <= 0) {
            throw new ExecutionException(
                self::TYPE,
                'update_failed',
                is_wp_error($result)
                    ? $result->get_error_message()
                    : __('WordPress refused to save the global styles.', 'swishfolio-core')
            );
        }

        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        }

        return [
            'postId'  => $postId,
            'title'   => isset($variation['title']) ? (string) $variation['title'] : $title,
            'applied' => true,
        ];
    }

    /**
     * @param string $title Requested variation title.
     *
     * @return array|null
     */
    private function findVariation($title)
    {
        if ($title === '') {
            return null;
        }

        foreach ($this->variations() as $variation) {
            if (! is_array($variation) || ! isset($variation['title'])) {
                continue;
            }

            $known = (string) $variation['title'];

            if ($known === $title || sanitize_title($known) === sanitize_title($title)) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    private function variations()
    {
        if ($this->variationsResolver !== null) {
            return (array) call_user_func($this->variationsResolver);
        }

        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return [];
        }

        return (array) \WP_Theme_JSON_Resolver::get_style_variations();
    }

    /**
     * @return int
     */
    private function globalStylesPostId()
    {
        if ($this->globalStylesIdResolver !== null) {
            return (int) call_user_func($this->globalStylesIdResolver);
        }

        if (! class_exists('\WP_Theme_JSON_Resolver')) {
            return 0;
        }

        return (int) \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
    }
}
