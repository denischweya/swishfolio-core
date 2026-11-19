<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

require_once SFCORE_PLUGIN_DIR . 'tests/Unit/Tools/wp-tools-stubs.php';

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\CopySlots;

/**
 * Words drawn inside an inline SVG (a badge ring) are slots too, so a build
 * can rewrite them instead of publishing the demo's.
 */
class CopySlotsSvgTextTest extends TestCase
{
    const BADGE = <<<'HTML'
<!-- wp:html -->
<p class="x-lead">Book a table tonight</p>
<a class="lu-badge" href="#" aria-label="Book a table"><svg class="lu-badge__ring" viewBox="0 0 160 160" aria-hidden="true"><defs><path id="r" d="M80,80 m-61,0 a61,61 0 1,1 122,0"/></defs><text><textPath href="#r" textLength="380" lengthAdjust="spacing">Open Tue–Sun · Reservations · Walk-ins at the counter · </textPath></text></svg></a>
<p class="x-after">Last orders 10pm</p>
<!-- /wp:html -->
HTML;

    public function test_drawn_text_is_a_slot_numbered_after_the_rest()
    {
        $copy  = new CopySlots(self::BADGE);
        $slots = $copy->slots();

        $this->assertSame('Book a table tonight', $slots['t0']['value']);
        $this->assertSame('Last orders 10pm', $slots['t1']['value']);
        $this->assertTrue($slots['t2']['svg']);
        $this->assertStringContainsString('Walk-ins at the counter', $slots['t2']['value']);
        $this->assertStringContainsString('textPath in a drawing', $copy->outline());
    }

    public function test_short_ring_text_is_repeated_round_the_ring_as_plain_text()
    {
        $copy = new CopySlots(self::BADGE, static function ($h) {
            return $h;
        });
        $out = $copy->apply(['texts' => ['t2' => '<b>Grind</b> Lagos']]);

        $this->assertStringNotContainsString('Walk-ins', $out);
        $this->assertStringNotContainsString('<b>', $out);
        $this->assertMatchesRegularExpression('#textLength="380" lengthAdjust="spacing">Grind Lagos · Grind Lagos · (Grind Lagos · )*</textPath>#u', $out);
        $this->assertStringContainsString('<p class="x-after">Last orders 10pm</p>', $out);
    }

    public function test_long_ring_text_is_cut_at_a_word_and_escaped()
    {
        $long = CopySlots::svgText('Espresso <script>x</script>& pour-over, cold brew, beans to take home, roasted every single morning in Lekki by hand', 'Oak · Applewood · Ash · ');

        $this->assertStringNotContainsString('<', $long);
        $this->assertStringContainsString('&amp;', $long);
        $this->assertLessThanOrEqual((int) ceil(mb_strlen('Oak · Applewood · Ash · ') * 1.3) + 6, mb_strlen(html_entity_decode($long)));
    }
}
