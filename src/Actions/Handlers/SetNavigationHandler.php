<?php
/**
 * Creates a navigation menu for the generated pages.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Actions\Handlers;

/**
 * Writes one wp_navigation post whose content is core/navigation-link blocks.
 * Those are dynamic, so PHP can serialise them from attributes alone — the whole
 * reason a menu is buildable server-side while a static block is not.
 *
 * The menu is published rather than drafted, because the navigation block only
 * reads published menus. It is a new menu: nothing existing is edited.
 */
class SetNavigationHandler implements ServerActionHandlerInterface
{
    const TYPE = 'set_navigation';

    public function getType()
    {
        return self::TYPE;
    }

    public function authorize(array $action, ExecutionContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            throw new ExecutionException(
                self::TYPE,
                'forbidden',
                __('You cannot change navigation menus.', 'swishfolio-core')
            );
        }
    }

    public function execute(array $action, ExecutionContext $context)
    {
        $items = isset($action['items']) ? (array) $action['items'] : [];
        $links = [];

        foreach ($items as $item) {
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

            $links[] = serialize_block([
                'blockName'    => 'core/navigation-link',
                'attrs'        => $attributes,
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ]);
        }

        if ($links === []) {
            throw new ExecutionException(
                self::TYPE,
                'empty_menu',
                __('A navigation menu needs at least one item.', 'swishfolio-core')
            );
        }

        $title  = isset($action['title']) && (string) $action['title'] !== ''
            ? (string) $action['title']
            : __('Main Navigation', 'swishfolio-core');

        $postId = wp_insert_post(
            wp_slash([
                'post_type'    => 'wp_navigation',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => implode("\n\n", $links),
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
                    : __('WordPress refused to create the navigation menu.', 'swishfolio-core')
            );
        }

        $postId = (int) $postId;

        $context->recordCreated($postId);

        return [
            'postId'  => $postId,
            'title'   => $title,
            'items'   => count($links),
            'applied' => true,
        ];
    }
}
