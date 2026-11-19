<?php
/**
 * The journal's name for the visual review pass.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

use SwishfolioCore\Tools\ToolContext;
use SwishfolioCore\Tools\ToolInterface;

/**
 * Not a tool anyone can call: it only gives the pass's journal rows (done,
 * skipped, failed) a name in the AI audit log. Its writes go through
 * ReviewSite, under the same run.
 */
class VisualReviewTool implements ToolInterface
{
    public function getName()
    {
        return ReviewSite::TOOL;
    }

    public function getTitle()
    {
        return __('Visual review', 'swishfolio-core');
    }

    public function getDescription()
    {
        return 'Looks at screenshots of a newly built site and makes small fixes from a fixed list.';
    }

    public function getInputSchema()
    {
        return ['type' => 'object'];
    }

    public function getSideEffect()
    {
        return ToolInterface::EFFECT_WRITE_PUBLISHED;
    }

    public function authorize(array $input, ToolContext $context)
    {
    }

    public function execute(array $input, ToolContext $context)
    {
        return [];
    }
}
