<?php
/**
 * One step in the plan validation pipeline.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;

/**
 * Same shape and same discipline as Actions\Stages\StageInterface, and it raises
 * the same ValidationException so one error contract reaches the editor: a stage
 * slug, a message and the offending path.
 */
interface PlanStageInterface
{
    /**
     * @param array       $plan    Plan, as returned by the previous stage.
     * @param PlanContext $context Validation context.
     *
     * @throws \SwishfolioCore\Actions\ValidationException When the plan is unacceptable.
     *
     * @return array The plan, possibly transformed.
     */
    public function process(array $plan, PlanContext $context);

    /**
     * @return string Stage slug reported on failure.
     */
    public function getSlug();
}
