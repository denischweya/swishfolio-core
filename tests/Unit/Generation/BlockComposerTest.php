<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Generation\BlockComposer;

class BlockComposerTest extends GenerationTestCase
{
    private function composer()
    {
        return new BlockComposer($this->kit());
    }

    /**
     * The fixture section as the validator hands it on: id => copy maps.
     */
    private function section(array $overrides = [], $unitId = 'home-01-hero')
    {
        $section = $this->patternSection($overrides, $unitId);

        foreach (['texts' => 'html', 'attrs' => 'value'] as $key => $field) {
            $map = [];
            foreach (isset($section[$key]) ? $section[$key] : [] as $id => $pair) {
                if (is_array($pair)) {
                    $map[$pair['id']] = $pair[$field];
                } else {
                    $map[$id] = $pair;
                }
            }
            $section[$key] = $map;
        }

        return $section;
    }

    public function test_copy_is_spliced_into_the_kit_markup_and_the_rest_is_kept_byte_for_byte()
    {
        $composed = $this->composer()->composePattern('home-01-hero', $this->section());

        $this->assertStringContainsString('<h1 class="wp-block-heading">Built to last</h1>', $composed);
        $this->assertStringContainsString('<!-- wp:group {"className":"al-hero","layout":{"type":"default"}} -->', $composed);
        $this->assertStringContainsString('<path d="M0 0h10v10z"/>', $composed, 'Inline SVG survives: kit markup is kept verbatim.');
        $this->assertStringContainsString('href="tel:+447700900123"', $composed);
        $this->assertStringContainsString('aria-label="Call Oak and Iron"', $composed);
        $this->assertStringNotContainsString('Demo', $composed);
    }

    public function test_kit_links_resolve_through_the_link_map_or_to_a_hash()
    {
        $composer = $this->composer();

        $mapped = $composer->composePattern('home-01-hero', $this->section(), ['contact' => '{{page:enquire}}']);
        $this->assertStringContainsString('href="{{page:enquire}}">Get a quote</a>', $mapped);

        $unmapped = $composer->composePattern('home-01-hero', $this->section());
        $this->assertStringContainsString('href="#">Get a quote</a>', $unmapped);
    }

    public function test_kit_images_resolve_to_the_shipped_asset()
    {
        $composed = $this->composer()->composePattern('home-01-hero', $this->section());

        $this->assertStringContainsString('src="https://example.test/wp-content/plugins/swishfolio-core/assets/kits/alpha/images/photo.jpg"', $composed);
        $this->assertStringNotContainsString('{{kit-image', $composed);
    }

    public function test_removed_units_are_left_out_and_clones_are_added_after_their_original()
    {
        $composed = $this->composer()->composePattern('home-01-hero', $this->section([
            'remove' => ['u2'],
            'clones' => [['unit' => 'u1', 'texts' => ['t2' => 'Wardrobes'], 'attrs' => []]],
        ]));

        $this->assertStringNotContainsString('Stairs', $composed);
        $this->assertSame(1, substr_count($composed, '<li>Kitchens</li>'));
        $this->assertLessThan(strpos($composed, '<li>Wardrobes</li>'), strpos($composed, '<li>Kitchens</li>'));
    }

    public function test_link_protection_round_trips()
    {
        $html = '<a href="{{link:contact}}">Go</a>';

        $this->assertSame('<a href="#sf-link-contact">Go</a>', BlockComposer::protectLinks($html));
        $this->assertSame($html, BlockComposer::restoreLinks(BlockComposer::protectLinks($html)));
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
            $this->section([], 'contact-01-cta'),
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
}
