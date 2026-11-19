<?php

namespace SwishfolioCore\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use SwishfolioCore\AI\Context\TokenResolver;

class TokenResolverTest extends TestCase
{
    private function resolver()
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../../fixtures/theme.json'), true);

        return TokenResolver::fromArray($json);
    }

    public function test_reads_colour_slugs_from_palette()
    {
        $this->assertSame(['base', 'contrast', 'accent-1', 'accent-2'], $this->resolver()->getSlugs('color'));
    }

    public function test_reads_spacing_and_font_size_slugs()
    {
        $this->assertSame(['50', '60'], $this->resolver()->getSlugs('spacing'));
        $this->assertSame(['medium', 'large', 'display'], $this->resolver()->getSlugs('fontSize'));
    }

    public function test_expands_slug_to_css_custom_property()
    {
        $this->assertSame(
            'var(--wp--preset--color--accent-1)',
            $this->resolver()->toStorageValue('color', 'accent-1')
        );
    }

    public function test_font_size_uses_hyphenated_preset_path()
    {
        $this->assertSame(
            'var(--wp--preset--font-size--large)',
            $this->resolver()->toStorageValue('fontSize', 'large')
        );
    }

    public function test_inverts_storage_value_back_to_slug()
    {
        $this->assertSame(
            'accent-2',
            $this->resolver()->toSlug('color', 'var(--wp--preset--color--accent-2)')
        );
    }

    public function test_inverting_an_unknown_value_returns_null()
    {
        $this->assertNull($this->resolver()->toSlug('color', '#123456'));
    }

    public function test_unknown_slug_is_invalid()
    {
        $this->assertFalse($this->resolver()->isValid('color', 'accent-9'));
        $this->assertTrue($this->resolver()->isValid('color', 'accent-1'));
    }

    public function test_literal_hex_is_never_a_valid_slug()
    {
        $this->assertFalse($this->resolver()->isValid('color', '#FF6B6B'));
    }

    public function test_unknown_token_set_yields_no_slugs()
    {
        $this->assertSame([], $this->resolver()->getSlugs('shadow'));
        $this->assertFalse($this->resolver()->isValid('shadow', 'anything'));
    }

    public function test_missing_theme_section_yields_empty_set_not_error()
    {
        $resolver = TokenResolver::fromArray(['version' => 3, 'settings' => []]);

        $this->assertSame([], $resolver->getSlugs('color'));
    }

    /**
     * The live wp_get_global_settings() shape, observed on WP 7.0: presets are
     * keyed by origin, not a flat list as in a raw theme.json file.
     */
    private function liveSettings($defaultPalette = false)
    {
        return [
            'settings' => [
                'color' => [
                    'defaultPalette' => $defaultPalette,
                    'palette'        => [
                        'default' => [
                            ['slug' => 'vivid-red', 'color' => '#cf2e2e'],
                            ['slug' => 'black', 'color' => '#000000'],
                        ],
                        'theme' => [
                            ['slug' => 'base', 'color' => '#FFFDF5'],
                            ['slug' => 'accent-1', 'color' => '#FF6B6B'],
                        ],
                    ],
                ],
                'typography' => [
                    'defaultFontSizes' => false,
                    'fontSizes'        => [
                        'default' => [['slug' => 'x-large', 'size' => '1.5rem']],
                        'theme'   => [['slug' => 'display', 'size' => '3rem']],
                    ],
                ],
            ],
        ];
    }

    public function test_origin_keyed_presets_are_read()
    {
        $resolver = TokenResolver::fromArray($this->liveSettings());

        $this->assertTrue($resolver->isValid('color', 'accent-1'));
        $this->assertSame('display', $resolver->toSlug('fontSize', 'var(--wp--preset--font-size--display)'));
    }

    public function test_default_origin_is_excluded_when_the_theme_disables_it()
    {
        $resolver = TokenResolver::fromArray($this->liveSettings(false));

        $this->assertFalse(
            $resolver->isValid('color', 'vivid-red'),
            'A disabled default palette emits no CSS custom property, so its slugs must not be offered.'
        );
        $this->assertFalse($resolver->isValid('fontSize', 'x-large'));
    }

    public function test_default_origin_is_included_when_the_theme_allows_it()
    {
        $resolver = TokenResolver::fromArray($this->liveSettings(true));

        $this->assertTrue($resolver->isValid('color', 'vivid-red'));
        $this->assertTrue($resolver->isValid('color', 'accent-1'));
    }

    public function test_theme_origin_overrides_a_default_slug_of_the_same_name()
    {
        $settings = $this->liveSettings(true);
        $settings['settings']['color']['palette']['theme'][] = ['slug' => 'black', 'color' => '#121212'];

        $resolver = TokenResolver::fromArray($settings);
        $slugs    = $resolver->getSlugs('color');

        $this->assertSame(1, count(array_keys($slugs, 'black')), 'A slug must not appear twice.');
    }
}
