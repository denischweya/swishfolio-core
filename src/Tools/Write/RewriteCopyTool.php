<?php
/**
 * Rewrites the copy and images of an existing designed page in place.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\AI\Copy\Dashes;
use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Onboarding\WordmarkFit;
use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * The write half of template customisation. Input is a set of edits addressed
 * by CopySlots ids, never a page body: the tool re-reads the page, checks it is
 * still the content the ids were read from, and splices the edits in itself.
 * That is what makes it safe to save without kses (see TrustedMarkup): text is
 * sanitised slot by slot, image swaps take an attachment ID and resolve the URL
 * here, and new sections are kses-filtered before they are inserted.
 *
 * Covers pages, posts and projects (like update-content) and the header and
 * footer template parts, which on a template site carry the demo business name
 * and phone number. Like update-content, it is declared write_draft and asks
 * for confirmation itself when the target is live; a template part is always
 * live.
 *
 * Not in the agent's default registry: it exists for the onboarding wizard,
 * whose own approve step is the confirmation.
 */
class RewriteCopyTool extends AbstractWriteTool
{
    const POST_TYPES = ['page', 'post', 'sfcore_project', 'wp_template_part'];

    const MAX_APPEND_CHARS = 20000;

    public function getName()
    {
        return 'swishfolio/rewrite-copy';
    }

