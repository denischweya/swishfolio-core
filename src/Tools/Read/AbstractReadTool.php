<?php
/**
 * Shared behaviour for tools that only look.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Tools\Read;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolError;
use SwishfolioCore\Tools\ToolException;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Six tools share the same side-effect class and the same permission floor, so
 * the floor lives in one place: anyone who can edit posts on this site can read
 * what the site contains.
 *
 * It is a floor, not the whole check. read-page narrows further to the specific
 * post with edit_post, the way the write tools do.
 */
abstract class AbstractReadTool implements ToolInterface
{
    public function getSideEffect()
    {
        return self::EFFECT_READ;
    }

    public function getInputSchema()
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [],
        ];
    }

    public function authorize(array $input, ToolContext $context)
    {
        if (! current_user_can('edit_posts')) {
            throw ToolException::make(
                $this->getName(),
                ToolError::FORBIDDEN,
                __('You cannot read this site\'s content.', 'swishfolio-core')
            );
        }
    }
}
