<?php

namespace SwishfolioCore\Tests\Unit\Copy;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Copy\Dashes;

/**
 * House style: AI-written copy never keeps an em dash or a spaced dash.
 */
class DashesTest extends TestCase
{
    /**
     * @dataProvider plainCases
     */
    public function test_plain_text_is_rewritten_by_context($in, $out)
    {
        $this->assertSame($out, Dashes::text($in));
        $this->assertFalse(Dashes::has(Dashes::text($in)));
    }

    public static function plainCases()
    {
        return [
            'clause before a capital'   => ['We roast every bean in Lekki — Come and taste it.', 'We roast every bean in Lekki. Come and taste it.'],
            'clause, lowercase'         => ['We roast every bean in small batches — always by hand.', 'We roast every bean in small batches, always by hand.'],
            'short label'               => ['Tuesday — closed', 'Tuesday: closed'],
            'aside pair'                => ['Our beans — all single origin — are roasted weekly.', 'Our beans, all single origin, are roasted weekly.'],
            'unspaced em dash'          => ['We serve fresh coffee every morning—no exceptions', 'We serve fresh coffee every morning, no exceptions'],
            'spaced en dash'            => ['Espresso and pour-over – made to order', 'Espresso and pour-over: made to order'],
            'spaced hyphen'             => ['Roasted in Lekki - brewed by hand', 'Roasted in Lekki: brewed by hand'],
            'double hyphen'             => ['Cold brew -- steeped overnight', 'Cold brew: steeped overnight'],
            'leading attribution'       => ['— Ada Obi', 'Ada Obi'],
            'trailing dash'             => ['Open daily —', 'Open daily'],
            'spaced time range'         => ['Open 9:00 – 17:00', 'Open 9:00–17:00'],
            'price range'               => ['From ₦2,500 - ₦4,000', 'From ₦2,500–₦4,000'],
            'day range'                 => ['Mon — Fri', 'Mon–Fri'],
            'after punctuation'         => ['Small batches. — Big flavour', 'Small batches. Big flavour'],
            'entity'                    => ['Roasted daily &mdash; brewed by hand', 'Roasted daily: brewed by hand'],
        ];
    }

    public function test_ranges_and_compounds_stay()
    {
        foreach (['Open Tue–Sun', '9:00–12:00', 'well-known roasters', 'pour-over', 'Mon-Fri'] as $ok) {
            $this->assertSame($ok, Dashes::text($ok));
            $this->assertFalse(Dashes::has($ok));
        }
    }

    public function test_links_are_not_copy()
    {
        $this->assertSame('mailto:a--b@example.org', Dashes::text('mailto:a--b@example.org'));
        $this->assertSame('tel:+2348012345678', Dashes::text('tel:+2348012345678'));
    }

    public function test_html_only_changes_text_and_reads_across_inline_tags()
    {
        $this->assertSame('<em>Fresh</em>: always <a href="/x--y" class="a--b">roasted</a>', Dashes::html('<em>Fresh</em> — always <a href="/x--y" class="a--b">roasted</a>'));
        $this->assertSame('Roasted here<br>poured fresh.', Dashes::html('Roasted here —<br>poured fresh.'));
        $this->assertSame('<span class="k">Hours</span>: 7am–6pm', Dashes::html('<span class="k">Hours</span> — 7am – 6pm'));
        $this->assertSame('No dashes here', Dashes::html('No dashes here'));
    }

    public function test_never_leaves_a_spaced_hyphen()
    {
        $samples = ['A - b - c - d', 'one — two — three — four', 'x – y', 'The menu is short - on purpose - and the beans rotate.'];
        foreach ($samples as $s) {
            $fixed = Dashes::text($s);
            $this->assertStringNotContainsString(' - ', $fixed, $s);
            $this->assertStringNotContainsString('—', $fixed, $s);
            $this->assertFalse(Dashes::has($fixed), $fixed);
        }
    }

    public function test_plan_and_edits()
    {
        $plan = [
            'summary' => 'A coffee site — four pages.',
            'site'    => ['title' => 'Grind', 'tagline' => 'Roasted — brewed'],
            'pages'   => [[
                'key' => 'home', 'title' => 'Home — Grind', 'slug' => 'home--x',
                'sections' => [[
                    'pattern' => 'restaurant-home--hero',
                    'texts'   => ['t1' => 'Roasted in Lekki — brewed by hand'],
                    'attrs'   => ['a1' => 'Call us — now', 'a2' => 'tel:+234--1'],
                    'clones'  => [['unit' => 'u1', 'texts' => ['t3' => 'Latte — milky']]],
                ]],
            ]],
        ];
        $out = Dashes::plan($plan);
        $this->assertSame('A coffee site: four pages.', $out['summary']);
        $this->assertSame('Roasted: brewed', $out['site']['tagline']);
        $this->assertSame('Home: Grind', $out['pages'][0]['title']);
        $this->assertSame('home--x', $out['pages'][0]['slug']);
        $this->assertSame('restaurant-home--hero', $out['pages'][0]['sections'][0]['pattern']);
        $this->assertSame('Roasted in Lekki: brewed by hand', $out['pages'][0]['sections'][0]['texts']['t1']);
        $this->assertSame('tel:+234--1', $out['pages'][0]['sections'][0]['attrs']['a2']);
        $this->assertSame('Latte: milky', $out['pages'][0]['sections'][0]['clones'][0]['texts']['t3']);

        $edits = Dashes::edits(['texts' => [['id' => 't1', 'html' => 'A — b']], 'images' => ['i0' => ['attachmentId' => 3, 'alt' => 'Beans — roasted']]]);
        $this->assertSame('A: b', $edits['texts'][0]['html']);
        $this->assertSame('Beans: roasted', $edits['images']['i0']['alt']);
    }
}
