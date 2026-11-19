<?php

namespace SwishfolioCore\Tests\Evals;

use SwishfolioCore\Tests\Evals\Support\BlockMarkup;
use SwishfolioCore\Tests\Evals\Support\EvalTestCase;

/**
 * The instrument, tested before it is trusted.
 *
 * "The result parses as valid block markup" is the eval suite's strongest claim
 * about generated content, and it is worth exactly as much as the parser behind
 * it. A parser that never rejects anything turns that assertion into a comment.
 *
 * So this proves the parser refuses each way block markup actually breaks, and
 * that it round-trips the units the plugin really ships — which is also the
 * check that makes the corpus assertions meaningful rather than decorative.
 */
class BlockMarkupTest extends EvalTestCase
{
    /**
     * @return array
     */
    public function malformed()
    {
        return [
            'a block that is never closed' => [
                "<!-- wp:group -->\n<div class=\"wp-block-group\">nothing closes this",
            ],
            'a closing delimiter with nothing open' => [
                "<p>loose</p>\n<!-- /wp:paragraph -->",
            ],
            'a block closed by the wrong name' => [
                "<!-- wp:group -->\n<div><p>x</p></div>\n<!-- /wp:columns -->",
            ],
            'attribute JSON that does not decode' => [
                '<!-- wp:paragraph {"fontSize":} --><p>x</p><!-- /wp:paragraph -->',
            ],
            'an inner block closed at the wrong depth' => [
                "<!-- wp:group -->\n<div><!-- wp:paragraph --><p>x</p></div>\n<!-- /wp:group -->",
            ],
        ];
    }

    /**
     * @dataProvider malformed
     *
     * @param string $markup Markup that must not parse.
     */
    public function test_the_parser_refuses_markup_that_is_not_block_markup($markup)
    {
        $this->expectException(\RuntimeException::class);

        BlockMarkup::parse($markup);
    }

    public function test_it_round_trips_every_kit_section_the_plugin_ships()
    {
        $checked = 0;

        foreach ($this->library()->kits() as $slug => $kit) {
            foreach (array_keys($kit->units('section')) as $id) {
                $trip = BlockMarkup::roundTrip($kit->markup($id));

                $this->assertTrue(
                    $trip['identical'],
                    sprintf('Kit section "%s/%s" does not survive a parse and re-serialise (offset %s).', $slug, $id, var_export($trip['diffAt'], true))
                );

                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(100, $checked, 'Fewer kit sections were found than the plugin ships.');
    }

    public function test_it_reads_back_the_attributes_a_dynamic_block_stores()
    {
        $attributes = [
            'columns'       => 3,
            'showFilters'   => true,
            'viewButtonText' => 'Open the series',
            'taxonomyColor' => 'var(--wp--preset--color--accent-1)',
        ];

        $markup = '<!-- wp:swishfolio-core/portfolio ' . BlockMarkup::serializeAttributes($attributes) . ' /-->';

        // The double hyphens in a design token are escaped on the way in; if that
        // escaping were ever dropped, the comment would terminate early.
        $this->assertStringContainsString('\\u002d\\u002d', $markup);

        $parsed = BlockMarkup::parse($markup);

        $this->assertCount(1, $parsed);
        $this->assertSame('swishfolio-core/portfolio', $parsed[0]['blockName']);
        $this->assertSame($attributes, $parsed[0]['attrs']);
    }

    public function test_it_sees_freeform_html_between_top_level_blocks()
    {
        $markup = "<!-- wp:paragraph -->\n<p>One</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Two</p>\n<!-- /wp:paragraph -->";
        $trip   = BlockMarkup::roundTrip($markup);

        $this->assertTrue($trip['identical']);
        $this->assertSame(
            ['core/paragraph', 'core/paragraph'],
            BlockMarkup::blockNames($trip['blocks']),
            'The blank line between two blocks must not be mistaken for a third.'
        );
    }

    public function test_it_reports_where_a_round_trip_first_diverges()
    {
        $tampered = '<!-- wp:paragraph {"fontSize":  "large"} --><p>x</p><!-- /wp:paragraph -->';
        $trip     = BlockMarkup::roundTrip($tampered);

        $this->assertFalse($trip['identical'], 'Re-serialising must normalise the spacing, so this cannot match.');
        $this->assertIsInt($trip['diffAt']);
    }
}
