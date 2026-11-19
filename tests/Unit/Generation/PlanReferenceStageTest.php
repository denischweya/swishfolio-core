<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanReferenceStage;
use SwishfolioCore\Actions\ValidationException;

class PlanReferenceStageTest extends GenerationTestCase
{
    private function apply(array $plan, $scope = 'site')
    {
        $context = $this->context($scope);
        $plan    = (new PlanEnvelopeStage())->process($plan, $context);

        return (new PlanReferenceStage())->process($plan, $context);
    }

    private function assertRejected(array $plan, $expectedPath = null)
    {
        try {
            $this->apply($plan);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-reference', $e->getStage());

            if ($expectedPath !== null) {
                $this->assertSame($expectedPath, $e->getPath());
            }
        }
    }

    public function test_a_plan_referring_only_to_real_things_passes()
    {
        $result = $this->apply($this->plan());

        $this->assertCount(1, $result['pages']);
    }

    public function test_an_unknown_generation_unit_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->patternSection([], 'no-such-unit')]])]]),
            'pages.0.sections.0.pattern'
        );
    }

    public function test_a_static_block_cannot_be_named_as_a_section()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->blockSection([], 'swishfolio-core/hero')]])]]),
            'pages.0.sections.0.block'
        );
    }

    public function test_the_form_block_is_not_generatable_because_its_fields_are_unmanifested()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->blockSection([], 'swishfolio-core/swish-form')]])]]),
            'pages.0.sections.0.block'
        );
    }

    public function test_the_two_permitted_dynamic_blocks_pass()
    {
        foreach (['swishfolio-core/portfolio', 'swishfolio-core/latest-posts'] as $blockName) {
            $result = $this->apply($this->plan([
                'pages' => [$this->page(['sections' => [$this->blockSection(['columns' => 2], $blockName)]])],
            ]));

            $this->assertSame($blockName, $result['pages'][0]['sections'][0]['block']);
        }
    }

    public function test_a_template_the_theme_does_not_declare_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['template' => 'page-invented'])]]),
            'pages.0.template'
        );
    }

    public function test_a_declared_custom_template_is_accepted()
    {
        $result = $this->apply($this->plan(['pages' => [$this->page(['template' => 'page-no-title'])]]));

        $this->assertSame('page-no-title', $result['pages'][0]['template']);
    }

    public function test_a_front_page_naming_an_unknown_key_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['site' => ['frontPage' => 'ghost', 'navigation' => []]]),
            'site.frontPage'
        );
    }

    public function test_a_navigation_item_naming_an_unknown_key_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['site' => ['frontPage' => 'home', 'navigation' => ['home', 'ghost']]]),
            'site.navigation.1'
        );
    }

    public function test_a_header_part_is_not_a_section_a_plan_can_name()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->patternSection([], 'header')]])]]),
            'pages.0.sections.0.pattern'
        );
    }

    public function test_a_plan_for_another_kit_is_rejected_before_any_unit_is_looked_up()
    {
        try {
            $this->apply($this->plan(['kit' => 'beta']));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-envelope', $e->getStage());
            $this->assertSame('kit', $e->getPath());
        }
    }
}
