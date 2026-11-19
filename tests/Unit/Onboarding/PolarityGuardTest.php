<?php

namespace SwishfolioCore\Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Onboarding\PolarityGuard;
use SwishfolioCore\Tools\Write\SetDesignTokensTool;

class PolarityGuardTest extends TestCase
{
    private function map(array $swaps)
    {
        return array_column($swaps, 'to', 'from');
    }

    public function test_an_accent_only_change_is_left_alone()
    {
        $swaps = [['from' => '#ff3d5a', 'to' => '#39ff14']];

        $this->assertSame($swaps, PolarityGuard::complete($swaps, ['#ffffff', '#15110e', '#f4f0ea'], '#F4F0EA'));
    }

    public function test_a_dark_page_turns_every_neutral_to_the_other_side()
    {
        // The swaps a model made for "dark and minimal with neon green" on a light kit.
        $model = [
            ['from' => '#f4f0ea', 'to' => '#0b0b0c'],
            ['from' => '#15110e', 'to' => '#f2f5f2'],
            ['from' => '#ff3d5a', 'to' => '#39ff14'],
            ['from' => '#ffffff', 'to' => '#141416'],
            ['from' => '#1b1418', 'to' => '#050505'],
        ];
        $used = ['#ff3d5a', '#ffffff', '#f4f0ea', '#15110e', '#1b1418', '#4f4740', '#857a70', '#ffb020'];
        $map  = $this->map(PolarityGuard::complete($model, $used, '#F4F0EA'));

        $this->assertSame('#0b0b0c', $map['#f4f0ea']);
        $this->assertSame('#f2f5f2', $map['#15110e']);
        $this->assertSame('#141416', $map['#ffffff'], 'the model already moved white to the dark side');
        $this->assertSame('#39ff14', $map['#ff3d5a'], 'accents keep the model choice');
        $this->assertArrayNotHasKey('#ffb020', $map, 'an untouched accent stays');

        foreach (['#1b1418', '#4f4740', '#857a70'] as $dark) {
            $this->assertTrue(PolarityGuard::isLight($map[$dark]), $dark . ' moves to the light side');
        }
    }

    public function test_mirroring_keeps_the_hue_and_flips_the_lightness()
    {
        $this->assertSame('#000000', PolarityGuard::mirror('#ffffff'));
        $this->assertFalse(PolarityGuard::isLight(PolarityGuard::mirror('#f4f0ea')));
        $this->assertTrue(PolarityGuard::isLight(PolarityGuard::mirror('#1b1418')));
    }

    public function test_swaps_apply_in_one_pass_and_cover_short_hex_and_rgb()
    {
        $config = ['styles' => ['css' => '.a{color:#fff;background:#15110E}.b{background:rgba(21,17,14,.9);border-color:rgba(255,255,255,.2)}']];
        $out    = SetDesignTokensTool::transform($config, ['colorSwaps' => [
            ['from' => '#15110e', 'to' => '#ffffff'],
            ['from' => '#ffffff', 'to' => '#000000'],
        ]], [], []);

        $css = $out['config']['styles']['css'];
        $this->assertStringContainsString('.a{color:#000000;background:#ffffff}', $css, 'ink became white once, white became black once');
        $this->assertStringContainsString('rgba(255,255,255,.9)', $css);
        $this->assertStringContainsString('rgba(0,0,0,.2)', $css);
    }
}
