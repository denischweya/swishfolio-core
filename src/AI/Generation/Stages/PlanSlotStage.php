<?php
/**
 * Stage 3: every filled slot is one the unit actually declares.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * The sidecar's slot list is the allowlist, exactly as a capability manifest is
 * for attributes. An undeclared slot name has no token in the markup, so writing
 * it would substitute nothing and silently lose the copy: refuse it instead.
 */
class PlanSlotStage implements PlanStageInterface
{
    public function getSlug()
    {
        return 'plan-slot';
    }

    public function process(array $plan, PlanContext $context)
    {
        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern'])) {
                    continue;
                }

                $path = 'pages.' . $pageIndex . '.sections.' . $sectionIndex . '.content';
                $unit = $context->getLibrary()->get($section['pattern']);

                foreach (array_keys($section['content']) as $name) {
                    if ($unit->getSlot($name) === null) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Unit "%s" has no slot called "%s".', $unit->getId(), (string) $name),
                            $path . '.' . $name
                        );
                    }
                }
            }
        }

        return $plan;
    }
}
