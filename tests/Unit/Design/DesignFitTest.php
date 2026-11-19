<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\ColorRemap;
use SwishfolioCore\AI\Design\CssCompiler;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\HeadingSafety;
use SwishfolioCore\AI\Onboarding\CopySlots;
use SwishfolioCore\AI\Onboarding\KitImages;
use SwishfolioCore\AI\Onboarding\WordmarkFit;

/**
 * Long names and flipped designs: headings wrap, wordmarks fit, dark kit
 * bands stay bands under a dark design, scrims stay dark, unsuitable kit
 * photos are recast.
 */
class DesignFitTest extends TestCase
{
    private function dark()
    {
        return DesignTokens::validate([
            'palette' => ['background' => '#0e0e0e', 'surface' => '#1a1a1a', 'text' => '#f5f5f0', 'muted' => '#a0a0a0', 'accent' => '#ff3b1f', 'accent2' => '#ff3b1f', 'soft' => '#222222', 'inverse' => '#f5f5f0'],
            'fonts' => ['display' => 'Inter Tight', 'body' => 'Inter Tight', 'label' => 'JetBrains Mono'],
            'radius' => 'none',
        ]);
    }

    private function compile($kit)
    {
        $t     = $this->dark();
        $remap = new ColorRemap(['base' => '#F3ECE0', 'contrast' => '#1F1410', 'accent-1' => '#E0452B'], '#F3ECE0', '#1F1410', $t['palette']);

        return (new CssCompiler($t, $remap, 'Fraunces', 'Hanken Grotesk', 'lu-', 'https://x.test/p', 'https://x.test/t'))->compile($kit);
    }

    public function test_light_kit_footer_band_stays_a_dark_band_under_a_dark_design()
    {
        $css = $this->compile('body{--bone:#F3ECE0;--ink:#1F1410}.lu-foot{background:var(--ink);color:var(--bone)}.lu-foot__word{color:var(--bone)}.lu-card{background:var(--bone);color:var(--ink)}');

        $this->assertMatchesRegularExpression('/\.lu-foot\{background:var\(--sf-surface,#1a1a1a\);color:#f5f5f0\}/', $css);
        $this->assertStringContainsString('.lu-foot__word{color:#f5f5f0}', $css);
    }

    public function test_dark_scrim_stays_dark_and_mirrored_ghost_title_stays()
    {
        $css = $this->compile('.lu-arch::after{background:linear-gradient(transparent,rgba(18,10,7,.45))}');

        $this->assertStringContainsString('rgba(14,14,14,0.45)', $css);
        $this->assertStringContainsString(':not([class*="title"])', $css);
        $this->assertStringContainsString(HeadingSafety::MARKER, $css);
    }

    public function test_heading_safety_is_added_once()
    {
        $json = (string) json_encode(['version' => 3, 'styles' => ['css' => '.a{b:c}']]);
        $once = HeadingSafety::addTo($json);
        $this->assertStringContainsString('white-space:normal', $once);
        $this->assertSame($once, HeadingSafety::addTo($once));
    }

    public function test_wordmark_fits_any_name_length()
    {
        $css   = '.lu-foot__word{font-size:clamp(5.5rem,36vw,33.5rem);white-space:nowrap}.ch-foot__word{font:400 clamp(6rem,24vw,22rem)/.8 serif;white-space:nowrap}.small{font-size:1rem;white-space:nowrap}@media (max-width:600px){.oz-foot__word{font-size:50vw;white-space:nowrap}}';
        $sizes = WordmarkFit::sizes($css);
        $this->assertSame(['lu-foot__word' => 'clamp(5.5rem,36vw,33.5rem)', 'ch-foot__word' => 'clamp(6rem,24vw,22rem)'], $sizes);

        $html = '<p class="lu-foot__word">Grind Lagos Roasters<span>.</span></p>';
        $out  = WordmarkFit::apply($html, $sizes);
        $this->assertStringContainsString('style="font-size:min(clamp(5.5rem,36vw,33.5rem),calc(min(100vw - 3rem,84rem) / 11.76));--sf-fit:21"', $out);
        // Re-running recomputes, never stacks.
        $again = WordmarkFit::apply(str_replace('Grind Lagos Roasters', 'Grind', $out), $sizes);
        $this->assertSame(1, substr_count($again, 'font-size'));
        $this->assertStringContainsString('--sf-fit:6', $again);
    }

    public function test_unfit_kit_photos_are_recast_without_repeats()
    {
        $slots = [
            ['ref' => '5:i0', 'postId' => 5, 'slot' => 'i0', 'file' => 'octopus.jpg'],
            ['ref' => '5:i1', 'postId' => 5, 'slot' => 'i1', 'file' => 'steak.jpg'],
            ['ref' => '5:i2', 'postId' => 5, 'slot' => 'i2', 'file' => 'wine.jpg'],
            ['ref' => '6:i0', 'postId' => 6, 'slot' => 'i0', 'file' => 'room.jpg'],
        ];
        $decoded = ['unfit' => [
            ['slot' => '5:i0', 'swapTo' => 'bread'],
            ['slot' => '5:i1', 'swapTo' => 'bread'],
            ['slot' => '5:i2', 'swapTo' => 'nonsense'],
            ['slot' => '6:i0', 'swapTo' => 'bread'],
            ['slot' => '9:i9', 'swapTo' => 'bread'],
        ]];
        $placements = [['postId' => 6, 'slot' => 'i0']];

        $this->assertSame([
            ['postId' => 5, 'slot' => 'i0', 'image' => 'bread'],
            ['postId' => 5, 'slot' => 'i1', 'image' => ''],
            ['postId' => 5, 'slot' => 'i2', 'image' => ''],
        ], KitImages::recasts($decoded, $slots, ['bread' => 'Bread'], $placements));
    }

    public function test_a_kit_photo_swap_needs_the_server_flag()
    {
        $html  = '<!-- wp:html --><figure class="lu-arch"><img src="https://x.test/octopus.jpg" alt="Grilled octopus"></figure><!-- /wp:html -->';
        $slots = new CopySlots($html);
        $id    = array_keys(array_filter($slots->slots(), static function ($s) { return $s['type'] === 'image'; }))[0];

        $this->assertSame($html, $slots->apply(['images' => [$id => ['id' => 0, 'url' => 'https://evil.test/x.jpg', 'alt' => 'x']]]));
        $out = $slots->apply(['images' => [$id => ['id' => 0, 'kitAsset' => true, 'url' => 'https://x.test/bread.jpg', 'alt' => 'Hands tearing bread']]]);
        $this->assertStringContainsString('src="https://x.test/bread.jpg" alt="Hands tearing bread"', $out);
    }
}
