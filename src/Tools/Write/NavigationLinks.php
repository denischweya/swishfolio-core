<?php
/**
 * Turns menu items into core/navigation-link markup.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

/**
 * Shared by the two navigation tools rather than duplicated, because the detail
 * that matters is easy to get subtly different: an item that carries a postId is
 * serialised as a post-type link, so the menu keeps working when the page is
 * renamed, while one with only a URL is a plain link that does not.
 *
 * core/navigation-link is dynamic, which is the whole reason a menu can be built
 * server-side at all: PHP can serialise it from attributes, where a static block
 * would need its save() output.
 */
class NavigationLinks
{
    const MAX_ITEMS = 30;

    /**
     * @param array $items Each ['label' => string, 'url' => string, 'postId' => int].
     *
     * @return string[] Serialised blocks; items missing a label or URL are dropped.
     */
    public static function serialise(array $items)
    {
        $links = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = isset($item['label']) ? trim((string) $item['label']) : '';
            $url   = isset($item['url']) ? trim((string) $item['url']) : '';

            if ($label === '' || $url === '') {
                continue;
            }

            $attributes = [
                'label' => $label,
                'url'   => $url,
            ];

            if (! empty($item['postId'])) {
                $attributes['id']   = (int) $item['postId'];
                $attributes['kind'] = 'post-type';
                $attributes['type'] = 'page';
            }

            $links[] = serialize_block(
                [
                    'blockName'    => 'core/navigation-link',
                    'attrs'        => $attributes,
                    'innerBlocks'  => [],
                    'innerHTML'    => '',
                    'innerContent' => [],
                ]
            );
        }

        return $links;
    }

    /**
     * @return array JSON Schema for a list of menu items.
     */
    public static function schema()
    {
        return [
            'type'     => 'array',
            'minItems' => 1,
            'maxItems' => self::MAX_ITEMS,
            'items'    => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['label', 'url'],
                'properties'           => [
                    'label'  => ['type' => 'string', 'maxLength' => 100],
                    'url'    => ['type' => 'string', 'maxLength' => 500],
                    'postId' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
        ];
    }
}
