<?php
/**
 * Stage 3: every edit names a slot or unit the section actually has.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation\Stages;

use SwishfolioCore\AI\Generation\PlanContext;
use SwishfolioCore\Actions\ValidationException;

/**
 * A kit section's slots are found in its markup by CopySlots, and that list is
 * the allowlist, as a capability manifest is for attributes. CopySlots would
 * quietly ignore an id it does not know, which loses the copy written for it,
 * so an unknown id is refused instead, and so is an id of the wrong kind (a
 * text written into an attribute slot, a clone of a whole section).
 */
class PlanSlotStage implements PlanStageInterface
{
    public function getSlug()
    {
        return 'plan-slot';
    }

    public function process(array $plan, PlanContext $context)
    {
        $kit = $context->getKit();

        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern'])) {
                    continue;
                }

                $path   = 'pages.' . $pageIndex . '.sections.' . $sectionIndex;
                $copy   = $kit->slots($section['pattern']);
                $slots  = $copy->slots();
                $units  = $copy->units();
                $unitId = $section['pattern'];

                $this->checkEdits($section['texts'], $slots, ['text', 'run'], $unitId, $path . '.texts');
                $this->checkEdits($section['attrs'], $slots, ['attr'], $unitId, $path . '.attrs');

                foreach ($section['remove'] as $index => $id) {
                    if (! isset($units[$id])) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Section "%s" has no unit "%s" to remove.', $unitId, $id),
                            $path . '.remove.' . $index
                        );
                    }
                }

                foreach ($section['clones'] as $index => $clone) {
                    $clonePath = $path . '.clones.' . $index;
                    $unit      = isset($units[$clone['unit']]) ? $units[$clone['unit']] : null;

                    if ($unit === null || $unit['top']) {
                        throw new ValidationException(
                            $this->getSlug(),
                            sprintf('Section "%s" has no repeatable unit "%s" to clone.', $unitId, $clone['unit']),
                            $clonePath . '.unit'
                        );
                    }

                    // A clone's edits use the ids of slots inside the unit it copies.
                    $inside = array_filter($slots, static function ($slot) use ($unit) {
                        return $slot['start'] >= $unit['start'] && $slot['end'] <= $unit['end'];
                    });

                    $this->checkEdits($clone['texts'], $inside, ['text', 'run'], $unitId, $clonePath . '.texts');
                    $this->checkEdits($clone['attrs'], $inside, ['attr'], $unitId, $clonePath . '.attrs');
                }
            }
        }

        return $plan;
    }

    /**
     * @param array    $edits  id => value.
     * @param array    $slots  CopySlots slots by id.
     * @param string[] $types  Slot types these edits may target.
     * @param string   $unitId Section unit id.
     * @param string   $path   Error path.
     *
     * @return void
     */
    private function checkEdits(array $edits, array $slots, array $types, $unitId, $path)
    {
        foreach (array_keys($edits) as $id) {
            if (! isset($slots[$id]) || ! in_array($slots[$id]['type'], $types, true)) {
                throw new ValidationException(
                    $this->getSlug(),
                    sprintf('Section "%s" has no %s slot "%s".', $unitId, $types === ['attr'] ? 'attribute' : 'text', (string) $id),
                    $path . '.' . $id
                );
            }
        }
    }
}
