<?php
/**
 * Creates one page, post or project.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from CreatePageHandler, with its four refusals intact — four things
 * are never taken from the input as free text, whatever it says:
 *
 *  - post_type comes from a closed enum, so no amount of model output can
 *    create a revision, a menu item or an attachment through this tool;
 *  - post_status is always "draft", because publishing is a human act;
 *  - post_author is always the current user;
 *  - the only meta written is _wp_page_template, and only for a page.
 *
 * postType defaults to "page", which is what keeps the generation engine
 * working unchanged: a plan's create_page action carries no postType and lands
 * on the default.
 *
 * The capability checked is the target post type's own create_posts mapping,
 * read from the registered object rather than assumed, so a site that remaps it
 * is respected.
 */
class CreateContentTool extends AbstractWriteTool
{
    /** The only types this tool will ever create. */
    const POST_TYPES = ['page', 'post', 'sfcore_project'];

    public function getName()
    {
        return 'swishfolio/create-content';
    }

    public function getTitle()
    {
        return __('Create content', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Create a new page, blog post or portfolio project as a draft, with block markup as its '
            . 'content. It is never published by this tool, so it is safe to call while building a site. '
            . 'Returns the new ID, which you then use for navigation, the front page or set-terms.';
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
                'postType' => [
                    'type'        => 'string',
                    'enum'        => self::POST_TYPES,
                    'default'     => 'page',
                    'description' => 'What kind of content to create.',
                ],
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
        $postType = $this->postTypeFrom($input);

        if (! current_user_can($this->createCapability($postType))) {
            $this->forbid(
                sprintf(
                    /* translators: %s: post type label. */
                    __('You cannot create %s on this site.', 'swishfolio-core'),
                    $this->typeLabel($postType)
                )
            );
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $key      = (string) $input['key'];
        $title    = (string) $input['title'];
        $postType = $this->postTypeFrom($input);

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
                    'post_type'    => $postType,
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

        // Templates are a page concept; writing one onto a post or a project
        // would store meta the theme never reads.
        $template = 'page' === $postType && ! empty($input['template'])
            ? (string) $input['template']
            : null;

        if (null !== $template) {
            update_post_meta($postId, '_wp_page_template', $template);
        }

        return [
            'key'      => $key,
            'postId'   => $postId,
            'postType' => $postType,
            'title'    => $title,
            'slug'     => (string) get_post_field('post_name', $postId),
            'status'   => 'draft',
            'template' => $template,
            'created'  => true,
        ];
    }

    /**
     * @param array $input Validated input.
     *
     * @return string
     */
    private function postTypeFrom(array $input)
    {
        $postType = isset($input['postType']) ? (string) $input['postType'] : 'page';

        return in_array($postType, self::POST_TYPES, true) ? $postType : 'page';
    }

    /**
     * @param string $postType Target post type.
     *
     * @return string
     */
    private function createCapability($postType)
    {
        $type = get_post_type_object($postType);

        if ($type !== null && isset($type->cap->create_posts)) {
            return (string) $type->cap->create_posts;
        }

        return 'page' === $postType ? 'edit_pages' : 'edit_posts';
    }

    /**
     * @param string $postType Target post type.
     *
     * @return string
     */
    private function typeLabel($postType)
    {
        $type = get_post_type_object($postType);

        return $type !== null && isset($type->labels->name)
            ? (string) $type->labels->name
            : $postType;
    }
}
