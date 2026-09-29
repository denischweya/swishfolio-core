<?php
/**
 * Creates one page from a validated plan.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Four things are never taken from the plan, whatever it says:
 *
 *  - post_type is always "page";
 *  - post_status is always "draft", because publishing is a human act;
 *  - post_author is always the current user;
 *  - the only meta written is _wp_page_template, and only for a template name
 *    the theme itself declares.
 *
 * The capability checked is the page post type's own create_posts mapping, read
 * from the registered object rather than assumed, so a site that remaps it is
 * respected.
 */
class CreatePageHandler implements ServerActionHandlerInterface
{
    const TYPE = 'create_page';

    public function getType()
    {
        return self::TYPE;
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        if (! current_user_can($this->createCapability())) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot create pages on this site.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $action, ExecutionContext $context)
    {
        // wp_insert_post() unslashes what it is given, so content must arrive
        // slashed. Verified the hard way on a live site: passing it raw turned
        // every \u002d escape in a block comment into a bare u002d, which
        // corrupted design tokens like var(--wp--preset--color--accent-1) into
        // var(u002du002dwp...). Block comments are full of those escapes.
        $postId = wp_insert_post(
            wp_slash([
                'post_type'    => 'page',
                'post_status'  => 'draft',
                'post_title'   => (string) $action['title'],
                'post_name'    => (string) $action['slug'],
                'post_content' => (string) $action['content'],
                'post_author'  => $context->getUserId(),
            ]),
            true
        );

        if (is_wp_error($postId) || (int) $postId <= 0) {
            throw new ExecutionException(
                self::TYPE,
                'insert_failed',
                is_wp_error($postId)
                    ? $postId->get_error_message()
                    : __('WordPress refused to create the page.', 'swishfolio-core')
            );
        }

        $postId = (int) $postId;

        $context->recordCreated($postId);
        $context->setPageId((string) $action['key'], $postId);

        if (! empty($action['template'])) {
            update_post_meta($postId, '_wp_page_template', (string) $action['template']);
        }

        return [
            'key'      => (string) $action['key'],
            'postId'   => $postId,
            'title'    => (string) $action['title'],
            'slug'     => (string) get_post_field('post_name', $postId),
            'status'   => 'draft',
            'template' => empty($action['template']) ? null : (string) $action['template'],
            'created'  => true,
        ];
    }

    /**
     * @return string
     */
    private function createCapability()
    {
        $type = get_post_type_object('page');

        if ($type !== null && isset($type->cap->create_posts)) {
            return (string) $type->cap->create_posts;
        }

        return 'edit_pages';
    }
}
