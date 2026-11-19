<?php

namespace SwishfolioCore\Tests\Unit\Generation;

use SwishfolioCore\AI\Design\HeadingSafety;
use SwishfolioCore\AI\Generation\BlockComposer;
use SwishfolioCore\AI\Generation\HeadlineFit;

/**
 * A title longer than the demo's is drawn smaller, and a decorative copy of
 * it says, wraps and scales exactly as it does.
 */
class HeadlineFitTest extends GenerationTestCase
{
    public function test_a_title_near_the_demo_length_keeps_the_kit_size()
    {
        $demo = 'Cooked over<br><em>embers,</em> eaten slowly.';

        $this->assertSame(100, HeadlineFit::scale($demo, 'Slow fires, <em>long</em> lunches.'));
        $this->assertSame(100, HeadlineFit::scale($demo, 'Grown on the plateau, poured.'));
    }

    public function test_a_longer_title_scales_by_the_square_root_in_steps_with_a_floor()
    {
        $demo = 'Cooked over<br><em>embers,</em> eaten slowly.';

        $this->assertSame(95, HeadlineFit::scale($demo, 'Grown on the <em>plateau,</em> poured in Wuse 2.'));
        $this->assertSame(60, HeadlineFit::scale($demo, str_repeat('Fresh coffee and warm tables. ', 8)));
        $this->assertSame(0, HeadlineFit::scale($demo, 'Grown on the plateau, poured in Wuse 2, served all day.') % 5);
    }

    public function test_wrap_is_idempotent_and_skipped_at_full_size_unless_mirrored()
    {
        $this->assertSame('Short', HeadlineFit::wrap('Short', 100));
        $this->assertSame('<span class="sf-fit">Short</span>', HeadlineFit::wrap('Short', 100, true));

        $once = HeadlineFit::wrap('A <em>long</em> title', 80);
        $this->assertSame('<span class="sf-fit sf-fit-80">A <em>long</em> title</span>', $once);
        $this->assertSame('<span class="sf-fit sf-fit-70">A <em>long</em> title</span>', HeadlineFit::wrap($once, 70));
    }

    public function test_the_ghost_title_gets_the_very_same_fitted_copy()
    {
        $kit     = $this->shippedLibrary()->kit('restaurant');
        $mirrors = $kit->mirrorSlots('home-01-hero');
        $this->assertNotEmpty($mirrors);

        $heading  = reset($mirrors);
        $title    = 'Grown on the <em>plateau,</em> poured in Wuse 2, every single morning.';
        $composed = (new BlockComposer($kit))->composePattern('home-01-hero', ['texts' => [$heading => $title], 'attrs' => [], 'remove' => [], 'clones' => []]);

        $fitted = '<span class="sf-fit sf-fit-' . HeadlineFit::scale((string) $kit->slots('home-01-hero')->slots()[$heading]['value'], $title) . '">' . $title . '</span>';
        $this->assertStringContainsString('lu-hero__title">' . $fitted . '</h1>', $composed);
        $this->assertStringContainsString('aria-hidden="true">' . $fitted . '</div>', $composed);
    }

    public function test_a_mirrored_title_is_wrapped_even_at_full_size_so_both_wrap_alike()
    {
        $kit      = $this->shippedLibrary()->kit('restaurant');
        $mirrors  = $kit->mirrorSlots('home-01-hero');
        $heading  = reset($mirrors);
        $composed = (new BlockComposer($kit))->composePattern('home-01-hero', ['texts' => [$heading => 'Slow fires, long lunches.'], 'attrs' => [], 'remove' => [], 'clones' => []]);

        $this->assertSame(2, substr_count($composed, '<span class="sf-fit">Slow fires, long lunches.</span>'));
    }

    public function test_the_planner_is_told_the_title_length_and_to_leave_the_copy()
    {
        $kit    = $this->shippedLibrary()->kit('restaurant');
        $notes  = $kit->slotNotes('home-01-hero');
        $mirror = array_keys($kit->mirrorSlots('home-01-hero'))[0];

        $this->assertContains('about 33 characters, keep it close to that', $notes);
        $this->assertStringContainsString('filled in for you', $notes[$mirror]);
        $this->assertStringContainsString('(about 33 characters', $kit->catalogue());
    }

    public function test_heading_safety_carries_the_fit_and_ghost_rules()
    {
        $css = HeadingSafety::css();

        $this->assertStringContainsString('body .sf-fit{display:block;white-space:normal!important', $css);
        $this->assertStringContainsString('body .sf-fit-60{zoom:0.6}', $css);
        $this->assertStringContainsString('body [aria-hidden="true"][class*="ghost"]', $css);
        $this->assertStringNotContainsString(':is(h1,h2,h3,h4,h5,h6,', $css, 'Headings keep their low specificity.');
    }
}
