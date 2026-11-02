<?php
/**
 * Rewrites an existing navigation menu.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Write;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * The counterpart set-navigation was missing, and the gap mattered: on a site
 * that already exists — which is every site the agent is used on after the first
 * day — "add the new page to my menu" is the request, and creating a second menu
 * is the wrong answer to it.
 *
 * It replaces rather than appends, because a merge would need to guess at
 * ordering and at which existing items the owner meant to keep. So the model
 * reads the menu, decides the whole list, and writes it back — which is also why
 * the description insists on reading first.
 *
 * wp_navigation supports revisions in core (post.php:588), so the journal
 * captures one before the overwrite and a rollback restores the old menu exactly.
 */
class UpdateNavigationTool extends AbstractWriteTool
{
    public function getName()
    {
        return 'swishfolio/update-navigation';
    }

    public function getTitle()
    {
        return __('Update navigation menu', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Replace the items of an existing navigation menu, named by its menu ID from list-navigation. '
            . 'This replaces the whole menu, so read it first and pass the full list you want in order, '
            . 'including the items that are staying. Must be confirmed.';
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
            'required'             => ['menuId', 'items'],
            'properties'           => [
                'menuId' => [
                    'type'    => 'integer',
                    'minimum' => 1,
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

        $menuId = isset($input['menuId']) ? (int) $input['menuId'] : 0;

        if ($menuId <= 0 || get_post_type($menuId) !== 'wp_navigation') {
            throw ToolException::make(
                $this->getName(),
                ToolError::TARGET_MISSING,
                __('That is not a navigation menu.', 'swishfolio-core'),
                ['field' => 'menuId']
            );
        }

        if (! current_user_can('edit_post', $menuId)) {
            $this->forbid(__('You cannot edit that menu.', 'swishfolio-core'));
        }
    }

    public function execute(array $input, ToolContext $context)
    {
        $menuId = (int) $input['menuId'];
        $links  = NavigationLinks::serialise((array) $input['items']);

        if ($links === []) {
            throw ToolException::make(
                $this->getName(),
                ToolError::WRITE_FAILED,
                __('A navigation menu needs at least one item with both a label and a URL.', 'swishfolio-core')
            );
        }

        $this->journal->updatePost(
            $context,
            $this->getName(),
            wp_slash(
                [
                    'ID'           => $menuId,
                    'post_content' => implode("\n\n", $links),
                ]
            ),
            sprintf(
                /* translators: 1: menu title, 2: number of items. */
                __('Rewrote the menu "%1$s" with %2$d items', 'swishfolio-core'),
                (string) get_post_field('post_title', $menuId),
                count($links)
            )
        );

        return [
            'menuId'  => $menuId,
            'items'   => count($links),
            'applied' => true,
        ];
    }
}
