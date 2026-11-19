<?php
/**
 * Which demo copy a plan has not replaced yet.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Generation;

/**
 * Kit sections are the template's own markup, so any slot a plan leaves alone
 * still says what the demo business says: its name, its phone, its prices. A
 * plan is only complete when every required slot (Kit::requiredSlots()) of
 * every section it uses is rewritten or sits inside a unit the plan removes.
 *
 * missing() lists the gaps, fill() merges replacement copy for them, and
 * prune() removes the smallest repeatable unit around a gap as a last resort.
 */
class PlanCoverage
{
    /**
     * @param array $plan Envelope-normalised plan.
     * @param Kit   $kit  Its kit.
     *
     * @return array[] {page, section, unit, slot, demo} per gap.
     */
    public static function missing(array $plan, Kit $kit)
    {
        $gaps = [];

        foreach ($plan['pages'] as $pageIndex => $page) {
            foreach ($page['sections'] as $sectionIndex => $section) {
                if (! isset($section['pattern']) || $kit->section($section['pattern']) === null) {
                    continue;
                }

                $copy    = $kit->slots($section['pattern']);
                $slots   = $copy->slots();
                $units   = $copy->units();
                $removed = [];

                foreach ($section['remove'] as $unitId) {
                    if (isset($units[$unitId])) {
                        $removed[] = $units[$unitId];
                    }
                }

                foreach ($kit->requiredSlots($section['pattern']) as $slotId) {
                    if (isset($section['texts'][$slotId]) || isset($section['attrs'][$slotId])) {
                        continue;
                    }

                    foreach ($removed as $unit) {
                        if ($slots[$slotId]['start'] >= $unit['start'] && $slots[$slotId]['end'] <= $unit['end']) {
                            continue 2;
                        }
                    }

                    $gaps[] = [
                        'page'    => (int) $pageIndex,
                        'section' => (int) $sectionIndex,
                        'unit'    => (string) $section['pattern'],
                        'slot'    => (string) $slotId,
                        'kind'    => $slots[$slotId]['type'] === 'attr' ? 'attr' : 'text',
                        'demo'    => trim(preg_replace('/\s+/', ' ', (string) $slots[$slotId]['value'])),
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Merge replacement copy for gaps into the plan. Only gaps listed by
     * missing() are taken; anything else in $fills is ignored.
     *
     * @param array   $plan  Plan.
     * @param array[] $gaps  From missing().
     * @param array[] $fills [{page, section, slot, html}].
     *
     * @return array
     */
    public static function fill(array $plan, array $gaps, array $fills)
    {
        $open = [];

        foreach ($gaps as $gap) {
            $open[$gap['page'] . '|' . $gap['section'] . '|' . $gap['slot']] = $gap['kind'];
        }

        foreach ($fills as $fill) {
            if (! is_array($fill) || ! isset($fill['page'], $fill['section'], $fill['slot'], $fill['html'])) {
                continue;
            }

            $key = (int) $fill['page'] . '|' . (int) $fill['section'] . '|' . (string) $fill['slot'];

            if (! isset($open[$key]) || trim((string) $fill['html']) === '') {
                continue;
            }

            $field = $open[$key] === 'attr' ? 'attrs' : 'texts';

            $plan['pages'][(int) $fill['page']]['sections'][(int) $fill['section']][$field][(string) $fill['slot']] = (string) $fill['html'];
        }

        return $plan;
    }

    /**
     * Remove the smallest repeatable unit around each remaining gap, when there
     * is one. A gap in a section's fixed frame stays a gap.
     *
     * @param array   $plan Plan.
     * @param array[] $gaps From missing().
     * @param Kit     $kit  Its kit.
     *
     * @return array
     */
    public static function prune(array $plan, array $gaps, Kit $kit)
    {
        foreach ($gaps as $gap) {
            $copy  = $kit->slots($gap['unit']);
            $slot  = $copy->slots()[$gap['slot']];
            $best  = null;
            $width = PHP_INT_MAX;

            foreach ($copy->units() as $unitId => $unit) {
                if ($unit['top'] || $slot['start'] < $unit['start'] || $slot['end'] > $unit['end']) {
                    continue;
                }

                if ($unit['end'] - $unit['start'] < $width) {
                    $best  = $unitId;
                    $width = $unit['end'] - $unit['start'];
                }
            }

            if ($best !== null) {
                $section = &$plan['pages'][$gap['page']]['sections'][$gap['section']];
                if (! in_array($best, $section['remove'], true)) {
                    $section['remove'][] = $best;
                }
                unset($section);
            }
        }

        return $plan;
    }
}
