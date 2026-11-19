<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\PlanCoverage;
use SwishfolioCore\AI\Generation\PlanValidator;

class PlanCoverageTest extends GenerationTestCase
{
    /**
     * A plan through every stage but coverage, with only the headline written.
     */
    private function partialPlan()
    {
        $section = $this->patternSection(['texts' => [['id' => 't0', 'html' => 'Built to last']], 'attrs' => []]);

        return PlanValidator::withDefaultStages(false)->validate(
            $this->plan(['pages' => [$this->page(['sections' => [$section]])]]),
            $this->context()
        );
    }

    private function slotsOf(array $gaps)
    {
        return array_column($gaps, 'slot');
    }

    public function test_a_fully_rewritten_plan_has_no_gaps()
    {
        $plan = PlanValidator::withDefaultStages()->validate($this->plan(), $this->context());

        $this->assertSame([], PlanCoverage::missing($plan, $this->kit()));
    }

    public function test_every_slot_still_holding_demo_copy_is_a_gap()
    {
        $gaps = PlanCoverage::missing($this->partialPlan(), $this->kit());

        $this->assertSame(['t1', 't2', 't3', 't4', 'a0', 'a1', 't5'], $this->slotsOf($gaps));
        $this->assertSame('attr', $gaps[4]['kind']);
        $this->assertSame('Demo body copy.', $gaps[0]['demo']);
    }

    public function test_fill_writes_only_the_listed_gaps_into_the_right_field()
    {
        $plan  = $this->partialPlan();
        $gaps  = PlanCoverage::missing($plan, $this->kit());
        $plan  = PlanCoverage::fill($plan, $gaps, [
            ['page' => 0, 'section' => 0, 'slot' => 't1', 'html' => 'Joinery.'],
            ['page' => 0, 'section' => 0, 'slot' => 'a1', 'html' => 'Call us'],
            ['page' => 0, 'section' => 0, 'slot' => 't0', 'html' => 'Not a gap, ignored'],
            ['page' => 0, 'section' => 0, 'slot' => 't2', 'html' => '  '],
        ]);
        $section = $plan['pages'][0]['sections'][0];

        $this->assertSame('Joinery.', $section['texts']['t1']);
        $this->assertSame('Call us', $section['attrs']['a1']);
        $this->assertSame('Built to last', $section['texts']['t0']);
        $this->assertArrayNotHasKey('t2', $section['texts']);
    }

    public function test_prune_removes_the_smallest_repeatable_unit_and_leaves_frame_gaps()
    {
        $plan = $this->partialPlan();
        $plan = PlanCoverage::prune($plan, PlanCoverage::missing($plan, $this->kit()), $this->kit());

        $this->assertSame(['u1', 'u2'], $plan['pages'][0]['sections'][0]['remove']);
        $this->assertSame(['t1', 't4', 'a0', 'a1', 't5'], $this->slotsOf(PlanCoverage::missing($plan, $this->kit())));
    }
}
