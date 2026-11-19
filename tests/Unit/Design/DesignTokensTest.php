<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\Color;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\FontCatalogue;

class DesignTokensTest extends TestCase
{
    public function test_contrast_ratio_matches_wcag()
    {
        $this->assertEqualsWithDelta(21.0, Color::contrast('#000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, Color::contrast('#777777', '#777777'), 0.01);
        $this->assertEqualsWithDelta(4.48, Color::contrast('#777777', '#ffffff'), 0.02);
    }

    public function test_ensure_contrast_reaches_the_ratio_and_keeps_good_colours()
    {
        $this->assertSame('#111111', Color::ensureContrast('#111111', '#ffffff', 4.5));

        $fixed = Color::ensureContrast('#ffd6e8', '#fff7fb', 4.5);
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($fixed, '#fff7fb'));

        $neon = Color::ensureContrast('#145214', '#050505', 4.5);
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($neon, '#050505'));
    }

    public function test_parses_css_colours()
    {
        $this->assertSame(['hex' => '#1f1410', 'alpha' => 0.16], Color::parseCss('rgba(31,20,16,.16)'));
        $this->assertSame('#ffffff', Color::parseCss('#FFF')['hex']);
        $this->assertNull(Color::parseCss('var(--x)'));
        $this->assertSame('rgba(0,0,0,0.5)', Color::toCss('#000000', 0.5));
    }

    public function test_garbage_becomes_complete_defaults()
    {
        $t = DesignTokens::validate(['palette' => ['text' => 'red; background:url(x)'], 'fonts' => ['display' => 'Comic Sans', 'body' => 'DM Mono'], 'radius' => 'blob', 'headingWeight' => 'heavy', 'borderWidth' => 99, 'evil' => '}</style>']);

        $this->assertSame(DesignTokens::DEFAULTS['palette']['text'], $t['palette']['text']);
        $this->assertSame(DesignTokens::DEFAULTS['fonts']['display'], $t['fonts']['display']);
        $this->assertSame(DesignTokens::DEFAULTS['fonts']['body'], $t['fonts']['body'], 'a monospace body is refused');
        $this->assertSame('rounded', $t['radius']);
        $this->assertSame(4, $t['borderWidth']);
        $this->assertArrayNotHasKey('evil', $t);
        foreach (array_keys(DesignTokens::ENUMS) as $key) {
            $this->assertContains($t[$key], DesignTokens::ENUMS[$key]);
        }
    }

    public function test_numbers_are_clamped_to_the_font()
    {
        $t = DesignTokens::validate(['fonts' => ['display' => 'Gloock', 'body' => 'Jost', 'label' => 'Jost'], 'headingWeight' => 850, 'headingScale' => 3]);

        $this->assertSame(400, $t['headingWeight'], 'Gloock has one weight; no faux bold');
        $this->assertSame(1.4, $t['headingScale']);
    }

    public function test_every_text_pair_reaches_aa_even_from_an_unreadable_palette()
    {
        $t = DesignTokens::validate(['palette' => [
            'background' => '#fff5fa', 'surface' => '#ffe3ef', 'text' => '#f3c6d8', 'muted' => '#ffd1e3',
            'accent' => '#ffb3d1', 'accent2' => '#ffe0f0', 'soft' => '#c79bb0', 'inverse' => '#ffe9f3',
        ]]);
        $p = $t['palette'];
        $d = $t['derived'];

        $this->assertGreaterThanOrEqual(7.0, Color::contrast($p['text'], $p['background']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['text'], $p['surface']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['text'], $p['soft']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['muted'], $p['background']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['muted'], $p['surface']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['accent'], $p['background']));
        $this->assertGreaterThanOrEqual(3.0, Color::contrast($p['accent2'], $p['background']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['inverse'], $p['background']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($d['onAccent'], $p['accent']));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($d['onInverse'], $p['inverse']));
    }

    public function test_dark_designs_stay_readable()
    {
        $t = DesignTokens::validate(['palette' => [
            'background' => '#050505', 'surface' => '#111111', 'text' => '#f5f5f5', 'muted' => '#555555',
            'accent' => '#39ff14', 'accent2' => '#ff00e6', 'soft' => '#16221a', 'inverse' => '#1a1a1a',
        ]]);

        $this->assertSame('#39ff14', $t['palette']['accent'], 'a readable neon is kept');
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($t['palette']['muted'], '#050505'));
        $this->assertGreaterThanOrEqual(4.5, Color::contrast($t['palette']['inverse'], '#050505'));
        $this->assertSame('#050505', $t['derived']['onAccent']);
    }

    public function test_accent_reads_on_light_bands_of_a_dark_design()
    {
        $t = DesignTokens::validate(['palette' => [
            'background' => '#0a0a0a', 'surface' => '#161616', 'text' => '#fafafa', 'muted' => '#b5b5b5',
            'accent' => '#ff3b30', 'accent2' => '#ffffff', 'soft' => '#1e1e1e', 'inverse' => '#fbfbf8',
        ]]);

        $this->assertGreaterThanOrEqual(4.5, Color::contrast($t['palette']['accent'], '#0a0a0a'));
        $this->assertGreaterThanOrEqual(3.0, Color::contrast($t['palette']['accent'], $t['palette']['inverse']));
    }

    public function test_summary_loses_long_dashes()
    {
        $t = DesignTokens::validate(['summary' => "Raw \u{2014} loud"]);

        $this->assertStringNotContainsString("\u{2014}", $t['summary']);
    }

    public function test_schema_names_only_catalogue_fonts_and_every_field()
    {
        $s = DesignTokens::schema();

        $this->assertSame(FontCatalogue::families(), $s['properties']['fonts']['properties']['display']['enum']);
        $this->assertSame(array_keys($s['properties']), $s['required']);
        $this->assertFalse($s['additionalProperties']);
    }

    public function test_every_catalogue_font_file_is_bundled()
    {
        $theme = dirname(SFCORE_PLUGIN_DIR, 2) . '/themes/swishfolio/';
        foreach (FontCatalogue::files() as $f) {
            $path = ($f[0] === 'plugin' ? SFCORE_PLUGIN_DIR : $theme) . $f[1];
            $this->assertFileExists($path);
        }
    }
}
