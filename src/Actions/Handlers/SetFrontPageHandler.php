<?php
/**
 * Points the site's front page at a generated page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * This one refuses to do its job most of the time, on purpose.
 *
 * Generated pages are drafts. Pointing page_on_front at a draft takes the live
 * front page down for every visitor, which is a far worse outcome than a setting
 * the owner has to flip themselves. So the options are written only when the
 * target is already published — which happens when a plan chose onConflict
 * "skip" and reused an existing page — and otherwise the action reports back as
 * deferred, having written nothing.
 */
class SetFrontPageHandler implements ServerActionHandlerInterface
{
    const TYPE = 'set_front_page';

    public function getType()
    {
        return self::TYPE;
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        if (! current_user_can('manage_options')) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot change site settings.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $action, ExecutionContext $context)
    {
        $postId = isset($action['postId']) ? (int) $action['postId'] : 0;

        if ($postId <= 0 || get_post_type($postId) !== 'page') {
            throw new ExecutionException(
                self::TYPE,
                'not_a_page',
                __('The front page must be a page.', 'swishfolio-core')
            );
        }

        if (get_post_status($postId) !== 'publish') {
            return [
                'postId'   => $postId,
                'applied'  => false,
                'deferred' => true,
                'reason'   => __('The page is still a draft. Set it as the front page after publishing it.', 'swishfolio-core'),
            ];
        }

        update_option('show_on_front', 'page');
        update_option('page_on_front', $postId);

        $blogId  = isset($action['blogPageId']) ? (int) $action['blogPageId'] : 0;
        $blogSet = false;

        if ($blogId > 0 && get_post_type($blogId) === 'page' && get_post_status($blogId) === 'publish') {
            update_option('page_for_posts', $blogId);
            $blogSet = true;
        }

        return [
            'postId'     => $postId,
            'applied'    => true,
            'deferred'   => false,
            'blogPageId' => $blogSet ? $blogId : null,
        ];
    }
}
