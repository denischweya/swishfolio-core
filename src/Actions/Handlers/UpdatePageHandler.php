<?php
/**
 * Replaces the content of one existing page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Used for the second pass of a run, once every page has an ID and internal links
 * can be resolved to permalinks.
 *
 * The capability is checked against the specific post, not against a general
 * "can edit pages": edit_post is the only check that respects another author's
 * page, a locked page or a filtered permission.
 */
class UpdatePageHandler implements ServerActionHandlerInterface
{
    const TYPE = 'update_page';

    public function getType()
    {
        return self::TYPE;
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        $postId = isset($action['postId']) ? (int) $action['postId'] : 0;

        if ($postId <= 0 || get_post_type($postId) !== 'page') {
            throw new ExecutionException(
                self::TYPE,
                'not_a_page',
                __('That is not a page.', 'swishfolio-core')
            );
        }

        if (! current_user_can('edit_post', $postId)) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot edit that page.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $action, ExecutionContext $context)
    {
        $postId = (int) $action['postId'];

        $changes = [
            'ID'           => $postId,
            'post_content' => (string) $action['content'],
        ];

        if (isset($action['title']) && (string) $action['title'] !== '') {
            $changes['post_title'] = (string) $action['title'];
        }

        // Slashed, for the same reason CreatePageHandler slashes: wp_update_post()
        // unslashes, and a block comment is full of \u escapes.
        $result = wp_update_post(wp_slash($changes), true);

        if (is_wp_error($result) || (int) $result <= 0) {
            throw new ExecutionException(
                self::TYPE,
                'update_failed',
                is_wp_error($result)
                    ? $result->get_error_message()
                    : __('WordPress refused to update the page.', 'swishfolio-core')
            );
        }

        return [
            'postId'  => $postId,
            'updated' => true,
        ];
    }
}
