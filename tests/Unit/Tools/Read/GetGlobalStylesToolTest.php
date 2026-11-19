<?php

namespace SwishfolioCore\Tests\Unit\Tools\Read;

use SwishfolioCore\AI\Context\TokenResolver;
use SwishfolioCore\Tests\Unit\Tools\ToolsTestCase;
use SwishfolioCore\Tools\Read\GetGlobalStylesTool;

/**
 * This tool's answer is what every colour, spacing and font-size attribute the
 * AI writes has to come from: those fields carry theme.json slugs, never
 * literals, because the theme's variations re-skin the palette and a literal
 * would survive the switch as a wrong colour. A tool that reported the wrong
 * vocabulary would produce attributes the validator rejects — or worse, ones it
 * accepts that render wrongly.
 *
 * Tokens come from the committed copy of the theme's own theme.json, the same
 * fixture the evals use, so "a real slug" means what it means on a real site.
 */
class GetGlobalStylesToolTest extends ToolsTestCase
{
    private function tokens()
    {
        return TokenResolver::fromArray(
            json_decode(file_get_contents(SFCORE_PLUGIN_DIR . 'tests/fixtures/theme.json'), true)
        );
    }

    private function tool()
    {
        return new GetGlobalStylesTool(
            function () {
                return [
                    ['title' => 'Default', 'version' => 3],
                    ['title' => 'Swiss International', 'version' => 3],
                ];
            },
            function () {
                return 23;
            },
            $this->tokens()
        );
    }

    public function test_it_reports_the_token_vocabulary_the_ai_must_write_with()
    {
        $result = $this->tool()->execute([], $this->context());

        foreach (['color', 'spacing', 'fontSize'] as $set) {
            $this->assertArrayHasKey($set, $result['tokens']);
            $this->assertNotEmpty($result['tokens'][$set], $set . ' has no slugs, so the AI has nothing legal to write.');
        }
    }

    public function test_the_slugs_are_slugs_rather_than_literal_values()
    {
        $tokens = $this->tool()->execute([], $this->context())['tokens'];

        foreach ($tokens['color'] as $slug) {
            $this->assertStringNotContainsString('#', $slug, 'A palette slug must not be a hex value.');
            $this->assertStringNotContainsString('var(', $slug, 'A palette slug must not be a CSS variable.');
        }

        foreach ($tokens['spacing'] as $slug) {
            $this->assertStringNotContainsString('px', $slug, 'A spacing slug must not carry a unit.');
            $this->assertStringNotContainsString('rem', $slug);
        }
    }

    public function test_it_reports_the_variations_the_theme_ships()
    {
        $result = $this->tool()->execute([], $this->context());

        $this->assertSame(['Default', 'Swiss International'], $result['styleVariations']);
    }

    public function test_it_reports_the_global_styles_post_so_a_rollback_has_a_target()
    {
        $this->assertSame(23, $this->tool()->execute([], $this->context())['globalStylesPost']);
    }

    public function test_the_active_variation_is_null_rather_than_guessed_when_the_theme_cannot_say()
    {
        // swishfolio_get_active_style_variation_slug() is the theme's, so it is
        // absent here. Reporting null is honest; inferring one by diffing the
        // saved palette against every shipped variation would be a guess the
        // AI would then act on.
        $this->assertNull($this->tool()->execute([], $this->context())['activeVariation']);
    }
}
