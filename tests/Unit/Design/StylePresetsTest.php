<?php

namespace SwishfolioCore\Tests\Unit\Design;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Design\Color;
use SwishfolioCore\AI\Design\CustomDesigner;
use SwishfolioCore\AI\Design\DesignTokens;
use SwishfolioCore\AI\Design\FontCatalogue;
use SwishfolioCore\AI\Design\StylePresets;
use SwishfolioCore\AI\Generation\PatternLibrary;
use SwishfolioCore\AI\Prompt\PromptManager;
use SwishfolioCore\REST\CustomDesignController;
use SwishfolioCore\Tests\Unit\AI\FakeProvider;
use SwishfolioCore\Tests\Unit\AI\FakeRestRequest;

class StylePresetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sfcore_test_options'] = [];
        $GLOBALS['sfcore_test_user_id'] = 7;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['sfcore_test_user_id']);
        parent::tearDown();
    }

    private static function themeDir()
    {
        return dirname(SFCORE_PLUGIN_DIR, 2) . '/themes/swishfolio/';
    }

    public function test_every_theme_variation_has_a_preset()
    {
        $this->assertSame(['default', 'neo-brutalist', 'swiss', 'sasha', 'terminal'], StylePresets::slugs());
        foreach (StylePresets::PRESETS as $slug => $p) {
            $this->assertFileExists(self::themeDir() . 'styles/' . $p['file'], $slug);
        }
    }

    public function test_seed_colours_and_fonts_come_from_the_variation()
    {
        $base = (string) file_get_contents(self::themeDir() . 'theme.json');
        foreach (StylePresets::PRESETS as $slug => $p) {
            $json    = json_decode((string) file_get_contents(self::themeDir() . 'styles/' . $p['file']), true);
            $colours = array_map('strtolower', array_column($json['settings']['color']['palette'], 'color'));
            foreach ($p['seed']['palette'] as $role => $hex) {
                $this->assertContains($hex, $colours, $slug . ' ' . $role . ' is in the variation palette');
            }
            $text = json_encode($json) . $base;
            foreach (['display', 'label'] as $role) {
                $this->assertStringContainsStringIgnoringCase($p['seed']['fonts'][$role], $text, $slug . ' ' . $role . ' font is the variation\'s');
            }
        }
    }

    public function test_every_preset_validates_without_falling_back()
    {
        foreach (StylePresets::slugs() as $slug) {
            $seed   = StylePresets::seed($slug);
            $tokens = StylePresets::tokens($slug);

            $this->assertSame($seed['fonts'], $tokens['fonts'], $slug . ': fonts are all in the catalogue (self-hosted) and the body is not mono');
            foreach ($seed['fonts'] as $family) {
                $this->assertTrue(FontCatalogue::has($family), $family);
            }
            $this->assertNotSame('mono', FontCatalogue::role($tokens['fonts']['body']));
            foreach (array_keys(DesignTokens::ENUMS) as $key) {
                $this->assertSame($seed[$key], $tokens[$key], $slug . ' ' . $key);
            }
            $this->assertSame($seed['headingWeight'], $tokens['headingWeight'], $slug . ': weight is within the display font');
            $this->assertSame($seed['borderWidth'], $tokens['borderWidth']);
            $this->assertSame($seed['name'], $tokens['name']);
            $this->assertStringNotContainsString("\u{2014}", $tokens['summary']);
            $this->assertSame($tokens, DesignTokens::validate($tokens), $slug . ': validation is stable');
        }
    }

    public function test_every_preset_holds_contrast()
    {
        foreach (StylePresets::slugs() as $slug) {
            $t = StylePresets::tokens($slug);
            $p = $t['palette'];
            $d = $t['derived'];

            $this->assertGreaterThanOrEqual(7.0, Color::contrast($p['text'], $p['background']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['text'], $p['surface']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['text'], $p['soft']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['muted'], $p['background']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['muted'], $p['surface']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['accent'], $p['background']), $slug);
            $this->assertGreaterThanOrEqual(3.0, Color::contrast($p['accent2'], $p['background']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($p['inverse'], $p['background']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($d['onAccent'], $p['accent']), $slug);
            $this->assertGreaterThanOrEqual(4.5, Color::contrast($d['onInverse'], $p['inverse']), $slug);
        }
    }

    public function test_signature_colours_survive_enforcement()
    {
        $this->assertSame('#3563cc', StylePresets::tokens('default')['palette']['accent']);
        $this->assertSame('#33ff00', StylePresets::tokens('terminal')['palette']['accent'], 'the phosphor green stays bright');
        $this->assertSame('#33ff00', StylePresets::tokens('terminal')['palette']['text']);
    }

    public function test_previews_carry_real_palette_and_catalogue_stacks()
    {
        $previews = StylePresets::previews();
        $this->assertCount(5, $previews);
        foreach ($previews as $pv) {
            $this->assertStringStartsWith('"', $pv['display']);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $pv['bg']);
            $this->assertStringNotContainsString("\u{2014}", $pv['summary'] . $pv['label']);
        }
        $this->assertEqualsCanonicalizing(['Outfit', 'Space Grotesk', 'Manrope', 'Geist', 'JetBrains Mono', 'Fira Code'], StylePresets::families());
    }

    public function test_preset_without_prompt_is_answered_without_ai()
    {
        $request = new FakeRestRequest(['preset' => 'neo-brutalist', 'prompt' => '  ', 'kit' => 'agency']);
        $this->assertTrue(CustomDesignController::presetOnly($request));
        $this->assertFalse(CustomDesignController::presetOnly(new FakeRestRequest(['preset' => 'neo-brutalist', 'prompt' => 'but green', 'kit' => 'agency'])));
        $this->assertFalse(CustomDesignController::presetOnly(new FakeRestRequest(['preset' => 'nope', 'prompt' => '', 'kit' => 'agency'])));
        $this->assertFalse(CustomDesignController::presetOnly(new FakeRestRequest(['prompt' => 'brutal and loud', 'kit' => 'agency'])));

        $out = CustomDesignController::presetDesign('neo-brutalist', 'agency');

        $this->assertSame('Neo Brutalist', $out['name']);
        $this->assertSame(['in' => 0, 'out' => 0, 'model' => ''], $out['usage']);
        $draft = $GLOBALS['sfcore_test_options'][CustomDesignController::DRAFT];
        $this->assertSame($out['designId'], $draft['id']);
        $this->assertSame('neo-brutalist', $draft['preset']);
        $this->assertSame(StylePresets::tokens('neo-brutalist'), $draft['tokens']);
        $this->assertSame(7, $draft['user']);

        // The same request again reuses the draft (so it is never metered either).
        $this->assertSame($draft, CustomDesignController::reusable($request));
        $this->assertNull(CustomDesignController::reusable(new FakeRestRequest(['preset' => 'swiss', 'prompt' => '', 'kit' => 'agency'])), 'the preset is part of the reuse key');
        $this->assertNull(CustomDesignController::reusable(new FakeRestRequest(['preset' => 'neo-brutalist', 'prompt' => 'but green', 'kit' => 'agency'])));
    }

    public function test_preset_with_prompt_hands_the_seed_to_the_model()
    {
        $library  = PatternLibrary::shipped();
        $provider = new FakeProvider();
        // Sasha's own pairing (Manrope + Geist) is also a template's; kept from the seed it stays.
        $provider->payload = array_merge(StylePresets::seed('sasha'), ['palette' => ['accent' => '#1f6b3a']]);

        $out = (new CustomDesigner($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))
            ->design('Start from Sasha, but with a deep green accent', 'A florist', array_values($library->kits()), 'm', StylePresets::seed('sasha'));

        $prompt = $provider->lastRequest->getUserPrompt();
        $this->assertStringContainsString('start from our "Sasha" style', $prompt);
        $this->assertStringContainsString('"display": "Manrope"', $prompt);
        $this->assertStringContainsString('deep green accent', $prompt);
        $this->assertSame('Manrope', $out['tokens']['fonts']['display']);
        $this->assertSame('Geist', $out['tokens']['fonts']['body']);
    }

    public function test_no_seed_leaves_the_prompt_as_before()
    {
        $provider = new FakeProvider();
        $provider->payload = [];
        (new CustomDesigner($provider, new PromptManager(SFCORE_PLUGIN_DIR . 'src/AI/Prompt')))->design('dark and moody', 'A wine bar', [], 'm');

        $this->assertStringNotContainsString('start from our', $provider->lastRequest->getUserPrompt());
        $this->assertStringNotContainsString('{{', $provider->lastRequest->getUserPrompt());
    }
}
