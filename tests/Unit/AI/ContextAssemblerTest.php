<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\ContextAssembler;
use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Actions\ActionSchema;
use SwishfolioCore\Blocks\BlockRegistry;

class ContextAssemblerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_transients'] = [];
    }

    private function assembler(array $brand = [])
    {
        return new ContextAssembler(
            new BlockRegistry([
                SFCORE_PLUGIN_DIR . 'blocks',
                SFCORE_PLUGIN_DIR . 'src/AI/Manifests/core',
            ]),
            TokenResolver::fromArray(
                json_decode(file_get_contents(SFCORE_PLUGIN_DIR . 'tests/fixtures/theme.json'), true)
            ),
            new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt'),
            $brand
        );
    }

    private function selection()
    {
        return [
            'clientId'    => 'hero-1',
            'blockName'   => 'swishfolio-core/hero',
            'attributes'  => ['heading' => 'Welcome', 'subheading' => 'We build things'],
            'innerBlocks' => [],
            'parent'      => null,
            'siblings'    => [
                ['clientId' => 'tst-1', 'blockName' => 'swishfolio-core/testimonials',
                 'attributes' => ['columns' => 3, 'secretSauce' => 'SIBLING_ATTRS']],
            ],
            'postType'       => 'page',
            'styleVariation' => 'neo-brutalism',
        ];
    }

    public function test_returns_an_ai_request_carrying_the_action_schema()
    {
        $request = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o');

        $this->assertSame('gpt-4o', $request->getModel());
        $this->assertSame([ActionSchema::SCHEMA_VERSION], $request->getSchema()['properties']['schemaVersion']['enum']);
    }

    public function test_selected_block_attributes_are_sent_in_full()
    {
        $request = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o');

        $this->assertStringContainsString('We build things', $request->getUserPrompt());
    }

    public function test_sibling_attributes_are_never_sent()
    {
        $request = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o');
        $whole   = $request->getSystemPrompt() . $request->getUserPrompt();

        $this->assertStringNotContainsString('SIBLING_ATTRS', $whole);
        $this->assertStringContainsString('swishfolio-core/testimonials', $whole);
    }

    public function test_only_manifests_for_blocks_in_play_are_included()
    {
        $system = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o')->getSystemPrompt();

        $this->assertStringContainsString('"heading"', $system, 'Hero manifest should be present.');
        $this->assertStringNotContainsString('swishfolio-core/swish-gallery', $system, 'Unrelated manifests must not be sent.');
    }

    public function test_design_tokens_are_listed_for_the_active_theme()
    {
        $system = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o')->getSystemPrompt();

        $this->assertStringContainsString('accent-1', $system);
        $this->assertStringNotContainsString('accent-9', $system);
    }

    public function test_system_prompt_is_byte_stable_across_instructions()
    {
        $a = $this->assembler()->forSelection($this->selection(), 'Make it bolder', 'gpt-4o')->getSystemPrompt();
        $b = $this->assembler()->forSelection($this->selection(), 'Something else entirely', 'gpt-4o')->getSystemPrompt();

        $this->assertSame($a, $b, 'A volatile system prefix would destroy prompt caching.');
    }

    public function test_the_instruction_reaches_the_user_prompt()
    {
        $request = $this->assembler()->forSelection($this->selection(), 'Make it feel premium', 'gpt-4o');

        $this->assertStringContainsString('Make it feel premium', $request->getUserPrompt());
    }

    public function test_brand_context_reaches_the_cache_stable_system_prompt()
    {
        $request = $this->assembler([
            'siteName'        => 'Ramanops',
            'siteDescription' => 'We help Nairobi SMEs grow online.',
            'designStyle'     => 'minimalist',
            'blockShapes'     => 'soft',
            'copywritingTone' => 'professional',
        ])->forSelection($this->selection(), 'Make it bolder', 'gpt-4o');

        $system = $request->getSystemPrompt();

        $this->assertStringContainsString('Ramanops', $system);
        $this->assertStringContainsString('Nairobi SMEs', $system);
        $this->assertStringContainsString('minimalist', $system);
        $this->assertStringContainsString('professional', $system);

        // Brand belongs in the prefix, not the volatile half — it changes far
        // less often than the selection does.
        $this->assertStringNotContainsString('Ramanops', $request->getUserPrompt());
    }

    public function test_empty_brand_fields_are_omitted_rather_than_sent_blank()
    {
        $system = $this->assembler(['siteName' => 'Ramanops'])
            ->forSelection($this->selection(), 'Make it bolder', 'gpt-4o')
            ->getSystemPrompt();

        $this->assertStringContainsString('Ramanops', $system);
        $this->assertStringNotContainsString('designStyle', $system);
        $this->assertStringNotContainsString('copywritingTone', $system);
    }

    public function test_no_brand_section_appears_when_nothing_is_configured()
    {
        $system = $this->assembler()
            ->forSelection($this->selection(), 'Make it bolder', 'gpt-4o')
            ->getSystemPrompt();

        $this->assertStringNotContainsString('About this business', $system);
    }

    public function test_unknown_block_type_raises()
    {
        $selection              = $this->selection();
        $selection['blockName'] = 'acme/widget';

        $this->expectException(\RuntimeException::class);
        $this->assembler()->forSelection($selection, 'Do something', 'gpt-4o');
    }
}