    public function getTitle()
    {
        return __('Rewrite copy', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Rewrite the text, contact details and images of an existing page or template part in place, '
            . 'addressed by slot ids, keeping its design and blocks. Rewriting something live must be confirmed.';
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_DRAFT;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['postId', 'contentHash', 'edits'],
            'properties'           => [
                'postId'      => ['type' => 'integer', 'minimum' => 1],
                'contentHash' => ['type' => 'string', 'maxLength' => 32],
                // JSON: maps keyed by slot id, which the schema subset cannot describe.
                // Checked slot by slot in execute(); CopySlots ignores ids it does not know.
                'edits'       => ['type' => 'string'],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        $postId = isset($input['postId']) ? (int) $input['postId'] : 0;
        $type   = $postId > 0 ? (string) get_post_type($postId) : '';

        if (! in_array($type, self::POST_TYPES, true)) {
            throw ToolException::make($this->getName(), ToolError::TARGET_MISSING, __('That is not a page or template part.', 'swishfolio-core'));
        }

        if ('wp_template_part' === $type ? ! current_user_can('edit_theme_options') : ! current_user_can('edit_post', $postId)) {
            $this->forbid(__('You cannot edit that.', 'swishfolio-core'));
        }

        $live = 'wp_template_part' === $type || 'publish' === get_post_status($postId);

        if ($live && ! $context->isConfirmed()) {
            throw ToolException::make(
                $this->getName(),
                ToolError::CONFIRM_REQUIRED,
                __('That is live, so rewriting it changes your site. Confirm it first.', 'swishfolio-core'),
                ['postId' => $postId, 'sideEffect' => ToolInterface::EFFECT_WRITE_PUBLISHED]
            );
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId  = (int) $input['postId'];
        $content = (string) get_post_field('post_content', $postId);

        if (md5($content) !== (string) $input['contentHash']) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                __('That page changed while it was being rewritten, so it was left alone.', 'swishfolio-core')
            );
        }

        $edits = json_decode((string) $input['edits'], true);
        $edits = is_array($edits) ? $edits : [];
        $slots = new CopySlots($content);

        $edits['images'] = $this->resolveImages(isset($edits['images']) ? (array) $edits['images'] : []);

        if (isset($edits['clones'])) {
            foreach ((array) $edits['clones'] as $i => $clone) {
                $edits['clones'][$i]['images'] = $this->resolveImages(is_array($clone) && isset($clone['images']) ? (array) $clone['images'] : []);
            }
        }

        $edits['append'] = $this->cleanSections(isset($edits['append']) ? (array) $edits['append'] : []);
        // House style for AI-written copy: no em dashes or spaced dashes.
        $edits = Dashes::edits($edits);
        // A decorative copy of a title (a ghost over the photo) keeps its words.
        foreach (\SwishfolioCore\AI\Generation\Kit::mirrorsIn($slots->slots()) as $mirror => $heading) {
            if (isset($edits['texts'][$heading])) {
                $edits['texts'][$mirror] = $edits['texts'][$heading];
            }
        }
        foreach ($edits['append'] as $i => $section) {
            $edits['append'][$i]['markup'] = Dashes::html($section['markup']);
        }

        $updated = $slots->apply($edits);

        // A header or footer whose giant wordmark now says the owner's name: fit it.
        if ('wp_template_part' === get_post_type($postId) && $updated !== $content) {
            $updated = WordmarkFit::apply($updated, WordmarkFit::forSite());
        }

        if ($updated === $content) {
            return ['postId' => $postId, 'updated' => false];
        }

        TrustedMarkup::allow($postId, $content);
        TrustedMarkup::allow($postId, $updated);

        $this->journal->updatePost(
            $context,
            $this->getName(),
            wp_slash(['ID' => $postId, 'post_content' => $updated]),
            sprintf(
                /* translators: %s: title. */
                __('Rewrote the copy of "%s"', 'swishfolio-core'),
                (string) get_post_field('post_title', $postId)
            )
        );

        if ('wp_template_part' === get_post_type($postId) && function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('theme_json');
        }

        return [
            'postId'  => $postId,
            'updated' => true,
        ];
    }

    /**
     * @param array $images slot id => ['attachmentId' => int, 'alt' => string].
     *
     * @return array slot id => ['id', 'url', 'alt'] for real images only.
     */
    private function resolveImages(array $images)
    {
        $out = [];

        foreach ($images as $slot => $image) {
            $id = is_array($image) && isset($image['attachmentId']) ? (int) $image['attachmentId'] : 0;

            if ($id <= 0 || 'attachment' !== get_post_type($id) || ! wp_attachment_is_image($id) || ! current_user_can('edit_post', $id)) {
                continue;
            }

            $url = wp_get_attachment_image_url($id, 'large');

            if (! $url) {
                continue;
            }

            $out[(string) $slot] = [
                'id'  => $id,
                'url' => $url,
                'alt' => isset($image['alt']) ? sanitize_text_field((string) $image['alt']) : null,
            ];
        }

        return $out;
    }

    /**
     * New sections: registered blocks only, always kses-filtered.
     *
     * @param array $sections [['after' => unit id, 'markup' => string]].
     *
     * @return array
     */
    private function cleanSections(array $sections)
    {
        $out      = [];
        $registry = \WP_Block_Type_Registry::get_instance();

        foreach (array_slice($sections, 0, 2) as $section) {
            $markup = is_array($section) && isset($section['markup']) ? trim((string) $section['markup']) : '';

            if ($markup === '' || strlen($markup) > self::MAX_APPEND_CHARS) {
                continue;
            }

            $blocks = parse_blocks($markup);
            $ok     = false;
            $walk   = static function (array $list) use (&$walk, $registry, &$ok) {
                foreach ($list as $block) {
                    if (empty($block['blockName'])) {
                        if (trim((string) $block['innerHTML']) !== '') {
                            return false;
                        }
                        continue;
                    }
                    if (! $registry->is_registered($block['blockName'])) {
                        return false;
                    }
                    $ok = true;
                    if (! empty($block['innerBlocks']) && ! $walk($block['innerBlocks'])) {
                        return false;
                    }
                }

                return true;
            };

            if (! $walk($blocks) || ! $ok) {
                continue;
            }

            $out[] = [
                'after'  => isset($section['after']) ? (string) $section['after'] : '',
                'markup' => wp_kses_post(filter_block_content($markup, 'post')),
            ];
        }

        return $out;
    }
}
