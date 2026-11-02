<?php
/**
 * Replaces the content of one existing page, post or project.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from UpdatePageHandler, and widened from pages to the same closed
 * set of types create-content writes. The type is read from the target rather
 * than taken from the input: the post already is what it is, and accepting a
 * type here would only create a way to disagree with reality.
 *
 * Used for the second pass of a generation run,
 * once every page has an ID and internal links resolve to permalinks, and by the
 * agent when it edits a page that already exists.
 *
 * The side-effect class is declared as write_draft but enforced per target,
 * which is the one place in this subsystem where the two differ. Whether this
 * tool touches the live site depends entirely on the status of the page it is
 * given: rewriting a draft is harmless, rewriting a published page is not, and
 * the status is not knowable until the input is. So authorize() — which runs
 * before the runner's confirmation gate — demands confirmation itself when the
 * target turns out to be published. Declaring write_published instead would
 * force a confirmation prompt on every relink of a freshly created draft.
 *
 * The capability is checked against the specific post, not against a general
 * "can edit pages": edit_post is the only check that respects another author's
 * page, a locked page or a filtered permission.
 */
class UpdateContentTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/update-content';
    }

    public function getTitle()
    {
        return __('Update content', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Replace the content, and optionally the title, of an existing page, post or project. Read it '
            . 'first with read-page so you are replacing it with something complete: this overwrites the body '
            . 'rather than merging into it. Updating something already published changes the live site and '
            . 'must be confirmed.';
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
            'required'             => ['postId', 'content'],
            'properties'           => [
                'postId' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Serialised block markup replacing the page body.',
                ],
                'title' => [
                    'type'      => 'string',
                    'maxLength' => 200,
                    'default'   => '',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        $postId = isset($input['postId']) ? (int) $input['postId'] : 0;

        if ($postId <= 0 || ! in_array((string) get_post_type($postId), CreateContentTool::POST_TYPES, true)) {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                __('That is not a page, post or project.', 'swishfolio-core')
            );
        }

        if (! current_user_can('edit_post', $postId)) {
            $this->forbid(__('You cannot edit that.', 'swishfolio-core'));
        }

        if ('publish' === get_post_status($postId) && ! $context->isConfirmed()) {
            throw ToolException::make(
                $this->getName(),
                ToolError::CONFIRM_REQUIRED,
                __('That is published, so rewriting it changes the live site. Confirm it first.', 'swishfolio-core'),
                ['postId' => $postId, 'sideEffect' => ToolInterface::EFFECT_WRITE_PUBLISHED]
            );
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId  = (int) $input['postId'];
        $changes = [
            'ID'           => $postId,
            'post_content' => (string) $input['content'],
        ];

        if (! empty($input['title'])) {
            $changes['post_title'] = (string) $input['title'];
        }

        // Slashed, for the same reason CreateContentTool slashes: wp_update_post()
        // unslashes, and a block comment is full of \u escapes.
        $this->journal->updatePost(
            $context,
            $this->getName(),
            wp_slash($changes),
            sprintf(
                /* translators: %s: title. */
                __('Rewrote "%s"', 'swishfolio-core'),
                (string) get_post_field('post_title', $postId)
            )
        );

        return [
            'postId'   => $postId,
            'postType' => (string) get_post_type($postId),
            'updated'  => true,
        ];
    }
}
