<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\ColorRemap;
use SwishfolioCore\AI\Design\CssCompiler;
use SwishfolioCore\AI\Design\CustomDesigner;
use SwishfolioCore\AI\Design\DesignCompiler;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\FontCatalogue;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;

class CssCompilerTest extends TestCase
{
    const PLUGIN = 'https://x.test/wp-content/plugins/swishfolio-core';
    const THEME  = 'https://x.test/wp-content/themes/swishfolio';

    private function brutal()
    {
        return DesignTokens::validate([
            'name' => 'Concrete',
            'palette' => ['background' => '#ffffff', 'surface' => '#ececec', 'text' => '#000000', 'muted' => '#444444', 'accent' => '#000000', 'accent2' => '#ff2200', 'soft' => '#f2f2f2', 'inverse' => '#000000'],
            'fonts' => ['display' => 'Big Shoulders Display', 'body' => 'Inter Tight', 'label' => 'JetBrains Mono'],
            'headingWeight' => 900, 'headingCase' => 'uppercase', 'headingScale' => 1.3, 'radius' => 'none', 'borderWidth' => 3,
            'shadow' => 'hard', 'buttons' => 'hard-shadow', 'decor' => 'lines', 'spacing' => 'compact',
        ]);
    }

    private function soft()
    {
        return DesignTokens::validate([
            'palette' => ['background' => '#fff8f3', 'surface' => '#fdeee4', 'text' => '#2d2440', 'muted' => '#5f5670', 'accent' => '#7a3fb0', 'accent2' => '#e0607e', 'soft' => '#e9e1ff', 'inverse' => '#3b2f63'],
            'fonts' => ['display' => 'Outfit', 'body' => 'DM Sans', 'label' => 'DM Sans'],
            'radius' => 'soft', 'cards' => 'filled', 'decor' => 'blobs',
        ]);
    }

    private function compiler(array $tokens)
    {
        $remap = new ColorRemap(['base' => '#F3ECE0', 'contrast' => '#1F1410', 'accent-1' => '#E0452B', 'accent-3' => '#3A0F12'], '#F3ECE0', '#1F1410', $tokens['palette']);

        return new CssCompiler($tokens, $remap, 'Fraunces', 'Hanken Grotesk', 'lu-', self::PLUGIN, self::THEME);
    }

    public function test_kit_colours_fonts_and_shapes_are_replaced()
    {
        $kit = '@font-face{font-family:"Fraunces";src:url({{kit-fonts}}/f.woff2)}'
            . 'body{--bone:#F3ECE0;--ink:#1F1410;--tomato:#E0452B;--line:rgba(31,20,16,.16);--serif:"Fraunces",Georgia,serif;--sans:"Hanken Grotesk",system-ui,sans-serif;--sec:clamp(4rem,9vw,9rem)}'
            . 'body :is(h1,h2){font-family:var(--serif);font-weight:340;letter-spacing:-.025em}'
            . '.lu-arch{border-radius:999px 999px 0 0}.lu-btn{border:1px solid var(--tomato);color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.2)}'
            . '.lu-kicker{text-transform:uppercase}'
            . '@media (max-width:600px){.lu-hero__title{font-size:3rem;color:#E0452B}}';

        $css = $this->compiler($this->brutal())->compile($kit);

        $this->assertStringStartsWith(CssCompiler::MARKER, $css);
        $this->assertStringNotContainsString('Fraunces', $css);
        $this->assertStringNotContainsString('Hanken', $css);
        $this->assertStringNotContainsString('{{kit-fonts}}', $css);
        $this->assertStringNotContainsStringIgnoringCase('#E0452B', $css);
        $this->assertStringNotContainsStringIgnoringCase('#F3ECE0', $css);
        $this->assertStringContainsString('--serif:"Big Shoulders Display"', $css);
        $this->assertStringContainsString('font-weight:900', $css);
        $this->assertStringContainsString('.lu-arch{border-radius:0}', $css);
        $this->assertStringContainsString('border:3px solid', $css);
        $this->assertStringContainsString('6px 6px 0 #000000', $css);
        $this->assertStringContainsString('rgba(0,0,0,0.16)', $css, 'alpha is kept');
        $this->assertStringContainsString('font-size:3.9rem', $css, 'heading scale applies inside media queries');
        $this->assertStringContainsString('--sec:clamp(2.88rem,6.48vw,6.48rem)', $css, 'compact spacing');
        $this->assertStringContainsString('assets/kits/fitness/fonts/big-shoulders-var.woff2', $css);
    }

