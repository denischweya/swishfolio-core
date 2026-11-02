<?php
/**
 * Lists the site's navigation menus and what is in them.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Tools\ToolContext;

/**
 * update-navigation replaces a menu wholesale, so without this the model would
 * have to rewrite a menu it has never seen — which in practice means dropping
 * every item it did not think to include.
 *
 * The items come back as labels and URLs rather than as block markup, for the
 * same reason read-page returns a tree: the model has to be able to hand them
 * straight back.
 */
class ListNavigationTool extends AbstractReadTool
{
    const MAX_MENUS = 20;

    public function getName()
    {
        return 'swishfolio/list-navigation';
    }

    public function getTitle()
    {
        return __('List navigation menus', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'List this site\'s navigation menus with their IDs, titles, and the label and URL of every '
            . 'item in order. Call this before update-navigation: that tool replaces a menu completely, so you '
            . 'need to know what is already in it.';
    }

    public function execute(array $input, ToolContext $context)
    {
        $query = new \WP_Query(
            [
                'post_type'        => 'wp_navigation',
                'post_status'      => 'any',
                'posts_per_page'   => self::MAX_MENUS,
                'orderby'          => 'date',
                'order'            => 'ASC',
                'no_found_rows'    => false,
                'suppress_filters' => false,
            ]
        );

        $menus = [];

        foreach ($query->posts as $post) {
            $menus[] = [
                'menuId' => (int) $post->ID,
                'title'  => (string) $post->post_title,
                'items'  => $this->itemsOf((string) $post->post_content),
            ];
        }

        return ['menus' => $menus];
    }

    /**
     * @param string $content Serialised menu blocks.
     *
     * @return array[]
     */
    private function itemsOf($content)
    {
        $items = [];

        foreach (parse_blocks($content) as $block) {
            if (! is_array($block) || empty($block['blockName'])) {
                continue;
            }

            $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];

            $item = [
                'label' => isset($attrs['label']) ? (string) $attrs['label'] : '',
                'url'   => isset($attrs['url']) ? (string) $attrs['url'] : '',
            ];

            if (! empty($attrs['id'])) {
                $item['postId'] = (int) $attrs['id'];
            }

            $items[] = $item;
        }

        return $items;
    }
}
