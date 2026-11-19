<?php
/**
 * Writes a compiled custom design to the current site.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Design;

use SwishfolioCore\AI\Generation\Kit;
use SwishfolioCore\AI\Onboarding\KitArt;
use SwishfolioCore\Tools\Journal\MutationJournal;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;
use SwishfolioCore\Tools\Write\TrustedMarkup;

/**
 * Everything goes through the mutation journal under the caller's run ID, so
 * the build and its design are one undo in the audit log:
 *
 * - the user global styles: compiled from the structural kit's shipped design
 *   (never from what the site has now, so re-running replaces rather than
 *   stacks) and the validated tokens;
 * - the header and footer parts and the given pages: their hard-coded colours
 *   (block attributes, inline SVG) recoloured with the same mapping, and the
 *   kit's decorative pictures swapped for copies in this palette (KitArt);
 * - the option swish_custom_design: the tokens, the kit and the prompt.
 *
 * Only bodies this class composed are vouched for with TrustedMarkup: the
 * compiled styles (CssCompiler::sanitize() already passed) and markup that
 * differs from what was on the post only in colour values.
 */
class CustomDesignApplier
{
    const TOOL   = 'swishfolio/custom-design';
    const OPTION = 'swish_custom_design';

    /** @var MutationJournal */
    private $journal;

    public function __construct(MutationJournal $journal)
    {
        $this->journal = $journal;
        TrustedMarkup::register();
    }

    /**
     * @param array       $tokens  Valid tokens (DesignTokens::validate()).
     * @param Kit         $kit     Structural kit.
     * @param int[]       $pageIds Pages of this build to recolour.
     * @param string      $prompt  The owner's design prompt (stored for re-runs).
     * @param ToolContext $context Run.
     *
     * @throws ToolException When the user may not change the design.
     *
     * @return array {globalStyles, parts, pages, name}
     */
    public function apply(array $tokens, Kit $kit, array $pageIds, $prompt, ToolContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            throw ToolException::make(self::TOOL, ToolError::FORBIDDEN, __('You cannot change the site design.', 'swishfolio-core'));
        }

        $design   = $kit->getDesignFile();
        $kitMeta  = $kit->getDesign();
        $compiler = new DesignCompiler(
            $tokens,
            $design,
            isset($kitMeta['classPrefix']) ? (string) $kitMeta['classPrefix'] : '',
            untrailingslashit(SFCORE_PLUGIN_URL),
            untrailingslashit(get_template_directory_uri())
        );

        try {
            $doc = $compiler->globalStyles();
        } catch (\UnexpectedValueException $e) {
            throw ToolException::make(self::TOOL, ToolError::WRITE_FAILED, __('The custom design could not be compiled safely. Nothing was changed.', 'swishfolio-core'));
        }

        $postId = SetDesignTokensTool::globalStylesPostId();
        if ($postId <= 0 || ! current_user_can('edit_post', $postId)) {
            throw ToolException::make(self::TOOL, ToolError::FORBIDDEN, __('You cannot edit this site\'s global styles.', 'swishfolio-core'));
        }

        $content = (string) wp_json_encode($doc);
        $styled  = $this->write($context, $postId, (string) get_post_field('post_content', $postId), $content, sprintf(/* translators: %s: design name. */ __('Applied your custom design "%s"', 'swishfolio-core'), $tokens['name']));

        // Markup this same design already recoloured (and nobody changed since)
        // is left alone: recolouring is not a fixed point, so a re-run would
        // otherwise nudge vivid colours each time.
        $previous = get_option(self::OPTION);
        $written  = is_array($previous) && isset($previous['written']) && ($previous['tokens'] ?? null) == $tokens ? (array) $previous['written'] : [];
        $hashes   = [];
        // Decorative kit pictures (abstract art, not photos) in this palette too.
        $art      = KitArt::recolourer($kit, KitArt::palette(isset($doc['settings']['color']['palette']['theme']) ? (array) $doc['settings']['color']['palette']['theme'] : []), $tokens['palette']['accent2']);
        $recolour = function ($postId, $before, $summary) use ($context, $compiler, $written, &$hashes, $kit, $art) {
            if (isset($written[$postId]) && hash_equals((string) $written[$postId], md5($before))) {
                $hashes[$postId] = $written[$postId];

                return false;
            }
            $after           = KitArt::rewrite($compiler->recolourMarkup($before), $kit, $art);
            $hashes[$postId] = md5($after);

            return $this->write($context, $postId, $before, $after, $summary);
        };

        $parts = 0;
        foreach (self::parts() as $partId) {
            $parts += $recolour($partId, (string) get_post_field('post_content', $partId), __('Recoloured the header and footer for your custom design', 'swishfolio-core')) ? 1 : 0;
        }

        $pages = 0;
        foreach (array_unique(array_map('intval', $pageIds)) as $pageId) {
            $post = get_post($pageId);
            if (! $post || 'page' !== $post->post_type || ! current_user_can('edit_post', $pageId)) {
                continue;
            }
            $pages += $recolour($pageId, (string) $post->post_content, __('Recoloured a page for your custom design', 'swishfolio-core')) ? 1 : 0;
        }

        KitArt::covers($kit, $art, $this->journal, $context);

        $this->journal->updateOption($context, self::TOOL, self::OPTION, [
            'name'      => $tokens['name'],
            'kit'       => $kit->getSlug(),
            'prompt'    => mb_substr((string) $prompt, 0, 1000),
            'tokens'    => $tokens,
            'appliedAt' => time(),
            'runId'     => $context->getRunId(),
            'written'   => $hashes,
        ], __('Saved your custom design', 'swishfolio-core'));

        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        }

        return ['globalStyles' => $styled, 'parts' => $parts, 'pages' => $pages, 'name' => $tokens['name']];
    }

    /**
     * @return bool Whether it changed.
     */
    private function write(ToolContext $context, $postId, $before, $after, $summary)
    {
        if ($after === $before || $after === '') {
            return false;
        }

        TrustedMarkup::allow((int) $postId, $before);
        TrustedMarkup::allow((int) $postId, $after);
        $this->journal->updatePost($context, self::TOOL, wp_slash(['ID' => (int) $postId, 'post_content' => $after]), $summary);

        return true;
    }

    /**
     * This theme's header and footer parts the user can edit.
     *
     * @return int[]
     */
    private static function parts()
    {
        $out = [];
        foreach (get_posts([
            'post_type'      => 'wp_template_part',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'no_found_rows'  => true,
            'tax_query'      => [[ // phpcs:ignore WordPress.DB.SlowDBQuery
                'taxonomy' => 'wp_theme',
                'field'    => 'name',
                'terms'    => get_stylesheet(),
            ]],
        ]) as $post) {
            if (preg_match('/^(header|footer)/', $post->post_name) && current_user_can('edit_post', $post->ID)) {
                $out[] = (int) $post->ID;
            }
        }

        return $out;
    }
}