    public function test_soft_design_keeps_circles_but_drops_arches()
    {
        $c = $this->compiler($this->soft());

        $this->assertSame('50%', $c->radius('50%'));
        $this->assertSame('999px', $c->radius('999px'));
        $this->assertSame('30px', $c->radius('999px 999px 0 0'));
        $this->assertSame('18px', $c->radius('6px'));
        $this->assertStringContainsString('radial-gradient', $c->overrides());
    }

    public function test_display_titles_do_not_grow_past_their_layout()
    {
        $c = $this->compiler($this->brutal());

        $this->assertSame('clamp(2.4rem,8vw,9.6rem)', $c->value('font-size', 'clamp(3rem,10vw,12rem)', '.lu-hero__title'), 'uppercase display type shrinks');
        $this->assertSame('2.6rem', $c->value('font-size', '2rem', '.lu-h2'), 'smaller headings take the scale');
        $this->assertSame('normal', $c->value('white-space', 'nowrap', '.lu-hero__title'));
        $this->assertSame('nowrap', $c->value('white-space', 'nowrap', '.lu-nav a'));
        $this->assertSame('balance', $c->value('text-wrap', 'nowrap', 'body h1.lu-hero__title'));
    }

    public function test_labels_follow_the_label_style()
    {
        $t               = $this->soft();
        $t['labelStyle'] = 'plain';
        $c               = $this->compiler($t);

        $this->assertSame('none', $c->value('text-transform', 'uppercase', '.lu-kicker'));
        $this->assertSame('uppercase', $c->value('text-transform', 'uppercase', '.lu-nav a'));
    }

    public function test_import_and_unknown_at_rules_are_dropped()
    {
        $css = $this->compiler($this->soft())->transform('@import url(https://evil.test/x.css);@charset "utf-8";.a{color:#fff}@page{margin:0}');

        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringNotContainsString('@page', $css);
        $this->assertStringContainsString('.a{color:', $css);
    }

