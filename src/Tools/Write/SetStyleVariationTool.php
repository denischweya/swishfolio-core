<?php
/**
 * Switches the site to one of the theme's shipped style variations.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from SetStyleVariationHandler. Reskinning through a variation the
 * theme already ships is the whole of what the AI is allowed to do to global
 * styles. Writing an arbitrary theme.json patch is deliberately absent.
 *
 * What the migration adds is the undo. The global styles post supports
 * revisions in core (post.php:522), so the journal captures one before the
 * overwrite and a rollback restores the previous design exactly — which is what
 * made "an overwrite there has no undo" true before and false now.
 *
 * WP_Theme_JSON_Resolver::get_style_variations() returns no slug for any
 * variation, only a title, so a title is the only identifier available. Titles
 * are translatable, so a sanitize_title comparison is accepted too — that makes
 * "swiss-international" work when "Swiss International" has been localised.
 *
 * The two core lookups are injectable so this can be tested without a theme.
 */
class SetStyleVariationTool extends AbstractWriteTool
{
    /** @var callable|null Returns the list of variation configs. */
    private $variationsResolver;

    /** @var callable|null Returns the user global styles post ID. */
    private $globalStylesIdResolver;

    /**
     * @param MutationJournal $journal                Write gateway.
     * @param callable|null   $variationsResolver     Variation list resolver.
     * @param callable|null   $globalStylesIdResolver Global styles post ID resolver.
     */
    public function __construct(MutationJournal $journal, $variationsResolver = null, $globalStylesIdResolver = null)
    {
        parent::__construct($journal);

        $this->variationsResolver     = $variationsResolver;
        $this->globalStylesIdResolver = $globalStylesIdResolver;
    }

    public function getName()
    {
        return 'swishfolio/set-style-variation';
    }

    public function getTitle()
    {
        return __('Apply style variation', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Reskin the whole site by applying one of the style variations the theme ships, named by its '
            . 'title. Call get-global-styles first for the available titles. This changes colours, typography '
            . 'and spacing everywhere at once and must be confirmed.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_PUBLISHED;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['title'],
            'properties'           => [
                'title' => [
                    'type'        => 'string',
                    'maxLength'   => 200,
                    'description' => 'Title of a style variation the theme ships.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            $this->forbid(__('You cannot change the site design.', 'swishfolio-core'));
        }

        $postId = $this->globalStylesPostId();

        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            $this->forbid(__('You cannot edit this site\'s global styles.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $title     = (string) $input['title'];
        $variation = $this->findVariation($title);

        if ($variation === null) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                sprintf(
                    /* translators: %s: style variation title. */
                    __('"%s" is not a style variation this theme ships.', 'swishfolio-core'),
                    $title
                ),
                ['available' => $this->variationTitles()]
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

        $resolved = isset($variation['title']) ? (string) $variation['title'] : $title;

        // Slashed: wp_json_encode escapes forward slashes as \/, and an
        // unslashed wp_update_post would strip those backslashes and leave
        // invalid JSON in the global styles post.
        $postId = $this->journal->updatePost(
            $context,
            $this->getName(),
            wp_slash(
                [
                    'ID'           => $this->globalStylesPostId(),
                    'post_content' => wp_json_encode($config),
                ]
            ),
            sprintf(
                /* translators: %s: style variation title. */
                __('Applied the style variation "%s"', 'swishfolio-core'),
                $resolved
            )
        );

        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        }

        return [
            'postId'  => $postId,
            'title'   => $resolved,
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
     * @return string[]
     */
    private function variationTitles()
    {
        $titles = [];

        foreach ($this->variations() as $variation) {
            if (is_array($variation) && isset($variation['title'])) {
                $titles[] = (string) $variation['title'];
            }
        }

        return $titles;
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
