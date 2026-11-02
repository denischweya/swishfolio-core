<?php
/**
 * Lists images already in the media library.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Tools\ToolContext;

/**
 * Many Swishfolio blocks take an imageId, and the library usually already holds
 * something suitable. Without this the model either leaves image fields empty or
 * invents IDs, and an invented attachment ID renders as a broken image rather
 * than as an obvious error.
 *
 * Uploading is not here. This slice can find and reuse what exists; adding new
 * media is the media engine's job.
 */
class ListMediaTool extends AbstractReadTool
{
    const MAX_LIMIT     = 50;
    const DEFAULT_LIMIT = 20;

    public function getName()
    {
        return 'swishfolio/list-media';
    }

    public function getTitle()
    {
        return __('List media', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Search the media library for images already uploaded to this site, returning each one\'s '
            . 'attachment ID, alt text, MIME type, dimensions and URL. Use an ID from here for any block '
            . 'attribute that takes an image. Never invent an attachment ID.';
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'search' => [
                    'type'        => 'string',
                    'maxLength'   => 200,
                    'description' => 'Optional keyword to match against filenames, titles and alt text.',
                ],
                'mimeType' => [
                    'type'        => 'string',
                    'maxLength'   => 100,
                    'description' => 'Optional MIME filter, e.g. "image" or "image/png".',
                ],
                'limit' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                ],
                'offset' => [
                    'type'    => 'integer',
                    'minimum' => 0,
                    'default' => 0,
                ],
            ],
        ];
    }

    public function execute(array $input, ToolContext $context)
    {
        $limit  = isset($input['limit']) ? (int) $input['limit'] : self::DEFAULT_LIMIT;
        $offset = isset($input['offset']) ? (int) $input['offset'] : 0;

        $args = [
            'post_type'        => 'attachment',
            'post_status'      => 'inherit',
            'post_mime_type'   => ! empty($input['mimeType']) ? (string) $input['mimeType'] : 'image',
            'posts_per_page'   => $limit,
            'offset'           => $offset,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'no_found_rows'    => false,
            'suppress_filters' => false,
        ];

        if (! empty($input['search'])) {
            $args['s'] = (string) $input['search'];
        }

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = $this->describe((int) $post->ID, $post);
        }

        $total = (int) $query->found_posts;

        return [
            'items'      => $items,
            'total'      => $total,
            'offset'     => $offset,
            'nextOffset' => ($offset + count($items)) < $total ? $offset + count($items) : null,
        ];
    }

    /**
     * @param int      $id   Attachment ID.
     * @param \WP_Post $post Attachment post.
     *
     * @return array
     */
    private function describe($id, $post)
    {
        $meta = function_exists('wp_get_attachment_metadata') ? wp_get_attachment_metadata($id) : [];

        return [
            'id'       => $id,
            'title'    => (string) $post->post_title,
            'alt'      => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'mimeType' => (string) $post->post_mime_type,
            'width'    => is_array($meta) && isset($meta['width']) ? (int) $meta['width'] : null,
            'height'   => is_array($meta) && isset($meta['height']) ? (int) $meta['height'] : null,
            'url'      => (string) wp_get_attachment_url($id),
        ];
    }
}
