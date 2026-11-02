<?php
/**
 * Creates one page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from CreatePageHandler, with its four refusals intact — four things
 * are never taken from the input, whatever it says:
 *
 *  - post_type is always "page";
 *  - post_status is always "draft", because publishing is a human act;
 *  - post_author is always the current user;
 *  - the only meta written is _wp_page_template.
 *
 * The capability checked is the page post type's own create_posts mapping, read
 * from the registered object rather than assumed, so a site that remaps it is
 * respected.
 */
class CreatePageTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/create-page';
    }

    public function getTitle()
    {
        return __('Create page', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Create a new page as a draft, with block markup as its content. The page is never published '
            . 'by this tool, so it is safe to call while building a site. Returns the new page ID.';
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
            'required'             => ['key', 'title', 'content'],
            'properties'           => [
                'key' => [
                    'type'        => 'string',
                    'maxLength'   => 60,
                    'description' => 'Short identifier for referring to this page later in the same run.',
                ],
                'title' => [
                    'type'      => 'string',
                    'maxLength' => 200,
                ],
                'slug' => [
                    'type'      => 'string',
                    'maxLength' => 200,
                    'default'   => '',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Serialised block markup for the page body.',
                ],
                'template' => [
                    'type'        => 'string',
                    'maxLength'   => 100,
                    'description' => 'Optional theme template name.',
                ],
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can($this->createCapability())) {
            $this->forbid(__('You cannot create pages on this site.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $key   = (string) $input['key'];
        $title = (string) $input['title'];

        // wp_insert_post() unslashes what it is given, so content must arrive
        // slashed. Verified the hard way on a live site: passing it raw turned
        // every \u002d escape in a block comment into a bare u002d, which
        // corrupted design tokens like var(--wp--preset--color--accent-1) into
        // var(u002du002dwp...). Block comments are full of those escapes.
        $postId = $this->journal->createPost(
            $context,
            $this->getName(),
            wp_slash(
                [
                    'post_type'    => 'page',
                    'post_status'  => 'draft',
                    'post_title'   => $title,
                    'post_name'    => isset($input['slug']) ? (string) $input['slug'] : '',
                    'post_content' => (string) $input['content'],
                    'post_author'  => $context->getUserId(),
                ]
            ),
            sprintf(
                /* translators: %s: page title. */
                __('Created the draft page "%s"', 'swishfolio-core'),
                $title
            )
        );

        $context->setPageId($key, $postId);

        if (! empty($input['template'])) {
            update_post_meta($postId, '_wp_page_template', (string) $input['template']);
        }

        return [
            'key'      => $key,
            'postId'   => $postId,
            'title'    => $title,
            'slug'     => (string) get_post_field('post_name', $postId),
            'status'   => 'draft',
            'template' => empty($input['template']) ? null : (string) $input['template'],
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
