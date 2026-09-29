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

    private function applySection(array $content)
    {
        return $this->apply($this->plan([
            'pages' => [$this->page(['sections' => [$this->patternSection($content)]])],
        ]));
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

    private function assertSectionRejected(array $content, $expectedPath = null)
    {
        $this->assertRejected(
            $this->plan(['pages' => [$this->page(['sections' => [$this->patternSection($content)]])]]),
            $expectedPath
        );
    }

    public function test_slot_copy_within_its_limit_passes_and_is_trimmed()
    {
        $result = $this->applySection(['headline' => '  Built to last  ']);

        $this->assertSame('Built to last', $result['pages'][0]['sections'][0]['content']['headline']);
    }

    public function test_slot_copy_over_its_maximum_length_is_rejected()
    {
        $this->assertSectionRejected(
            ['headline' => str_repeat('a', 41)],
            'pages.0.sections.0.content.headline'
        );
    }

    public function test_a_slot_stored_in_a_block_comment_refuses_an_ampersand()
    {
        $this->assertSectionRejected(['label' => 'Design & build'], 'pages.0.sections.0.content.label');
    }

    public function test_a_slot_stored_in_a_block_comment_refuses_angle_brackets()
    {
        $this->assertSectionRejected(['label' => 'a <b> c'], 'pages.0.sections.0.content.label');
    }

    public function test_a_slot_stored_in_a_block_comment_refuses_a_double_hyphen()
    {
        $this->assertSectionRejected(['label' => 'wait -- what'], 'pages.0.sections.0.content.label');
    }

    public function test_a_slot_stored_only_in_markup_accepts_an_ampersand()
    {
        $result = $this->applySection(['headline' => 'Design & build']);

        $this->assertSame('Design & build', $result['pages'][0]['sections'][0]['content']['headline']);
    }

    public function test_a_url_slot_with_a_disallowed_scheme_is_rejected()
    {
        $this->assertSectionRejected(['ctaUrl' => 'javascript:alert(1)'], 'pages.0.sections.0.content.ctaUrl');
    }

    public function test_a_url_slot_accepts_https_and_relative_values()
    {
        foreach (['https://example.com/x', '/about', '#'] as $url) {
            $result = $this->applySection(['ctaUrl' => $url]);

            $this->assertSame($url, $result['pages'][0]['sections'][0]['content']['ctaUrl']);
        }
    }

    public function test_an_internal_page_reference_survives_the_url_check()
    {
        $result = $this->applySection(['ctaUrl' => '{{page:home}}']);

        $this->assertSame('{{page:home}}', $result['pages'][0]['sections'][0]['content']['ctaUrl']);
    }

    public function test_an_array_slot_value_is_rejected()
    {
        $this->assertSectionRejected(['headline' => ['nope']], 'pages.0.sections.0.content.headline');
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

    public function test_an_unknown_style_variation_is_rejected()
    {
        $this->assertRejected(
            $this->plan(['site' => ['frontPage' => 'home', 'navigation' => [], 'styleVariation' => 'Bauhaus']]),
            'site.styleVariation'
        );
    }

    public function test_a_shipped_style_variation_is_accepted_by_title_or_by_slug()
    {
        foreach (['Swiss International', 'swiss-international'] as $title) {
            $result = $this->apply($this->plan([
                'site' => ['frontPage' => 'home', 'navigation' => [], 'styleVariation' => $title],
            ]));

            $this->assertSame($title, $result['site']['styleVariation']);
        }
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
