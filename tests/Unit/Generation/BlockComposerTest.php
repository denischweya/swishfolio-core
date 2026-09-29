<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\BlockComposer;

class BlockComposerTest extends GenerationTestCase
{
    private function composer()
    {
        return new BlockComposer($this->library());
    }

    public function test_a_filled_slot_replaces_its_token_everywhere_it_occurs()
    {
        $composed = $this->composer()->composePattern('alpha-hero', [
            'label'    => 'Opening',
            'headline' => 'Built to last',
        ]);

        $this->assertStringContainsString('"name":"Opening"', $composed);
        $this->assertStringContainsString('<h2 class="wp-block-heading">Built to last</h2>', $composed);
        $this->assertStringNotContainsString('{{slot:', $composed);
    }

    public function test_an_unfilled_slot_falls_back_to_its_declared_default()
    {
        $composed = $this->composer()->composePattern('alpha-hero', ['headline' => 'Only this']);

        $this->assertStringContainsString('Fixture body copy.', $composed);
        $this->assertStringContainsString('href="#"', $composed);
    }

    public function test_a_page_unit_is_composed_verbatim()
    {
        $this->assertSame(
            self::FAKE_PATTERN_MARKUP,
            $this->composer()->composePattern('alpha-page', [])
        );
    }

    public function test_a_dynamic_block_is_serialised_as_a_self_closing_comment()
    {
        $composed = $this->composer()->composeBlock('swishfolio-core/portfolio', ['columns' => 3, 'layout' => 'grid']);

        $this->assertSame(
            '<!-- wp:swishfolio-core/portfolio {"columns":3,"layout":"grid"} /-->',
            $composed
        );
    }

    public function test_block_attributes_that_would_break_a_comment_are_escaped_by_core()
    {
        $composed = $this->composer()->composeBlock('swishfolio-core/portfolio', [
            'viewButtonText' => 'Read & react -- now',
        ]);

        // serialize_block_attributes() escapes exactly the characters that would
        // corrupt a block comment, so an attribute value never needs policing.
        $this->assertStringNotContainsString('&', $composed);
        $this->assertStringNotContainsString('-- now', $composed);
        $this->assertStringContainsString('\\u0026', $composed);
        $this->assertStringContainsString('\\u002d\\u002d', $composed);
    }

    public function test_sections_are_joined_with_a_blank_line()
    {
        $composed = $this->composer()->compose([
            $this->patternSection(['headline' => 'One']),
            $this->blockSection(['columns' => 2]),
        ]);

        $this->assertStringContainsString("<!-- /wp:group -->\n\n<!-- wp:swishfolio-core/portfolio", $composed);
    }

    public function test_composing_nothing_produces_nothing()
    {
        $this->assertSame('', $this->composer()->compose([]));
    }

    public function test_an_unknown_unit_raises_instead_of_composing_an_empty_section()
    {
        $this->expectException(\RuntimeException::class);
        $this->composer()->composePattern('no-such-unit', []);
    }

    public function test_an_undeclared_token_left_in_markup_raises()
    {
        $library = new \SwishfolioCore\AI\Generation\PatternLibrary(
            [$this->fixtureDir()],
            function ($ref) {
                return '<!-- wp:paragraph --><p>{{slot:ghost}}</p><!-- /wp:paragraph -->';
            }
        );

        $this->expectException(\RuntimeException::class);
        (new BlockComposer($library))->composePattern('alpha-page', []);
    }
}
