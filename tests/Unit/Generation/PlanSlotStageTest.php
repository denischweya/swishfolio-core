<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanSlotStage;
use SwishfolioCore\Actions\ValidationException;

class PlanSlotStageTest extends GenerationTestCase
{
    private function apply(array $plan)
    {
        $context = $this->context();
        $plan    = (new PlanEnvelopeStage())->process($plan, $context);

        return (new PlanSlotStage())->process($plan, $context);
    }

    public function test_declared_slots_pass()
    {
        $section = $this->patternSection(['headline' => 'Hello', 'body' => 'World', 'ctaUrl' => '/x']);

        $result = $this->apply($this->plan(['pages' => [$this->page(['sections' => [$section]])]]));

        $this->assertSame('Hello', $result['pages'][0]['sections'][0]['content']['headline']);
    }

    public function test_an_undeclared_slot_is_rejected_rather_than_silently_dropped()
    {
        $section = $this->patternSection(['subtitle' => 'Nope']);

        try {
            $this->apply($this->plan(['pages' => [$this->page(['sections' => [$section]])]]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-slot', $e->getStage());
            $this->assertSame('pages.0.sections.0.content.subtitle', $e->getPath());
        }
    }

    public function test_a_unit_with_no_slots_rejects_any_content()
    {
        $section = $this->patternSection(['headline' => 'Nope'], 'alpha-page');

        try {
            $this->apply($this->plan(['pages' => [$this->page(['sections' => [$section]])]]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-slot', $e->getStage());
        }
    }

    public function test_block_sections_are_left_alone()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->blockSection(['columns' => 2])]])],
        ]));

        $this->assertSame(2, $result['pages'][0]['sections'][0]['attributes']['columns']);
    }
}
