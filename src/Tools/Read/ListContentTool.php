<?php
/**
 * Lists the site's pages, posts or projects.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Tools\ToolContext;

/**
 * Paged, with a hard ceiling. An unbounded list is the commonest way a tool
 * layer becomes unusable: one call against a 400-page site exhausts the context
 * window and the model never recovers within that conversation.
 *
 * Excerpts are short on purpose. The model asking "what pages exist" needs
 * titles and IDs; the one that needs a page's contents calls read-page.
 */
class ListContentTool extends AbstractReadTool
{
    const MAX_LIMIT      = 50;
    const DEFAULT_LIMIT  = 20;
    const EXCERPT_LENGTH = 160;

    public function getName()
    {
        return 'swishfolio/list-content';
    }

    public function getTitle()
    {
        return __('List content', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'List the site\'s pages, posts or portfolio projects, newest first, with their IDs, titles, '
            . 'slugs, statuses and a short excerpt. Use it to find the ID of something before reading or '
            . 'changing it. Results are paged: pass the returned nextOffset to continue.';
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'postType' => [
                    'type'        => 'string',
                    'enum'        => ['page', 'post', SiteInfoTool::PROJECT_POST_TYPE],
                    'default'     => 'page',
                    'description' => 'Which kind of content to list.',
                ],
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['any', 'publish', 'draft', 'pending', 'future', 'private'],
                    'default'     => 'any',
                    'description' => 'Filter by status.',
                ],
                'search' => [
                    'type'        => 'string',
                    'maxLength'   => 200,
                    'description' => 'Optional keyword to match against titles and content.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => self::MAX_LIMIT,
                    'default'     => self::DEFAULT_LIMIT,
                    'description' => 'How many items to return.',
                ],
                'offset' => [
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'default'     => 0,
                    'description' => 'How many items to skip, for paging.',
                ],
            ],
        ];
    }

    public function execute(array $input, ToolContext $context)
    {
        $limit  = isset($input['limit']) ? (int) $input['limit'] : self::DEFAULT_LIMIT;
        $offset = isset($input['offset']) ? (int) $input['offset'] : 0;

        $args = [
            'post_type'        => isset($input['postType']) ? $input['postType'] : 'page',
            'post_status'      => isset($input['status']) ? $input['status'] : 'any',
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'orderby'          => 'modified',
            'order'            => 'DESC',
            'suppress_filters' => false,
            'no_found_rows'    => false,
        ];

        if (! empty($input['search'])) {
            $args['s'] = (string) $input['search'];
        }

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            // WP_Query does not apply read permissions to an explicit status,
            // so a contributor would otherwise see others' drafts and private
            // posts. Hidden items still count toward paging so offsets hold.
            if (! current_user_can('read_post', $post->ID)) {
                continue;
            }

            $items[] = [
                'id'       => (int) $post->ID,
                'title'    => (string) $post->post_title,
                'slug'     => (string) $post->post_name,
                'status'   => (string) $post->post_status,
                'postType' => (string) $post->post_type,
                'modified' => (string) $post->post_modified_gmt,
                'excerpt'  => $this->excerpt($post),
            ];
        }

        $total = (int) $query->found_posts;

        return [
            'items'      => $items,
            'total'      => $total,
            'offset'     => $offset,
            'nextOffset' => ($offset + count($query->posts)) < $total ? $offset + count($query->posts) : null,
        ];
    }

    /**
     * @param \WP_Post $post Post to summarise.
     *
     * @return string
     */
    private function excerpt($post)
    {
        $text = '' !== (string) $post->post_excerpt ? (string) $post->post_excerpt : (string) $post->post_content;
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($text))));

        return mb_strlen($text) > self::EXCERPT_LENGTH
            ? mb_substr($text, 0, self::EXCERPT_LENGTH - 1) . '…'
            : $text;
    }
}
