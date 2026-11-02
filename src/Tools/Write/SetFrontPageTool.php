<?php
/**
 * Points the site's front page at a page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from SetFrontPageHandler, which refuses to do its job most of the
 * time on purpose.
 *
 * Generated pages are drafts. Pointing page_on_front at a draft takes the live
 * front page down for every visitor, which is a far worse outcome than a setting
 * the owner has to flip themselves. So the options are written only when the
 * target is already published, and otherwise the call reports back as deferred,
 * having written nothing — and a deferred call leaves no journal targets,
 * because there is nothing to undo.
 */
class SetFrontPageTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/set-front-page';
    }

    public function getTitle()
    {
        return __('Set front page', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Make a published page the site\'s front page, and optionally nominate a page to list blog '
            . 'posts. The page must already be published: if it is still a draft the call is deferred and '
            . 'nothing changes. This changes what visitors see first and must be confirmed.';
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
            'required'             => ['postId'],
            'properties'           => [
                'postId' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'blogPageId' => [
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'default'     => 0,
                    'description' => 'Optional published page to list blog posts on.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('manage_options')) {
            $this->forbid(__('You cannot change site settings.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $postId = (int) $input['postId'];

        if (get_post_type($postId) !== 'page') {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
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

        $title   = (string) get_post_field('post_title', $postId);
        $summary = sprintf(
            /* translators: %s: page title. */
            __('Set the front page to "%s"', 'swishfolio-core'),
            $title
        );

        $this->journal->updateOption($context, $this->getName(), 'show_on_front', 'page', $summary);
        $this->journal->updateOption($context, $this->getName(), 'page_on_front', $postId, $summary);

        $blogId  = isset($input['blogPageId']) ? (int) $input['blogPageId'] : 0;
        $blogSet = false;

        if ($blogId > 0 && get_post_type($blogId) === 'page' && get_post_status($blogId) === 'publish') {
            $this->journal->updateOption(
                $context,
                $this->getName(),
                'page_for_posts',
                $blogId,
                sprintf(
                    /* translators: %s: page title. */
                    __('Set the blog page to "%s"', 'swishfolio-core'),
                    (string) get_post_field('post_title', $blogId)
                )
            );

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
