<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\CssCompiler;
use SwishfolioCore\AI\Design\DesignCompiler;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\PolishCss;

class PolishCssTest extends TestCase
{
    public function testKeepsKitScopedRulesUnderBody()
    {
        $css = PolishCss::clean(".oz-cta__card { border-radius: 2rem; padding-block: 4rem }\n.oz-btn .wp-block-button__link{letter-spacing:.06em;border:2px solid var(--sf-text)}", 'oz-');

        $this->assertStringContainsString('body .oz-cta__card{border-radius:2rem;padding-block:4rem}', $css);
        $this->assertStringContainsString('body .oz-btn .wp-block-button__link{letter-spacing:.06em;border:2px solid var(--sf-text)}', $css);
    }

    public function testSelectorListEveryPartMustBeTheKits()
    {
        $this->assertSame('body .oz-a,body .oz-b h2{margin:0}', PolishCss::clean('.oz-a, .oz-b h2 {margin:0}', 'oz-'));
        $this->assertSame('', PolishCss::clean('.oz-a, h2 {margin:0}', 'oz-'));
        $this->assertSame('', PolishCss::clean('body{margin:0}', 'oz-'));
        $this->assertSame('', PolishCss::clean('html .oz-a{margin:0}', 'oz-'));
        $this->assertSame('', PolishCss::clean('.ym-card{margin:0}', 'oz-'), 'another kit\'s classes');
    }

    public function testNoPrefixMeansNothing()
    {
        $this->assertSame('', PolishCss::clean('.oz-a{margin:0}', ''));
    }

    /**
     * @dataProvider injections
     */
    public function testInjectionAttemptsRejectTheWholeBlock($css)
    {
        $this->assertSame('', PolishCss::clean(".oz-ok{margin:0}\n" . $css, 'oz-'));
    }

    public function injections()
    {
        return [
            'import'            => ['@import url("https://evil.test/x.css");'],
            'import bare'       => ['@import "https://evil.test/x.css";'],
            'url'               => ['.oz-a{background-color:url(https://evil.test/p.gif)}'],
            'url spaced'        => ['.oz-a{border-image:url ( "javascript:alert(1)" )}'],
            'expression'        => ['.oz-a{width:expression(alert(1))}'],
            'javascript'        => ['.oz-a{color:javascript:alert(1)}'],
            'escape'            => ['.oz-a{color:\\75 rl(x)}'],
            'style breakout'    => ['.oz-a{margin:0}</style><script>alert(1)</script>'],
            'position fixed'    => ['.oz-a{position:fixed;top:0;left:0}'],
            'position sticky'   => ['.oz-a{position : sticky}'],
            'media query'       => ['@media (min-width:1px){.oz-a{margin:0}}'],
            'font face'         => ['@font-face{font-family:x;src:url(x.woff)}'],
            'nesting'           => ['.oz-a{.oz-b{margin:0}}'],
            'behavior'          => ['.oz-a{behavior:url(x.htc)}'],
            'moz binding'       => ['.oz-a{-moz-binding:url(x.xml#x)}'],
            'data uri'          => ['.oz-a{content:"data:text/html,x"}'],
            'image-set'         => ['.oz-a{background-color:image-set("x.png" 1x)}'],
            'attr'              => ['.oz-a{width:attr(data-x)}'],
            'unbalanced'        => ['.oz-a{margin:0'],
            'html entity'       => ['.oz-a{margin:0}&lt;'],
            'comment smuggling' => ['.oz-a{margin:0 /* x */ }/*'],
        ];
    }

    public function testDropsDeclarationsOutsideTheSubset()
    {
        $css = PolishCss::clean('.oz-a{display:none;z-index:9999;content:"x";transform:translateX(-9999px);margin:0;color:#ff0000;color:red;background:linear-gradient(red,blue);border:2px solid red;box-shadow:0 0 0 2px rgb(255,0,0);color:var(--evil);color:var(--sf-accent)}', 'oz-');

        $this->assertSame('body .oz-a{margin:0;color:var(--sf-accent)}', $css);
    }

    public function testColourPropertiesTakeOnlyDesignVariables()
    {
        $this->assertSame('body .oz-a{color:currentColor;background-color:var(--sf-soft);border-color:transparent}', PolishCss::clean('.oz-a{color:currentColor;background-color:var(--sf-soft);border-color:transparent}', 'oz-'));
        $this->assertSame('', PolishCss::clean('.oz-a{background-color:#fff}', 'oz-'));
    }

    public function testSizeCap()
    {
        $rule = ".oz-a{margin:0}\n";
        $this->assertSame('', PolishCss::clean(str_repeat($rule, (int) ceil((PolishCss::MAX_BYTES + 1) / strlen($rule))), 'oz-'));
        $this->assertNotSame('', PolishCss::clean(str_repeat($rule, 10), 'oz-'));
    }

    public function testImportantIsKeptFunctionsAreLimited()
    {
        $this->assertSame('body .oz-a{padding:clamp(1rem, 4vw, 3rem)!important}', PolishCss::clean('.oz-a{padding:clamp(1rem, 4vw, 3rem) !important}', 'oz-'));
        $this->assertSame('', PolishCss::clean('.oz-a{padding:env(safe-area-inset-top)}', 'oz-'));
    }

    public function testTokensKeepBoundedCssAndTheCompilerScopesIt()
    {
        $tokens = DesignTokens::validate(['css' => '.oz-cta{padding-block:5rem}']);
        $this->assertSame('.oz-cta{padding-block:5rem}', $tokens['css']);
        $this->assertSame('', DesignTokens::validate(['css' => str_repeat('x', PolishCss::MAX_BYTES + 1)])['css']);
        $this->assertSame('', DesignTokens::validate(['css' => ['not', 'a', 'string']])['css']);
        $this->assertSame('', DesignTokens::validate([])['css']);

        $design = ['globalStyles' => ['settings' => ['color' => ['palette' => ['theme' => [['slug' => 'base', 'color' => '#ffffff'], ['slug' => 'contrast', 'color' => '#111111']]]]], 'styles' => ['css' => '.oz-cta{padding:2rem}']]];
        $doc    = (new DesignCompiler($tokens, $design, 'oz-', 'https://x.test/p', 'https://x.test/t'))->globalStyles();
        $this->assertStringContainsString('body .oz-cta{padding-block:5rem}', $doc['styles']['css']);

        // An unsafe block never reaches the stylesheet, and the rest compiles.
        $bad = DesignTokens::validate(['css' => '.oz-cta{padding:1rem}@import url(https://evil.test/x.css);']);
        $doc = (new DesignCompiler($bad, $design, 'oz-', 'https://x.test/p', 'https://x.test/t'))->globalStyles();
        $this->assertStringNotContainsString('evil.test', $doc['styles']['css']);
        $this->assertStringContainsString(CssCompiler::MARKER, $doc['styles']['css']);
    }

    public function testSchemaOffersCss()
    {
        $schema = DesignTokens::schema();
        $this->assertSame('string', $schema['properties']['css']['type']);
        $this->assertContains('css', $schema['required']);
    }
}