    public function test_sanitizer_refuses_foreign_urls_and_script()
    {
        $allowed = FontCatalogue::urls(self::PLUGIN, self::THEME);

        foreach ([
            '.a{background:url(https://evil.test/x.png)}',
            '.a{background:url("javascript:alert(1)")}',
            '.a{width:expression(alert(1))}',
            '@import "x.css";',
            '.a{}</style><script>alert(1)</script>',
            '.a{background:u\\72l(x)}',
            '.a{behavior:url(x.htc)}',
            '.a{background:url("data:image/svg+xml,<svg onload=alert(1)>")}',
        ] as $bad) {
            try {
                CssCompiler::sanitizeCss($bad, $allowed);
                $this->fail('Accepted: ' . $bad);
            } catch (\UnexpectedValueException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $ok = '@font-face{src:url(' . $allowed[0] . ')}.a{scroll-behavior:smooth;content:\'\\2013\';background:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\'/%3E")}';
        $this->assertSame($ok, CssCompiler::sanitizeCss($ok, $allowed));
    }

    public function test_every_kit_compiles_safely_and_loses_its_look()
    {
        $library = PatternLibrary::shipped();
        $this->assertNotEmpty($library->kits());

        foreach ($library->kits() as $slug => $kit) {
            foreach ([$this->brutal(), $this->soft()] as $tokens) {
                $compiler = new DesignCompiler($tokens, $kit->getDesignFile(), (string) ($kit->getDesign()['classPrefix'] ?? ''), self::PLUGIN, self::THEME);
                $doc      = $compiler->globalStyles();
                $css      = $doc['styles']['css'];

                $this->assertStringStartsWith(CssCompiler::MARKER, $css, $slug);
                foreach ((array) ($kit->getDesign()['fonts'] ?? []) as $family) {
                    if (! in_array($family, $tokens['fonts'], true)) {
                        $this->assertStringNotContainsString('"' . $family . '"', $css, $slug . ' still uses ' . $family);
                    }
                }
                $kitPalette = array_column((array) ($kit->getDesignFile()['globalStyles']['settings']['color']['palette']['theme'] ?? []), 'color', 'slug');
                foreach ($doc['settings']['color']['palette']['theme'] as $entry) {
                    $before = strtolower((string) ($kitPalette[$entry['slug']] ?? ''));
                    $this->assertTrue($entry['color'] !== $before || in_array($entry['color'], $tokens['palette'], true), $slug . ' kept its ' . $entry['slug'] . ' colour');
                }
                $this->assertSame($tokens['fonts']['display'], trim(explode(',', $doc['styles']['elements']['heading']['typography']['fontFamily'])[0], '"'));
                foreach (['h1', 'h2', 'h3'] as $h) {
                    if (isset($doc['styles']['elements'][$h]['typography']['fontWeight'])) {
                        $this->assertSame((string) $tokens['headingWeight'], $doc['styles']['elements'][$h]['typography']['fontWeight'], $slug . ' ' . $h . ' keeps the kit weight');
                    }
                }
                if (isset($doc['styles']['blocks']['core/heading']['typography'])) {
                    $this->assertSame($doc['styles']['elements']['heading']['typography']['fontFamily'], $doc['styles']['blocks']['core/heading']['typography']['fontFamily'], $slug . ' heading block keeps the kit font');
                }
            }
        }
    }

    public function test_markup_colours_change_in_comment_and_html_together()
    {
        $tokens   = $this->brutal();
        $compiler = new DesignCompiler($tokens, ['globalStyles' => ['settings' => ['color' => ['palette' => ['theme' => [['slug' => 'base', 'color' => '#F3ECE0'], ['slug' => 'contrast', 'color' => '#1F1410'], ['slug' => 'accent-1', 'color' => '#E0452B']]]]], 'styles' => []]], 'lu-', self::PLUGIN, self::THEME);
        $markup   = '<!-- wp:group {"style":{"color":{"background":"#E0452B"}}} --><div class="wp-block-group has-background" style="background-color:#E0452B"><svg><path fill="#E0452B" stroke="#1F1410"/></svg><p>Call #E0452B now</p></div><!-- /wp:group -->';
        $out      = $compiler->recolourMarkup($markup);

        $accent = $compiler->remap()->map('#E0452B');
        $this->assertStringContainsString('"background":"' . $accent . '"', $out);
        $this->assertStringContainsString('background-color:' . $accent, $out);
        $this->assertStringContainsString('fill="' . $accent . '"', $out);
        $this->assertStringContainsString('Call #E0452B now', $out, 'text is never touched');
    }

    public function test_designer_validates_the_reply_and_steers_off_kit_pairs()
    {
        $library  = PatternLibrary::shipped();
        $provider = new FakeProvider();
        $provider->payload = ['name' => 'Ember', 'palette' => ['background' => '#000'], 'fonts' => ['display' => 'Fraunces', 'body' => 'Hanken Grotesk', 'label' => 'DM Mono'], 'headingWeight' => 500];

        $out = (new CustomDesigner($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))->design('dark and moody', 'A wine bar', array_values($library->kits()), 'm');

        $this->assertNotSame('Fraunces', $out['tokens']['fonts']['display'], 'the Open Hearth pairing is not reused');
        $this->assertSame('serif', FontCatalogue::role($out['tokens']['fonts']['display']));
        $this->assertStringContainsString('dark and moody', $provider->lastRequest->getUserPrompt());
        $this->assertStringContainsString('Open Hearth', $provider->lastRequest->getUserPrompt());
        $this->assertStringStartsWith('You are a senior UI/UX designer', $provider->lastRequest->getSystemPrompt());
    }
}
