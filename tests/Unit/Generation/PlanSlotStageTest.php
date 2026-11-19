<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanSlotStage;
use SwishfolioCore\Actions\ValidationException;

class PlanSlotStageTest extends GenerationTestCase
{
    private function apply(array $section)
    {
        $context = $this->context();
        $plan    = (new PlanEnvelopeStage())->process(
            $this->plan(['pages' => [$this->page(['sections' => [$section]])]]),
            $context
        );

        return (new PlanSlotStage())->process($plan, $context);
    }

    private function assertRejected(array $section, $expectedPath)
    {
        try {
            $this->apply($section);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-slot', $e->getStage());
            $this->assertSame($expectedPath, $e->getPath());
        }
    }

    public function test_real_slots_pass_and_come_back_as_id_maps()
    {
        $result = $this->apply($this->patternSection());

        $this->assertSame('Built to last', $result['pages'][0]['sections'][0]['texts']['t0']);
        $this->assertSame('Call Oak and Iron', $result['pages'][0]['sections'][0]['attrs']['a1']);
    }

    private function warnings()
    {
        return array_values(array_filter(isset($GLOBALS['sfcore_test_actions']) ? $GLOBALS['sfcore_test_actions'] : [], static function ($a) {
            return $a[0] === 'swishfolio_ai_plan_warning';
        }));
    }

    public function test_an_unknown_text_slot_is_dropped_with_a_warning_and_the_page_kept()
    {
        $GLOBALS['sfcore_test_actions'] = [];
        $result  = $this->apply($this->patternSection(['texts' => [['id' => 't0', 'html' => 'Built to last'], ['id' => 't99', 'html' => 'Nope']]]));
        $section = $result['pages'][0]['sections'][0];

        $this->assertSame('Built to last', $section['texts']['t0'], 'The real edits stay.');
        $this->assertArrayNotHasKey('t99', $section['texts']);
        $this->assertCount(1, $this->warnings());
        $this->assertSame('plan-slot', $this->warnings()[0][1][0]);
        $this->assertSame('pages.0.sections.0.texts.t99', $this->warnings()[0][1][2]);
    }

    public function test_an_unknown_attribute_slot_is_dropped_like_the_one_that_lost_a_page()
    {
        // A built page was refused for: Section "work-06-cta" has no attribute slot "a0".
        $result = $this->apply($this->patternSection(['attrs' => array_merge($this->heroAttrs(), [['id' => 'a77', 'value' => 'tel:+447700900123']])]));

        $this->assertArrayNotHasKey('a77', $result['pages'][0]['sections'][0]['attrs']);
        $this->assertSame('Call Oak and Iron', $result['pages'][0]['sections'][0]['attrs']['a1']);
    }

    public function test_an_attribute_slot_cannot_take_text_edits()
    {
        $result = $this->apply($this->patternSection(['texts' => [['id' => 'a0', 'html' => 'Nope']]]));

        $this->assertArrayNotHasKey('a0', $result['pages'][0]['sections'][0]['texts'], 'A text written into an attribute slot is dropped.');
    }

    public function test_removing_an_unknown_unit_is_rejected()
    {
        $this->assertRejected($this->patternSection(['remove' => ['u9']]), 'pages.0.sections.0.remove.0');
    }

    public function test_the_whole_section_unit_cannot_be_cloned()
    {
        $this->assertRejected(
            $this->patternSection(['clones' => [['unit' => 'u0', 'texts' => [['id' => 't0', 'html' => 'x']]]]]),
            'pages.0.sections.0.clones.0.unit'
        );
    }

    public function test_a_clone_may_only_edit_slots_inside_the_unit_it_copies()
    {
        $this->apply($this->patternSection(['clones' => [['unit' => 'u1', 'texts' => [['id' => 't2', 'html' => 'Doors']]]]]));

        $result = $this->apply($this->patternSection(['clones' => [['unit' => 'u1', 'texts' => [['id' => 't0', 'html' => 'x']]]]]));
        $this->assertSame([], $result['pages'][0]['sections'][0]['clones'][0]['texts'], 'An edit outside the copied unit is dropped.');
    }

    public function test_block_sections_are_left_alone()
    {
        $result = $this->apply($this->blockSection(['columns' => 2]));

        $this->assertSame(2, $result['pages'][0]['sections'][0]['attributes']['columns']);
    }
}
