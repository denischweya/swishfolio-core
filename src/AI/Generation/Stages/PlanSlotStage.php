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
 * the allowlist, as a capability manifest is for attributes. A text or
 * attribute edit naming a slot the section does not have (or a slot of the
 * wrong kind, or one outside the unit a clone copies) has nowhere to go: it is
 * dropped with a warning (action swishfolio_ai_plan_warning) rather than
 * refusing the whole page for one stray id. Whatever demo copy that leaves is
 * still caught by the coverage stage, and the value stage still checks every
 * edit that is kept (links, length, kit syntax). Structure stays strict: an
 * unknown unit to remove, or a clone of a whole section, is refused.
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

                $plan['pages'][$pageIndex]['sections'][$sectionIndex]['texts'] = $this->knownEdits($section['texts'], $slots, ['text', 'run'], $unitId, $path . '.texts');
                $plan['pages'][$pageIndex]['sections'][$sectionIndex]['attrs'] = $this->knownEdits($section['attrs'], $slots, ['attr'], $unitId, $path . '.attrs');

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

                    $plan['pages'][$pageIndex]['sections'][$sectionIndex]['clones'][$index]['texts'] = $this->knownEdits($clone['texts'], $inside, ['text', 'run'], $unitId, $clonePath . '.texts');
                    $plan['pages'][$pageIndex]['sections'][$sectionIndex]['clones'][$index]['attrs'] = $this->knownEdits($clone['attrs'], $inside, ['attr'], $unitId, $clonePath . '.attrs');
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
     * @return array The edits that name a real slot of the right kind.
     */
    private function knownEdits(array $edits, array $slots, array $types, $unitId, $path)
    {
        foreach (array_keys($edits) as $id) {
            if (! isset($slots[$id]) || ! in_array($slots[$id]['type'], $types, true)) {
                unset($edits[$id]);

                if (function_exists('do_action')) {
                    /**
                     * A plan edit was dropped because its slot does not exist.
                     *
                     * @param string $stage   Stage slug.
                     * @param string $message What was dropped.
                     * @param string $path    Plan path.
                     */
                    do_action(
                        'swishfolio_ai_plan_warning',
                        $this->getSlug(),
                        sprintf('Dropped an edit to %s slot "%s", which section "%s" does not have.', $types === ['attr'] ? 'attribute' : 'text', (string) $id, $unitId),
                        $path . '.' . $id
                    );
                }
            }
        }

        return $edits;
    }
}
