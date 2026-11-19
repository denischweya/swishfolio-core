<?php
/**
 * Runs the plan validation pipeline.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanCollisionStage;
use SwishfolioCore\AI\Generation\Stages\PlanCoverageStage;
use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;
use SwishfolioCore\AI\Generation\Stages\PlanSanitizeStage;
use SwishfolioCore\AI\Generation\Stages\PlanSlotStage;
use SwishfolioCore\AI\Generation\Stages\PlanStageInterface;
use SwishfolioCore\AI\Generation\Stages\PlanValueStage;

/**
 * All-or-nothing, for the same reason ActionValidator is: half a generated site
 * is worse than none of it, and here it would also be half-written to the
 * database.
 *
 * This runs twice per generation — once on the AI's plan, and again on whatever
 * the review UI sends back. A plan that has been through a browser is exactly as
 * untrusted as a plan that came from a model.
 */
class PlanValidator
{
    /** @var PlanStageInterface[] */
    private $stages;

    /**
     * @param PlanStageInterface[] $stages Ordered pipeline.
     */
    public function __construct(array $stages)
    {
        $this->stages = $stages;
    }

    /**
     * @param bool $requireCoverage Whether every demo slot must be rewritten.
     *                              /ai/plan validates once without it, fills
     *                              the gaps, then validates with it.
     *
     * @return self
     */
    public static function withDefaultStages($requireCoverage = true)
    {
        $stages = [
            new PlanEnvelopeStage(),
            new PlanReferenceStage(),
            new PlanSlotStage(),
            new PlanValueStage(),
            new PlanSanitizeStage(),
        ];

        if ($requireCoverage) {
            $stages[] = new PlanCoverageStage();
        }

        $stages[] = new PlanCollisionStage();

        return new self($stages);
    }

    /**
     * @param array       $plan    Plan from the AI or from the review UI.
     * @param PlanContext $context Validation context.
     *
     * @throws \SwishfolioCore\Actions\ValidationException When any stage rejects the plan.
     *
     * @return array Validated, normalised plan.
     */
    public function validate(array $plan, PlanContext $context)
    {
        foreach ($this->stages as $stage) {
            $plan = $stage->process($plan, $context);
        }

        return $plan;
    }
}
