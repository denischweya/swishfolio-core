<?php
/**
 * Stage 6: no demo copy survives into a generated page.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\AI\Generation\PlanCoverage;
use SwishfolioCore\Actions\ValidationException;

/**
 * A kit section is the template's verbatim markup, so a plan that leaves one of
 * its required slots alone would publish the demo business's words, phone or
 * prices as the owner's. Every such slot must be rewritten or sit inside a unit
 * the plan removes. /ai/plan fills gaps with one follow-up call before this runs.
 */
class PlanCoverageStage implements PlanStageInterface
{
    const MAX_REPORTED = 8;

    public function getSlug()
    {
        return 'plan-coverage';
    }

    public function process(array $plan, PlanContext $context)
    {
        $gaps = PlanCoverage::missing($plan, $context->getKit());

        if ($gaps === []) {
            return $plan;
        }

        $first = $gaps[0];
        $list  = array_map(static function ($gap) {
            return $gap['unit'] . ' ' . $gap['slot'];
        }, array_slice($gaps, 0, self::MAX_REPORTED));

        throw new ValidationException(
            $this->getSlug(),
            sprintf(
                '%d slot(s) still hold the template\'s demo copy: %s%s. Rewrite them or remove their units.',
                count($gaps),
                implode(', ', $list),
                count($gaps) > self::MAX_REPORTED ? ', ...' : ''
            ),
            sprintf('pages.%d.sections.%d.texts.%s', $first['page'], $first['section'], $first['slot'])
        );
    }
}
