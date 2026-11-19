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

    public function test_an_unknown_text_slot_is_rejected_rather_than_silently_dropped()
    {
        $this->assertRejected(
            $this->patternSection(['texts' => [['id' => 't99', 'html' => 'Nope']]]),
            'pages.0.sections.0.texts.t99'
        );
    }

    public function test_an_attribute_slot_cannot_take_text_edits()
    {
        $this->assertRejected(
            $this->patternSection(['texts' => [['id' => 'a0', 'html' => 'Nope']]]),
            'pages.0.sections.0.texts.a0'
        );
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

        $this->assertRejected(
            $this->patternSection(['clones' => [['unit' => 'u1', 'texts' => [['id' => 't0', 'html' => 'x']]]]]),
            'pages.0.sections.0.clones.0.texts.t0'
        );
    }

    public function test_block_sections_are_left_alone()
    {
        $result = $this->apply($this->blockSection(['columns' => 2]));

        $this->assertSame(2, $result['pages'][0]['sections'][0]['attributes']['columns']);
    }
}
