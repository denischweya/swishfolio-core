<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\PlanAssembler;
use SwishfolioCore\AI\Prompt\PromptManager;

class PlanAssemblerTest extends GenerationTestCase
{
    private function assembler()
    {
        return new PlanAssembler(
            $this->library(),
            $this->registry(),
            $this->tokens(),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')
        );
    }

    private function brief(array $overrides = [])
    {
        return array_merge([
            'instruction'     => 'A studio site for a two person design practice.',
            'siteTitle'       => 'Acme',
            'styleVariation'  => '',
            'styleVariations' => ['Default', 'Swiss International'],
            'customTemplates' => ['page-no-title'],
            'existingSlugs'   => ['home'],
        ], $overrides);
    }

    public function test_the_system_prompt_carries_the_catalogue_but_never_its_markup()
    {
        $request = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o');
        $system  = $request->getSystemPrompt();

        $this->assertStringContainsString('alpha-hero', $system);
        $this->assertStringContainsString('"role": "hero"', $system);
        $this->assertStringNotContainsString('wp:group', $system);
        $this->assertStringNotContainsString('{{slot:', $system);
    }

    public function test_the_system_prompt_carries_the_theme_design_tokens()
    {
        $system = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o')->getSystemPrompt();

        $this->assertStringContainsString('accent-2', $system);
        $this->assertStringContainsString('never literal values', $system);
    }

    public function test_the_system_prompt_only_offers_the_generatable_dynamic_blocks()
    {
        $system = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o')->getSystemPrompt();

        $this->assertStringContainsString('swishfolio-core/portfolio', $system);
        $this->assertStringContainsString('swishfolio-core/latest-posts', $system);
        $this->assertStringNotContainsString('swishfolio-core/hero', $system);
        $this->assertStringNotContainsString('swishfolio-core/swish-form', $system);
    }

    public function test_a_unit_needing_media_is_never_offered_to_the_planner()
    {
        $system = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o')->getSystemPrompt();

        $this->assertStringNotContainsString('needs-media', $system);
    }

    public function test_a_variation_specific_unit_is_offered_only_under_its_variation()
    {
        $assembler = $this->assembler();

        $this->assertStringNotContainsString(
            'swiss-only',
            $assembler->forSitePlan($this->brief(), 'gpt-4o')->getSystemPrompt()
        );

        $this->assertStringContainsString(
            'swiss-only',
            $assembler->forSitePlan($this->brief(['styleVariation' => 'Swiss International']), 'gpt-4o')->getSystemPrompt()
        );
    }

    public function test_the_user_prompt_carries_the_brief_and_leaves_no_placeholder()
    {
        $user = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o')->getUserPrompt();

        $this->assertStringContainsString('two person design practice', $user);
        $this->assertStringContainsString('"existingSlugs"', $user);
        $this->assertSame(0, preg_match('/\{\{[a-z0-9_]+\}\}/i', $user));
    }

    public function test_the_site_request_carries_the_site_schema()
    {
        $schema = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o')->getSchema();

        $this->assertContains('site', $schema['required']);
    }

    public function test_the_page_request_carries_the_single_page_schema_and_page_facts()
    {
        $request = $this->assembler()->forPagePlan(
            $this->brief(['page' => ['title' => 'Services', 'slug' => 'services']]),
            'gpt-4o'
        );

        $this->assertArrayNotHasKey('site', $request->getSchema()['properties']);
        $this->assertStringContainsString('"Services"', $request->getUserPrompt());
        $this->assertSame(0, preg_match('/\{\{[a-z0-9_]+\}\}/i', $request->getUserPrompt()));
    }

    public function test_the_page_system_prompt_is_the_page_one_not_the_site_one()
    {
        $system = $this->assembler()->forPagePlan($this->brief(), 'gpt-4o')->getSystemPrompt();

        $this->assertStringContainsString('You plan a single WordPress page.', $system);
        $this->assertStringContainsString('"maxPages": 1', $system);
    }

    public function test_the_model_and_an_output_budget_are_carried_through()
    {
        $request = $this->assembler()->forSitePlan($this->brief(), 'gpt-4o-mini');

        $this->assertSame('gpt-4o-mini', $request->getModel());
        $this->assertGreaterThan(4096, $request->getMaxTokens());
    }
}
