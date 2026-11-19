<?php
/**
 * Creates a navigation menu.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Migrated from SetNavigationHandler. Writes one wp_navigation post whose
 * content is core/navigation-link blocks. Those are dynamic, so PHP can
 * serialise them from attributes alone — the whole reason a menu is buildable
 * server-side while a static block is not.
 *
 * The menu is published rather than drafted, because the navigation block only
 * reads published menus, and that is why this is write_published. It is a new
 * menu: nothing existing is edited, so the undo is to trash what was created.
 */
class SetNavigationTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/set-navigation';
    }

    public function getTitle()
    {
        return __('Create navigation menu', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Create a new published navigation menu from a list of labels and URLs. Pass a page ID with an '
            . 'item when it links to a page on this site, so the link keeps working if the page is renamed. '
            . 'Existing menus are never edited; this adds one. Must be confirmed.';
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
            'required'             => ['items'],
            'properties'           => [
                'title' => [
                    'type'      => 'string',
                    'maxLength' => 200,
                    'default'   => '',
                ],
                'items' => NavigationLinks::schema(),
            ],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('edit_theme_options')) {
            $this->forbid(__('You cannot change navigation menus.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $links = NavigationLinks::serialise((array) $input['items']);

        if ($links === []) {
            throw ToolException::make(
                $this->getName(),
                ToolError::WRITE_FAILED,
                __('A navigation menu needs at least one item with both a label and a URL.', 'swishfolio-core')
            );
        }

        $title = ! empty($input['title'])
            ? (string) $input['title']
            : __('Main Navigation', 'swishfolio-core');

        $postId = $this->journal->createPost(
            $context,
            $this->getName(),
            wp_slash(
                [
                    'post_type'    => 'wp_navigation',
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_content' => implode("\n\n", $links),
                    'post_author'  => $context->getUserId(),
                ]
            ),
            sprintf(
                /* translators: 1: menu title, 2: number of items. */
                __('Created the navigation menu "%1$s" with %2$d items', 'swishfolio-core'),
                $title,
                count($links)
            )
        );

        return [
            'postId'  => $postId,
            'title'   => $title,
            'items'   => count($links),
            'applied' => true,
        ];
    }
}
