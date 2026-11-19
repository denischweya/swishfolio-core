<?php
/**
 * Reports the design tokens the AI may reference.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Tools\ToolContext;

/**
 * Colour, spacing and font-size values in Swishfolio blocks are theme.json
 * slugs, never literals — the theme's style variations re-skin the whole palette
 * and a literal would survive the switch as a wrong colour. So a model about to
 * write attributes needs the slug vocabulary, and this is where it gets it.
 *
 * The active variation comes from the theme's own
 * swishfolio_get_active_style_variation_slug(), the same source
 * GenerationController already uses, rather than being inferred by diffing the
 * saved palette against every shipped variation. When the theme is not active
 * the answer is null, which is honest; a guess from a near-matching palette
 * would not be.
 */
class GetGlobalStylesTool extends AbstractReadTool
{
    /** @var callable|null Returns the variation configs. */
    private $variationsResolver;

    /** @var callable|null Returns the user global styles post ID. */
    private $globalStylesIdResolver;

    /** @var TokenResolver|null */
    private $tokens;

    /**
     * @param callable|null      $variationsResolver     Variation list resolver.
     * @param callable|null      $globalStylesIdResolver Global styles post ID resolver.
     * @param TokenResolver|null $tokens                 Token resolver.
     */
    public function __construct($variationsResolver = null, $globalStylesIdResolver = null, TokenResolver $tokens = null)
    {
        $this->variationsResolver     = $variationsResolver;
        $this->globalStylesIdResolver = $globalStylesIdResolver;
        $this->tokens                 = $tokens;
    }

    public function getName()
    {
        return 'swishfolio/get-global-styles';
    }

    public function getTitle()
    {
        return __('Get global styles', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Read the design tokens this site supports: colour, spacing and font-size slugs from theme.json, '
            . 'the style variations the theme ships, and which variation is currently applied. Block attributes '
            . 'must use these slugs rather than literal values like #ffffff or 24px.';
    }

    public function execute(array $input, ToolContext $context)
    {
        $resolver = $this->tokens instanceof TokenResolver ? $this->tokens : TokenResolver::fromTheme();
        $tokens   = [];

        foreach (array_keys(TokenResolver::SETS) as $set) {
            $tokens[$set] = $resolver->getSlugs($set);
        }

        $postId = $this->globalStylesPostId();

        return [
            'tokens'           => $tokens,
            'styleVariations'  => $this->variationTitles(),
            'activeVariation'  => $this->activeVariation(),
            'globalStylesPost' => $postId > 0 ? $postId : null,
        ];
    }

    /**
     * @return string|null
     */
    private function activeVariation()
    {
        if (! function_exists('swishfolio_get_active_style_variation_slug')) {
            return null;
        }

        $slug = (string) swishfolio_get_active_style_variation_slug();

        if ('' === $slug) {
            return null;
        }

        foreach ($this->variationTitles() as $title) {
            if (sanitize_title($title) === $slug) {
                return $title;
            }
        }

        return $slug;
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
