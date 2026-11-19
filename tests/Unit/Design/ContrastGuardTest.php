<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\Color;
use SwishfolioCore\AI\Design\ContrastGuard;
use SwishfolioCore\AI\Design\DesignCompiler;
use SwishfolioCore\AI\Design\DesignTokens;

class ContrastGuardTest extends TestCase
{
    public function testContrastIsTheWorstOverEveryStop()
    {
        $this->assertEqualsWithDelta(21.0, ContrastGuard::contrast('#000000', ['#ffffff']), 0.01);
        $this->assertEqualsWithDelta(Color::contrast('#ffffff', '#777777'), ContrastGuard::contrast('#ffffff', ['#000000', '#777777']), 0.001);
    }

    public function testFixKeepsAPassingColour()
    {
        $this->assertSame('#ffffff', ContrastGuard::fix('#ffffff', ['#3b1030']));
    }

    public function testFixNudgesPinkOnPlumToAA()
    {
        $fixed = ContrastGuard::fix('#c2407f', ['#3b1838', '#5a1f4c', '#7e1f4f']);

        $this->assertNotSame('#c2407f', $fixed);
        $this->assertGreaterThanOrEqual(Color::AA, ContrastGuard::contrast($fixed, ['#3b1838', '#5a1f4c', '#7e1f4f']));
        // Lighter, still pink (hue kept rather than jumping to white).
        $this->assertGreaterThan(Color::lightness('#c2407f'), Color::lightness($fixed));
        $this->assertGreaterThan(0.03, Color::chroma($fixed));
    }

    public function testFixLargeTextNeedsLess()
    {
        $bg = ['#ffffff'];
        $this->assertSame('#888888', ContrastGuard::fix('#888888', $bg, Color::AA_LARGE));
        $this->assertGreaterThanOrEqual(Color::AA, ContrastGuard::contrast(ContrastGuard::fix('#888888', $bg), $bg));
    }

    public function testFixOnAMidGreyGradientFallsBackToAPole()
    {
        $bgs   = ['#000000', '#ffffff'];
        $fixed = ContrastGuard::fix('#777777', $bgs);
        $this->assertContains($fixed, ['#000000', '#ffffff']);
    }

    private function pink(array $extra = [])
    {
        return DesignTokens::validate(array_merge([
            'links'   => 'accent',
            'palette' => ['background' => '#fdf2f7', 'surface' => '#f8e1ec', 'text' => '#3b1030', 'muted' => '#7a4a66', 'accent' => '#c2407f', 'accent2' => '#a98be0', 'soft' => '#f6d6e6', 'inverse' => '#4a1740'],
        ], $extra));
    }

    public function testCtaBandEmailLinkGetsTheBandsLinkColour()
    {
        // The Rosewater case: the band's own link rule (cream) loses to the
        // page-wide accent link rule, giving pink on plum.
        $kit = '.ym-cta__card{background:linear-gradient(135deg,#3B1838 0%,#5A1F4C 55%,#7E1F4F 100%);color:#fdf2f7}'
            . '.ym-cta__direct a{color:#fdf2f7;text-decoration:underline}';
        $css = $kit . 'body main :is(p,li) a:not(.wp-block-button__link){color:var(--sf-accent)}body{--sf-accent:#c2407f}';

        $out = ContrastGuard::css($css, $this->pink());

        $this->assertStringContainsString(':root .ym-cta__direct a:not(.wp-block-button__link):not(.wp-element-button){color:#fdf2f7}', $out);
        $this->assertStringContainsString(':root .ym-cta__card :is(p,li) a:not(.wp-block-button__link):not(.wp-element-button){color:#fdf2f7}', $out);
    }

    public function testFailingTextInsideABandIsNudged()
    {
        $css = '.oz-band{background-color:#4a1740;color:#fdf2f7}.oz-band .oz-eyebrow{color:#c2407f}.oz-band h2{color:#c2407f}';
        $out = ContrastGuard::css($css, $this->pink());

        $this->assertMatchesRegularExpression('/:root \.oz-band \.oz-eyebrow\{color:(#[0-9a-f]{6})\}/', $out);
        preg_match('/:root \.oz-band \.oz-eyebrow\{color:(#[0-9a-f]{6})\}/', $out, $m);
        $this->assertGreaterThanOrEqual(Color::AA, Color::contrast($m[1], '#4a1740'));
        // A heading only needs 3:1 (large text), checked as such.
        if (Color::contrast('#c2407f', '#4a1740') >= Color::AA_LARGE) {
            $this->assertStringNotContainsString('.oz-band h2{', $out);
        } else {
            $this->assertMatchesRegularExpression('/:root \.oz-band h2\{color:#[0-9a-f]{6}\}/', $out);
        }
    }

    public function testVariablesResolveAndPassingPairsAddNothing()
    {
        $css = 'body{--ink:#111111;--paper:#ffffff}.oz-card{background:var(--paper)}.oz-card .oz-t{color:var(--ink)}';
        $this->assertSame('', ContrastGuard::css($css, $this->pink(['links' => 'plain'])));
    }

    public function testSeeThroughBackgroundsPhotosAndHoverAreIgnored()
    {
        $css = '.oz-a{background:rgba(0,0,0,.4)}.oz-a p{color:#555555}'
            . '.oz-b{background:#000 url(x.jpg)}.oz-b p{color:#111111}'
            . '.oz-c{background:#000000}.oz-c a:hover{color:#111111}';
        $this->assertSame('', ContrastGuard::css($css, $this->pink(['links' => 'plain'])));
    }

    public function testCompiledDesignCarriesTheGuard()
    {
        $design = ['globalStyles' => [
            'settings' => ['color' => ['palette' => ['theme' => [['slug' => 'base', 'color' => '#fff8f6'], ['slug' => 'contrast', 'color' => '#2a1a22'], ['slug' => 'accent-1', 'color' => '#e8467c']]]]],
            'styles'   => ['css' => '.ym-cta__card{background:#2a1a22;color:#fff8f6}.ym-cta__direct a{color:#fff8f6}'],
        ]];
        $doc = (new DesignCompiler($this->pink(), $design, 'ym-', 'https://x.test/p', 'https://x.test/t'))->globalStyles();

        $this->assertStringContainsString('/* contrast guard */', $doc['styles']['css']);
        $this->assertMatchesRegularExpression('/:root \.ym-cta__direct a:not\(\.wp-block-button__link\):not\(\.wp-element-button\)\{color:#[0-9a-f]{6}\}/', $doc['styles']['css']);
    }
}
