<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\Stages\PlanEnvelopeStage;
use SwishfolioCore\AI\Generation\Stages\PlanValueStage;
use SwishfolioCore\Actions\ValidationException;

class PlanValueStageTest extends GenerationTestCase
{
    private function apply(array $plan)
    {
        $context = $this->context();
        $plan    = (new PlanEnvelopeStage())->process($plan, $context);

        return (new PlanValueStage())->process($plan, $context);
    }

    private function applySection(array $texts = [], array $attrs = [])
    {
        return $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection($this->edits($texts, $attrs))]])],
        ]));
    }

    /**
     * The fixture hero's edits with some replaced.
     */
    private function edits(array $texts, array $attrs)
    {
        $merge = function (array $pairs, array $over, $field) {
            foreach ($pairs as $i => $pair) {
                if (array_key_exists($pair['id'], $over)) {
                    $pairs[$i][$field] = $over[$pair['id']];
                }
            }

            return $pairs;
        };

        return [
            'texts' => $merge($this->heroTexts(), $texts, 'html'),
            'attrs' => $merge($this->heroAttrs(), $attrs, 'value'),
        ];
    }

    private function assertRejected(array $plan, $expectedPath = null)
    {
        try {
            $this->apply($plan);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('plan-value', $e->getStage());

            if ($expectedPath !== null) {
                $this->assertSame($expectedPath, $e->getPath());
            }
        }
    }

    private function assertSectionRejected(array $texts, array $attrs, $expectedPath)
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->patternSection($this->edits($texts, $attrs))]])]]),
            $expectedPath
        );
    }

    public function test_slot_copy_within_its_limit_passes_and_is_trimmed()
    {
        $result = $this->applySection(['t0' => '  Built to last  ']);

        $this->assertSame('Built to last', $result['pages'][0]['sections'][0]['texts']['t0']);
    }

    public function test_slot_copy_over_the_maximum_length_is_rejected()
    {
        $this->assertSectionRejected(['t1' => str_repeat('a', 1201)], [], 'pages.0.sections.0.texts.t1');
    }

    public function test_an_empty_slot_is_rejected_in_favour_of_removing_its_unit()
    {
        $this->assertSectionRejected(['t2' => '   '], [], 'pages.0.sections.0.texts.t2');
    }

    public function test_an_input_value_slot_may_be_cleared_but_other_attributes_may_not()
    {
        $check = new \ReflectionMethod(PlanValueStage::class, 'checkValues');
        $check->setAccessible(true);
        $slots = [
            'a1' => ['type' => 'attr', 'attr' => 'value', 'value' => '2026-11-20'],
            'a2' => ['type' => 'attr', 'attr' => 'aria-label', 'value' => 'Table request'],
        ];

        $this->assertSame(['a1' => ''], $check->invoke(new PlanValueStage(), ['a1' => ' '], $slots, 'p'));

        $this->expectException(ValidationException::class);
        $check->invoke(new PlanValueStage(), ['a2' => ''], $slots, 'p');
    }

    public function test_copy_cannot_introduce_placeholder_syntax()
    {
        $this->assertSectionRejected(['t1' => 'See {{kit-image:photo}}'], [], 'pages.0.sections.0.texts.t1');
        $this->assertSectionRejected(['t1' => '<a href="{{link:contact}}">Go</a>'], [], 'pages.0.sections.0.texts.t1');
    }

    public function test_copy_may_keep_the_link_placeholder_its_demo_copy_had()
    {
        $result = $this->applySection();

        $this->assertStringContainsString('{{link:contact}}', $result['pages'][0]['sections'][0]['texts']['t4']);
    }

    public function test_a_demo_link_may_point_at_another_template_page_but_not_multiply_or_leave_the_kit()
    {
        $result = $this->applySection(['t4' => '<a class="btn" href="{{link:home}}">Back home</a>']);
        $this->assertStringContainsString('{{link:home}}', $result['pages'][0]['sections'][0]['texts']['t4']);

        $this->assertSectionRejected(['t4' => '<a href="{{link:nowhere}}">Go</a>'], [], 'pages.0.sections.0.texts.t4');
        $this->assertSectionRejected(['t4' => '<a href="{{link:home}}">A</a><a href="{{link:contact}}">B</a>'], [], 'pages.0.sections.0.texts.t4');
    }

    public function test_a_phone_link_slot_must_stay_a_tel_or_mailto_address()
    {
        $this->assertSectionRejected([], ['a0' => 'https://evil.test'], 'pages.0.sections.0.attrs.a0');

        $result = $this->applySection([], ['a0' => 'mailto:hello@oak.test']);
        $this->assertSame('mailto:hello@oak.test', $result['pages'][0]['sections'][0]['attrs']['a0']);
    }

    public function test_a_link_in_copy_cannot_use_a_script_scheme()
    {
        $this->assertSectionRejected(
            ['t4' => '<a class="wp-block-button__link" href="javascript:alert(1)">Go</a>'],
            [],
            'pages.0.sections.0.texts.t4'
        );
    }

    public function test_an_array_slot_value_is_rejected()
    {
        try {
            $this->applySection(['t0' => ['nope']]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('pages.0.sections.0.texts.t0', $e->getPath());
        }
    }

    public function test_block_attributes_go_through_the_existing_action_pipeline()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->blockSection(['columns' => '3'])]])],
        ]));

        $this->assertSame(3.0, $result['pages'][0]['sections'][0]['attributes']['columns']);
    }

    public function test_a_design_token_slug_is_expanded_by_the_reused_pipeline()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->blockSection(['taxonomyColor' => 'accent-2'])]])],
        ]));

        $this->assertSame(
            'var(--wp--preset--color--accent-2)',
            $result['pages'][0]['sections'][0]['attributes']['taxonomyColor']
        );
    }

    public function test_an_attribute_absent_from_the_manifest_is_rejected_with_a_plan_path()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->blockSection(['featuredProjectId' => 4])]])]]),
            'pages.0.sections.0.attributes.featuredProjectId'
        );
    }

    public function test_an_out_of_range_attribute_is_rejected_with_a_plan_path()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->blockSection(['columns' => 99])]])]]),
            'pages.0.sections.0.attributes.columns'
        );
    }

    public function test_a_literal_colour_for_a_token_attribute_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->blockSection(['taxonomyColor' => '#FF6B6B'])]])]]),
            'pages.0.sections.0.attributes.taxonomyColor'
        );
    }

    public function test_the_failing_section_is_identified_when_several_carry_attributes()
    {
        $page = $this->page([
            'sections' => [
                $this->blockSection(['columns' => 2]),
                $this->patternSection(),
                $this->blockSection(['columns' => 99]),
            ],
        ]);

        $this->assertRejected($this->plan(['pages' => [$page]]), 'pages.0.sections.2.attributes.columns');
    }

    public function test_a_block_attribute_containing_an_ampersand_is_refused()
    {
        $this->assertRejected(
            $this->plan([
                'pages' => [$this->page(['sections' => [$this->blockSection(['viewButtonText' => 'Read & react'])]])],
            ]),
            'pages.0.sections.0.attributes.viewButtonText'
        );
    }

    public function test_a_block_attribute_containing_a_closing_angle_bracket_is_refused()
    {
        $this->assertRejected(
            $this->plan([
                'pages' => [$this->page(['sections' => [$this->blockSection(['viewButtonText' => 'Read > react'])]])],
            ]),
            'pages.0.sections.0.attributes.viewButtonText'
        );
    }

    public function test_an_expanded_design_token_is_not_mistaken_for_an_unsafe_attribute()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->blockSection(['taxonomyColor' => 'accent-1'])]])],
        ]));

        $this->assertSame(
            'var(--wp--preset--color--accent-1)',
            $result['pages'][0]['sections'][0]['attributes']['taxonomyColor']
        );
    }

    public function test_ordinary_punctuation_in_a_block_attribute_is_accepted()
    {
        $result = $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->blockSection(['viewButtonText' => "Sasha's work — see it"])]])],
        ]));

        $this->assertSame(
            "Sasha's work — see it",
            $result['pages'][0]['sections'][0]['attributes']['viewButtonText']
        );
    }
}
